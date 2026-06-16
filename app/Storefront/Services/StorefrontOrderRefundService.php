<?php

namespace App\Storefront\Services;

use App\Models\FeedSale;
use App\Models\Finance\JournalBatch;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\User;
use App\Services\Finance\JournalReversalService;
use App\Storefront\Services\Exceptions\InvalidOrderTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Refunds a storefront order.
 *
 * Scope boundary (deliberate — see README Phase 6):
 *   - Reverses the CUSTOMER-FACING financial legs correctly via the audited
 *     JournalReversalService: the revenue batch (which, for a cash sale, also
 *     returns the cash in one balanced reversal) and any settled cash-receipt
 *     batch. These have no inventory-subledger coupling, so the reversal is
 *     drift-free.
 *   - Restores storefront OPERATIONAL availability (product listing quantity /
 *     republish) so stock is sellable again.
 *   - Does NOT auto-reverse COGS / inventory / biological-asset legs or
 *     un-delete a sold pig: there is no drift-free primitive for a sales
 *     return, so these are explicitly HANDED TO FINANCE via a recorded
 *     finance_reversal_note rather than risk GL↔subledger drift.
 *
 * If the order was never posted to finance (finance_posting_status = pending),
 * the refund is clean — there is simply nothing to reverse.
 *
 * Authorisation (refund_storefront_orders, treasurer-grade) is enforced at the
 * controller; this service performs the reversal.
 */
class StorefrontOrderRefundService
{
    public function __construct(
        protected JournalReversalService $reversalService,
        protected ListingService $listingService,
    ) {
    }

    public function refund(StorefrontOrder $order, User $actor, string $reason): StorefrontOrder
    {
        if (! $this->isRefundable($order)) {
            throw new InvalidOrderTransitionException(
                "Order {$order->reference} cannot be refunded from status '{$order->status}'."
            );
        }

        return DB::transaction(function () use ($order, $actor, $reason) {
            $order->loadMissing('items');

            $reversed = [];
            $handed   = [];

            if ($order->finance_posting_status === 'posted') {
                foreach ($order->items as $item) {
                    if ($item->posted_sale_type === FeedSale::class && $item->posted_sale_id) {
                        $this->reverseFeedSale((int) $item->posted_sale_id, $actor, $order->reference, $reversed, $handed);
                    } elseif ($item->posted_sale_type === PigSale::class && $item->posted_sale_id) {
                        $handed[] = "PigSale#{$item->posted_sale_id}: invoice/COGS/biological-asset reversal + pig restore — process as a sales return";
                    }
                }
            } else {
                $reversed[] = 'No finance posting existed — clean refund, nothing to reverse';
            }

            $this->restoreOperationalAvailability($order, $actor, $handed);

            $note = 'Reversed automatically: ' . (empty($reversed) ? 'none' : implode('; ', $reversed)) . '.';
            if (! empty($handed)) {
                $note .= ' Handed to finance (sales-return): ' . implode('; ', $handed) . '.';
            }

            $order->forceFill([
                'status'                => StorefrontOrder::STATUS_REFUNDED,
                'payment_status'        => StorefrontOrder::PAY_REFUNDED,
                'refunded_at'           => now(),
                'refunded_by_user_id'   => $actor->id,
                'refund_reason'         => substr($reason, 0, 500),
                'finance_reversal_note' => $note,
            ])->save();

            $this->recordEvent($order, $actor, "Order refunded. {$note}");

            return $order->refresh();
        });
    }

    protected function isRefundable(StorefrontOrder $order): bool
    {
        return in_array($order->status, [
            StorefrontOrder::STATUS_CONFIRMED,
            StorefrontOrder::STATUS_READY_FOR_PICKUP,
            StorefrontOrder::STATUS_OUT_FOR_DELIVERY,
            StorefrontOrder::STATUS_COMPLETED,
            StorefrontOrder::STATUS_REFUND_REQUESTED,
            StorefrontOrder::STATUS_REFUND_APPROVED,
        ], true);
    }

    protected function reverseFeedSale(int $saleId, User $actor, string $orderRef, array &$reversed, array &$handed): void
    {
        $sale = FeedSale::find($saleId);
        if (! $sale) {
            return;
        }

        // Revenue (cash sale: this leg also returns the cash).
        if ($sale->revenue_journal_batch_id) {
            $batch = JournalBatch::find($sale->revenue_journal_batch_id);
            if ($this->reversible($batch)) {
                $this->reversalService->reverse($batch, $actor, "Storefront refund: order {$orderRef}");
                $reversed[] = "FeedSale#{$sale->id} revenue batch #{$batch->id}";
            }
        }

        // Settled cash receipt (COD collected at completion) — returns the cash.
        $receipt = JournalBatch::query()
            ->where('source_type', FeedSale::class)
            ->where('source_id', $sale->id)
            ->where('batch_type', 'auto_feed_sale_receipt')
            ->where('status', JournalBatch::STATUS_POSTED)
            ->first();
        if ($this->reversible($receipt)) {
            $this->reversalService->reverse($receipt, $actor, "Storefront refund (receipt): order {$orderRef}");
            $reversed[] = "FeedSale#{$sale->id} receipt batch #{$receipt->id}";
        }

        // COGS / inventory: no drift-free sales-return primitive — hand to finance.
        if ($sale->cogs_journal_batch_id) {
            $handed[] = "FeedSale#{$sale->id} COGS/inventory (batch #{$sale->cogs_journal_batch_id}) — process as a sales return";
        }
    }

    protected function reversible(?JournalBatch $batch): bool
    {
        return $batch !== null
            && $batch->isPosted()
            && $batch->reversed_by_batch_id === null;
    }

    /**
     * Make stock sellable again (storefront-side only). Product listings have
     * their quantity returned and are republished; pig listings whose pig was
     * sold/soft-deleted are left sold_out and flagged for the sales return.
     */
    protected function restoreOperationalAvailability(StorefrontOrder $order, User $actor, array &$handed): void
    {
        foreach ($order->items as $item) {
            if (! $item->listing_id) {
                continue;
            }
            $listing = StorefrontListing::find($item->listing_id);
            if (! $listing) {
                continue;
            }

            $isPig = in_array($listing->kind, [StorefrontListing::KIND_LIVE_PIG, StorefrontListing::KIND_BREEDING_STOCK], true);

            if ($isPig) {
                // The pig was soft-deleted by the sale spine; restoring it is
                // part of the finance sales return, not a storefront action.
                $handed[] = "Listing #{$listing->id} (pig) remains sold_out pending pig restore";
                continue;
            }

            // Product (feed/manure/etc.): return the quantity and republish.
            if ($listing->quantity_available !== null) {
                $listing->quantity_available = bcadd((string) $listing->quantity_available, (string) $item->quantity, 4);
            }
            if (in_array($listing->status, [StorefrontListing::STATUS_SOLD_OUT, StorefrontListing::STATUS_RESERVED], true)) {
                $listing->status = StorefrontListing::STATUS_PUBLISHED;
            }
            $listing->save();
        }
    }

    protected function recordEvent(StorefrontOrder $order, User $actor, string $note): void
    {
        \App\Models\Storefront\StorefrontOrderEvent::create([
            'organization_id' => $order->organization_id,
            'order_id'        => $order->id,
            'type'            => 'status_change',
            'from_status'     => null,
            'to_status'       => StorefrontOrder::STATUS_REFUNDED,
            'note'            => $note,
            'actor_user_id'   => $actor->id,
        ]);
    }
}
