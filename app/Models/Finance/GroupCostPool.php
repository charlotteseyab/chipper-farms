<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 5 — group cost pool snapshot.
 *
 * One row per (organization, group). Carries the running pig count,
 * carrying value, and weight. Written exclusively by
 * GroupCostPoolService under row-level lock.
 *
 * Closed pools (closed_at != null) have zero value, zero weight, zero
 * count. Reports filter on closed_at IS NULL by default.
 */
class GroupCostPool extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'group_id',
        'group_name_cache',
        'group_type_cache',
        'on_hand_pig_count',
        'total_carrying_value',
        'total_weight_kg',
        'last_movement_id',
        'opened_at',
        'closed_at',
        'first_movement_at',
        'last_movement_at',
    ];

    protected $casts = [
        'on_hand_pig_count'    => 'integer',
        'total_carrying_value' => 'decimal:4',
        'total_weight_kg'      => 'decimal:4',
        'opened_at'            => 'datetime',
        'closed_at'            => 'datetime',
        'first_movement_at'    => 'datetime',
        'last_movement_at'     => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function lastMovement(): BelongsTo
    {
        return $this->belongsTo(GroupCostMovement::class, 'last_movement_id');
    }

    /**
     * Per-kg cost basis = value / weight. Returns string for bcmath
     * round-trip safety. Returns '0' for empty pools.
     */
    public function perKgBasis(): string
    {
        $weight = (string) $this->total_weight_kg;
        $value  = (string) $this->total_carrying_value;

        if (bccomp($weight, '0', 4) <= 0) {
            return '0';
        }
        return bcdiv($value, $weight, 6);
    }

    /**
     * Per-pig average. Less defensible than per-kg (small pigs absorb
     * the same share as big pigs), but useful for at-a-glance reporting.
     */
    public function perPigAverage(): string
    {
        if ((int) $this->on_hand_pig_count <= 0) {
            return '0';
        }
        return bcdiv((string) $this->total_carrying_value, (string) $this->on_hand_pig_count, 4);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function isEmpty(): bool
    {
        return (int) $this->on_hand_pig_count <= 0;
    }
}
