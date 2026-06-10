<?php

namespace App\Finance\Subscribers\Sales;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Resolvers\CounterpartyResolver;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\Invoice;
use App\Models\Setting;
use App\Models\PigSale;
use App\Services\Finance\Invoicing\InvoiceLineDraft;
use App\Services\Finance\Invoicing\InvoicePostingService;
use App\Services\Finance\Invoicing\InvoiceService;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2b — when finance.use_invoices_for_pig_sales is TRUE, this
 * subscriber handles pig_sale.recorded events INSTEAD of the Phase 1c
 * direct-posting one.
 *
 * Behaviour:
 *   1. Read the settings flag. If FALSE → return null (skip; the legacy
 *      Phase 1c subscriber will handle it instead).
 *   2. If the pig_sale already has invoice_id set → already handled,
 *      return null.
 *   3. Build an InvoiceLineDraft from the pig sale's data.
 *   4. Create a draft invoice via InvoiceService.
 *   5. Auto-post it via InvoicePostingService (operations recorded the
 *      sale, so authorisation is implicit — no two-step approval needed
 *      for auto-generated invoices).
 *   6. Write the invoice id back to pig_sales.invoice_id.
 *
 * IMPORTANT: this subscriber does NOT extend the regular Subscriber
 * abstract base because:
 *   - The base class's buildDraft() returns a JournalBatchDraft. This
 *     subscriber posts via the invoice flow, not directly.
 *   - The base's idempotency goes through PostingEngine. The invoice
 *     flow has its OWN idempotency at the invoice level (the source
 *     index on invoices catches duplicates from a re-delivered event).
 *
 * Instead, this subscriber implements the same handle($eventId) shape
 * but routes through InvoiceService → InvoicePostingService.
 *
 * Coexistence:
 *   The SubscriberRegistry can hold MULTIPLE subscribers per event_type.
 *   In Phase 2b deployment, BOTH this subscriber AND the legacy
 *   PigSaleRecordedSubscriber are registered. Each checks the flag and
 *   the other side's marker (pig_sales.invoice_id, pig_sales.is_posted)
 *   so only one acts on any given event:
 *
 *     flag ON  + invoice_id NULL   → this subscriber acts
 *     flag ON  + invoice_id SET    → already handled (re-delivery), skip
 *     flag OFF + is_posted FALSE   → legacy subscriber acts
 *     flag OFF + is_posted TRUE    → already handled, skip
 *
 *   The legacy subscriber's existing payment_status checks remain.
 *   When operations are confident in the new flow, future batch can
 *   retire the legacy subscriber entirely.
 */
class PigSaleInvoicingSubscriber extends Subscriber
{
    public function __construct(
        protected CounterpartyResolver $counterpartyResolver,
        protected InvoiceService $invoiceService,
        protected InvoicePostingService $postingService,
    ) {
    }

    protected function eventType(): string
    {
        return 'pig_sale.recorded';
    }

    /**
     * Override the base handle() — this subscriber does not produce a
     * JournalBatchDraft. Posts via invoice flow instead.
     */
    public function handle(int $domainEventId): void
    {
        $event = DomainEvent::find($domainEventId);
        if (! $event || $event->event_type !== $this->eventType()) {
            return;
        }
        // Settings flag check.
        if (! $this->flagEnabled()) {
            // Mark skipped so the legacy subscriber can pick it up.
            // Actually — DON'T mark it. Multiple subscribers run per event
            // in ProcessPendingDomainEventsJob's loop. If we mark it
            // skipped here, the legacy subscriber after us sees status=
            // skipped and won't act.
            return;
        }

        $sale = PigSale::find($event->source_id);
        if (! $sale) {
            $this->markEvent($event, DomainEvent::STATUS_FAILED, 'PigSale source missing');
            return;
        }

        // Guard against re-delivery: if pig_sales.invoice_id is set, skip.
        if ($sale->invoice_id) {
            $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
            return;
        }

        try {
            $invoice = $this->createAndPostInvoice($sale, $event);

            // Link the pig sale back to the invoice (also flips legacy is_posted).
            $sale->update([
                'invoice_id' => $invoice->id,
                'is_posted'  => true,
                'posted_at'  => now(),
            ]);

            $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
        } catch (\Throwable $e) {
            $this->markEvent($event, DomainEvent::STATUS_FAILED, $e->getMessage());
            Log::error("PigSaleInvoicingSubscriber failed for event #{$event->id}", [
                'error' => $e->getMessage(),
                'sale_id' => $sale->id,
            ]);
            throw $e;
        }
    }

    /**
     * Required by the abstract base — but we don't use it.
     */
    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        return null;
    }

    /**
     * Create + immediately post the invoice for this pig sale.
     */
    protected function createAndPostInvoice(PigSale $sale, DomainEvent $event): Invoice
    {
        $customer = $this->counterpartyResolver->forPigSale($sale->customer_id);

        $line = new InvoiceLineDraft(
            description:    sprintf(
                'Pig %s — %s kg @ %s/kg (%s)',
                $sale->pig?->ear_tag_number ?? "pig#{$sale->pig_id}",
                number_format((float) $sale->sale_weight, 2),
                number_format((float) $sale->price_per_kg, 2),
                $sale->weight_type ?? 'live',
            ),
            quantity:        (string) (float) $sale->sale_weight,
            unitPrice:       (string) (float) $sale->price_per_kg,
            unitOfMeasure:   'kg',
            itemCode:        $sale->pig?->ear_tag_number,
            pigId:           $sale->pig_id,
            taxCodeId:       $sale->tax_code_id,
            costCentreId:    $sale->cost_centre_id,
            salesChannelId:  $sale->sales_channel_id,
        );

        // Verify computed line total roughly matches the sale_price.
        // If they diverge significantly, log — the sale may have manual
        // total override that doesn't match weight × price-per-kg.
        $expectedTotal = (string) (float) $sale->sale_price;
        if (bccomp(bcsub($line->lineTotal, $expectedTotal, 2), '0.05', 2) > 0
            || bccomp(bcsub($expectedTotal, $line->lineTotal, 2), '0.05', 2) > 0) {
            Log::info("PigSaleInvoicingSubscriber: computed line total {$line->lineTotal} differs from sale_price {$expectedTotal} for sale #{$sale->id}");
        }

        $invoice = $this->invoiceService->createDraft(
            customer:    $customer,
            headerData: [
                'invoice_date'      => $sale->sale_date,
                'source_type'       => PigSale::class,
                'source_id'         => $sale->id,
                'currency_code'     => $customer->currency_code ?? 'GHS',
                'payment_method_id' => $sale->payment_method_id,
                'reference'         => $sale->document_number,
                'description'       => sprintf(
                    'Pig sale to %s on %s',
                    $sale->buyer_name ?? $customer->name,
                    optional($sale->sale_date)->format('Y-m-d'),
                ),
            ],
            lineDrafts: [$line],
            createdBy: $event->dispatchedBy,
        );

        // Auto-post — operations already authorised by recording the sale.
        $postingUser = $event->dispatchedBy ?? $invoice->createdBy;
        return $this->postingService->post($invoice, $postingUser);
    }

    protected function flagEnabled(): bool
    {
        return (bool) Setting::get('finance.use_invoices_for_pig_sales', false);
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
