<?php

namespace App\Support\Features;

/**
 * Module presets (Phase 1, blueprint §33).
 *
 * A preset is a named bundle of feature states an administrator can apply in
 * one action, so a farm does not have to reason about twenty toggles to get a
 * sensible starting configuration.
 *
 * PRESETS ARE ADDITIVE-BY-DEFAULT, NOT DESTRUCTIVE
 * -------------------------------------------------
 * `enables()` lists what a preset turns ON. Applying a preset does not
 * implicitly turn everything else off, because that would make "apply
 * Storefront Seller" silently destroy an accountant's Advanced Accounting
 * setup. The service exposes an explicit `$exclusive` flag for callers that
 * genuinely want a reset, and the settings UI must confirm before using it.
 *
 * That asymmetry is deliberate: the blueprint's compatibility strategy (§53)
 * says implementation must be additive and must not unexpectedly hide
 * functionality for current customers.
 *
 * STOREFRONT SELLER AND PROCUREMENT ADVANCED ARE OVERLAYS
 * -------------------------------------------------------
 * Essentials, Growing Farm and Full Accounting form a ladder — each is a
 * superset of the previous. Storefront Seller and Procurement Advanced are not
 * rungs on that ladder; they are overlays that can be applied on top of any
 * rung. Treating them as ladder steps would mean a farm that sells online has
 * to give up bank reconciliation, which is nonsense.
 */
final class ModulePreset
{
    public const ESSENTIALS           = 'essentials';
    public const GROWING_FARM         = 'growing_farm';
    public const FULL_ACCOUNTING      = 'full_accounting';
    public const STOREFRONT_SELLER    = 'storefront_seller';
    public const PROCUREMENT_ADVANCED = 'procurement_advanced';

    /**
     * @return array<string, array{label:string, description:string, overlay:bool, enables:list<string>}>
     */
    public static function all(): array
    {
        $essentials = [
            FeatureCatalog::FINANCE_OVERVIEW,
            FeatureCatalog::FINANCE_QUICK_EXPENSE,
            FeatureCatalog::FINANCE_SIMPLE_PURCHASE,
            FeatureCatalog::FINANCE_SALES_MONEY_IN,
            FeatureCatalog::FINANCE_CASH_BANK_MOMO,
            FeatureCatalog::FINANCE_LOANS,
            FeatureCatalog::FINANCE_CONTACTS,
            FeatureCatalog::FINANCE_REPORTS,
            FeatureCatalog::STOREFRONT_ENABLED,
            FeatureCatalog::STOREFRONT_LIVE_PIGS,
            FeatureCatalog::STOREFRONT_PIGLETS,
            FeatureCatalog::STOREFRONT_BREEDING_STOCK,
            FeatureCatalog::STOREFRONT_FEED_SALES,
            FeatureCatalog::STOREFRONT_QUOTES,
            FeatureCatalog::STOREFRONT_GUEST_CHECKOUT,
            FeatureCatalog::STOREFRONT_PAYSTACK,
            FeatureCatalog::STOREFRONT_COD,
            FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS,
        ];

        $growing = array_merge($essentials, [
            FeatureCatalog::FINANCE_ADVANCED_AP,
            FeatureCatalog::FINANCE_BANK_RECONCILIATION,
            FeatureCatalog::FINANCE_CUSTOMER_CREDIT,
            FeatureCatalog::FINANCE_ADVANCED_TREASURY,
        ]);

        $full = array_merge($growing, [
            FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING,
            FeatureCatalog::FINANCE_PERIOD_CLOSE,
            FeatureCatalog::FINANCE_WHT,
            FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT,
            FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL,
        ]);

        return [
            self::ESSENTIALS => [
                'label'       => 'Essentials',
                'description' => 'For a small farm running on cash. Everyday money in and out, '
                    . 'a simple shop, and no accounting jargon.',
                'overlay'     => false,
                'enables'     => array_values(array_unique($essentials)),
            ],
            self::GROWING_FARM => [
                'label'       => 'Growing Farm',
                'description' => 'Adds supplier credit, bank reconciliation and customer credit '
                    . 'for a farm with more staff and more suppliers.',
                'overlay'     => false,
                'enables'     => array_values(array_unique($growing)),
            ],
            self::FULL_ACCOUNTING => [
                'label'       => 'Full Accounting',
                'description' => 'Everything, including the general ledger, journals, period close, '
                    . 'withholding tax and biological asset reporting. For an accountant-led farm.',
                'overlay'     => false,
                'enables'     => array_values(array_unique($full)),
            ],
            self::STOREFRONT_SELLER => [
                'label'       => 'Storefront Seller',
                'description' => 'Adds the full online selling surface: manure, services and delivery '
                    . 'on top of whichever finance preset you are using.',
                'overlay'     => true,
                'enables'     => [
                    FeatureCatalog::STOREFRONT_ENABLED,
                    FeatureCatalog::STOREFRONT_LIVE_PIGS,
                    FeatureCatalog::STOREFRONT_PIGLETS,
                    FeatureCatalog::STOREFRONT_BREEDING_STOCK,
                    FeatureCatalog::STOREFRONT_FEED_SALES,
                    FeatureCatalog::STOREFRONT_MANURE_SALES,
                    FeatureCatalog::STOREFRONT_SERVICES,
                    FeatureCatalog::STOREFRONT_QUOTES,
                    FeatureCatalog::STOREFRONT_GUEST_CHECKOUT,
                    FeatureCatalog::STOREFRONT_PAYSTACK,
                    FeatureCatalog::STOREFRONT_COD,
                    FeatureCatalog::STOREFRONT_DELIVERY,
                    FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS,
                ],
            ],
            self::PROCUREMENT_ADVANCED => [
                'label'       => 'Procurement Advanced',
                'description' => 'Adds formal purchase requests, approvals, goods receiving and '
                    . 'vendor invoice matching.',
                'overlay'     => true,
                'enables'     => [
                    FeatureCatalog::FINANCE_SIMPLE_PURCHASE,
                    FeatureCatalog::FINANCE_PROCUREMENT_APPROVALS,
                    FeatureCatalog::FINANCE_ADVANCED_AP,
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function isKnown(string $code): bool
    {
        return array_key_exists($code, self::all());
    }

    public static function assertKnown(string $code): void
    {
        if (! self::isKnown($code)) {
            throw new \InvalidArgumentException("Unknown module preset [{$code}].");
        }
    }

    /** @return array{label:string, description:string, overlay:bool, enables:list<string>} */
    public static function definition(string $code): array
    {
        self::assertKnown($code);

        return self::all()[$code];
    }

    /** @return list<string> */
    public static function enables(string $code): array
    {
        return self::definition($code)['enables'];
    }

    public static function isOverlay(string $code): bool
    {
        return self::definition($code)['overlay'];
    }
}
