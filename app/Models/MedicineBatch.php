<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A received batch of a medicine. Its stock lives in the inventory ledger (Phase 3). */
class MedicineBatch extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id', 'medicine_id', 'batch_number', 'expiry_date', 'received_at', 'quantity_received',
        'unit_cost', 'supplier', 'supplier_id', 'notes', 'is_active', 'received_by',
    ];

    protected $casts = [
        'expiry_date' => 'date', 'received_at' => 'date', 'quantity_received' => 'decimal:4',
        'unit_cost' => 'decimal:6', 'is_active' => 'boolean',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /** Usable on its expiry date, refused after (decision D6). */
    public function isExpiredOn(\DateTimeInterface|string $date): bool
    {
        return $this->expiry_date->lt(\Carbon\CarbonImmutable::parse($date)->startOfDay());
    }
}
