<?php

namespace App\Finance\Subscribers\Sales;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Subscribers\Subscriber;
use App\Domain\Events\Sales\FeedSaleRecorded;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Services\Finance\Inventory\InsufficientInventoryException;
use App\Services\Finance\Inventory\InventoryAccountResolver;
use App\Services\Finance\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Posts the COGS side of a feed sale and decrements finished-feed inventory.
 *
 *   1. recordUsage(finished_feed, feed_type_id, quantity) → decrements
 *      inventory at the weighted-average cost, returning a movement whose
 *      value_delta is the EXACT cost removed.
 *   2. Posts:  Dr Cost of Feed Sold (5100) / Cr Finished Feed Inventory (1220)
 *      at that value_delta — so the GL COGS equals the inventory decrement to
 *      the pesewa (no drift).
 *
 * SKIP/FAIL conditions (mark the sale's cogs_posting_status, no GL):
 *   - feed sale or feed type missing → skip
 *   - already posted → skip (idempotent)
 *   - insufficient inventory → fail (cogs_posting_status='failed'); the
 *     revenue leg still stands, but COGS is flagged for correction. We do NOT
 *     silently allow negative inventory.
 *   - zero-cost decrement (WAC was zero) → skip with reason
 *
 * Inventory is decremented BEFORE the GL post (in buildDraft) so the cost is
 * known; the resulting movement is linked to the batch in onPostedSuccessfully.
 */
class FeedSaleCogsSubscriber extends Subscriber
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
        protected InventoryAccountResolver $inventoryResolver,
        protected InventoryService $inventoryService,
    ) {
    }

    protected function eventType(): string
    {
        return FeedSaleRecorded::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $payload = $event->payload;
        $sale = FeedSale::find($event->source_id);
        if (! $sale) {
            Log::error("FeedSaleCogsSubscriber: event #{$event->id} references missing FeedSale {$event->source_id}");
            return null;
        }

        if ($sale->cogs_journal_batch_id !== null && $sale->cogs_posting_status === FeedSale::POST_POSTED) {
            return null; // already posted
        }

        $feedType = FeedType::find($sale->feed_type_id);
        if (! $feedType) {
            $this->markCogs($sale, FeedSale::POST_SKIPPED, 'feed_type_missing');
            return null;
        }

        $quantity = (float) $sale->quantity_kg;
        if ($quantity <= 0) {
            $this->markCogs($sale, FeedSale::POST_SKIPPED, 'zero_quantity');
            return null;
        }

        // 1. Decrement finished-feed inventory at WAC.
        try {
            $movement = $this->inventoryService->recordUsage(
                itemKind:    InventoryMovement::ITEM_KIND_FINISHED_FEED,
                itemId:      $feedType->id,
                quantity:    $quantity,
                sourceType:  FeedSale::class,
                sourceId:    $sale->id,
                occurredAt:  CarbonImmutable::instance($event->occurred_at),
                actorUserId: $event->dispatched_by_user_id,
                reference:   'Feed Sale #' . $sale->id,
            );
        } catch (InsufficientInventoryException $e) {
            $this->markCogs($sale, FeedSale::POST_FAILED, $e->getMessage());
            Log::warning("FeedSaleCogsSubscriber: insufficient feed inventory for sale #{$sale->id}: {$e->getMessage()}");
            return null;
        }

        // value_delta is negative on an outflow; COGS is its magnitude.
        $cogsAmount = bcsub('0', (string) $movement->value_delta, 2);
        if (bccomp($cogsAmount, '0', 2) <= 0) {
            $this->markCogs($sale, FeedSale::POST_SKIPPED, 'zero_cost_decrement');
            // Inventory still moved (quantity), but there is no cost to post.
            $sale->update(['inventory_movement_id' => $movement->id]);
            return null;
        }

        $cogsAcct      = $this->inventoryResolver->cogsAccountForFeedType($feedType);
        $inventoryAcct = $this->inventoryResolver->inventoryAccountForFeedType($feedType);

        $sale->update([
            'cogs_amount'         => $cogsAmount,
            'inventory_movement_id' => $movement->id,
            'cogs_posting_status' => FeedSale::POST_PENDING,
            'cogs_posting_error'  => null,
        ]);

        $description = sprintf('Feed COGS: %s kg %s (Feed Sale #%d)',
            number_format($quantity, 2), $feedType->name, $sale->id);

        $dims = [
            'source_type' => FeedSale::class,
            'source_id'   => $sale->id,
            'reference'   => 'Feed Sale #' . $sale->id,
        ];

        $journal = new JournalDraft(
            journalType: 'cogs',
            lines: [
                JournalLineDraft::debit($cogsAcct, $cogsAmount, $description, $dims),
                JournalLineDraft::credit($inventoryAcct, $cogsAmount, $description, $dims),
            ],
            reference: 'Feed Sale #' . $sale->id,
            description: $description,
            currencyCode: 'GHS',
        );

        return new JournalBatchDraft(
            batchType:      'auto_feed_sale_cogs',
            postingDate:    CarbonImmutable::instance($event->occurred_at),
            journals:       [$journal],
            description:    $description,
            sourceModule:   'sales',
            sourceType:     FeedSale::class,
            sourceId:       $sale->id,
            domainEventId:  $event->id,
            idempotencyKey: JournalPosting::buildIdempotencyKey(
                $event->event_type, FeedSale::class, $sale->id, JournalPosting::PURPOSE_COGS,
            ),
            postingPurpose:  JournalPosting::PURPOSE_COGS,
            subscriberClass: static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $sale = FeedSale::find($event->source_id);
        if (! $sale) {
            return;
        }

        $sale->update([
            'cogs_journal_batch_id' => $batch->id,
            'cogs_posting_status'   => FeedSale::POST_POSTED,
            'cogs_posting_error'    => null,
        ]);

        // Link the inventory movement to the COGS batch for traceability.
        if ($sale->inventory_movement_id) {
            InventoryMovement::where('id', $sale->inventory_movement_id)
                ->whereNull('journal_batch_id')
                ->update(['journal_batch_id' => $batch->id]);
        }
    }

    protected function markCogs(FeedSale $sale, string $status, ?string $error): void
    {
        $sale->update([
            'cogs_posting_status' => $status,
            'cogs_posting_error'  => $error,
        ]);
    }
}
