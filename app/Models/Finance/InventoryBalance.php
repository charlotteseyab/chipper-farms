<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 4 — current inventory snapshot.
 *
 * One row per (organization_id, item_kind, item_id). Authoritative for
 * "what's on hand" and "what's the current WAC". Updated synchronously
 * by InventoryService at every movement under row-level lock.
 *
 * NEVER UPDATED OUTSIDE THE INVENTORY SERVICE. Manual edits break the
 * invariant that balances reflect the sum of all movements. The model
 * is deliberately bare of public mutators / fillable mass-assignment
 * for the WAC math fields; only the service's internal save() reaches
 * them via setAttribute() or fill().
 *
 * Why the model is mass-assignable at all:
 *   The service uses Model::create() and Model::update() with arrays
 *   built inside its own write methods. Mass-assignment protection is
 *   the right discipline at the controller layer; at the service layer
 *   we have invariant tests covering the math.
 */
class InventoryBalance extends Model
{
    use BelongsToOrganization;

    public const ITEM_KIND_RAW_MATERIAL  = 'raw_material';
    public const ITEM_KIND_FINISHED_FEED = 'finished_feed';
    public const ITEM_KIND_MEDICINE_BATCH = 'medicine_batch';

    public const ALLOWED_ITEM_KINDS = [
        self::ITEM_KIND_RAW_MATERIAL,
        self::ITEM_KIND_FINISHED_FEED,
        self::ITEM_KIND_MEDICINE_BATCH,
    ];

    /**
     * Kinds whose value is posted to the general ledger (inventory 1210/1220).
     * Medicine stock is costed in the ledger but not posted (decision D10), so
     * finance totals and the period-close check read only these kinds.
     */
    public const GL_POSTED_ITEM_KINDS = [
        self::ITEM_KIND_RAW_MATERIAL,
        self::ITEM_KIND_FINISHED_FEED,
    ];

    protected $fillable = [
        'organization_id',
        'item_kind',
        'item_id',
        'item_name_cache',
        'item_unit_cache',
        'on_hand_qty',
        'on_hand_value',
        'weighted_avg_cost',
        'last_movement_id',
        'first_movement_at',
        'last_movement_at',
    ];

    protected $casts = [
        'on_hand_qty'       => 'decimal:4',
        'on_hand_value'     => 'decimal:4',
        'weighted_avg_cost' => 'decimal:6',
        'first_movement_at' => 'datetime',
        'last_movement_at'  => 'datetime',
    ];

    public function lastMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'last_movement_id');
    }

    /**
     * The on-hand snapshot is non-negative by design. If the on-hand
     * goes negative due to a numerical edge case (e.g. tiny residual
     * from floating-point math), the service clamps to zero at write
     * time. Read-side defensive helper.
     */
    public function safeOnHandQty(): float
    {
        return max(0.0, (float) $this->on_hand_qty);
    }

    public function safeOnHandValue(): float
    {
        return max(0.0, (float) $this->on_hand_value);
    }

    public function isEmpty(): bool
    {
        return (float) $this->on_hand_qty <= 0.0;
    }
}
