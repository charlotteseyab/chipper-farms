<?php

namespace App\Finance\Subscribers\Inventory;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\FeedBatch;
use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\RawMaterial;
use App\Services\Finance\Inventory\InventoryAccountResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — GL posting for feed batches.
 *
 * Posts the inventory transformation when a feed batch is produced:
 *
 *   Dr  Finished Feed Inventory       total_cost
 *         Cr  Raw Materials Inventory  ingredient[0].total_cost
 *         Cr  Raw Materials Inventory  ingredient[1].total_cost
 *         Cr  Raw Materials Inventory  ingredient[N].total_cost
 *
 * Per-ingredient credits land on the SAME raw material inventory account
 * unless mappings differ by category (the resolver decides). If all
 * ingredients route to the same account, the credits could be combined,
 * but we keep them per-ingredient for traceability: an accountant
 * reviewing the journal sees exactly which ingredient cost what.
 *
 * IDEMPOTENCY
 *
 *   Posting key:
 *     "feed_batch_produced_posted:FeedBatch:{id}:feed_production"
 *
 *   Source dedup on the inventory_movements side is keyed on
 *   source_type='feed_batch' / 'feed_batch_item' which are different
 *   tokens — duplicate event delivery is safe.
 */
class FeedBatchProducedSubscriber extends Subscriber
{
    public function __construct(
        protected InventoryAccountResolver $accountResolver,
    ) {
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Feed\FeedBatchProduced::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $feedBatchId = (int) $event->source_id;

        /** @var FeedBatch|null $batch */
        $batch = FeedBatch::with(['feedType', 'items.rawMaterial'])->find($feedBatchId);
        if (! $batch) {
            \Illuminate\Support\Facades\Log::warning(
                "FeedBatchProducedSubscriber: feed batch #{$feedBatchId} not found. Skipping."
            );
            return null;
        }

        $feedType = $batch->feedType;
        if (! $feedType) {
            \Illuminate\Support\Facades\Log::warning(
                "FeedBatchProducedSubscriber: feed batch #{$batch->id} has no feed_type. Skipping."
            );
            return null;
        }

        $items = $batch->items;
        if ($items->isEmpty()) {
            // Empty batch — nothing to post. Returning null marks the
            // event SKIPPED.
            return null;
        }

        $totalCost = '0';
        foreach ($items as $item) {
            $totalCost = bcadd($totalCost, (string) $item->total_cost, 4);
        }

        if (bccomp($totalCost, '0', 4) === 0) {
            // Zero-cost batch (every ingredient at zero WAC). Nothing to
            // post. Skip.
            return null;
        }

        $ffAccountId = $this->accountResolver->inventoryAccountForFeedType($feedType);

        $lines = [];

        // Single debit for the finished feed value.
        $debitDescription = sprintf(
            'Feed batch produced: %s (%s kg)',
            $feedType->name,
            number_format((float) $batch->batch_size_kg, 4),
        );

        $lines[] = JournalLineDraft::debit(
            accountId:   $ffAccountId,
            amount:      $totalCost,
            description: $debitDescription,
            dimensions: [
                'source_type' => FeedBatch::class,
                'source_id'   => $batch->id,
                'reference'   => 'Feed Batch #' . $batch->id,
            ],
        );

        // One credit per ingredient — per-material inventory account
        // resolved individually.
        foreach ($items as $item) {
            /** @var RawMaterial|null $material */
            $material = $item->rawMaterial;
            if (! $material) {
                // Defensive — items should always have a material.
                continue;
            }

            $itemCost = (string) $item->total_cost;
            if (bccomp($itemCost, '0', 4) <= 0) {
                continue;  // zero-cost ingredient
            }

            $rmAccountId = $this->accountResolver->inventoryAccountForRawMaterial($material);

            $lines[] = JournalLineDraft::credit(
                accountId:   $rmAccountId,
                amount:      $itemCost,
                description: sprintf(
                    'Consumed for batch: %s (%s %s)',
                    $material->name,
                    number_format((float) $item->quantity_used, 4),
                    $material->unit,
                ),
                dimensions: [
                    'source_type' => FeedBatch::class,
                    'source_id'   => $batch->id,
                    'reference'   => 'Feed Batch #' . $batch->id,
                ],
            );
        }

        // Defensive: if no usable credits (all ingredients were zero-cost),
        // bail out cleanly.
        if (count($lines) < 2) {
            return null;
        }

        $description = sprintf(
            'Feed batch #%d: %s × %s kg @ total cost %s',
            $batch->id,
            $feedType->name,
            number_format((float) $batch->batch_size_kg, 2),
            number_format((float) $totalCost, 2),
        );

        $journal = new JournalDraft(
            journalType:  'feed_production',
            lines:        $lines,
            reference:    'Feed Batch #' . $batch->id,
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   FeedBatch::class,
            sourceId:     $batch->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_feed_production',
            postingDate:      CarbonImmutable::instance($batch->batch_date ?? $batch->created_at ?? now()),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'feed',
            sourceType:       FeedBatch::class,
            sourceId:         $batch->id,
            domainEventId:    (int) $event->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'feed_batch_produced_posted',
                FeedBatch::class,
                $batch->id,
                JournalPosting::PURPOSE_FEED_PRODUCTION,
            ),
            postingPurpose:   JournalPosting::PURPOSE_FEED_PRODUCTION,
            subscriberClass:  static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $feedBatchId = (int) $event->source_id;

        DB::transaction(function () use ($feedBatchId, $batch) {
            FeedBatch::query()
                ->where('id', $feedBatchId)
                ->update([
                    'is_posted'        => true,
                    'posted_at'        => now(),
                    'posting_status'   => 'posted',
                    'posting_error'    => null,
                    'journal_batch_id' => $batch->id,
                ]);

            // Forward-link the finished-feed produce movement and all
            // raw-material usage movements for this batch.
            InventoryMovement::query()
                ->where(function ($q) use ($feedBatchId) {
                    $q->where(function ($q) use ($feedBatchId) {
                        $q->where('source_type', InventoryMovement::SOURCE_FEED_BATCH)
                          ->where('source_id', $feedBatchId);
                    });
                })
                ->update(['journal_batch_id' => $batch->id]);

            // Per-ingredient usage movements: tie to the same batch GL.
            // Look up batch item ids first because their source_id values
            // are the feed_batch_item ids.
            $itemIds = \App\Models\FeedBatchItem::query()
                ->where('feed_batch_id', $feedBatchId)
                ->pluck('id')
                ->all();

            if (! empty($itemIds)) {
                InventoryMovement::query()
                    ->where('source_type', InventoryMovement::SOURCE_FEED_BATCH_ITEM)
                    ->whereIn('source_id', $itemIds)
                    ->update(['journal_batch_id' => $batch->id]);
            }
        });
    }
}
