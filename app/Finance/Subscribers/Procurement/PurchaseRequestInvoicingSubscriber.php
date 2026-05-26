<?php

namespace App\Finance\Subscribers\Procurement;

use App\Models\Counterparty;
use App\Models\DomainEvent;
use App\Models\Finance\VendorInvoice;
use App\Models\PurchaseRequest;
use App\Models\Setting;
use App\Services\Finance\VendorInvoicing\VendorInvoiceLineDraft;
use App\Services\Finance\VendorInvoicing\VendorInvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3b — when finance.use_vendor_invoices_for_purchase_requests is TRUE,
 * this subscriber listens to purchase_request.approved events and creates
 * a placeholder vendor invoice in DRAFT status.
 *
 * The placeholder pre-populates lines from purchase_request_items. When the
 * actual supplier bill arrives:
 *   1. Accounts opens the draft VI from the PR show page.
 *   2. Adjusts line quantities/prices to match the supplier's invoice.
 *   3. Sets supplier_invoice_number from the bill.
 *   4. Attaches the bill PDF (via Phase 2d's polymorphic attachments).
 *   5. Three-way matching service surfaces variances against PR + receipts.
 *   6. Submits for approval. Treasury approves and posts.
 *
 * Why pre-populate at PR approval rather than at PR receipt?
 *   The PR's line items already capture what we expect to be billed for.
 *   Creating the VI early lets accounts see "open bills expected" alongside
 *   actual bills received. Matches operational reality — operations
 *   approves a PR knowing the bill is on its way.
 *
 *   Phase 4+ may shift this trigger to "receipt event" once the goods-in
 *   flow is properly modelled, since accruing AP at PR-approval-but-not-yet-
 *   received overstates AP for unreceived goods. Tradeoff acceptable for
 *   Phase 3 — operations needs the visibility today.
 *
 * Coexistence with legacy:
 *   The legacy PurchaseRequestPaid event has no AP subscriber — purchase
 *   requests in the existing system don't produce GL entries. So unlike
 *   Phase 2b's pig-sale subscriber pattern, there's no legacy AP path
 *   to early-return out of. The flag controls whether AP runs AT ALL.
 *
 *   When flag is OFF: PRs continue to operate without finance integration
 *   (current behaviour). When ON: every approved PR generates a draft VI
 *   that accounts must clear or cancel.
 *
 * Idempotency:
 *   Re-delivery is caught by checking if a vendor_invoice with
 *   source_type='App\Models\PurchaseRequest' AND source_id=PR.id already
 *   exists. If yes, skip.
 */
class PurchaseRequestInvoicingSubscriber implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected VendorInvoiceService $invoiceService,
    ) {
    }

    public static function eventType(): string
    {
        return 'purchase_request.approved';
    }

    public function handle(int $domainEventId): void
    {
        $event = DomainEvent::find($domainEventId);
        if (! $event || $event->event_type !== static::eventType()) {
            return;
        }
        if ($event->status === DomainEvent::STATUS_PROCESSED) {
            return;
        }

        // Flag check — if disabled, skip silently. Don't mark the event
        // as skipped because future deliveries to other subscribers
        // (none today, but possibly later) shouldn't be blocked.
        if (! $this->flagEnabled()) {
            return;
        }

        $pr = PurchaseRequest::find($event->source_id);
        if (! $pr) {
            $event->markFailed('PurchaseRequest source missing');
            return;
        }

        // Idempotency: already created VI for this PR?
        $exists = VendorInvoice::query()
            ->where('source_type', PurchaseRequest::class)
            ->where('source_id', $pr->id)
            ->exists();
        if ($exists) {
            $event->markProcessed();
            return;
        }

        // PR must have a supplier_id by the time it's approved. Phase 0.8
        // readiness migration added the column; if it's null, ops hasn't
        // assigned a supplier — we can't create a VI.
        if (! $pr->supplier_id) {
            $event->markSkipped('PurchaseRequest has no supplier_id assigned');
            Log::info("PurchaseRequestInvoicingSubscriber: skipping PR #{$pr->id} — no supplier assigned");
            return;
        }

        $supplier = Counterparty::find($pr->supplier_id);
        if (! $supplier || ! $supplier->isSupplier()) {
            $event->markFailed("PurchaseRequest #{$pr->id} supplier_id={$pr->supplier_id} not found or not a supplier");
            return;
        }

        try {
            $invoice = $this->createDraftFromPr($pr, $supplier, $event);

            Log::info("PurchaseRequestInvoicingSubscriber: created draft VI #{$invoice->id} from PR #{$pr->id}");

            $event->markProcessed();
        } catch (\Throwable $e) {
            $event->markFailed($e->getMessage());
            Log::error("PurchaseRequestInvoicingSubscriber failed for event #{$event->id}", [
                'error' => $e->getMessage(),
                'pr_id' => $pr->id,
            ]);
            throw $e;
        }
    }

    /**
     * Build line drafts from purchase_request_items.
     */
    protected function createDraftFromPr(PurchaseRequest $pr, Counterparty $supplier, DomainEvent $event): VendorInvoice
    {
        $pr->loadMissing('items');

        if ($pr->items->isEmpty()) {
            throw new \DomainException(
                "PurchaseRequest #{$pr->id} has no items — cannot create vendor invoice"
            );
        }

        $lineDrafts = [];
        foreach ($pr->items as $item) {
            // For each PR item, default tax/withholding from supplier's category
            // and the raw_material's default_purchase_tax_code_id if linked.
            // Withholding will be honoured per supplier policy via VendorInvoiceService.
            $lineDrafts[] = new VendorInvoiceLineDraft(
                description:           $item->item_name . ($item->description ? " — {$item->description}" : ''),
                quantity:              (string) (float) $item->quantity,
                unitCost:              (string) (float) $item->unit_price,
                unit:                  null,  // PR items don't track unit
                rawMaterialId:         null,  // PR items don't link to raw_materials yet
                purchaseRequestItemId: $item->id,
                taxRate:               '0',   // No tax on placeholder; accounts adjusts when bill arrives
                taxCodeId:             null,
                withholdingRate:       $supplier->effectiveWithholdingPercent(),
                withholdingTaxCodeId:  null,  // Captured at edit time
                accountId:             null,  // Resolver picks based on category/supplier
            );
        }

        return $this->invoiceService->createDraft(
            supplier:    $supplier,
            headerData: [
                'invoice_date'        => now()->toDateString(),
                'received_at'         => null,  // Bill not yet received
                'source_type'         => PurchaseRequest::class,
                'source_id'           => $pr->id,
                'purchase_request_id' => $pr->id,
                'currency_code'       => $supplier->currency_code ?? 'GHS',
                'description'         => sprintf(
                    'Auto-draft from PR %s (%s)',
                    $pr->request_no,
                    $pr->purpose ? \Illuminate\Support\Str::limit($pr->purpose, 60) : 'no purpose stated',
                ),
                'internal_notes'      => "Generated from PurchaseRequest #{$pr->id} on " . now()->toIso8601String() . ". Adjust line items to match supplier's actual invoice before submitting for approval.",
            ],
            lineDrafts: $lineDrafts,
            createdBy:  $event->dispatchedBy,
        );
    }

    protected function flagEnabled(): bool
    {
        return (bool) Setting::get('finance.use_vendor_invoices_for_purchase_requests', false);
    }
}
