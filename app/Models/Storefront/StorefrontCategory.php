<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Storefront category — public commerce taxonomy.
 *
 * `kind` aligns a category to the listing source types it accepts and drives
 * navigation/filtering. The canonical kinds are exposed as constants so
 * controllers/services/seeders share one source of truth.
 */
class StorefrontCategory extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'storefront_categories';

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

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'slug',
        'kind',
        'parent_id',
        'icon',
        'description',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('display_order');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(StorefrontListing::class, 'category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('display_order')->orderBy('name');
    }
}
