<?php

namespace App\Finance\Subscribers\Inventory;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\RawMaterialStockLog;
use App\Services\Finance\Inventory\InventoryAccountResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — GL posting for raw material adjustments.
 *
 * Routes by signed value_delta on the inventory_movements row.
 *
 *   Net positive (count gain / adjustment_add / adjustment_set up):
 *     Dr  Inventory          value_delta
 *           Cr  Inventory Adjustments    value_delta
 *
 *   Net negative (count loss / adjustment_deduct / adjustment_set down):
 *     Dr  Inventory Adjustments       |value_delta|
 *           Cr  Inventory             |value_delta|
 *
 * The amount comes from the INVENTORY MOVEMENT, not the event payload.
 * The event carries quantity_delta + unit_cost which would let us recompute
 * but the movement row has the authoritative pre-clamped value_delta
 * computed by InventoryService at the time of the adjustment. Using it
 * eliminates rounding drift.
 *
 * ZERO-VALUE ADJUSTMENT
 *
 *   Adjustment_set to the SAME on-hand (e.g. confirming a count) writes
 *   a movement row with quantity_delta=0 and value_delta=0. The
 *   subscriber returns null (skipped) — no GL entry needed for a
 *   confirmation that didn't move stock.
 *
 * IDEMPOTENCY
 *
 *   Posting key:
 *     "raw_material_adjusted_posted:RawMaterialStockLog:{id}:inventory_consumption"
 *
 *   We use PURPOSE_INVENTORY_CONSUMPTION as the umbrella for inventory
 *   non-receive postings. PURPOSE_INVENTORY_RECEIPT is reserved for the
 *   receive subscriber so the two are distinguishable in reporting.
 */
class RawMaterialAdjustedSubscriber extends Subscriber
{
    public function __construct(
        protected InventoryAccountResolver $accountResolver,
    ) {
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Inventory\RawMaterialAdjusted::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $stockLogId = (int) $event->source_id;

        /** @var RawMaterialStockLog|null $log */
        $log = RawMaterialStockLog::with('rawMaterial')->find($stockLogId);
        if (! $log) {
            \Illuminate\Support\Facades\Log::warning(
                "RawMaterialAdjustedSubscriber: stock log #{$stockLogId} not found. Skipping."
            );
            return null;
        }
        if ($log->type !== 'adjustment') {
            return null;
        }

        $material = $log->rawMaterial;
        if (! $material) {
            \Illuminate\Support\Facades\Log::warning(
                "RawMaterialAdjustedSubscriber: stock log #{$log->id} has no raw_material. Skipping."
            );
            return null;
        }

        // Look up the corresponding inventory_movements row for the
        // authoritative value_delta.
        /** @var InventoryMovement|null $movement */
        $movement = InventoryMovement::query()
            ->where('source_type', InventoryMovement::SOURCE_STOCK_LOG)
            ->where('source_id', $log->id)
            ->first();

        if (! $movement) {
            \Illuminate\Support\Facades\Log::warning(
                "RawMaterialAdjustedSubscriber: inventory_movements row missing for stock log #{$log->id}. "
                . "InventoryService write may have failed; will not post GL."
            );
            return null;
        }

        $valueDelta = (string) $movement->value_delta;

        if (bccomp($valueDelta, '0', 4) === 0) {
            // No-op adjustment (count confirmation). Skip.
            return null;
        }

        $inventoryAccountId  = $this->accountResolver->inventoryAccountForRawMaterial($material);
        $adjustmentAccountId = $this->accountResolver->adjustmentAccountForRawMaterial($material);

        $signPositive = bccomp($valueDelta, '0', 4) > 0;
        $absValue     = $signPositive
            ? $valueDelta
            : ltrim((string) bcsub('0', $valueDelta, 4), '-');

        $debitDescription = sprintf(
            'Stock adjustment: %s (%s %s)',
            $material->name,
            $signPositive ? 'gain' : 'loss',
            number_format((float) $movement->quantity_delta, 4),
        );

        $sourceRef = $log->reference ?? sprintf('Adj #%d', $log->id);

        if ($signPositive) {
            // Net gain — debit inventory, credit adjustments.
            $lines = [
                JournalLineDraft::debit(
                    accountId:   $inventoryAccountId,
                    amount:      $absValue,
                    description: $debitDescription,
                    dimensions: [
                        'source_type'     => RawMaterialStockLog::class,
                        'source_id'       => $log->id,
                        'reference'       => $sourceRef,
                    ],
                ),
                JournalLineDraft::credit(
                    accountId:   $adjustmentAccountId,
                    amount:      $absValue,
                    description: $debitDescription,
                    dimensions: [
                        'source_type'     => RawMaterialStockLog::class,
                        'source_id'       => $log->id,
                        'reference'       => $sourceRef,
                    ],
                ),
            ];
        } else {
            // Net loss — debit adjustments (expense up), credit inventory.
            $lines = [
                JournalLineDraft::debit(
                    accountId:   $adjustmentAccountId,
                    amount:      $absValue,
                    description: $debitDescription,
                    dimensions: [
                        'source_type'     => RawMaterialStockLog::class,
                        'source_id'       => $log->id,
                        'reference'       => $sourceRef,
                    ],
                ),
                JournalLineDraft::credit(
                    accountId:   $inventoryAccountId,
                    amount:      $absValue,
                    description: $debitDescription,
                    dimensions: [
                        'source_type'     => RawMaterialStockLog::class,
                        'source_id'       => $log->id,
                        'reference'       => $sourceRef,
                    ],
                ),
            ];
        }

        $description = sprintf(
            'Adjustment of %s — Stock Log #%d (%s)',
            $material->name,
            $log->id,
            $movement->event_type,
        );

        $journal = new JournalDraft(
            journalType:  'inventory_adjustment',
            lines:        $lines,
            reference:    $sourceRef,
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   RawMaterialStockLog::class,
            sourceId:     $log->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_inventory_adjustment',
            postingDate:      CarbonImmutable::instance($log->created_at ?? now()),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'inventory',
            sourceType:       RawMaterialStockLog::class,
            sourceId:         $log->id,
            domainEventId:    (int) $event->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'raw_material_adjusted_posted',
                RawMaterialStockLog::class,
                $log->id,
                JournalPosting::PURPOSE_INVENTORY_CONSUMPTION,
            ),
            postingPurpose:   JournalPosting::PURPOSE_INVENTORY_CONSUMPTION,
            subscriberClass:  static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $stockLogId = (int) $event->source_id;

        DB::transaction(function () use ($stockLogId, $batch) {
            RawMaterialStockLog::query()
                ->where('id', $stockLogId)
                ->update([
                    'is_posted'        => true,
                    'posted_at'        => now(),
                    'posting_status'   => 'posted',
                    'posting_error'    => null,
                    'journal_batch_id' => $batch->id,
                ]);

            InventoryMovement::query()
                ->where('source_type', InventoryMovement::SOURCE_STOCK_LOG)
                ->where('source_id', $stockLogId)
                ->update(['journal_batch_id' => $batch->id]);
        });
    }
}
