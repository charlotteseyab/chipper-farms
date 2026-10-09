<?php

namespace App\Models;

use App\Models\Finance\InventoryBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RawMaterial model.
 *
 * Phase 5 cleanup:
 *   The Phase 0.7 `raw_material_valuations` table was retired by Phase 4
 *   migration 2027_03_01_120800 (data migrated into the unified
 *   `inventory_movements` / `inventory_balances` ledger and the old table
 *   dropped). The old `valuations()` / `latestValuation()` relations and
 *   the `RawMaterialValuation` model are therefore gone.
 *
 *   The `current_wac` and `current_value_on_hand` accessors are PRESERVED
 *   (callers like ProduceFeedBatchService depend on them) but now read
 *   from the Phase 4 InventoryBalance snapshot instead of the dropped
 *   valuation table.
 *
 * The legacy `unit_cost` column is still PRESERVED and written on receipt.
 * It remains the fallback for materials with no inventory_balances row yet
 * (fresh deploys before opening balances are imported, or brand-new
 * materials with no movements).
 */
class RawMaterial extends Model
{
    use \App\Models\Concerns\BelongsToOrganization;

    protected $fillable = [
        'is_active',
        'organization_id',
        'name',
        'category',
        'unit',
        'kg_per_unit',
        'quantity_in_stock',
        'unit_cost',
        'reorder_level',
        'inventory_account_id',
        'expense_account_id',
        'inventory_item_type',
        'notes',
    ];

    protected $casts = [
        'quantity_in_stock' => 'decimal:2',
        'unit_cost'         => 'decimal:2',
        'reorder_level'     => 'decimal:2',
        'kg_per_unit'       => 'decimal:4',
        'inventory_account_id' => 'integer',
        'expense_account_id'   => 'integer',
    ];

    public function isMeasuredInKg(): bool
    {
        return in_array(strtolower(trim((string) $this->unit)), ['kg', 'kgs', 'kilogram', 'kilograms'], true);
    }

    /**
     * Kilograms in one stock unit: 1 for kg materials, the configured weight
     * for bags and similar, or null when a recipe cannot use it yet.
     */
    public function kgPerUnit(): ?float
    {
        if ($this->isMeasuredInKg()) {
            return 1.0;
        }

        return (float) $this->kg_per_unit > 0 ? (float) $this->kg_per_unit : null;
    }

    public function stockLogs(): HasMany
    {
        return $this->hasMany(RawMaterialStockLog::class);
    }

    /**
     * The unified-ledger balance row for this raw material, if any.
     *
     * One row per (organization, item_kind, item_id). Returns null when
     * the material has had no inventory movements yet.
     */
    public function inventoryBalance()
    {
        return $this->hasOne(InventoryBalance::class, 'item_id')
            ->where('item_kind', InventoryBalance::ITEM_KIND_RAW_MATERIAL);
    }

    /**
     * Current weighted-average cost.
     *
     * Preference order:
     *   1. The unified ledger balance row's weighted_avg_cost (authoritative,
     *      Phase 4+).
     *   2. Legacy unit_cost column (fallback for materials with no ledger
     *      movements yet, or brand-new materials).
     *
     * Used by ProduceFeedBatchService to cost the recipe at mix time.
     */
    public function getCurrentWacAttribute(): float
    {
        $balance = $this->ledgerBalance();

        if ($balance !== null && (float) $balance->weighted_avg_cost > 0) {
            return (float) $balance->weighted_avg_cost;
        }

        return (float) $this->unit_cost;
    }

    /**
     * Current total value on hand under WAC. Useful for inventory reports.
     */
    public function getCurrentValueOnHandAttribute(): float
    {
        $balance = $this->ledgerBalance();

        if ($balance !== null) {
            return (float) $balance->on_hand_value;
        }

        // Fallback uses legacy fields.
        return (float) $this->quantity_in_stock * (float) $this->unit_cost;
    }

    /** Consistent presentation status used by the inventory table and filters. */
    public function getStockStatusAttribute(): string
    {
        $quantity = (float) $this->quantity_in_stock;

        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        return $quantity <= (float) $this->reorder_level
            ? 'low_stock'
            : 'in_stock';
    }

    /** Value on hand at average cost, from the stock ledger. */
    public function getStockValueAttribute(): float
    {
        return $this->current_value_on_hand;
    }

    /** Total raw material value on hand at average cost for the current farm. */
    public static function ledgerStockValue(): float
    {
        return (float) InventoryBalance::query()
            ->where('item_kind', InventoryBalance::ITEM_KIND_RAW_MATERIAL)
            ->whereIn('item_id', static::query()->select('id'))
            ->sum('on_hand_value');
    }

    /** Uses an eager-loaded balance when present to avoid a query per row. */
    private function ledgerBalance(): ?InventoryBalance
    {
        return $this->relationLoaded('inventoryBalance')
            ? $this->getRelation('inventoryBalance')
            : $this->inventoryBalance()->first();
    }
}
