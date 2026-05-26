<?php

namespace App\Services\Finance\Invoicing;

use App\Models\Counterparty;
use App\Models\CounterpartyAddress;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceLine;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Owns the invoice lifecycle: create, update, delete drafts, cancel,
 * reverse-link, plus header-total recomputation.
 *
 * Does NOT post to GL — that's InvoicePostingService. Separation matters
 * because invoice CRUD is independent of GL state, and tests for one
 * shouldn't require the other.
 *
 * Concurrency:
 *   Invoice number allocation is delegated to NumberingService (atomic,
 *   serialised). The invoice + lines insert is wrapped in a transaction
 *   so partial inserts are impossible.
 *
 * State machine enforcement:
 *   updateDraft / addLine / removeLine require status='draft'. Any other
 *   status throws DomainException — caller should check canEdit() first.
 *
 * Atomic recomputation:
 *   When lines change, the invoice header subtotal/discount/tax/total
 *   are recomputed inside the same transaction so the header is never
 *   inconsistent with line sums.
 */
class InvoiceService
{
    public function __construct(
        protected NumberingService $numbering,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create a draft invoice with one or more lines.
     *
     * @param  array  $headerData
     * @param  list<InvoiceLineDraft>  $lineDrafts
     */
    public function createDraft(
        Counterparty $customer,
        array $headerData,
        array $lineDrafts,
        ?User $createdBy = null,
    ): Invoice {
        if (empty($lineDrafts)) {
            throw new \InvalidArgumentException('Invoice must have at least 1 line');
        }
        foreach ($lineDrafts as $i => $ld) {
            if (! $ld instanceof InvoiceLineDraft) {
                throw new \InvalidArgumentException("Line draft #{$i} is not InvoiceLineDraft");
            }
        }

        $organizationId = $this->currentOrganization->resolve()?->id;

        return DB::transaction(function () use ($customer, $headerData, $lineDrafts, $createdBy, $organizationId) {
            $invoiceDate = isset($headerData['invoice_date'])
                ? CarbonImmutable::parse($headerData['invoice_date'])
                : CarbonImmutable::today();

            // Compute due date from terms.
            $dueDate = $headerData['due_date'] ?? null;
            if ($dueDate === null) {
                $terms = $customer->effectivePaymentTermsDays();
                $dueDate = $terms !== null
                    ? $invoiceDate->addDays($terms)
                    : $invoiceDate;
            } else {
                $dueDate = CarbonImmutable::parse($dueDate);
            }

            // Determine cash-vs-credit based on payment_method.
            $paymentMethodId = $headerData['payment_method_id'] ?? null;
            $isCash = false;
            if ($paymentMethodId) {
                $pm = PaymentMethod::find($paymentMethodId);
                $isCash = $pm && ($pm->is_cash || $pm->is_bank || $pm->is_mobile_money);
            }

            $invoiceNumber = $this->numbering->next(
                sequenceType:   'invoice',
                prefix:         'INV',
                year:           (int) $invoiceDate->format('Y'),
                organizationId: $organizationId,
            );

            // Snapshot the customer's default billing address.
            $billingSnap = $this->snapshotAddress($customer, 'billing');
            $shippingSnap = $this->snapshotAddress($customer, 'shipping') ?? $billingSnap;

            $invoice = Invoice::create([
                'organization_id'           => $organizationId,
                'invoice_number'            => $invoiceNumber,
                'invoice_date'              => $invoiceDate,
                'due_date'                  => $dueDate,
                'counterparty_id'           => $customer->id,
                'currency_code'             => $headerData['currency_code'] ?? $customer->currency_code ?? 'GHS',
                'exchange_rate'             => $headerData['exchange_rate'] ?? '1',
                'source_type'               => $headerData['source_type'] ?? null,
                'source_id'                 => $headerData['source_id'] ?? null,
                'status'                    => Invoice::STATUS_DRAFT,
                'is_cash_invoice'           => $isCash,
                'payment_method_id'         => $paymentMethodId,
                'default_tax_code_id'       => $headerData['default_tax_code_id'] ?? null,
                'header_discount_percent'   => $headerData['header_discount_percent'] ?? '0',
                'created_by_user_id'        => $createdBy?->id,
                'description'               => $headerData['description'] ?? null,
                'notes'                     => $headerData['notes'] ?? null,
                'reference'                 => $headerData['reference'] ?? null,
                'internal_notes'            => $headerData['internal_notes'] ?? null,
                'billing_address_snapshot'  => $billingSnap,
                'shipping_address_snapshot' => $shippingSnap,
                'subtotal'                  => '0',
                'discount_total'            => '0',
                'tax_total'                 => '0',
                'total'                     => '0',
            ]);

            // Persist lines.
            $lineNumber = 1;
            foreach ($lineDrafts as $draft) {
                InvoiceLine::create($draft->toPersistedArray(
                    invoiceId: $invoice->id,
                    lineNumber: $lineNumber++,
                    organizationId: $organizationId,
                ));
            }

            // Recompute header from persisted lines.
            $this->recomputeHeader($invoice);

            return $invoice->fresh(['lines', 'counterparty']);
        });
    }

    /**
     * Cancel a draft. Posted invoices cannot be cancelled — they must be reversed.
     */
    public function cancel(Invoice $invoice, User $cancelledBy, string $reason): Invoice
    {
        if (! $invoice->canCancel()) {
            throw new \DomainException(
                "Invoice #{$invoice->id} cannot be cancelled: status is '{$invoice->status}'"
            );
        }

        $invoice->update([
            'status'              => Invoice::STATUS_CANCELLED,
            'cancelled_by_user_id'=> $cancelledBy->id,
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
        ]);

        return $invoice->fresh();
    }

    /**
     * Recompute header totals from current lines. Run after any line change.
     */
    public function recomputeHeader(Invoice $invoice): Invoice
    {
        $subtotal = '0';
        $discount = '0';
        $tax = '0';

        foreach ($invoice->lines()->get() as $line) {
            $subtotal = bcadd($subtotal, (string) $line->line_subtotal, 2);
            $discount = bcadd($discount, (string) $line->line_discount_amount, 2);
            $tax      = bcadd($tax, (string) $line->line_tax_amount, 2);
        }

        // Apply header-level discount on top of line discounts.
        $headerPct = (string) ($invoice->header_discount_percent ?? '0');
        if (bccomp($headerPct, '0', 2) > 0) {
            $headerDiscount = bcdiv(bcmul(bcsub($subtotal, $discount, 2), $headerPct, 8), '100', 2);
            $discount = bcadd($discount, $headerDiscount, 2);
        }

        $total = bcadd(bcsub($subtotal, $discount, 2), $tax, 2);

        $invoice->update([
            'subtotal'       => $subtotal,
            'discount_total' => $discount,
            'tax_total'      => $tax,
            'total'          => $total,
        ]);

        return $invoice->fresh();
    }

    /**
     * Update header fields on a draft. Status-immutable fields (number, source,
     * counterparty, currency) are not changeable after creation.
     */
    public function updateDraftHeader(Invoice $invoice, array $data): Invoice
    {
        if (! $invoice->isEditable()) {
            throw new \DomainException(
                "Invoice #{$invoice->id} cannot be edited: status is '{$invoice->status}'"
            );
        }

        $allowed = [
            'invoice_date', 'due_date',
            'description', 'notes', 'reference', 'internal_notes',
            'header_discount_percent',
            'payment_method_id', 'default_tax_code_id',
        ];

        $update = array_intersect_key($data, array_flip($allowed));

        if (! empty($update)) {
            $invoice->update($update);
            $this->recomputeHeader($invoice);
        }

        return $invoice->fresh(['lines', 'counterparty']);
    }

    public function addLine(Invoice $invoice, InvoiceLineDraft $draft): InvoiceLine
    {
        if (! $invoice->isEditable()) {
            throw new \DomainException(
                "Invoice #{$invoice->id} not editable: status is '{$invoice->status}'"
            );
        }

        $organizationId = $this->currentOrganization->resolve()?->id;

        return DB::transaction(function () use ($invoice, $draft, $organizationId) {
            $lineNumber = ((int) $invoice->lines()->max('line_number')) + 1;

            $line = InvoiceLine::create($draft->toPersistedArray(
                invoiceId: $invoice->id,
                lineNumber: $lineNumber,
                organizationId: $organizationId,
            ));

            $this->recomputeHeader($invoice);

            return $line->fresh();
        });
    }

    public function removeLine(InvoiceLine $line): void
    {
        $invoice = $line->invoice;

        if (! $invoice->isEditable()) {
            throw new \DomainException(
                "Cannot remove line: invoice #{$invoice->id} status is '{$invoice->status}'"
            );
        }

        if ($invoice->lines()->count() <= 1) {
            throw new \DomainException('Cannot remove the last line — invoice must have at least one line');
        }

        DB::transaction(function () use ($line, $invoice) {
            $line->delete();
            $this->recomputeHeader($invoice);
        });
    }

    /**
     * Capture the customer's address as a single string at invoice time.
     * Once captured, future edits to the customer's address don't change
     * what the printed invoice shows.
     */
    protected function snapshotAddress(Counterparty $customer, string $type): ?string
    {
        $address = $customer->addresses()
            ->where('address_type', $type)
            ->where('is_default', true)
            ->first();

        if (! $address) {
            // Fallback to the legacy fields on the counterparty.
            $parts = array_filter([
                $customer->address_line_1,
                $customer->address_line_2,
                $customer->city,
                $customer->region,
                $customer->country,
            ]);
            return $parts ? implode(', ', $parts) : null;
        }

        return $address->asSingleLine();
    }
}
