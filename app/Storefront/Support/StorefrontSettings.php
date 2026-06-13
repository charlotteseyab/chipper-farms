<?php

namespace App\Storefront\Support;

use App\Models\Setting;

/**
 * Typed accessor over the existing key/value Setting store (system_configs)
 * for the `storefront.*` namespace.
 *
 * We deliberately reuse the existing Setting model rather than introducing a
 * new storefront_settings table — it is the established pattern in this
 * codebase (sales.pricing.live_weight_price, finance.use_invoices_for_pig_sales,
 * etc.), keeps the change additive, and means storefront config is visible in
 * the same admin Settings surface as everything else.
 *
 * Default pig pricing intentionally falls back to the SAME settings the
 * operational sale path reads (sales.pricing.*), so a listing's "default from
 * system settings" price matches what a recorded sale would charge.
 */
class StorefrontSettings
{
    public const KEY_ENABLED            = 'storefront.enabled';
    public const KEY_STORE_NAME         = 'storefront.store_name';
    public const KEY_SUPPORT_EMAIL      = 'storefront.support_email';
    public const KEY_SUPPORT_PHONE      = 'storefront.support_phone';
    public const KEY_DEFAULT_CURRENCY   = 'storefront.default_currency';
    public const KEY_PICKUP_ADDRESS     = 'storefront.pickup_address';
    public const KEY_DELIVERY_ENABLED   = 'storefront.delivery_enabled';
    public const KEY_HERO_HEADLINE      = 'storefront.hero_headline';
    public const KEY_HERO_SUBHEADLINE   = 'storefront.hero_subheadline';

    // Operational pricing keys reused as listing defaults.
    public const KEY_LIVE_PRICE    = 'sales.pricing.live_weight_price';
    public const KEY_CARCASS_PRICE = 'sales.pricing.carcass_weight_price';

    public static function enabled(): bool
    {
        return (bool) Setting::get(self::KEY_ENABLED, true);
    }

    public static function storeName(): string
    {
        return (string) Setting::get(self::KEY_STORE_NAME, config('app.name', 'Chipper Farms'));
    }

    public static function supportEmail(): ?string
    {
        $v = Setting::get(self::KEY_SUPPORT_EMAIL);
        return $v !== null ? (string) $v : null;
    }

    public static function supportPhone(): ?string
    {
        $v = Setting::get(self::KEY_SUPPORT_PHONE);
        return $v !== null ? (string) $v : null;
    }

    public static function defaultCurrency(): string
    {
        return (string) Setting::get(self::KEY_DEFAULT_CURRENCY, 'GHS');
    }

    public static function pickupAddress(): ?string
    {
        $v = Setting::get(self::KEY_PICKUP_ADDRESS);
        return $v !== null ? (string) $v : null;
    }

    public static function deliveryEnabled(): bool
    {
        return (bool) Setting::get(self::KEY_DELIVERY_ENABLED, false);
    }

    public static function heroHeadline(): string
    {
        return (string) Setting::get(self::KEY_HERO_HEADLINE, 'Quality pigs, piglets & feed — straight from the farm.');
    }

    public static function heroSubheadline(): string
    {
        return (string) Setting::get(
            self::KEY_HERO_SUBHEADLINE,
            'Order live pigs, breeding stock, feed and more for pickup or local delivery.'
        );
    }

    /**
     * Default unit price (numeric string) for a listing of the given pricing
     * basis, sourced from system settings. Returns null for a fixed-price
     * listing where there is no system default.
     */
    public static function defaultPriceForBasis(string $basis): ?string
    {
        return match ($basis) {
            'live'    => (string) (float) Setting::get(self::KEY_LIVE_PRICE, 30.00),
            'carcass' => (string) (float) Setting::get(self::KEY_CARCASS_PRICE, 50.00),
            default   => null,
        };
    }
}
