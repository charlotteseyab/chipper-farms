<?php

namespace App\Storefront\Support;

use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;

/**
 * What this organisation actually sells, and how it will take the money
 * (Phase 8, blueprint §34 and §59 acceptance: "storefront settings control
 * public category/payment/delivery behaviour").
 *
 * WHY THIS CLASS EXISTS
 * ---------------------
 * Phase 1 added thirteen `storefront.*` feature flags and, before this phase,
 * **not one of them was enforced anywhere**. A farm could switch Manure Sales
 * off and keep selling manure; switch Paystack off and keep taking card
 * payments; switch the whole store off and stay open. The flags described a
 * configuration the application did not honour, which is worse than having no
 * flags at all — an administrator reads the screen and believes it.
 *
 * The mapping from a catalogue concept to its flag lives here, once, so the
 * public shop, the listing detail page, the cart and the checkout cannot
 * disagree about what is on sale.
 *
 * THIS IS NOT AN AUTHORIZATION CONTROL
 * ------------------------------------
 * Same rule as everywhere else in this codebase: a disabled kind is not
 * offered, but what protects a listing is its organisation scope and the
 * permission on the route that reaches it. Nothing here replaces either.
 */
final class StorefrontCatalog
{
    /**
     * Listing/category kind → the feature flag that governs it.
     *
     * Keyed by the kind constants, which `StorefrontListing` and
     * `StorefrontCategory` already share.
     */
    public const KIND_FEATURES = [
        StorefrontListing::KIND_LIVE_PIG       => FeatureCatalog::STOREFRONT_LIVE_PIGS,
        StorefrontListing::KIND_PIGLET_LOT     => FeatureCatalog::STOREFRONT_PIGLETS,
        StorefrontListing::KIND_BREEDING_STOCK => FeatureCatalog::STOREFRONT_BREEDING_STOCK,
        StorefrontListing::KIND_FEED           => FeatureCatalog::STOREFRONT_FEED_SALES,
        StorefrontListing::KIND_MANURE         => FeatureCatalog::STOREFRONT_MANURE_SALES,
        StorefrontListing::KIND_SERVICE        => FeatureCatalog::STOREFRONT_SERVICES,
    ];

    /**
     * Payment method → the feature flag that governs it.
     */
    public const PAYMENT_FEATURES = [
        StorefrontOrder::METHOD_PAYSTACK => FeatureCatalog::STOREFRONT_PAYSTACK,
        StorefrontOrder::METHOD_COD      => FeatureCatalog::STOREFRONT_COD,
    ];

    public static function isKindEnabled(string $kind, ?int $organizationId = null): bool
    {
        $feature = self::KIND_FEATURES[$kind] ?? null;

        // An unmapped kind is not silently allowed. If a seventh kind is added
        // to StorefrontListing without a flag, it stays off the public shop
        // until someone decides which flag governs it — a missing listing is a
        // recoverable oversight, a silently-public one is not.
        if ($feature === null) {
            return false;
        }

        return self::features()->usable($feature, $organizationId ?? self::organizationId());
    }

    /**
     * Every kind this organisation may show publicly.
     *
     * @return list<string>
     */
    public static function enabledKinds(?int $organizationId = null): array
    {
        $organizationId ??= self::organizationId();

        return array_values(array_filter(
            StorefrontListing::KINDS,
            fn (string $kind): bool => self::isKindEnabled($kind, $organizationId),
        ));
    }

    public static function isPaymentMethodEnabled(string $method, ?int $organizationId = null): bool
    {
        $feature = self::PAYMENT_FEATURES[$method] ?? null;

        if ($feature === null) {
            return false;
        }

        return self::features()->usable($feature, $organizationId ?? self::organizationId());
    }

    /**
     * The payment methods a buyer may actually choose.
     *
     * May legitimately be empty: a farm that turns off both Paystack and COD
     * has no way to take an order. The checkout page says so rather than
     * presenting an empty radio group, and `CheckoutService` refuses the order
     * — blueprint §46, no dead ends.
     *
     * @return list<string>
     */
    public static function enabledPaymentMethods(?int $organizationId = null): array
    {
        $organizationId ??= self::organizationId();

        return array_values(array_filter(
            array_keys(self::PAYMENT_FEATURES),
            fn (string $method): bool => self::isPaymentMethodEnabled($method, $organizationId),
        ));
    }

    /**
     * The fulfilment methods a buyer may choose.
     *
     * Pickup is always available: a farm that cannot deliver can still be
     * collected from, and removing both would leave a shop that can display
     * stock and never sell it.
     *
     * @return list<string>
     */
    public static function enabledFulfilmentMethods(?int $organizationId = null): array
    {
        $methods = [StorefrontOrder::FULFIL_PICKUP];

        if (StorefrontSettings::deliveryEnabled($organizationId ?? self::organizationId())) {
            $methods[] = StorefrontOrder::FULFIL_DELIVERY;
        }

        return $methods;
    }

    /**
     * Constrain a listing query to the publicly-sellable kinds.
     *
     * `whereIn` on an empty list matches nothing, which is the correct answer
     * when an organisation has disabled every category: the shop is empty, not
     * unfiltered. Written explicitly so nobody later "fixes" it into an
     * unfiltered query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<StorefrontListing>  $query
     */
    public static function constrainToEnabledKinds($query, ?int $organizationId = null)
    {
        return $query->whereIn('kind', self::enabledKinds($organizationId));
    }

    /**
     * Categories an organisation may show publicly.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<StorefrontCategory>  $query
     */
    public static function constrainCategoriesToEnabledKinds($query, ?int $organizationId = null)
    {
        return $query->whereIn('kind', self::enabledKinds($organizationId));
    }

    protected static function organizationId(): ?int
    {
        return StorefrontSettings::organizationId();
    }

    protected static function features(): OrganizationFeatureService
    {
        return app(OrganizationFeatureService::class);
    }
}
