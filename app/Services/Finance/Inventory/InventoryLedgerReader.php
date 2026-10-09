<?php

namespace App\Services\Finance\Inventory;

use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\RawMaterial;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Phase 4 — Read-only facade over the unified inventory ledger.
 *
 * Use cases:
 *   - Inventory value summary screens
 *   - Per-item movement detail screens
 *   - Point-in-time valuation (for closing-period reports)
 *   - Recent-activity listing
 *
 * Why a separate reader instead of letting controllers query the
 * tables directly?
 *   - Centralises org-scope filtering — every query goes through
 *     currentOrganization.
 *   - Normalises the output shape (so controllers + views don't have
 *     to reach into model internals).
 *   - Provides a stable surface for future enhancements (caching
 *     hot reads, switching to materialised views, etc.) without
 *     having to refactor every caller.
 */
class InventoryLedgerReader
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    // ============================================================
    // Aggregated reads
    // ============================================================

    /**
     * Total inventory value across all kinds, sliced by kind.
     *
     * @return array{raw_material_value: string, finished_feed_value: string, total_value: string}
     */
    public function totalValue(): array
    {
        $orgId = $this->currentOrganization->id();

        $byKind = InventoryBalance::query()
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->selectRaw('item_kind, COALESCE(SUM(on_hand_value), 0) AS total')
            ->groupBy('item_kind')
            ->pluck('total', 'item_kind');

        $rm = $this->str($byKind->get(InventoryBalance::ITEM_KIND_RAW_MATERIAL, '0'));
        $ff = $this->str($byKind->get(InventoryBalance::ITEM_KIND_FINISHED_FEED, '0'));
        $total = bcadd($rm, $ff, 4);

        return [
            'raw_material_value'  => $rm,
            'finished_feed_value' => $ff,
            'total_value'         => $total,
        ];
    }

    /**
     * Per-item summary rows for the inventory value table.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summary(?string $itemKindFilter = null): Collection
    {
        $orgId = $this->currentOrganization->id();

        $query = InventoryBalance::query()
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->whereIn('item_kind', InventoryBalance::GL_POSTED_ITEM_KINDS)
            ->orderBy('item_kind')
            ->orderByDesc('on_hand_value');

        if ($itemKindFilter !== null) {
            $query->where('item_kind', $itemKindFilter);
        }

        $balances = $query->get();

        // Hydrate item names from source tables. We have item_name_cache
        // but the source is authoritative on rename.
        $rmIds = $balances->where('item_kind', InventoryBalance::ITEM_KIND_RAW_MATERIAL)
            ->pluck('item_id')->all();
        $ffIds = $balances->where('item_kind', InventoryBalance::ITEM_KIND_FINISHED_FEED)
            ->pluck('item_id')->all();

        $rmItems = RawMaterial::query()->whereIn('id', $rmIds)
            ->get(['id', 'name', 'unit', 'category'])
            ->keyBy('id');
        $ffItems = FeedType::query()->whereIn('id', $ffIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        return $balances->map(function (InventoryBalance $b) use ($rmItems, $ffItems) {
            $name = $b->item_name_cache;
            $unit = $b->item_unit_cache;
            $category = null;

            if ($b->item_kind === InventoryBalance::ITEM_KIND_RAW_MATERIAL) {
                $rm = $rmItems->get($b->item_id);
                $name = $rm?->name ?? $name;
                $unit = $rm?->unit ?? $unit;
                $category = $rm?->category;
            } elseif ($b->item_kind === InventoryBalance::ITEM_KIND_FINISHED_FEED) {
                $ff = $ffItems->get($b->item_id);
                $name = $ff?->name ?? $name;
                $unit = $unit ?? 'kg';
            }

            return [
                'item_kind'   => $b->item_kind,
                'item_id'     => (int) $b->item_id,
                'item_name'   => $name ?? '(unknown)',
                'unit'        => $unit,
                'category'    => $category,
                'on_hand_qty' => (string) $b->on_hand_qty,
                'wac'         => (string) $b->weighted_avg_cost,
                'value'       => (string) $b->on_hand_value,
                'last_movement_at' => $b->last_movement_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Per-item summary for a single (kind, id). Null if absent.
     *
     * @return array<string, mixed>|null
     */
    public function summaryForItem(string $itemKind, int $itemId): ?array
    {
        $orgId = $this->currentOrganization->id();

        $balance = InventoryBalance::query()
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('item_kind', $itemKind)
            ->where('item_id',   $itemId)
            ->first();

        if (! $balance) {
            return null;
        }

        $name = $balance->item_name_cache;
        $unit = $balance->item_unit_cache;
        $category = null;

        if ($itemKind === InventoryBalance::ITEM_KIND_RAW_MATERIAL) {
            $rm = RawMaterial::query()->find($itemId);
            $name = $rm?->name ?? $name;
            $unit = $rm?->unit ?? $unit;
            $category = $rm?->category;
        } elseif ($itemKind === InventoryBalance::ITEM_KIND_FINISHED_FEED) {
            $ff = FeedType::query()->find($itemId);
            $name = $ff?->name ?? $name;
        }

        return [
            'item_kind'   => $itemKind,
            'item_id'     => $itemId,
            'item_name'   => $name ?? '(unknown)',
            'unit'        => $unit,
            'category'    => $category,
            'on_hand_qty' => (string) $balance->on_hand_qty,
            'wac'         => (string) $balance->weighted_avg_cost,
            'value'       => (string) $balance->on_hand_value,
            'last_movement_at' => $balance->last_movement_at?->toIso8601String(),
        ];
    }

    // ============================================================
    // Movement reads
    // ============================================================

    /**
     * @return Collection<int, InventoryMovement>
     */
    public function recentMovementsForItem(string $itemKind, int $itemId, int $limit = 20): Collection
    {
        $orgId = $this->currentOrganization->id();

        return InventoryMovement::query()
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('item_kind', $itemKind)
            ->where('item_id',   $itemId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Point-in-time value for a single item. Returns the
     * value_on_hand_after of the last movement at or before the cutoff
     * timestamp, or '0' if no movements exist by then.
     */
    public function itemValueAt(string $itemKind, int $itemId, CarbonImmutable $cutoff): string
    {
        $orgId = $this->currentOrganization->id();

        $movement = InventoryMovement::query()
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('item_kind', $itemKind)
            ->where('item_id',   $itemId)
            ->where('occurred_at', '<=', $cutoff)
            ->orderByDesc('id')
            ->first();

        return $movement?->value_on_hand_after !== null
            ? (string) $movement->value_on_hand_after
            : '0';
    }

    /**
     * Point-in-time quantity for a single item.
     */
    public function itemQuantityAt(string $itemKind, int $itemId, CarbonImmutable $cutoff): string
    {
        $orgId = $this->currentOrganization->id();

        $movement = InventoryMovement::query()
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('item_kind', $itemKind)
            ->where('item_id',   $itemId)
            ->where('occurred_at', '<=', $cutoff)
            ->orderByDesc('id')
            ->first();

        return $movement?->quantity_on_hand_after !== null
            ? (string) $movement->quantity_on_hand_after
            : '0';
    }

    // ============================================================
    // Helpers
    // ============================================================

    protected function str(string|float|null $v): string
    {
        if ($v === null || $v === '') {
            return '0';
        }
        if (is_float($v)) {
            return number_format($v, 4, '.', '');
        }
        return $v;
    }
}
