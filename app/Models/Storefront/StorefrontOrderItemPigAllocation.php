<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One animal an approver committed to one order line.
 *
 * A line for three pigs has three of these. That is the whole reason the table
 * exists: `storefront_order_items.posted_sale_id` holds a single sale id and
 * cannot express a line that becomes three `pig_sales` rows.
 *
 * THE STATUS LADDER
 * -----------------
 *   selected → an approver picked this animal; nothing irreversible yet
 *   approved → the selection passed validation at confirmation time
 *   sold     → `RecordPigSaleService` created the sale; `pig_sale_id` is set
 *   released → the order was cancelled or the selection withdrawn
 *   rejected → the animal failed re-validation at confirmation
 *
 * `selected` and `approved` are ACTIVE: they reserve the animal against any
 * other order. `sold` is terminal and authoritative only as a pointer — the
 * sale itself lives in `pig_sales`.
 */
class StorefrontOrderItemPigAllocation extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'storefront_order_item_pig_allocations';

    public const STATUS_SELECTED = 'selected';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SOLD     = 'sold';
    public const STATUS_RELEASED = 'released';
    public const STATUS_REJECTED = 'rejected';

    /**
     * States that reserve the animal against every other order.
     *
     * `sold` is excluded deliberately — a sold pig is soft-deleted and out of
     * Standard Stock, so it is already unreachable by any other path. Including
     * it would make "release the order's allocations" ambiguous about whether a
     * completed sale should be undone, which it must never be.
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_SELECTED,
        self::STATUS_APPROVED,
    ];

    protected $fillable = [
        'organization_id',
        'storefront_order_id',
        'storefront_order_item_id',
        'storefront_listing_id',
        'storefront_listing_pig_id',
        'pig_id',
        'pig_sale_id',
        'selected_by_user_id',
        'approved_by_user_id',
        'selected_at',
        'approved_at',
        'status',
        'release_reason',
        // How the animal on this line was bought, what it weighed, what it was
        // worth, and whether the CUSTOMER chose it or the farm did. The last
        // one changes what a manager may substitute.
        'purchase_type',
        'allocated_live_weight_kg',
        'allocated_carcass_weight_kg',
        'allocated_sale_amount',
        'customer_selected',
        'allocation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'selected_at'                 => 'datetime',
            'approved_at'                 => 'datetime',
            'allocated_live_weight_kg'    => 'decimal:4',
            'allocated_carcass_weight_kg' => 'decimal:4',
            'allocated_sale_amount'       => 'decimal:2',
            'customer_selected'           => 'boolean',
            'allocation_snapshot'         => 'array',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relations                                                          */
    /* ------------------------------------------------------------------ */

    public function order(): BelongsTo
    {
        return $this->belongsTo(StorefrontOrder::class, 'storefront_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(StorefrontOrderItem::class, 'storefront_order_item_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StorefrontListing::class, 'storefront_listing_id');
    }

    public function listingPig(): BelongsTo
    {
        return $this->belongsTo(StorefrontListingPig::class, 'storefront_listing_pig_id');
    }

    /**
     * Soft-deleted pigs included: a sold pig is trashed by
     * `RecordPigSaleService`, and the allocation that sold it must still be
     * able to name the animal.
     */
    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function pigSale(): BelongsTo
    {
        return $this->belongsTo(PigSale::class, 'pig_sale_id');
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeSold(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SOLD);
    }

    /* ------------------------------------------------------------------ */

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }
}
