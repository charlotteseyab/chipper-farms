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
 * One Standard Stock pig in a storefront listing's pool.
 *
 * A listing that used to mean "this one animal" can now mean "any of these
 * five". The row says a pig MAY be sold through this listing; it never says a
 * pig HAS been sold. That remains `pig_sales`, written by
 * `RecordPigSaleService`, and `pig_sale_id` here is only a back-reference so an
 * order line can be traced to `/sales`.
 *
 * THE STATUS LADDER
 * -----------------
 *   available → the pool can offer it to a buyer
 *   held      → a checkout hold covers it, no animal chosen yet
 *   allocated → an approver has committed THIS animal to an order line
 *   sold      → `RecordPigSaleService` created the sale
 *   removed   → it left the pool: sold directly from /sales, died, or pulled
 *
 * `available`, `held` and `allocated` are the ACTIVE states. A pig may be
 * active in at most one pool at a time, which `StorefrontPigPoolService`
 * enforces under a row lock — see the migration for why that is not an index.
 */
class StorefrontListingPig extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'storefront_listing_pigs';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_HELD      = 'held';
    public const STATUS_ALLOCATED = 'allocated';
    public const STATUS_SOLD      = 'sold';
    public const STATUS_REMOVED   = 'removed';

    /**
     * States in which a pig is spoken for by this listing and may not join
     * another pool.
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_HELD,
        self::STATUS_ALLOCATED,
    ];

    /**
     * States that still count toward what the shop can sell.
     *
     * `allocated` deliberately does NOT: once an approver has committed an
     * animal to an order it is no longer on offer, even though the sale has not
     * been written yet.
     */
    public const SELLABLE_STATUSES = [
        self::STATUS_AVAILABLE,
    ];

    protected $fillable = [
        'organization_id',
        'storefront_listing_id',
        'pig_id',
        'status',
        'added_by_user_id',
        'removed_by_user_id',
        'allocated_order_id',
        'allocated_order_item_id',
        'pig_sale_id',
        'added_at',
        'allocated_at',
        'sold_at',
        'removed_at',
        'removal_reason',
    ];

    protected function casts(): array
    {
        return [
            'added_at'     => 'datetime',
            'allocated_at' => 'datetime',
            'sold_at'      => 'datetime',
            'removed_at'   => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relations                                                          */
    /* ------------------------------------------------------------------ */

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StorefrontListing::class, 'storefront_listing_id');
    }

    /**
     * The pig, including soft-deleted ones.
     *
     * `RecordPigSaleService` soft-deletes a pig when it is sold, so a pool row
     * in `sold` state points at a trashed pig by design. Without
     * `withTrashed()` the relation would silently return null on exactly the
     * rows a human most wants to read.
     */
    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function pigSale(): BelongsTo
    {
        return $this->belongsTo(PigSale::class, 'pig_sale_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by_user_id');
    }

    public function allocatedOrder(): BelongsTo
    {
        return $this->belongsTo(StorefrontOrder::class, 'allocated_order_id');
    }

    public function allocatedOrderItem(): BelongsTo
    {
        return $this->belongsTo(StorefrontOrderItem::class, 'allocated_order_item_id');
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->whereIn('status', self::SELLABLE_STATUSES);
    }

    public function scopeAllocated(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ALLOCATED);
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

    public function isSellable(): bool
    {
        return in_array($this->status, self::SELLABLE_STATUSES, true);
    }
}
