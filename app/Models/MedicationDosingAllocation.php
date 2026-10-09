<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The stock one dosing event took from one batch, with its cost and expiry at the time. */
class MedicationDosingAllocation extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'medication_dosing_event_id', 'medicine_batch_id', 'stock_quantity',
        'unit_cost_snapshot', 'expiry_snapshot', 'inventory_movement_id',
    ];

    protected $casts = ['stock_quantity' => 'decimal:4', 'unit_cost_snapshot' => 'decimal:6', 'expiry_snapshot' => 'date'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(MedicationDosingEvent::class, 'medication_dosing_event_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }
}
