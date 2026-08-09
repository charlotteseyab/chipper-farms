<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A named delivery area and what it costs (Phase 8, blueprint §34).
 *
 * WHY THIS EXISTS ALONGSIDE THE PER-LISTING FEE
 * ---------------------------------------------
 * `storefront_listings.delivery_fee` charges by *what* is bought. A farm
 * delivering the same pig to the next village or to Kumasi pays very different
 * money for the same animal, so the real driver is *where* it goes.
 *
 * The two must not both charge, or a delivery to Kumasi costs the zone fee plus
 * a per-listing fee for every animal in the order. The rule, implemented in
 * `CheckoutService` and stated on the settings screen, is: when an organisation
 * has active zones the buyer picks one and its fee is the delivery fee; when it
 * has none, the per-listing fees apply exactly as before. One or the other,
 * never both.
 */
class StorefrontDeliveryZone extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'storefront_delivery_zones';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'fee',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /**
     * Same reasoning as `StorefrontSetting::booted()`: any writer invalidates
     * the resolver's cached zone list, not just the settings controller.
     */
    protected static function booted(): void
    {
        $forget = static function (self $model): void {
            app(\App\Storefront\Support\StorefrontSettingsResolver::class)
                ->forget($model->organization_id === null ? null : (int) $model->organization_id);
        };

        static::saved($forget);
        static::deleted($forget);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('display_order')->orderBy('name');
    }

    /**
     * The fee as a decimal string. Money never leaves this model as a float —
     * `bcadd` on a float that has already lost precision cannot recover it.
     */
    public function feeString(): string
    {
        return bcadd((string) $this->fee, '0', 2);
    }
}
