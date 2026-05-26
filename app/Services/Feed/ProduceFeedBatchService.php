<?php

namespace App\Services\Feed;

use App\Domain\Events\Feed\FeedBatchProduced;
use App\Domain\Events\Support\SourceReference;
use App\Models\FeedBatch;
use App\Models\FeedBatchItem;
use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — produce a feed batch from a recipe.
 *
 * REWRITTEN to:
 *   - Call the unified InventoryService instead of the deleted
 *     RawMaterialValuationService.
 *   - Record finished-feed RECEIPT as a feed_produce movement on the
 *     'finished_feed' ledger (previously, finished feed had no ledger
 *     at all — quantity_in_stock for feed was not tracked).
 *   - Mark feed_batches.is_posted / posting_status='pending' for the
 *     subscriber.
 *
 * MULTI-LEDGER WRITES
 *
 *   This is the one service that writes to BOTH item_kinds in a single
 *   transaction:
 *     - Per recipe ingredient: inventory_movements row with
 *       item_kind='raw_material', event_type='usage'.
 *     - One finished_feed row: inventory_movements with
 *       item_kind='finished_feed', event_type='feed_produce'.
 *
 *   Source identities:
 *     - Raw material usage: (source_type='feed_batch_item', source_id=item.id)
 *       — one per ingredient.
 *     - Finished feed produce: (source_type='feed_batch', source_id=batch.id)
 *       — exactly one per batch.
 *
 *   This pair-up means re-running the same batch (retry / replay) catches
 *   the duplicate on the UNIQUE(source_type, source_id) and bubbles up
 *   cleanly.
 *
 * STOCK CHECK
 *
 *   Pre-flight checks every ingredient against on-hand BEFORE any write.
 *   Insufficient stock aborts 422 with details. The InventoryService
 *   itself has the same guard at the row-lock level (belt-and-braces).
 */
class ProduceFeedBatchService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
        protected InventoryService $inventory,
    ) {
    }

    /**
     * @param  array{
     *   feed_type_id: int,
     *   batch_date: string,
     *   batch_size_kg: float,
     *   notes?: ?string,
     * }  $data
     */
    public function execute(User $actor, array $data): array
    {
        $organizationId = $this->currentOrganization->requireId();

        return DB::transaction(function () use ($actor, $data, $organizationId) {
            /** @var FeedType $feedType */
            $feedType = FeedType::with(['recipes.rawMaterial'])->findOrFail($data['feed_type_id']);

            if ($feedType->recipes->isEmpty()) {
                abort(422, 'This feed type does not have a recipe yet.');
            }

            $batchSizeKg = (float) $data['batch_size_kg'];
            if ($batchSizeKg <= 0) {
                abort(422, 'Batch size must be positive.');
            }
            $multiplier = $batchSizeKg / 100;

            // Pass 1: pre-flight stock check + per-ingredient cost.
            $batchItems = [];
            $totalCost  = 0.0;

            foreach ($feedType->recipes as $recipe) {
                /** @var RawMaterial|null $material */
                $material = $recipe->rawMaterial;
                if (! $material) {
                    abort(422, 'A recipe ingredient is missing its raw material.');
                }

                $requiredQty = (float) $recipe->quantity_per_100kg * $multiplier;
                $onHand      = (float) $material->quantity_in_stock;

                if ($onHand < $requiredQty) {
                    abort(422, sprintf(
                        'Not enough stock for %s. Required: %s %s, Available: %s %s.',
                        $material->name,
                        number_format($requiredQty, 2),
                        $material->unit,
                        number_format($onHand, 2),
                        $material->unit,
                    ));
                }

                $unitCost  = (float) $material->current_wac;
                $itemTotal = $requiredQty * $unitCost;
                $totalCost += $itemTotal;

                $batchItems[] = [
                    'raw_material' => $material,
                    'quantity_used'=> $requiredQty,
                    'unit_cost'    => $unitCost,
                    'total_cost'   => $itemTotal,
                ];
            }

            // Pass 2: create the batch (gives us IDs).
            $feedBatch = FeedBatch::create([
                'feed_type_id'    => $feedType->id,
                'batch_date'      => $data['batch_date'],
                'batch_size_kg'   => $batchSizeKg,
                'total_cost'      => $totalCost,
                'notes'           => $data['notes'] ?? null,
                'mixed_by'        => $actor->id,
                'organization_id' => $organizationId,
                'posting_status'  => 'pending',
                'is_posted'       => false,
            ]);

            // Pass 3: per-ingredient writes.
            foreach ($batchItems as $item) {
                /** @var RawMaterial $material */
                $material = $item['raw_material'];

                /** @var FeedBatchItem $batchItem */
                $batchItem = $feedBatch->items()->create([
                    'raw_material_id' => $material->id,
                    'quantity_used'   => $item['quantity_used'],
                    'unit_cost'       => $item['unit_cost'],
                    'total_cost'      => $item['total_cost'],
                ]);

                // Legacy on-hand decrement.
                $material->decrement('quantity_in_stock', $item['quantity_used']);

                // Stock log for the usage — gives the audit trail at
                // the raw_material_stock_logs grain.
                $usageLog = $material->stockLogs()->create([
                    'type'            => 'usage',
                    'quantity'        => -1 * $item['quantity_used'],
                    'unit_cost'       => $item['unit_cost'],
                    'reference'       => 'Feed Batch #' . $feedBatch->id,
                    'notes'           => 'Used for ' . $feedType->name . ' batch on ' . $data['batch_date'],
                    'performed_by'    => $actor->id,
                    'organization_id' => $organizationId,
                    'posting_status'  => 'skipped', // usage doesn't post — feed produce posts the offset
                    'is_posted'       => false,
                ]);

                // Unified inventory movement: raw material usage.
                // Source: feed_batch_item — one per recipe ingredient.
                $this->inventory->recordUsage(
                    itemKind:    InventoryMovement::ITEM_KIND_RAW_MATERIAL,
                    itemId:      (int) $material->id,
                    quantity:    (float) $item['quantity_used'],
                    sourceType:  InventoryMovement::SOURCE_FEED_BATCH_ITEM,
                    sourceId:    (int) $batchItem->id,
                    occurredAt:  CarbonImmutable::instance($feedBatch->created_at ?? now()),
                    actorUserId: $actor->id,
                    reference:   'Feed Batch #' . $feedBatch->id,
                );
            }

            // Pass 4: record the FINISHED FEED produced — one movement.
            $this->inventory->recordFeedProduction(
                feedTypeId:     (int) $feedType->id,
                quantity:       $batchSizeKg,
                totalCost:      $totalCost,
                feedBatchId:    (int) $feedBatch->id,
                occurredAt:     CarbonImmutable::instance($feedBatch->created_at ?? now()),
                actorUserId:    $actor->id,
                reference:      'Feed Batch #' . $feedBatch->id,
                itemNameCache:  $feedType->name,
            );

            // Domain event — single one, payload includes the full
            // ingredient list for the GL subscriber.
            $this->dispatcher->dispatch(new FeedBatchProduced(
                source: new SourceReference(
                    module:         'feed',
                    sourceType:     FeedBatch::class,
                    sourceId:       (int) $feedBatch->id,
                    organizationId: $organizationId,
                ),
                occurredAt: CarbonImmutable::parse($data['batch_date'])->setTime(
                    (int) now()->hour, (int) now()->minute, (int) now()->second,
                ),
                dispatchedByUserId: $actor->id,
                feedBatchId:  (int) $feedBatch->id,
                feedTypeId:   (int) $feedType->id,
                feedTypeName: (string) $feedType->name,
                batchSizeKg:  $batchSizeKg,
                totalCost:    $totalCost,
                ingredients:  collect($batchItems)->map(fn ($it) => [
                    'raw_material_id'   => (int) $it['raw_material']->id,
                    'raw_material_name' => (string) $it['raw_material']->name,
                    'quantity_used'     => (float) $it['quantity_used'],
                    'unit_cost'         => (float) $it['unit_cost'],
                    'total_cost'        => (float) $it['total_cost'],
                ])->values()->all(),
            ));

            return [
                'feed_batch' => $feedBatch->fresh(),
                'total_cost' => $totalCost,
                'items'      => $batchItems,
            ];
        });
    }
}
