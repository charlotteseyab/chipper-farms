<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Homepage / front-end placement slot.
 *
 * Each row pins a listing into a named slot (code) at a position, optionally
 * time-boxed. The canonical slot codes are exposed as constants so the admin
 * UI and the public renderer agree.
 */
class StorefrontPlacement extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'storefront_placements';

    public const CODE_HOME_HERO       = 'featured_home_hero';
    public const CODE_LIVE_PIGS       = 'featured_live_pigs';
    public const CODE_BREEDING_STOCK  = 'featured_breeding_stock';
    public const CODE_FEED            = 'featured_feed';
    public const CODE_MANURE          = 'featured_manure';
    public const CODE_BULK_ORDERS     = 'featured_bulk_orders';
    public const CODE_NEW_ARRIVALS    = 'featured_new_arrivals';
    public const CODE_BEST_SELLERS    = 'featured_best_sellers';

    public const CODES = [
        self::CODE_HOME_HERO,
        self::CODE_LIVE_PIGS,
        self::CODE_BREEDING_STOCK,
        self::CODE_FEED,
        self::CODE_MANURE,
        self::CODE_BULK_ORDERS,
        self::CODE_NEW_ARRIVALS,
        self::CODE_BEST_SELLERS,
    ];

    public const LABELS = [
        self::CODE_HOME_HERO      => 'Homepage spotlight',
        self::CODE_LIVE_PIGS      => 'Featured live pigs',
        self::CODE_BREEDING_STOCK => 'Featured breeding stock',
        self::CODE_FEED           => 'Feed & supplies',
        self::CODE_MANURE         => 'Featured manure',
        self::CODE_BULK_ORDERS    => 'Bulk orders',
        self::CODE_NEW_ARRIVALS   => 'New arrivals',
        self::CODE_BEST_SELLERS   => 'Best sellers',
    ];

    public const DESCRIPTIONS = [
        self::CODE_HOME_HERO      => 'The main product spotlight beside the storefront introduction. One listing is shown.',
        self::CODE_LIVE_PIGS      => 'Live-pig listings promoted in their own homepage section.',
        self::CODE_BREEDING_STOCK => 'Breeding-stock listings promoted in their own homepage section.',
        self::CODE_FEED           => 'Feed listings promoted in their own homepage section.',
        self::CODE_MANURE         => 'Manure listings promoted in their own homepage section.',
        self::CODE_BULK_ORDERS    => 'Listings suitable for larger or quote-based purchases.',
        self::CODE_NEW_ARRIVALS   => 'Recently added products selected by the storefront team.',
        self::CODE_BEST_SELLERS   => 'Popular products selected by the storefront team.',
    ];

    protected $fillable = [
        'organization_id',
        'code',
        'listing_id',
        'position',
        'is_active',
        'starts_at',
        'ends_at',
        'note',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'position'  => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StorefrontListing::class, 'listing_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeForCode(Builder $q, string $code): Builder
    {
        return $q->where('code', $code);
    }

    public function scopeActiveNow(Builder $q): Builder
    {
        $now = now();

        return $q->where('is_active', true)
            ->where(function (Builder $sub) use ($now) {
                $sub->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $sub) use ($now) {
                $sub->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('position');
    }
}
