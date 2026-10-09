<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizationThroughRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    use BelongsToOrganizationThroughRelation;

    public const POSTING_NOT_REQUIRED = 'not_required';
    public const POSTING_PENDING = 'pending';
    public const POSTING_POSTED = 'posted';

    protected static function organizationRelation(): string
    {
        return 'stockCount';
    }

    protected $fillable = [
        'stock_count_id', 'item_kind', 'item_id', 'item_name', 'unit',
        'system_qty', 'counted_qty', 'difference_qty', 'value_delta',
        'raw_material_stock_log_id', 'inventory_movement_id', 'posting_status', 'journal_batch_id',
    ];

    protected $casts = [
        'system_qty'     => 'decimal:4',
        'counted_qty'    => 'decimal:4',
        'difference_qty' => 'decimal:4',
        'value_delta'    => 'decimal:4',
    ];

    public function stockLog(): BelongsTo
    {
        return $this->belongsTo(RawMaterialStockLog::class, 'raw_material_stock_log_id');
    }

    /** Raw-material lines post through their stock log; feed lines track their own status. */
    public function getEffectivePostingStatusAttribute(): string
    {
        return $this->raw_material_stock_log_id ? (string) ($this->stockLog?->posting_status ?? $this->posting_status) : $this->posting_status;
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }
}
