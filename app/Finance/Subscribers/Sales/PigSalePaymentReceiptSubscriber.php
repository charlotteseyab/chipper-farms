<?php

namespace App\Finance\Subscribers\Sales;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\Counterparty;
use App\Models\DomainEvent;
use App\Models\Finance\Invoice;
use App\Models\PaymentMethod;
use App\Models\PigSale;
use App\Services\Finance\Allocations\AllocationService;
use App\Services\Finance\Receipts\ReceiptService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2c — auto-creates a receipt + allocation when an invoiced pig
 * sale's payment status changes to 'paid'.
 *
 * This subscriber sits ALONGSIDE the legacy PigSalePaymentReceivedSubscriber
 * (which posts directly to GL when the new invoice flow is OFF). The
 * coordination rule is the same as the recorded-event subscribers:
 *
 *   sale.invoice_id IS NULL  → legacy path; this subscriber returns
 *   sale.invoice_id IS SET   → new path; this subscriber acts, legacy returns
 *
 * The legacy subscriber's existing logic continues to work for sales
 * created before the flag was flipped (those have no invoice_id). The
 * patch document for the legacy subscriber documents the early-return.
 *
 * Routing by new payment status:
 *   paid     → create receipt at invoice.balance_due, auto-allocate to invoice
 *   partial  → log info; receipt creation requires a manual amount,
 *              so we don't synthesise one. Operations enters via Receipts UI.
 *   pending  → no-op (refund or write-off, handled separately)
 *   other    → log warning, mark processed
 *
 * Idempotency:
 *   If the invoice is already fully paid (invoice.fully_paid_at IS NOT NULL),
 *   the subscriber treats this as a re-delivery and silently marks
 *   processed. The PaymentMethod check ensures we don't double-receipt
 *   when status flips paid → partial → paid in quick succession.
 *
 * Cash invoices:
 *   For cash invoices (already paid at posting time), the payment_status
 *   change is metadata only — no AR balance to settle. The subscriber
 *   detects this via invoice.is_cash_invoice and returns without action.
 */
class PigSalePaymentReceiptSubscriber extends Subscriber
{
    public function __construct(
        protected ReceiptService $receiptService,
        protected AllocationService $allocationService,
    ) {
    }

    protected function eventType(): string
    {
        return 'pig_sale.payment_status_changed';
    }

    /**
     * Override base handle() — this subscriber routes through the receipts/
     * allocations service stack rather than producing a single JournalBatchDraft.
     */
    public function handle(int $domainEventId): void
    {
        $event = DomainEvent::find($domainEventId);
        if (! $event || $event->event_type !== $this->eventType()) {
            return;
        }
        $sale = PigSale::find($event->source_id);
        if (! $sale) {
            $this->markEvent($event, DomainEvent::STATUS_FAILED, 'PigSale source missing');
            return;
        }

        // Coordination with legacy subscriber: only act on sales that
        // were invoiced via the new flow.
        if (! $sale->invoice_id) {
            // Pre-2c sale or flag-was-off sale; legacy subscriber handles it.
            // We don't mark processed — leaving status PENDING for legacy.
            return;
        }

        $invoice = Invoice::find($sale->invoice_id);
        if (! $invoice) {
            $this->markEvent($event, DomainEvent::STATUS_FAILED, "Invoice #{$sale->invoice_id} missing");
            return;
        }

        // Cash invoices have no AR to settle — already paid at posting.
        if ($invoice->is_cash_invoice) {
            $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
            return;
        }

        // Already fully paid? Re-delivery / state flip noise — silently no-op.
        if ($invoice->isFullyPaid()) {
            $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
            return;
        }

        $newStatus = $event->payload['to_status'] ?? $event->payload['new_status'] ?? null;

        try {
            switch ($newStatus) {
                case 'paid':
                    $this->createReceiptAndAllocate($sale, $invoice, $event);
                    $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
                    break;

                case 'partial':
                    Log::info("PigSalePaymentReceiptSubscriber: sale #{$sale->id} marked partial — manual receipt entry required");
                    $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
                    break;

                case 'pending':
                    // Status moved BACK to pending — likely a correction or refund.
                    // The receipt+allocation reversal flow is manual through the
                    // Receipts UI. This event is informational.
                    Log::info("PigSalePaymentReceiptSubscriber: sale #{$sale->id} reverted to pending — no auto action");
                    $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
                    break;

                default:
                    Log::warning("PigSalePaymentReceiptSubscriber: sale #{$sale->id} unknown status '{$newStatus}'");
                    $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
            }
        } catch (\Throwable $e) {
            $this->markEvent($event, DomainEvent::STATUS_FAILED, $e->getMessage());
            Log::error("PigSalePaymentReceiptSubscriber failed for event #{$event->id}", [
                'sale_id' => $sale->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        // Unused; we override handle() above.
        return null;
    }

    protected function createReceiptAndAllocate(PigSale $sale, Invoice $invoice, DomainEvent $event): void
    {
        $customer = Counterparty::findOrFail($invoice->counterparty_id);

        $paymentMethod = $sale->payment_method_id
            ? PaymentMethod::find($sale->payment_method_id)
            : null;

        if (! $paymentMethod) {
            // Sales without an explicit payment method default to cash. Treasury
            // can later edit/move the receipt to the correct cash account.
            $paymentMethod = PaymentMethod::active()
                ->where('is_cash', true)
                ->where('organization_id', $sale->organization_id)
                ->first();

            if (! $paymentMethod) {
                throw new \DomainException(
                    "No payment method on sale #{$sale->id} and no default cash method exists in org"
                );
            }
        }

        $receiptDate = $sale->payment_status_updated_at
            ? CarbonImmutable::instance($sale->payment_status_updated_at)
            : CarbonImmutable::today();

        $amount = (string) $invoice->balance_due;
        $actor = $this->resolveActor($event, $invoice, $sale);

        $receipt = $this->receiptService->createPosted(
            customer: $customer,
            paymentMethod: $paymentMethod,
            amount: $amount,
            receiptDate: $receiptDate,
            createdBy: $actor,
            extras: [
                'description' => "Auto-receipt: pig sale #{$sale->id} marked paid",
                'currency_code' => $invoice->currency_code,
            ],
        );

        $this->allocationService->allocate(
            receipt: $receipt,
            invoice: $invoice,
            amount: $amount,
            user: $actor,
            allocationDate: $receiptDate,
            notes: "Auto-allocated from pig sale payment status change",
        );
    }

    /**
     * Resolve who to attribute the auto-receipt to. Tries in order:
     *   - the user who dispatched the event (operations recorded payment)
     *   - the invoice creator
     *   - the invoice poster
     *   - the sale's payment_status_updated_by user
     *   - first superuser/admin in the org as last resort
     */
    protected function resolveActor(DomainEvent $event, Invoice $invoice, PigSale $sale): \App\Models\User
    {
        $candidates = [
            $event->dispatchedBy ?? null,
            $invoice->createdBy ?? null,
            $invoice->postedBy ?? null,
            $sale->payment_status_updated_by_user_id
                ? \App\Models\User::find($sale->payment_status_updated_by_user_id)
                : null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof \App\Models\User) {
                return $candidate;
            }
        }

        // Last resort: any superuser/admin in the org. Should rarely hit.
        $fallback = \App\Models\User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['superuser', 'admin']))
            ->where('organization_id', $sale->organization_id)
            ->orderBy('id')
            ->first();

        if (! $fallback) {
            throw new \DomainException(
                "Cannot resolve user for auto-receipt on sale #{$sale->id} (no candidates and no fallback admin found)"
            );
        }

        return $fallback;
    }

    protected function markEvent(DomainEvent $event, string $status, ?string $error = null): void
    {
        $event->update([
            'status'              => $status,
            'processed_at'        => now(),
            'processing_error'    => $error,
            'processing_attempts' => ($event->processing_attempts ?? 0) + 1,
        ]);
    }
}
