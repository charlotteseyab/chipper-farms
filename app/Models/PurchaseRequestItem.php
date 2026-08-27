<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 4 — adds raw_material_id FK, receipt tracking columns, and a
 * relation to RawMaterialStockLog for receipts against this line.
 */
class PurchaseRequestItem extends Model
{
    use \App\Models\Concerns\BelongsToOrganizationThroughRelation;

    protected static function organizationRelation(): string
    {
        return 'purchaseRequest';
    }

    protected $fillable = [
        'purchase_request_id',
        'item_name',
        'description',
        'quantity',
        'unit_price',
        'total_price',

        // Phase 4
        'raw_material_id',
        'unit',
        'quantity_received',
        'is_receipt_closed',
        'receipt_closed_at',
    ];

    protected $casts = [
        'quantity'           => 'decimal:2',
        'unit_price'         => 'decimal:2',
        'total_price'        => 'decimal:2',

        // Phase 4
        'quantity_received'  => 'decimal:4',
        'is_receipt_closed'  => 'boolean',
        'receipt_closed_at'  => 'datetime',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

    public function stockLogs(): HasMany
    {
        return $this->hasMany(RawMaterialStockLog::class, 'purchase_request_item_id');
    }

    /**
     * Remaining quantity expected on this PR line. Useful in the
     * receive-against-PR UI to show the open amount.
     */
    public function quantityOutstanding(): float
    {
        return max(0.0, (float) $this->quantity - (float) $this->quantity_received);
    }
}
