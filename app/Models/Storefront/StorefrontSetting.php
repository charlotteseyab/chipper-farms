<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Finance\TaxProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-organisation storefront configuration (Phase 8, blueprint §34).
 *
 * One row per organisation, or none. An absent row is "not configured" and
 * resolves to the code defaults in {@see \App\Storefront\Support\StorefrontSettings};
 * a farm that has never opened the settings screen still has a working shop.
 *
 * This holds values only. The on/off switches — store enabled, payment methods,
 * public categories, delivery, guest checkout — are feature flags, so that one
 * behaviour has exactly one switch.
 */
class StorefrontSetting extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'storefront_settings';

    public const TAX_DISPLAY_EXCLUSIVE = 'exclusive';
    public const TAX_DISPLAY_INCLUSIVE = 'inclusive';

    public const TAX_DISPLAY_MODES = [
        self::TAX_DISPLAY_EXCLUSIVE,
        self::TAX_DISPLAY_INCLUSIVE,
    ];

    /**
     * Bounds on the checkout hold, enforced in the request class and repeated
     * here so anything writing the model directly shares one definition.
     *
     * A hold under a minute expires while the buyer is still on the Paystack
     * page; one over a day parks stock nobody is buying.
     */
    public const MIN_HOLD_MINUTES = 5;
    public const MAX_HOLD_MINUTES = 1440;

    protected $fillable = [
        'organization_id',
        'public_shop_open',
        'store_name',
        'support_email',
        'support_phone',
        'default_currency',
        'pickup_address',
        'hero_headline',
        'hero_subheadline',
        'tax_display_mode',
        'default_tax_profile_id',
        'checkout_hold_minutes',
        'refund_policy',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'public_shop_open'      => 'boolean',
            'checkout_hold_minutes' => 'integer',
        ];
    }

    public function defaultTaxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class, 'default_tax_profile_id');
    }

    /**
     * Invalidate the resolver's per-request cache whenever a row changes.
     *
     * `StorefrontSettingsResolver::save()` already does this for its own
     * writes, but seeders, console tooling and tests write the model directly.
     * Without this a caller that reads settings, writes them, then reads again
     * inside one request gets its first answer twice — and that is exactly the
     * shape of bug that is invisible in production (one request, one container)
     * and shows up only later, in something harder to diagnose.
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
}
