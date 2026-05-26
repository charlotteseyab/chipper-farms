<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 5 — pig group transfer.
 *
 * Records one event of a pig moving from one group to another. Both
 * sides post to group_cost_movements (transfer_out on source,
 * transfer_in on destination). No GL batch — both pools roll up to
 * 1230 Live Pig Inventory, so a Dr 1230 / Cr 1230 batch would have
 * no information value.
 *
 * Operational ergonomics:
 *   - For whole-group promotions ("nursery batch 3 → grower batch 3"),
 *     prefer renaming the group rather than per-pig transfers. Saves
 *     write volume and keeps the cost pool intact.
 *   - For pen reorganisations where a subset of pigs move, use this
 *     table.
 */
class PigGroupTransfer extends Model
{
    use BelongsToOrganization;

    public const REASON_WEANING_PROMOTION  = 'weaning_promotion';
    public const REASON_PEN_REORGANISATION = 'pen_reorganisation';
    public const REASON_HEALTH_ISOLATION   = 'health_isolation';
    public const REASON_MANUAL_OVERRIDE    = 'manual_override';

    public const ALLOWED_REASONS = [
        self::REASON_WEANING_PROMOTION,
        self::REASON_PEN_REORGANISATION,
        self::REASON_HEALTH_ISOLATION,
        self::REASON_MANUAL_OVERRIDE,
    ];

    protected $fillable = [
        'organization_id',
        'pig_id',
        'source_group_id',
        'destination_group_id',
        'pig_weight_at_transfer_kg',
        'transferred_value',
        'source_per_kg_basis_at_transfer',
        'transferred_at',
        'performed_by_user_id',
        'reason',
        'notes',
    ];

    protected $casts = [
        'pig_weight_at_transfer_kg'         => 'decimal:4',
        'transferred_value'                 => 'decimal:4',
        'source_per_kg_basis_at_transfer'   => 'decimal:6',
        'transferred_at'                    => 'datetime',
    ];

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id');
    }

    public function sourceGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'source_group_id');
    }

    public function destinationGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'destination_group_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
