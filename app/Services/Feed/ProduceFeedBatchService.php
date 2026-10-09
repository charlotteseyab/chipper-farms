<?php

namespace App\Services\Feed;

use App\Domain\Events\Feed\FeedBatchProduced;
use App\Domain\Events\Support\SourceReference;
use App\Models\FeedBatch;
use App\Models\FeedBatchItem;
use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    ) {}

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
            Organization::whereKey($organizationId)->lockForUpdate()->firstOrFail();
            abort_unless((int) $actor->organization_id === $organizationId, 403);
            $key = $data['submission_key'] ?? null;
            if ($key && ($existing = FeedBatch::where('organization_id', $organizationId)->where('submission_key', $key)->first())) {
                if ((int) $existing->feed_type_id !== (int) $data['feed_type_id']
                    || (float) $existing->batch_size_kg !== (float) $data['batch_size_kg']
                    || $existing->batch_date->toDateString() !== $data['batch_date']) {
                    abort(422, 'This confirmation was already used for a different production batch.');
                }

                return ['feed_batch' => $existing, 'total_cost' => $existing->total_cost, 'items' => $existing->items];
            }
            $feedType = FeedType::where('organization_id', $organizationId)->where('is_active', true)->findOrFail($data['feed_type_id']);
            try {
                $snapshot = $data['recipe_snapshot'] ?? app(FeedRecipeService::class)->resolve($feedType, $data['batch_date']);
                $ingredients = app(FeedRecipeService::class)->validateIngredients($organizationId, $snapshot['ingredients']);
            } catch (ValidationException $exception) {
                abort(422, $exception->validator->errors()->first());
            }
            $batchSizeKg = (float) $data['batch_size_kg'];
            if ($batchSizeKg <= 0 || ! is_finite($batchSizeKg)) {
                abort(422, 'Finished quantity must be positive.');
            }
            $plannedKg = (float) ($data['planned_quantity_kg'] ?? $batchSizeKg);
            if ($plannedKg <= 0 || ! is_finite($plannedKg)) {
                abort(422, 'Planned quantity must be positive.');
            }
            $multiplier = $plannedKg / 100;
            $actuals = $data['actual_quantities'] ?? [];
            if ($actuals && (array_diff(array_keys($actuals), array_column($ingredients, 'raw_material_id')) || array_diff(array_column($ingredients, 'raw_material_id'), array_keys($actuals)))) {
                abort(422, 'Actual ingredients must belong to the selected recipe.');
            }

            // Pass 1: pre-flight stock check + per-ingredient cost.
            $batchItems = [];
            $totalCost = 0.0;

            foreach (collect($ingredients)->sortBy('raw_material_id') as $recipe) {
                /** @var RawMaterial|null $material */
                $material = RawMaterial::where('organization_id', $organizationId)->whereKey($recipe['raw_material_id'])->lockForUpdate()->first();
                if (! $material) {
                    abort(422, 'A recipe ingredient is missing its raw material.');
                }

                $requiredKg = round((float) ($actuals[$material->id] ?? ((float) $recipe['quantity_per_100kg'] * $multiplier)), 4);
                if ($requiredKg <= 0 || ! is_finite($requiredKg)) {
                    abort(422, 'Actual ingredient quantities must be positive.');
                }
                // Recipes and actuals are in kg; stock and cost are per stock unit.
                $kgPerUnit = $material->kgPerUnit();
                if ($kgPerUnit === null) {
                    abort(422, "{$material->name} is stocked in {$material->unit}. Set its kg per {$material->unit} on Raw Materials first.");
                }
                $requiredQty = round($requiredKg / $kgPerUnit, 4);
                $balance = InventoryBalance::where('organization_id', $organizationId)
                    ->where('item_kind', 'raw_material')->where('item_id', $material->id)->lockForUpdate()->first();
                $onHand = (float) ($balance?->on_hand_qty ?? 0);

                if ($onHand < $requiredQty) {
                    abort(422, $kgPerUnit === 1.0
                        ? sprintf('Not enough stock for %s. Required: %s kg, Available: %s kg.',
                            $material->name, number_format($requiredQty, 2), number_format($onHand, 2))
                        : sprintf('Not enough stock for %s. Required: %s kg (%s %s), Available: %s %s.',
                            $material->name, number_format($requiredKg, 2), number_format($requiredQty, 2), $material->unit,
                            number_format($onHand, 2), $material->unit));
                }

                $unitCost = (float) ($balance?->weighted_avg_cost ?? 0);
                $itemTotal = $requiredQty * $unitCost;
                $totalCost += $itemTotal;

                $batchItems[] = [
                    'raw_material' => $material,
                    'quantity_used' => $requiredQty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $itemTotal,
                ];
            }

            // Pass 2: create the batch (gives us IDs).
            $feedBatch = FeedBatch::create([
                'feed_type_id' => $feedType->id,
                'batch_date' => $data['batch_date'],
                'batch_size_kg' => $batchSizeKg,
                'total_cost' => $totalCost,
                'notes' => $data['notes'] ?? null,
                'mixed_by' => $actor->id,
                'organization_id' => $organizationId,
                'posting_status' => 'pending',
                'submission_key' => $key, 'recipe_snapshot' => $snapshot, 'planned_quantity_kg' => $plannedKg,
                'is_posted' => false,
            ]);

            // Pass 3: per-ingredient writes.
            foreach ($batchItems as $item) {
                /** @var RawMaterial $material */
                $material = $item['raw_material'];

                /** @var FeedBatchItem $batchItem */
                $batchItem = $feedBatch->items()->create([
                    'raw_material_id' => $material->id,
                    'quantity_used' => $item['quantity_used'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['total_cost'],
                ]);

                // quantity_in_stock is mirrored from the ledger by InventoryService.

                // Stock log for the usage — gives the audit trail at
                // the raw_material_stock_logs grain.
                $usageLog = $material->stockLogs()->create([
                    'type' => 'usage',
                    'quantity' => -1 * $item['quantity_used'],
                    'unit_cost' => $item['unit_cost'],
                    'reference' => 'Feed Batch #'.$feedBatch->id,
                    'notes' => 'Used for '.$feedType->name.' batch on '.$data['batch_date'],
                    'performed_by' => $actor->id,
                    'organization_id' => $organizationId,
                    'posting_status' => 'skipped', // usage doesn't post — feed produce posts the offset
                    'is_posted' => false,
                ]);

                // Unified inventory movement: raw material usage.
                // Source: feed_batch_item — one per recipe ingredient.
                $this->inventory->recordUsage(
                    itemKind: InventoryMovement::ITEM_KIND_RAW_MATERIAL,
                    itemId: (int) $material->id,
                    quantity: (float) $item['quantity_used'],
                    sourceType: InventoryMovement::SOURCE_FEED_BATCH_ITEM,
                    sourceId: (int) $batchItem->id,
                    occurredAt: CarbonImmutable::instance($feedBatch->created_at ?? now()),
                    actorUserId: $actor->id,
                    reference: 'Feed Batch #'.$feedBatch->id,
                );
            }

            // Pass 4: record the FINISHED FEED produced — one movement.
            $this->inventory->recordFeedProduction(
                feedTypeId: (int) $feedType->id,
                quantity: $batchSizeKg,
                totalCost: $totalCost,
                feedBatchId: (int) $feedBatch->id,
                occurredAt: CarbonImmutable::instance($feedBatch->created_at ?? now()),
                actorUserId: $actor->id,
                reference: 'Feed Batch #'.$feedBatch->id,
                itemNameCache: $feedType->name,
            );

            // Domain event — single one, payload includes the full
            // ingredient list for the GL subscriber.
            $this->dispatcher->dispatch(new FeedBatchProduced(
                source: new SourceReference(
                    module: 'feed',
                    sourceType: FeedBatch::class,
                    sourceId: (int) $feedBatch->id,
                    organizationId: $organizationId,
                ),
                occurredAt: CarbonImmutable::parse($data['batch_date'])->setTime(
                    (int) now()->hour, (int) now()->minute, (int) now()->second,
                ),
                dispatchedByUserId: $actor->id,
                feedBatchId: (int) $feedBatch->id,
                feedTypeId: (int) $feedType->id,
                feedTypeName: (string) $feedType->name,
                batchSizeKg: $batchSizeKg,
                totalCost: $totalCost,
                ingredients: collect($batchItems)->map(fn ($it) => [
                    'raw_material_id' => (int) $it['raw_material']->id,
                    'raw_material_name' => (string) $it['raw_material']->name,
                    'quantity_used' => (float) $it['quantity_used'],
                    'unit_cost' => (float) $it['unit_cost'],
                    'total_cost' => (float) $it['total_cost'],
                ])->values()->all(),
            ));

            app(FeedConfigurationService::class)->audit($actor, 'feed_production_confirmed', $feedBatch, [], ['quantity_kg' => $batchSizeKg, 'planned_quantity_kg' => $plannedKg, 'recipe' => $snapshot], 'Production confirmed; raw materials consumed and finished feed received.');

            return [
                'feed_batch' => $feedBatch->fresh(),
                'total_cost' => $totalCost,
                'items' => $batchItems,
            ];
        });
    }
}
