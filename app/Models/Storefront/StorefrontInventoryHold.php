<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A claim on stock between checkout and a staff decision (Phase 10, §37).
 *
 * Holds do not move inventory. `quantity_available` is decremented once, by
 * `OrderService::applyInventoryEffect()` at confirmation, exactly as before —
 * a hold that also decremented would take the same stock away twice, once
 * softly and once for real.
 *
 * What a hold does is subtract from what the *shop* will sell. That is the
 * whole mechanism: `HoldService::availableToSell()` is `quantity_available`
 * minus the active holds, and every path that offers stock to a buyer asks that
 * question rather than reading the column directly.
 */
class StorefrontInventoryHold extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'storefront_inventory_holds';

    /**
     * WHAT A HOLD RESERVES.
     *
     *   quantity   → head. The original and the default: N of a thing.
     *   exact_pigs → these specific animals, chosen by the buyer.
     *   carcass_kg → kilograms of meat; which animals cover it is decided later.
     *
     * They are counted apart because they are different units. Netting
     * kilograms off a head count is how a shop sells four hundred pigs when it
     * meant four hundred kilograms.
     */
    public const TYPE_QUANTITY   = 'quantity';
    public const TYPE_EXACT_PIGS = 'exact_pigs';
    public const TYPE_CARCASS_KG = 'carcass_kg';

    public const TYPES = [self::TYPE_QUANTITY, self::TYPE_EXACT_PIGS, self::TYPE_CARCASS_KG];

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED  = 'expired';

    protected $fillable = [
        'organization_id',
        'listing_id',
        'order_id',
        'quantity',
        'hold_type',
        'requested_kg',
        'selected_listing_pig_ids',
        'status',
        'expires_at',
        'resolved_at',
        'resolution_reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'               => 'datetime',
            'resolved_at'              => 'datetime',
            'requested_kg'             => 'decimal:4',
            'selected_listing_pig_ids' => 'array',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StorefrontListing::class, 'listing_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StorefrontOrder::class, 'order_id');
    }

    /**
     * Holds that are still claiming stock right now.
     *
     * A null `expires_at` counts as live — it means the hold is waiting on a
     * person rather than a clock. Written as an explicit null branch because
     * `expires_at > now()` alone silently drops every null in SQL, which would
     * quietly stop protecting every cash order.
     */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function quantityString(): string
    {
        return bcadd((string) $this->quantity, '0', 4);
    }
}
