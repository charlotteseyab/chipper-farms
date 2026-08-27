<?php

namespace App\Storefront\Support;

use App\Models\Finance\TaxProfile;
use App\Models\Setting;
use App\Models\Storefront\StorefrontDeliveryZone;
use App\Models\Storefront\StorefrontSetting;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Support\Collection;

/**
 * Typed, organisation-scoped accessor for storefront configuration.
 *
 * WHAT CHANGED IN PHASE 8
 * -----------------------
 * This class used to read the `storefront.*` namespace out of `system_configs`,
 * a table with no `organization_id`. Every farm on the installation therefore
 * shared one store name, one support email and one pickup address, and the
 * defect had no upper bound — the same table prices real sales through
 * `sales.pricing.*` (IMP-001).
 *
 * Values now come from `storefront_settings`, one row per organisation, through
 * {@see StorefrontSettingsResolver}. The static shape is kept because a dozen
 * Blade views call it, and because "the current store's name" is genuinely
 * ambient in a public page — but every call now resolves an organisation first.
 *
 * WHERE THE SWITCHES LIVE
 * -----------------------
 * `enabled()`, `deliveryEnabled()`, `guestCheckoutEnabled()` and the payment
 * and category questions read **feature flags**, not this table. Blueprint §34
 * lists them as settings and §32 lists them as flags; implementing both would
 * give one behaviour two switches, and an administrator who turns COD on in one
 * place and finds it still off in the other. The settings screen writes the
 * flags. This class reads them, so callers do not have to know which store a
 * given question is answered from.
 *
 * The default pig pricing accessor still reads the global `sales.pricing.*`
 * keys. That is deliberate and unchanged: those settings drive the operational
 * sale path too, and a listing's "default from system settings" price must
 * match what a recorded sale would charge. Splitting them is the rest of
 * IMP-001 and belongs with the operational pricing work, not here.
 */
class StorefrontSettings
{
    // Retained so anything still reading the legacy global keys — and the
    // migration that backfills from them — names them from one place.
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

    public const DEFAULT_HERO_HEADLINE    = 'Quality pigs, piglets & feed — straight from the farm.';
    public const DEFAULT_HERO_SUBHEADLINE = 'Order live pigs, breeding stock, feed and more for pickup or local delivery.';

    /* ------------------------------------------------------------------ */
    /* Switches — answered by feature flags                               */
    /* ------------------------------------------------------------------ */

    /**
     * Is the public shop open for business?
     *
     * Before Phase 8 this read a global setting that nothing consulted, so a
     * farm could switch its store "off" and stay open. Two things now have to
     * be true, and they are different questions:
     *
     *   - `storefront.enabled` — does this organisation have a storefront at
     *     all. Owned by the Modules screen. Turning it off also removes the
     *     back-office workspace.
     *   - `public_shop_open`   — is the shop currently accepting visitors.
     *     Owned by the storefront settings screen; the seasonal open/closed
     *     sign, which leaves every other choice intact.
     *
     * `EnsureStorefrontOpen` enforces the pair on the public routes and
     * `CheckoutService` enforces it again before taking an order.
     */
    public static function enabled(?int $organizationId = null): bool
    {
        $organizationId ??= self::organizationId();

        if (! self::features()->enabled(FeatureCatalog::STOREFRONT_ENABLED, $organizationId)) {
            return false;
        }

        return (bool) self::row($organizationId)->public_shop_open;
    }

    /**
     * Whether the organisation has the storefront module at all, ignoring the
     * open/closed sign. The settings screen needs to tell an administrator
     * "your shop is closed" apart from "you do not have a shop".
     */
    public static function moduleEnabled(?int $organizationId = null): bool
    {
        return self::features()->enabled(FeatureCatalog::STOREFRONT_ENABLED, $organizationId ?? self::organizationId());
    }

    public static function publicShopOpen(?int $organizationId = null): bool
    {
        return (bool) self::row($organizationId)->public_shop_open;
    }

    public static function deliveryEnabled(?int $organizationId = null): bool
    {
        return self::features()->usable(FeatureCatalog::STOREFRONT_DELIVERY, $organizationId ?? self::organizationId());
    }

    public static function guestCheckoutEnabled(?int $organizationId = null): bool
    {
        return self::features()->usable(FeatureCatalog::STOREFRONT_GUEST_CHECKOUT, $organizationId ?? self::organizationId());
    }

    public static function quotesEnabled(?int $organizationId = null): bool
    {
        return self::features()->usable(FeatureCatalog::STOREFRONT_QUOTES, $organizationId ?? self::organizationId());
    }

    /**
     * Whether the farm offers customer accounts (Phase 16).
     *
     * Governs account self-service only. A customer's order history stays
     * reachable either way — see `EnsureCustomerAccountsEnabled`.
     */
    public static function customerAccountsEnabled(?int $organizationId = null): bool
    {
        return self::features()->usable(
            FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS,
            $organizationId ?? self::organizationId(),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Values — answered by storefront_settings                           */
    /* ------------------------------------------------------------------ */

    public static function storeName(?int $organizationId = null): string
    {
        $name = self::row($organizationId)->store_name;

        return $name !== null && trim($name) !== ''
            ? $name
            : (string) config('app.name', 'Chipper Farms');
    }

    public static function supportEmail(?int $organizationId = null): ?string
    {
        return self::nullIfBlank(self::row($organizationId)->support_email);
    }

    public static function supportPhone(?int $organizationId = null): ?string
    {
        return self::nullIfBlank(self::row($organizationId)->support_phone);
    }

    public static function defaultCurrency(?int $organizationId = null): string
    {
        return self::nullIfBlank(self::row($organizationId)->default_currency) ?? 'GHS';
    }

    public static function pickupAddress(?int $organizationId = null): ?string
    {
        return self::nullIfBlank(self::row($organizationId)->pickup_address);
    }

    public static function heroHeadline(?int $organizationId = null): string
    {
        return self::nullIfBlank(self::row($organizationId)->hero_headline) ?? self::DEFAULT_HERO_HEADLINE;
    }

    public static function heroSubheadline(?int $organizationId = null): string
    {
        return self::nullIfBlank(self::row($organizationId)->hero_subheadline) ?? self::DEFAULT_HERO_SUBHEADLINE;
    }

    public static function refundPolicy(?int $organizationId = null): ?string
    {
        return self::nullIfBlank(self::row($organizationId)->refund_policy);
    }

    /**
     * 'exclusive' — prices are shown before tax and tax is added at checkout.
     * 'inclusive' — the displayed price already contains it.
     *
     * Presentation only. What a confirmed order actually posts is decided by
     * the tax profile, never by this.
     */
    public static function taxDisplayMode(?int $organizationId = null): string
    {
        $mode = self::row($organizationId)->tax_display_mode;

        return in_array($mode, StorefrontSetting::TAX_DISPLAY_MODES, true)
            ? $mode
            : StorefrontSetting::TAX_DISPLAY_EXCLUSIVE;
    }

    public static function taxInclusive(?int $organizationId = null): bool
    {
        return self::taxDisplayMode($organizationId) === StorefrontSetting::TAX_DISPLAY_INCLUSIVE;
    }

    public static function defaultTaxProfile(?int $organizationId = null): ?TaxProfile
    {
        $id = self::row($organizationId)->default_tax_profile_id;

        return $id === null ? null : TaxProfile::query()->find($id);
    }

    /**
     * How long a checkout hold survives, in minutes (consumed by Phase 10).
     */
    public static function checkoutHoldMinutes(?int $organizationId = null): int
    {
        $minutes = (int) (self::row($organizationId)->checkout_hold_minutes ?? 30);

        return max(StorefrontSetting::MIN_HOLD_MINUTES, min(StorefrontSetting::MAX_HOLD_MINUTES, $minutes));
    }

    /**
     * @return Collection<int, StorefrontDeliveryZone>
     */
    public static function deliveryZones(?int $organizationId = null): Collection
    {
        return self::resolver()->zones($organizationId);
    }

    /* ------------------------------------------------------------------ */
    /* Paystack health — configuration status, never the secret           */
    /* ------------------------------------------------------------------ */

    /**
     * Whether Paystack is configured, and in which mode, for display on the
     * settings screen.
     *
     * Returns booleans and a mode string. It does NOT return, log or mask the
     * keys themselves: a screen that renders `sk_live_••••1234` has still put
     * the last four characters of a live secret into a browser, a proxy log and
     * a support screenshot. Blueprint §34: "config health display only, never
     * expose secret".
     *
     * @return array{configured: bool, mode: ?string, has_public_key: bool, base_url: string}
     */
    public static function paystackHealth(): array
    {
        $secret = (string) config('services.paystack.secret_key', '');
        $public = (string) config('services.paystack.public_key', '');

        $mode = null;
        if (str_starts_with($secret, 'sk_live_')) {
            $mode = 'live';
        } elseif (str_starts_with($secret, 'sk_test_')) {
            $mode = 'test';
        } elseif ($secret !== '') {
            $mode = 'unknown';
        }

        return [
            'configured'     => $secret !== '',
            'mode'           => $mode,
            'has_public_key' => $public !== '',
            'base_url'       => (string) config('services.paystack.base_url', ''),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Listing price defaults (unchanged — see the class docblock)        */
    /* ------------------------------------------------------------------ */

    /**
     * Default unit price (numeric string) for a listing of the given pricing
     * basis, sourced from system settings. Returns null for a fixed-price
     * listing where there is no system default.
     */
    public static function defaultPriceForBasis(string $basis): ?string
    {
        return match ($basis) {
            'live'    => (string) (float) Setting::get(self::KEY_LIVE_PRICE, 0),
            'carcass' => (string) (float) Setting::get(self::KEY_CARCASS_PRICE, 0),
            default   => null,
        };
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                          */
    /* ------------------------------------------------------------------ */

    public static function organizationId(): ?int
    {
        return self::resolver()->organizationId();
    }

    protected static function row(?int $organizationId): StorefrontSetting
    {
        return self::resolver()->for($organizationId);
    }

    protected static function resolver(): StorefrontSettingsResolver
    {
        return app(StorefrontSettingsResolver::class);
    }

    protected static function features(): OrganizationFeatureService
    {
        return app(OrganizationFeatureService::class);
    }

    protected static function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
