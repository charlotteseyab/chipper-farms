<?php

namespace App\Finance\Subscribers\Sales;

use App\Domain\Events\Sales\SalesReturnRecorded;
use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\SalesReturn;
use App\Services\Finance\Inventory\InventoryAccountResolver;
use App\Services\Finance\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Reverses the COGS and inventory legs of a returned FEED sale (CF-P0-008).
 *
 * This is the mirror of FeedSaleCogsSubscriber, and it exists because that
 * subscriber does two things which must be undone TOGETHER or not at all:
 *
 *   1. InventoryService::recordUsage() — decrements the finished-feed subledger
 *   2. a GL journal — Dr COGS / Cr Inventory for the movement's value
 *
 * Reversing only the journal (which JournalReversalService could do on its own)
 * would tell the general ledger that stock is back while the subledger still
 * says it left. That GL/subledger drift is exactly why the original
 * StorefrontOrderRefundService refused to attempt this and handed it to finance
 * as a note instead. The note was honest; it was just never actioned.
 *
 * COST BASIS — the single most important detail here
 * --------------------------------------------------
 * Stock re-enters at the unit cost it LEFT at, read from the original
 * InventoryMovement via FeedSale::inventory_movement_id.
 *
 * It is tempting to call recordReceive() and let it use the current weighted
 * average. That is wrong: WAC moves between the sale and the return, so
 * returning at today's cost books an inventory valuation gain or loss that never
 * occurred, silently, into cost of sales. The original movement is the only
 * record of what the goods actually cost when they left.
 *
 * RESTOCK
 * -------
 * When restock is false the goods are not coming back — spoiled feed, or an item
 * the customer keeps. The cost stays consumed, so this subscriber posts NOTHING.
 * Only revenue is reversed (elsewhere). Reversing COGS for goods that never
 * returned would understate cost of sales and overstate inventory.
 *
 * PERIOD
 * ------
 * The reversal posts on the RETURN's date, not the sale's. A return is a new
 * transaction, not a retroactive edit; back-dating it into a closed period would
 * change a reported month after the fact.
 */
class FeedSaleReturnCogsSubscriber extends Subscriber
{
    public function __construct(
        protected InventoryAccountResolver $inventoryResolver,
        protected InventoryService $inventoryService,
    ) {
    }

    protected function eventType(): string
    {
        return SalesReturnRecorded::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $return = SalesReturn::find($event->source_id);

        if (! $return) {
            Log::error("FeedSaleReturnCogsSubscriber: event #{$event->id} references missing SalesReturn {$event->source_id}");

            return null;
        }

        // One event type serves both feed and pig returns; this subscriber
        // handles only feed. Returning null is a clean skip, not a failure.
        if ($return->returnable_type !== FeedSale::class) {
            return null;
        }

        // Goods did not come back: cost stays consumed. See the class docblock.
        if (! $return->restock) {
            return null;
        }

        // Already reversed — a replayed event must not put stock back twice.
        // The inventory movement's unique (source_type, source_id) key would
        // also catch this, but failing loudly there would mark the event failed
        // when it has in fact already succeeded.
        if ($return->inventory_movement_id !== null && $return->cogs_reversal_batch_id !== null) {
            return null;
        }

        $sale = FeedSale::find($return->returnable_id);

        if (! $sale) {
            $this->markReturnFailed($return, 'feed_sale_missing');

            return null;
        }

        // Nothing to reverse if the sale never posted COGS. Not a failure: a
        // sale can legitimately be returned before its COGS posting has drained
        // from the outbox.
        if ($sale->cogs_journal_batch_id === null || $sale->inventory_movement_id === null) {
            $this->markReturnFailed($return, 'original_cogs_not_posted');

            return null;
        }

        $originalMovement = InventoryMovement::find($sale->inventory_movement_id);

        if (! $originalMovement) {
            $this->markReturnFailed($return, 'original_inventory_movement_missing');

            return null;
        }

        $feedType = FeedType::find($sale->feed_type_id);

        if (! $feedType) {
            $this->markReturnFailed($return, 'feed_type_missing');

            return null;
        }

        // --- Cost basis, derived from the ORIGINAL movement -----------------
        //
        // NOT read from event_unit_cost: InventoryService::recordOutflow() does
        // not populate that column, so on a usage movement it is null. Reading
        // it restores stock at zero cost — quantity right, value wrong — which
        // is precisely the silent drift this subscriber exists to prevent.
        //
        // The outflow's own numbers are the authoritative record of what the
        // goods cost when they left:
        //
        //     unit cost = |value_delta| / |quantity_delta|
        //
        // which is the weighted average at that moment.
        $originalValue    = bcsub('0', (string) $originalMovement->value_delta, 8);
        $originalQuantity = bcsub('0', (string) $originalMovement->quantity_delta, 8);

        if (bccomp($originalQuantity, '0', 8) <= 0) {
            $this->markReturnFailed($return, 'original_movement_has_no_quantity');

            return null;
        }

        // Scale 8 so the multiplication below lands cleanly at scale 4.
        $unitCost = bcdiv($originalValue, $originalQuantity, 8);

        // The GL amount is computed with EXACTLY the arithmetic recordReceive()
        // uses internally (quantity x unit cost at scale 4). Computing it any
        // other way — say, proportion x original value — can differ from the
        // subledger by a rounding unit, and a sub-pesewa difference that nothing
        // reconciles is still drift.
        $reversalValue = bcmul((string) $return->quantity, $unitCost, 4);

        if (bccomp($reversalValue, '0', 4) <= 0) {
            // The original sale had zero cost (WAC was zero). Quantity still
            // needs to go back, but there is no GL entry to make.
            $this->restoreQuantityOnly($return, $originalMovement, $unitCost, $event);

            return null;
        }

        // --- Put the stock back (subledger) --------------------------------
        //
        // Keyed on the RETURN, not the sale, so a second partial return against
        // the same sale records its own movement instead of colliding with the
        // first on the unique (source_type, source_id) index.
        try {
            $movement = $this->inventoryService->recordReceive(
                itemKind:    InventoryMovement::ITEM_KIND_FINISHED_FEED,
                itemId:      $feedType->id,
                quantity:    (float) $return->quantity,
                unitCost:    (float) $unitCost,
                sourceType:  SalesReturn::class,
                sourceId:    $return->id,
                occurredAt:  CarbonImmutable::instance($event->occurred_at),
                actorUserId: $event->dispatched_by_user_id,
                reference:   'Sales Return #' . $return->id,
                notes:       'Return of Feed Sale #' . $sale->id,
            );
        } catch (\Throwable $e) {
            $this->markReturnFailed($return, 'inventory_restore_failed: ' . $e->getMessage());

            return null;
        }

        $return->forceFill([
            'inventory_movement_id' => $movement->id,
            'cogs_reversed_amount'  => $reversalValue,
        ])->save();

        // --- Reverse the GL legs -------------------------------------------
        //
        // Dr Inventory / Cr COGS — the exact mirror of the sale's posting, at
        // the sale's cost, on the return's date.
        $cogsAcct      = $this->inventoryResolver->cogsAccountForFeedType($feedType);
        $inventoryAcct = $this->inventoryResolver->inventoryAccountForFeedType($feedType);

        $description = sprintf(
            'Feed COGS reversal: %s kg %s (Sales Return #%d, Feed Sale #%d)',
            rtrim(rtrim((string) $return->quantity, '0'), '.'),
            $feedType->name,
            $return->id,
            $sale->id,
        );

        $dims = [
            'source_type' => SalesReturn::class,
            'source_id'   => $return->id,
            'reference'   => 'Sales Return #' . $return->id,
        ];

        $journal = new JournalDraft(
            journalType: 'cogs',
            lines: [
                JournalLineDraft::debit($inventoryAcct, $reversalValue, $description, $dims),
                JournalLineDraft::credit($cogsAcct, $reversalValue, $description, $dims),
            ],
            reference: 'Sales Return #' . $return->id,
            description: $description,
            currencyCode: 'GHS',
        );

        return new JournalBatchDraft(
            batchType:      'auto_sales_return_cogs',
            // The RETURN's date, not the sale's. See the class docblock.
            postingDate:    CarbonImmutable::instance($event->occurred_at),
            journals:       [$journal],
            description:    $description,
            sourceModule:   'sales',
            sourceType:     SalesReturn::class,
            sourceId:       $return->id,
            domainEventId:  $event->id,
            idempotencyKey: JournalPosting::buildIdempotencyKey(
                $event->event_type,
                SalesReturn::class,
                $return->id,
                JournalPosting::PURPOSE_SALES_RETURN_COGS,
            ),
            postingPurpose:  JournalPosting::PURPOSE_SALES_RETURN_COGS,
            subscriberClass: static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $return = SalesReturn::find($event->source_id);

        if (! $return || $return->returnable_type !== FeedSale::class) {
            return;
        }

        $return->forceFill([
            'cogs_reversal_batch_id' => $batch->id,
            'posting_error'          => null,
        ])->save();

        // Link the movement to the batch, matching what the sale-side
        // subscriber does, so the subledger row and the GL batch are navigable
        // in both directions during reconciliation.
        if ($return->inventory_movement_id) {
            InventoryMovement::where('id', $return->inventory_movement_id)
                ->whereNull('journal_batch_id')
                ->update(['journal_batch_id' => $batch->id]);
        }
    }

    /**
     * Stock goes back but there is no cost to reverse (original WAC was zero).
     *
     * Recorded rather than skipped silently: the quantity must still return, and
     * an operator looking at why no journal exists needs to find the reason.
     */
    protected function restoreQuantityOnly(
        SalesReturn $return,
        InventoryMovement $originalMovement,
        string $unitCost,
        DomainEvent $event,
    ): void {
        try {
            $movement = $this->inventoryService->recordReceive(
                itemKind:    $originalMovement->item_kind,
                itemId:      (int) $originalMovement->item_id,
                quantity:    (float) $return->quantity,
                unitCost:    (float) $unitCost,
                sourceType:  SalesReturn::class,
                sourceId:    $return->id,
                occurredAt:  CarbonImmutable::instance($event->occurred_at),
                actorUserId: $event->dispatched_by_user_id,
                reference:   'Sales Return #' . $return->id,
                notes:       'Quantity restored; original cost was zero so no GL reversal.',
            );

            $return->forceFill([
                'inventory_movement_id' => $movement->id,
                'cogs_reversed_amount'  => '0',
                'posting_error'         => 'zero_cost_original: quantity restored, no GL entry required',
            ])->save();
        } catch (\Throwable $e) {
            $this->markReturnFailed($return, 'inventory_restore_failed: ' . $e->getMessage());
        }
    }

    /**
     * Named markReturnFailed() rather than fail(): Subscriber extends a queued
     * job, which already exposes a PUBLIC fail() from InteractsWithQueue.
     * Declaring a protected fail() here is a fatal access-level conflict.
     */
    protected function markReturnFailed(SalesReturn $return, string $reason): void
    {
        $return->forceFill([
            'status'        => SalesReturn::STATUS_FAILED,
            'posting_error' => $reason,
        ])->save();

        Log::warning('sales return COGS reversal could not proceed', [
            'sales_return_id' => $return->id,
            'reason'          => $reason,
        ]);
    }
}
