<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An adjustment or count of one medicine batch, with the reason. Source of one ledger movement. */
class MedicineStockAdjustment extends Model
{
    use BelongsToOrganization;

    public const TYPES = ['add' => 'Add stock', 'deduct' => 'Remove stock', 'count' => 'Stock count'];

    protected $fillable = [
        'organization_id', 'medicine_batch_id', 'type', 'quantity', 'quantity_before', 'quantity_after',
        'reason', 'adjusted_at', 'adjusted_by', 'inventory_movement_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4', 'quantity_before' => 'decimal:4', 'quantity_after' => 'decimal:4', 'adjusted_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
