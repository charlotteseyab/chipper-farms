<?php

namespace App\Services\Inventory;

use App\Domain\Events\Inventory\RawMaterialAdjusted;
use App\Domain\Events\Support\SourceReference;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — adjust raw material stock.
 *
 * REWRITTEN to call the unified InventoryService instead of the deleted
 * RawMaterialValuationService.
 *
 * Adjustment modes:
 *   - 'add'    : on-hand += magnitude
 *   - 'deduct' : on-hand -= magnitude (rejects if magnitude > on-hand
 *                unless caller has override permission)
 *   - 'set'    : on-hand := magnitude (signed delta computed from current
 *                on-hand to new target)
 *
 * The stock log records the signed delta. The domain event payload
 * includes the signed delta + the new on-hand for the GL subscriber.
 *
 * NEGATIVE-INVENTORY OVERRIDE
 *
 *   The caller (controller) may pass $allowNegative=true when the user
 *   has override_negative_inventory permission. The default is false:
 *   refuse to drive on-hand below zero.
 *
 *   This is a SAFEGUARD, not a policy — operators can still record
 *   a 'deduct' that brings on-hand to exactly zero. It only blocks
 *   actually going negative.
 */
class AdjustRawMaterialService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
        protected InventoryService $inventory,
    ) {
    }

    /**
     * @param  array{
     *   adjustment_type: 'add'|'deduct'|'set',
     *   quantity: float,
     *   reference?: ?string,
     *   notes?: ?string,
     *   adjusted_at?: ?string,
     * }  $data
     */
    public function execute(
        RawMaterial $material,
        User $actor,
        array $data,
        bool $allowNegative = false,
    ): array {
        $organizationId = $this->currentOrganization->requireId();
        $type = $data['adjustment_type'];
        $magnitude = (float) $data['quantity'];

        if (! in_array($type, ['add', 'deduct', 'set'], true)) {
            throw new \InvalidArgumentException("Unknown adjustment type: {$type}");
        }
        if ($magnitude < 0) {
            throw new \InvalidArgumentException('Adjustment quantity must be non-negative.');
        }

        $adjustedAt = isset($data['adjusted_at']) ? CarbonImmutable::parse($data['adjusted_at']) : CarbonImmutable::now();

        return DB::transaction(function () use (
            $material, $actor, $data, $organizationId, $type, $magnitude, $allowNegative, $adjustedAt,
        ) {
            $material = RawMaterial::where('organization_id', $organizationId)->whereKey($material->id)->lockForUpdate()->firstOrFail();
            // The ledger is the stock figure; the adjustment is measured and
            // valued against it, not the mirrored quantity_in_stock column.
            $balance = InventoryBalance::where('organization_id', $organizationId)
                ->where('item_kind', InventoryMovement::ITEM_KIND_RAW_MATERIAL)
                ->where('item_id', $material->id)
                ->first();
            $current = (float) ($balance?->on_hand_qty ?? 0);
            $unitCost = (float) ($balance && (float) $balance->weighted_avg_cost > 0 ? $balance->weighted_avg_cost : $material->unit_cost);

            // Pre-compute the target on-hand for clarity.
            $newQuantity = match ($type) {
                'add'    => $current + $magnitude,
                'deduct' => $current - $magnitude,
                'set'    => $magnitude,
            };

            $delta = $newQuantity - $current;

            // Hard refuse going negative unless override.
            if ($newQuantity < 0 && ! $allowNegative) {
                throw new \DomainException(sprintf(
                    'Adjustment would drive on-hand below zero (current %.4f, new %.4f). '
                    . 'Use override_negative_inventory permission if intentional.',
                    $current,
                    $newQuantity,
                ));
            }

            // Stock log — the signed delta. Phase 4 stock logs also
            // track posting_status; adjustments start 'pending' for the
            // subscriber to pick up.
            $log = $material->stockLogs()->create([
                'type'            => 'adjustment',
                'quantity'        => $delta,
                'unit_cost'       => $unitCost,
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes']
                    ?? ('Stock adjusted using "' . $type . '" action.'),
                'performed_by'    => $actor->id,
                'organization_id' => $organizationId,
                'posting_status'  => 'pending',
                'is_posted'       => false,
            ]);

            // Unified inventory movement.
            $movement = $this->inventory->recordAdjustment(
                itemKind:      InventoryMovement::ITEM_KIND_RAW_MATERIAL,
                itemId:        (int) $material->id,
                type:          $type,
                quantity:      $type === 'set' ? $newQuantity : $magnitude,
                sourceType:    InventoryMovement::SOURCE_STOCK_LOG,
                sourceId:      (int) $log->id,
                occurredAt:    $adjustedAt,
                actorUserId:   $actor->id,
                reference:     $data['reference'] ?? null,
                notes:         $data['notes'] ?? null,
                allowNegative: $allowNegative,
            );

            // Domain event for the GL subscriber.
            $this->dispatcher->dispatch(new RawMaterialAdjusted(
                source: new SourceReference(
                    module:         'inventory',
                    sourceType:     RawMaterialStockLog::class,
                    sourceId:       (int) $log->id,
                    organizationId: $organizationId,
                ),
                occurredAt: $adjustedAt,
                dispatchedByUserId: $actor->id,
                rawMaterialId:     (int) $material->id,
                rawMaterialName:   (string) $material->name,
                adjustmentType:    $type,
                quantityDelta:     $delta,
                newQuantityOnHand: $newQuantity,
                unitCost:          $unitCost,
                reference:         $data['reference'] ?? null,
                notes:             $data['notes'] ?? null,
            ));

            return [
                'material'    => $material->fresh(),
                'log'         => $log->fresh(),
                'movement'    => $movement,
                'delta'       => $delta,
                'newQuantity' => $newQuantity,
            ];
        });
    }
}
