<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Storefront listing — the sellable, public projection of an operational
 * record. See the migration docblock for the source-link / no-FK rationale.
 *
 * Lifecycle (status) is a deliberate state machine enforced in ListingService:
 *
 *   draft ──publish──▶ published ──unpublish──▶ unpublished
 *     │                    │  ▲                       │
 *     │                    │  └────── republish ──────┘
 *     └── archive ◀────────┴── archive ───────────────┘
 *
 *   published ──(source gone / sold via order)──▶ sold_out
 *   published ──(reserved by a confirmed order, Phase 3)──▶ reserved
 *
 * Only `published` listings are publicly visible AND orderable.
 */
class StorefrontListing extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $table = 'storefront_listings';

    // --- Kinds (mirror StorefrontCategory::KIND_*) ---
    public const KIND_LIVE_PIG       = 'live_pig';
    public const KIND_PIGLET_LOT     = 'piglet_lot';
    public const KIND_BREEDING_STOCK = 'breeding_stock';
    public const KIND_FEED           = 'feed';
    public const KIND_MANURE         = 'manure';
    public const KIND_SERVICE        = 'service';

    public const KINDS = [
        self::KIND_LIVE_PIG,
        self::KIND_PIGLET_LOT,
        self::KIND_BREEDING_STOCK,
        self::KIND_FEED,
        self::KIND_MANURE,
        self::KIND_SERVICE,
    ];

    // --- Sale modes ---
    public const MODE_FIXED  = 'fixed';
    public const MODE_QUOTE  = 'quote';
    public const MODE_HIDDEN = 'hidden';
    public const MODES = [self::MODE_FIXED, self::MODE_QUOTE, self::MODE_HIDDEN];

    // --- Pricing basis ---
    public const BASIS_LIVE    = 'live';
    public const BASIS_CARCASS = 'carcass';
    public const BASIS_FIXED   = 'fixed';
    public const BASES = [self::BASIS_LIVE, self::BASIS_CARCASS, self::BASIS_FIXED];

    // --- Status ---
    public const STATUS_DRAFT       = 'draft';
    public const STATUS_PUBLISHED   = 'published';
    public const STATUS_UNPUBLISHED = 'unpublished';
    public const STATUS_ARCHIVED    = 'archived';
    public const STATUS_SOLD_OUT    = 'sold_out';
    public const STATUS_RESERVED    = 'reserved';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_UNPUBLISHED,
        self::STATUS_ARCHIVED,
        self::STATUS_SOLD_OUT,
        self::STATUS_RESERVED,
    ];

    /** Kinds whose source is a single operational pig record (head-based). */
    public const PIG_BACKED_KINDS = [
        self::KIND_LIVE_PIG,
        self::KIND_BREEDING_STOCK,
    ];

    protected $fillable = [
        'organization_id',
        'category_id',
        'kind',
        'source_type',
        'source_id',
        'title',
        'slug',
        'reference',
        'public_description',
        'seller_note',
        'badges',
        'sale_mode',
        'pricing_basis',
        'price_per_kg',
        'fixed_price',
        'currency_code',
        'quantity_available',
        'source_category',
        'live_price_per_kg_snapshot',
        'carcass_price_per_kg_snapshot',
        'total_live_weight_kg_snapshot',
        'min_pig_weight_kg_snapshot',
        'max_pig_weight_kg_snapshot',
        'min_estimated_pig_price',
        'max_estimated_pig_price',
        'estimated_total_amount',
        'carcass_capacity_kg',
        'pricing_override',
        'pricing_source',
        'quantity_unit',
        'estimated_weight_kg',
        'sku',
        'pack_size',
        'pickup_allowed',
        'delivery_allowed',
        'delivery_fee',
        'fulfilment_notes',
        'status',
        'available_from',
        'available_until',
        'published_at',
        'view_count',
        'is_featured',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'badges'              => 'array',
            'price_per_kg'        => 'decimal:4',
            'fixed_price'         => 'decimal:2',
            'quantity_available'  => 'decimal:4',
            // Live/carcass pricing snapshots. Cast so a listing reads back the
            // same scale it was written with, whatever the engine returns.
            'live_price_per_kg_snapshot'    => 'decimal:4',
            'carcass_price_per_kg_snapshot' => 'decimal:4',
            'total_live_weight_kg_snapshot' => 'decimal:4',
            'min_pig_weight_kg_snapshot'    => 'decimal:4',
            'max_pig_weight_kg_snapshot'    => 'decimal:4',
            'min_estimated_pig_price'       => 'decimal:2',
            'max_estimated_pig_price'       => 'decimal:2',
            'estimated_total_amount'        => 'decimal:2',
            'carcass_capacity_kg'           => 'decimal:4',
            'pricing_override'              => 'boolean',
            'estimated_weight_kg' => 'decimal:4',
            'delivery_fee'        => 'decimal:2',
            'pickup_allowed'      => 'boolean',
            'delivery_allowed'    => 'boolean',
            'is_featured'         => 'boolean',
            'view_count'          => 'integer',
            'available_from'      => 'datetime',
            'available_until'     => 'datetime',
            'published_at'        => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(StorefrontCategory::class, 'category_id');
    }

    /** Soft polymorphic source (PigManagement, FeedType, ...). May be trashed/absent. */
    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    /**
     * Every pig ever linked to this listing, in any state.
     *
     * A pig-backed listing used to mean one animal, named by
     * `source_type`/`source_id`. It can now mean a pool: "Market-ready live
     * pigs — 5 head", from which an approver picks the exact animals when an
     * order is confirmed. `source_id` is still populated for single-pig
     * listings so nothing that reads it had to change.
     */
    public function listingPigs(): HasMany
    {
        return $this->hasMany(StorefrontListingPig::class, 'storefront_listing_id');
    }

    /**
     * Pool rows still on offer to a buyer.
     *
     * Excludes `allocated`: once an approver has committed an animal to an
     * order it is spoken for, even though its sale is not written yet.
     */
    public function availableListingPigs(): HasMany
    {
        return $this->listingPigs()->whereIn('status', StorefrontListingPig::SELLABLE_STATUSES);
    }

    public function allocatedListingPigs(): HasMany
    {
        return $this->listingPigs()->where('status', StorefrontListingPig::STATUS_ALLOCATED);
    }

    public function soldListingPigs(): HasMany
    {
        return $this->listingPigs()->where('status', StorefrontListingPig::STATUS_SOLD);
    }

    /**
     * Whether this listing draws from a pig pool at all.
     *
     * A pig-backed listing with no pool rows is a pre-existing single-pig
     * listing and keeps its old behaviour; the two must not be confused.
     */
    public function usesPigPool(): bool
    {
        return $this->listingPigs()->exists();
    }

    public function media(): HasMany
    {
        return $this->hasMany(StorefrontListingMedia::class, 'listing_id')->orderBy('sort_order');
    }

    public function primaryMedia(): HasMany
    {
        return $this->media()->where('is_primary', true);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(StorefrontPlacement::class, 'listing_id');
    }

    public function favourites(): HasMany
    {
        return $this->hasMany(StorefrontFavourite::class, 'listing_id');
    }

    public function isFavouritedBy(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return $this->favourites()->where('user_id', $userId)->exists();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeOfKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind);
    }

    public function scopeVisible(Builder $q): Builder
    {
        // Publicly visible: published, within availability window, not a
        // hidden sale mode.
        $now = now();

        return $q->where('status', self::STATUS_PUBLISHED)
            ->where('sale_mode', '!=', self::MODE_HIDDEN)
            ->where(function (Builder $sub) use ($now) {
                $sub->whereNull('available_from')->orWhere('available_from', '<=', $now);
            })
            ->where(function (Builder $sub) use ($now) {
                $sub->whereNull('available_until')->orWhere('available_until', '>=', $now);
            });
    }

    // ------------------------------------------------------------------
    // Domain helpers
    // ------------------------------------------------------------------

    public function isPigBacked(): bool
    {
        return in_array($this->kind, self::PIG_BACKED_KINDS, true);
    }

    public function isOrderable(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->sale_mode === self::MODE_FIXED;
    }

    public function isQuoteOnly(): bool
    {
        return $this->sale_mode === self::MODE_QUOTE;
    }

    /**
     * The unit display price for the listing. For live/carcass pricing this is
     * price_per_kg; for fixed pricing the fixed_price. Returned as a numeric
     * string to preserve precision for downstream bcmath.
     */
    public function displayPrice(): ?string
    {
        if ($this->pricing_basis === self::BASIS_FIXED) {
            return $this->fixed_price !== null ? (string) $this->fixed_price : null;
        }

        return $this->price_per_kg !== null ? (string) $this->price_per_kg : null;
    }

    /**
     * Estimated per-animal amount range for pooled pig listings.
     *
     * A pool is not one price: each animal carries a different latest weight,
     * so the public listing should say "from X to Y" until the buyer chooses
     * the exact animal.
     *
     * @return array{min:string,max:string}|null
     */
    public function displayPriceRangeBounds(): ?array
    {
        if (! $this->isPigBacked()
            || $this->min_estimated_pig_price === null
            || $this->max_estimated_pig_price === null) {
            return null;
        }

        return [
            'min' => (string) $this->min_estimated_pig_price,
            'max' => (string) $this->max_estimated_pig_price,
        ];
    }

    public function displayQuantityUnit(?string $quantity = null): string
    {
        $unit = strtolower((string) $this->quantity_unit);

        if ($unit === 'head') {
            $single = $quantity !== null && bccomp((string) $quantity, '1', 4) === 0;

            if ($this->kind === self::KIND_PIGLET_LOT) {
                return $single ? 'piglet' : 'piglets';
            }

            if ($this->isPigBacked()) {
                return $single ? 'pig' : 'pigs';
            }

            return 'stock';
        }

        return $this->quantity_unit ?: 'unit';
    }
}
