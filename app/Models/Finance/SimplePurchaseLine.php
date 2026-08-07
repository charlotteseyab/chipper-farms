<?php

namespace App\Models\Finance;

use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a farm purchase (Phase 6b).
 *
 * `line_kind` decides the accounting: a stock line goes through the inventory
 * subledger and its GL is posted by a subscriber; an expense line posts
 * directly. Mixing both on one purchase is normal — a feed delivery with a
 * transport charge is two lines of different kinds.
 */
class SimplePurchaseLine extends Model
{
    public const KIND_STOCK   = 'stock';
    public const KIND_EXPENSE = 'expense';

    protected $table = 'simple_purchase_lines';

    protected $fillable = [
        'organization_id', 'simple_purchase_id', 'line_kind', 'raw_material_id',
        'category_key', 'expense_account_code', 'description', 'quantity',
        'unit', 'unit_cost', 'line_total', 'inventory_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'unit_cost'  => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(SimplePurchase::class, 'simple_purchase_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function isStock(): bool
    {
        return $this->line_kind === self::KIND_STOCK;
    }
}
