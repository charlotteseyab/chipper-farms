<?php

namespace App\Services\Finance\VendorInvoicing;

use App\Models\Counterparty;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\VendorInvoiceLine;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Owns the vendor invoice lifecycle: create draft, add/remove lines, submit
 * for approval, cancel, and supplier-aware withholding override.
 *
 * Does NOT post to GL — that's VendorInvoicePostingService. Separation
 * matches the AR side (Invoice + InvoicePostingService) and means CRUD
 * tests don't need the posting engine wired.
 *
 * State machine enforcement:
 *   - addLine / removeLine / updateDraftHeader require status='draft'.
 *   - submitForApproval moves draft → pending_approval (requires lines).
 *   - approve and post are handled by VendorInvoicePostingService.
 *
 * Withholding override:
 *   When the supplier has withholding_exempt=true, all line-level WHT
 *   is forced to zero on persistence regardless of what the line draft
 *   contained. This is the supplier-level certificate trumping line
 *   policy. Tested.
 *
 * Concurrency:
 *   Number allocation via NumberingService (serialised). Header + lines
 *   wrapped in a transaction. Aggregate columns (subtotal, tax_total,
 *   withholding_total, total, balance_due) are recomputed inside the
 *   transaction whenever lines change.
 */
class VendorInvoiceService
{
    public function __construct(
        protected NumberingService $numbering,
        protected ExpenseAccountResolver $accountResolver,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create a draft vendor invoice with one or more lines.
     *
     * @param  Counterparty                       $supplier
     * @param  array<string, mixed>               $headerData
     * @param  list<VendorInvoiceLineDraft>       $lineDrafts
     * @param  ?User                              $createdBy
     */
    public function createDraft(
        Counterparty $supplier,
        array $headerData,
        array $lineDrafts,
        ?User $createdBy = null,
    ): VendorInvoice {
        if (! $supplier->isSupplier()) {
            throw new \DomainException(
                "Counterparty #{$supplier->id} is not a supplier (type: {$supplier->type})"
            );
        }
        if (empty($lineDrafts)) {
            throw new \InvalidArgumentException('Vendor invoice must have at least 1 line');
        }
        foreach ($lineDrafts as $i => $ld) {
            if (! $ld instanceof VendorInvoiceLineDraft) {
                throw new \InvalidArgumentException("Line draft #{$i} is not VendorInvoiceLineDraft");
            }
        }

        $organizationId = $this->currentOrganization->resolve()?->id;

        return DB::transaction(function () use ($supplier, $headerData, $lineDrafts, $createdBy, $organizationId) {
            $invoiceDate = isset($headerData['invoice_date'])
                ? CarbonImmutable::parse($headerData['invoice_date'])
                : CarbonImmutable::today();

            // Compute due date: explicit > supplier terms > category terms > 30
            $dueDate = $headerData['due_date'] ?? null;
            if ($dueDate === null) {
                $terms = $supplier->payment_terms_days
                    ?? $supplier->supplierCategory?->payment_terms_days
                    ?? 30;
                $dueDate = $invoiceDate->addDays((int) $terms);
            } else {
                $dueDate = CarbonImmutable::parse($dueDate);
            }

            $vendorInvoiceNumber = $this->numbering->next(
                sequenceType:   'vendor_invoice',
                prefix:         'VI',
                year:           (int) $invoiceDate->format('Y'),
                organizationId: $organizationId,
                padding:        6,
            );

            // Header
            $invoice = VendorInvoice::create([
                'organization_id'         => $organizationId,
                'vendor_invoice_number'   => $vendorInvoiceNumber,
                'supplier_invoice_number' => $headerData['supplier_invoice_number'] ?? null,
                'invoice_date'            => $invoiceDate->toDateString(),
                'due_date'                => $dueDate->toDateString(),
                'received_at'             => isset($headerData['received_at'])
                    ? CarbonImmutable::parse($headerData['received_at'])->toDateString()
                    : null,
                'counterparty_id'         => $supplier->id,
                'currency_code'           => $headerData['currency_code'] ?? $supplier->currency_code ?? 'GHS',
                'exchange_rate'           => $headerData['exchange_rate'] ?? '1',
                'source_type'             => $headerData['source_type'] ?? null,
                'source_id'               => $headerData['source_id'] ?? null,
                'purchase_request_id'     => $headerData['purchase_request_id'] ?? null,
                'status'                  => VendorInvoice::STATUS_DRAFT,
                'created_by_user_id'      => $createdBy?->id,
                'description'             => $headerData['description'] ?? null,
                'notes'                   => $headerData['notes'] ?? null,
                'internal_notes'          => $headerData['internal_notes'] ?? null,
                // Init aggregates to zero — recomputed below.
                'subtotal'                => '0',
                'tax_total'                => '0',
                'withholding_total'       => '0',
                'total'                   => '0',
                'balance_due'             => '0',
            ]);

            // Lines
            foreach ($lineDrafts as $idx => $ld) {
                $accountId = $this->accountResolver->resolve(
                    supplier:           $supplier,
                    explicitAccountId:  $ld->accountId,
                    rawMaterialId:      $ld->rawMaterialId,
                );

                // WHT override: supplier exemption forces line-level WHT to zero
                $whtRate = $supplier->withholding_exempt ? '0' : $ld->withholdingRate;
                $whtAmount = $supplier->withholding_exempt ? '0.00' : $ld->lineWithholdingAmount;
                $whtCodeId = $supplier->withholding_exempt ? null : $ld->withholdingTaxCodeId;

                VendorInvoiceLine::create([
                    'organization_id'           => $organizationId,
                    'vendor_invoice_id'         => $invoice->id,
                    'line_number'               => $idx + 1,
                    'description'               => $ld->description,
                    'raw_material_id'           => $ld->rawMaterialId,
                    'purchase_request_item_id'  => $ld->purchaseRequestItemId,
                    'quantity'                  => $ld->quantity,
                    'unit'                      => $ld->unit,
                    'unit_cost'                 => $ld->unitCost,
                    'line_subtotal'             => $ld->lineSubtotal,
                    'tax_code_id'               => $ld->taxCodeId,
                    'tax_rate'                  => $ld->taxRate,
                    'tax_amount'                => $ld->lineTaxAmount,
                    'withholding_tax_code_id'   => $whtCodeId,
                    'withholding_rate'          => $whtRate,
                    'withholding_amount'        => $whtAmount,
                    'line_total'                => $ld->lineTotal,
                    'account_id'                => $accountId,
                ]);
            }

            $this->recomputeHeader($invoice);

            return $invoice->fresh(['lines', 'counterparty']);
        });
    }

    /**
     * Submit a draft for approval. Required before posting.
     */
    public function submitForApproval(VendorInvoice $invoice, User $submittedBy): VendorInvoice
    {
        if (! $invoice->canSubmitForApproval()) {
            throw new \DomainException(
                "Invoice {$invoice->vendor_invoice_number} cannot be submitted for approval " .
                "(status: {$invoice->status}, has lines: " . ($invoice->lines()->exists() ? 'yes' : 'no') . ')'
            );
        }

        $invoice->update([
            'status'                              => VendorInvoice::STATUS_PENDING_APPROVAL,
            'submitted_for_approval_by_user_id'   => $submittedBy->id,
            'submitted_for_approval_at'           => now(),
        ]);

        return $invoice->fresh();
    }

    /**
     * Cancel a draft or pending invoice. No GL impact.
     */
    public function cancel(VendorInvoice $invoice, User $cancelledBy, string $reason): VendorInvoice
    {
        if (! $invoice->canCancel()) {
            throw new \DomainException(
                "Invoice {$invoice->vendor_invoice_number} cannot be cancelled " .
                "(status: {$invoice->status})"
            );
        }

        $invoice->update([
            'status'                  => VendorInvoice::STATUS_CANCELLED,
            'cancelled_by_user_id'    => $cancelledBy->id,
            'cancelled_at'            => now(),
            'cancellation_reason'     => $reason,
        ]);

        return $invoice->fresh();
    }

    /**
     * Recompute header aggregates from line totals.
     */
    public function recomputeHeader(VendorInvoice $invoice): VendorInvoice
    {
        $lines = $invoice->lines()->get();

        $subtotal = '0';
        $taxTotal = '0';
        $whtTotal = '0';
        $total = '0';

        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line->line_subtotal, 2);
            $taxTotal = bcadd($taxTotal, (string) $line->tax_amount, 2);
            $whtTotal = bcadd($whtTotal, (string) $line->withholding_amount, 2);
            $total    = bcadd($total,    (string) $line->line_total, 2);
        }

        $invoice->update([
            'subtotal'           => $subtotal,
            'tax_total'          => $taxTotal,
            'withholding_total'  => $whtTotal,
            'total'              => $total,
            'balance_due'        => $total,  // Initial balance = full total; payments reduce it
        ]);

        return $invoice->fresh();
    }

    /**
     * Add a line to a draft invoice. Recomputes header.
     */
    public function addLine(VendorInvoice $invoice, VendorInvoiceLineDraft $draft): VendorInvoiceLine
    {
        if (! $invoice->isDraft()) {
            throw new \DomainException("Cannot add lines to invoice in status '{$invoice->status}'");
        }

        return DB::transaction(function () use ($invoice, $draft) {
            $supplier = $invoice->counterparty;
            $accountId = $this->accountResolver->resolve(
                supplier:           $supplier,
                explicitAccountId:  $draft->accountId,
                rawMaterialId:      $draft->rawMaterialId,
            );

            $whtRate    = $supplier->withholding_exempt ? '0'    : $draft->withholdingRate;
            $whtAmount  = $supplier->withholding_exempt ? '0.00' : $draft->lineWithholdingAmount;
            $whtCodeId  = $supplier->withholding_exempt ? null   : $draft->withholdingTaxCodeId;

            $maxLineNumber = (int) $invoice->lines()->max('line_number');

            $line = VendorInvoiceLine::create([
                'organization_id'           => $invoice->organization_id,
                'vendor_invoice_id'         => $invoice->id,
                'line_number'               => $maxLineNumber + 1,
                'description'               => $draft->description,
                'raw_material_id'           => $draft->rawMaterialId,
                'purchase_request_item_id'  => $draft->purchaseRequestItemId,
                'quantity'                  => $draft->quantity,
                'unit'                      => $draft->unit,
                'unit_cost'                 => $draft->unitCost,
                'line_subtotal'             => $draft->lineSubtotal,
                'tax_code_id'               => $draft->taxCodeId,
                'tax_rate'                  => $draft->taxRate,
                'tax_amount'                => $draft->lineTaxAmount,
                'withholding_tax_code_id'   => $whtCodeId,
                'withholding_rate'          => $whtRate,
                'withholding_amount'        => $whtAmount,
                'line_total'                => $draft->lineTotal,
                'account_id'                => $accountId,
            ]);

            $this->recomputeHeader($invoice);

            return $line->fresh();
        });
    }

    /**
     * Remove a line. Recomputes header.
     */
    public function removeLine(VendorInvoiceLine $line): void
    {
        $invoice = $line->vendorInvoice;
        if (! $invoice->isDraft()) {
            throw new \DomainException("Cannot remove lines from invoice in status '{$invoice->status}'");
        }

        DB::transaction(function () use ($line, $invoice) {
            $line->delete();
            $this->recomputeHeader($invoice);
        });
    }
}
