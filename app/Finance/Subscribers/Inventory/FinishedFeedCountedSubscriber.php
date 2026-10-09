<?php

namespace App\Finance\Subscribers\Inventory;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\StockCountLine;
use App\Services\Finance\Inventory\InventoryAccountResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Posts the value change from a finished-feed stock count, mirroring
 * RawMaterialAdjustedSubscriber:
 *
 *   Gain:  Dr Finished Feed Inventory   Cr Inventory Adjustments
 *   Loss:  Dr Inventory Adjustments     Cr Finished Feed Inventory
 *
 * The amount is the inventory movement's value_delta (quantity at average cost).
 */
class FinishedFeedCountedSubscriber extends Subscriber
{
    public function __construct(
        protected InventoryAccountResolver $accountResolver,
    ) {
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Inventory\FinishedFeedCounted::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $lineId = (int) $event->source_id;

        /** @var StockCountLine|null $line */
        $line = StockCountLine::withoutGlobalScopes()->with('stockCount')->find($lineId);
        if (! $line || $line->item_kind !== InventoryMovement::ITEM_KIND_FINISHED_FEED) {
            Log::warning("FinishedFeedCountedSubscriber: finished-feed count line #{$lineId} not found. Skipping.");

            return null;
        }

        $movement = InventoryMovement::query()
            ->where('source_type', InventoryMovement::SOURCE_STOCK_COUNT_LINE)
            ->where('source_id', $line->id)
            ->first();
        if (! $movement) {
            Log::warning("FinishedFeedCountedSubscriber: inventory movement missing for count line #{$line->id}; not posting.");

            return null;
        }

        $valueDelta = (string) $movement->value_delta;
        if (bccomp($valueDelta, '0', 4) === 0) {
            return null;
        }

        $feedType = FeedType::withoutGlobalScopes()->find($line->item_id);
        if (! $feedType) {
            Log::warning("FinishedFeedCountedSubscriber: feed type #{$line->item_id} not found. Skipping.");

            return null;
        }

        $inventoryAccountId = $this->accountResolver->inventoryAccountForFeedType($feedType);
        $adjustmentAccountId = $this->accountResolver->adjustmentAccountForFeedType($feedType);

        $gain = bccomp($valueDelta, '0', 4) > 0;
        $amount = ltrim($valueDelta, '-');
        $reference = $line->stockCount?->reference ?: sprintf('Stock count #%d', $line->stock_count_id);
        $description = sprintf('Stock count: %s (%s %s kg)', $feedType->name, $gain ? 'gain' : 'loss', number_format((float) $movement->quantity_delta, 4));
        $dimensions = ['source_type' => StockCountLine::class, 'source_id' => $line->id, 'reference' => $reference];

        $lines = $gain
            ? [
                JournalLineDraft::debit(accountId: $inventoryAccountId, amount: $amount, description: $description, dimensions: $dimensions),
                JournalLineDraft::credit(accountId: $adjustmentAccountId, amount: $amount, description: $description, dimensions: $dimensions),
            ]
            : [
                JournalLineDraft::debit(accountId: $adjustmentAccountId, amount: $amount, description: $description, dimensions: $dimensions),
                JournalLineDraft::credit(accountId: $inventoryAccountId, amount: $amount, description: $description, dimensions: $dimensions),
            ];

        $journal = new JournalDraft(
            journalType:  'inventory_adjustment',
            lines:        $lines,
            reference:    $reference,
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   StockCountLine::class,
            sourceId:     $line->id,
        );

        return new JournalBatchDraft(
            batchType:       'auto_inventory_adjustment',
            postingDate:     CarbonImmutable::instance($line->stockCount?->counted_at ?? $line->created_at ?? now()),
            journals:        [$journal],
            description:     $description,
            sourceModule:    'inventory',
            sourceType:      StockCountLine::class,
            sourceId:        $line->id,
            domainEventId:   (int) $event->id,
            idempotencyKey:  JournalPosting::buildIdempotencyKey(
                'finished_feed_counted_posted',
                StockCountLine::class,
                $line->id,
                JournalPosting::PURPOSE_INVENTORY_CONSUMPTION,
            ),
            postingPurpose:  JournalPosting::PURPOSE_INVENTORY_CONSUMPTION,
            subscriberClass: static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $lineId = (int) $event->source_id;

        DB::transaction(function () use ($lineId, $batch) {
            StockCountLine::withoutGlobalScopes()->whereKey($lineId)
                ->update(['posting_status' => StockCountLine::POSTING_POSTED, 'journal_batch_id' => $batch->id]);

            InventoryMovement::query()
                ->where('source_type', InventoryMovement::SOURCE_STOCK_COUNT_LINE)
                ->where('source_id', $lineId)
                ->update(['journal_batch_id' => $batch->id]);
        });
    }
}
