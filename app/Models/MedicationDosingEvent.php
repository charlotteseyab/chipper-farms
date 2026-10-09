<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One "Record dose" submission: takes stock once for every animal dosed. */
class MedicationDosingEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'medicine_id', 'administered_at', 'administered_by', 'recorded_by', 'route', 'total_stock_quantity',
        'stock_unit', 'idempotency_key', 'status', 'corrects_event_id', 'correction_reason', 'notes',
    ];

    protected $casts = ['administered_at' => 'datetime', 'total_stock_quantity' => 'decimal:4'];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(MedicationDosingAllocation::class);
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function correctsEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_event_id');
    }
}
