<?php

namespace App\Support\Features;

/**
 * The canonical list of organisation features (Phase 1, blueprint §16/§18/§32).
 *
 * WHY THIS IS CODE AND NOT DATA
 * -----------------------------
 * A feature key is referenced by routes, middleware, navigation and tests. If
 * the catalogue lived in the database, a typo in a seeder would silently
 * disable a module in production and nothing would fail at boot. Keeping it as
 * code means `FeatureCatalog::assertKnown()` can reject an unknown key the
 * moment anyone tries to toggle it.
 *
 * ONLY FEATURES WITH REAL IMPLEMENTATIONS APPEAR HERE
 * ---------------------------------------------------
 * The implementation prompt is explicit: "Do not invent non-functional modules
 * just to have toggles." Every key below was checked against the live route
 * table before being added. The prompt's suggested list also mentioned
 * multi-currency, budgets and e-VAT; none of those has any implementation in
 * this codebase, so none of them is here. They can be added when the feature
 * they gate exists.
 *
 * DEFAULTS ARE FOR *NEW* ORGANISATIONS ONLY
 * ------------------------------------------
 * `default` is what a brand-new organisation gets. Existing organisations are
 * backfilled to Full Accounting by migration so that nothing a current customer
 * relies on disappears at deploy time (blueprint §53).
 *
 * A FEATURE FLAG IS NOT AN AUTHORIZATION CONTROL
 * -----------------------------------------------
 * Blueprint §32 and prompt §11 both say this, and it is worth repeating where
 * the keys are defined: access requires feature enabled AND permission granted
 * AND organisation scope valid. Disabling a feature hides a surface; it does
 * not protect it. Permissions and policies remain authoritative.
 */
final class FeatureCatalog
{
    // Advanced features are the paid add-ons. Essential ones are free; some
    // (manure, services, delivery in the shop) are free but start off.
    public const CATEGORY_ESSENTIAL = 'essential';
    public const CATEGORY_ADVANCED  = 'advanced';

    public const DOMAIN_FINANCE    = 'finance';
    public const DOMAIN_STOREFRONT = 'storefront';

    /* ---------------------------------------------------------------- */
    /* Finance — essentials                                             */
    /* ---------------------------------------------------------------- */

    public const FINANCE_OVERVIEW        = 'finance.overview';
    public const FINANCE_QUICK_EXPENSE   = 'finance.quick_expense';
    public const FINANCE_SIMPLE_PURCHASE = 'finance.simple_purchase';
    public const FINANCE_SALES_MONEY_IN  = 'finance.sales_money_in';
    public const FINANCE_CASH_BANK_MOMO  = 'finance.cash_bank_momo';
    public const FINANCE_LOANS           = 'finance.loans';
    public const FINANCE_CONTACTS        = 'finance.contacts';
    public const FINANCE_REPORTS         = 'finance.reports';

    /* ---------------------------------------------------------------- */
    /* Finance — advanced                                               */
    /* ---------------------------------------------------------------- */

    public const FINANCE_ADVANCED_ACCOUNTING   = 'finance.advanced_accounting';
    public const FINANCE_BANK_RECONCILIATION   = 'finance.bank_reconciliation';
    public const FINANCE_PROCUREMENT_APPROVALS = 'finance.procurement_approvals';
    public const FINANCE_ADVANCED_AP           = 'finance.advanced_ap';
    public const FINANCE_WHT                   = 'finance.wht';
    public const FINANCE_INVESTOR_MANAGEMENT   = 'finance.investor_management';
    public const FINANCE_ADVANCED_BIOLOGICAL   = 'finance.advanced_biological_accounting';
    public const FINANCE_ADVANCED_TREASURY     = 'finance.advanced_treasury';
    public const FINANCE_PERIOD_CLOSE          = 'finance.period_close';
    public const FINANCE_CUSTOMER_CREDIT       = 'finance.customer_credit';

    /* ---------------------------------------------------------------- */
    /* Storefront                                                       */
    /* ---------------------------------------------------------------- */

    public const STOREFRONT_ENABLED         = 'storefront.enabled';
    public const STOREFRONT_LIVE_PIGS       = 'storefront.live_pigs';
    public const STOREFRONT_PIGLETS         = 'storefront.piglets';
    public const STOREFRONT_BREEDING_STOCK  = 'storefront.breeding_stock';
    public const STOREFRONT_FEED_SALES      = 'storefront.feed_sales';
    public const STOREFRONT_MANURE_SALES    = 'storefront.manure_sales';
    public const STOREFRONT_SERVICES        = 'storefront.services';
    public const STOREFRONT_QUOTES          = 'storefront.quotes';
    public const STOREFRONT_GUEST_CHECKOUT  = 'storefront.guest_checkout';
    public const STOREFRONT_PAYSTACK        = 'storefront.paystack';
    public const STOREFRONT_COD             = 'storefront.cod';
    public const STOREFRONT_DELIVERY        = 'storefront.delivery';
    public const STOREFRONT_CUSTOMER_ACCOUNTS = 'storefront.customer_accounts';

    /**
     * The catalogue.
     *
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     domain: string,
     *     category: string,
     *     default: bool,
     *     requires: list<string>,
     *     backs: string,
     * }>
     */
    public static function all(): array
    {
        return [
            /* ---------------- Finance essentials ---------------- */

            self::FINANCE_OVERVIEW => [
                'label'       => 'Finance Overview',
                'description' => 'Farm money dashboard: cash, sales, expenses, who owes you, bills due.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [],
                'backs'       => 'finance dashboard',
            ],
            self::FINANCE_QUICK_EXPENSE => [
                'label'       => 'Record Expense',
                'description' => 'One-screen expense entry with category, payment method and receipt photo.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [],
                'backs'       => 'finance/expenses',
            ],
            self::FINANCE_SIMPLE_PURCHASE => [
                'label'       => 'Purchases',
                'description' => 'Buy feed, supplies and equipment. Pay now or pay later.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [],
                'backs'       => 'finance/purchases',
            ],
            self::FINANCE_SALES_MONEY_IN => [
                'label'       => 'Sales & Money In',
                'description' => 'Sales, invoices, receipts and outstanding customer balances in one place.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [],
                'backs'       => 'finance/invoices, finance/receipts',
            ],
            self::FINANCE_CASH_BANK_MOMO => [
                'label'       => 'Cash, Bank & MoMo',
                'description' => 'Money in, money out, transfers and pending verification.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [],
                'backs'       => 'finance/bank-accounts, finance/payments',
            ],
            self::FINANCE_LOANS => [
                'label'       => 'Loans',
                'description' => 'Record a loan received, see what is due, record repayments.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [],
                'backs'       => 'finance/loans',
            ],
            self::FINANCE_CONTACTS => [
                'label'       => 'Customers & Suppliers',
                'description' => 'Who you buy from and sell to, with balances.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [],
                'backs'       => 'finance/customers, finance/suppliers',
            ],
            self::FINANCE_REPORTS => [
                'label'       => 'Reports',
                'description' => 'Profit, cash flow, expenses by category, who owes you.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [],
                'backs'       => 'finance/reports',
            ],

            /* ---------------- Finance advanced ---------------- */

            self::FINANCE_ADVANCED_ACCOUNTING => [
                'label'       => 'Advanced Accounting',
                'description' => 'Chart of accounts, manual journals, trial balance, balance sheet and cash flow, loan reclassification, credit notes and refunds.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [],
                'backs'       => 'finance/coa, finance/journals, finance/trial-balance, finance/reports/balance-sheet, finance/reports/cash-flow, finance/loans/reclassification, finance/credit-notes, finance/refunds',
            ],
            self::FINANCE_BANK_RECONCILIATION => [
                'label'       => 'Bank Reconciliation',
                'description' => 'Import bank statements and match them against your records.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [self::FINANCE_CASH_BANK_MOMO],
                'backs'       => 'finance/bank-reconciliation, finance/bank-statements',
            ],
            self::FINANCE_PROCUREMENT_APPROVALS => [
                'label'       => 'Procurement Approvals',
                'description' => 'Formal purchase requests, approvals and goods receiving.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [self::FINANCE_SIMPLE_PURCHASE],
                'backs'       => 'finance/procurement',
            ],
            self::FINANCE_ADVANCED_AP => [
                'label'       => 'Advanced Payables',
                'description' => 'Vendor invoice approval, payment runs and supplier statements.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [self::FINANCE_SIMPLE_PURCHASE],
                'backs'       => 'finance/vendor-invoices, finance/payment-runs, finance/supplier-statements',
            ],
            self::FINANCE_WHT => [
                'label'       => 'Withholding Tax',
                'description' => 'WHT certificates for supplier payments.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [self::FINANCE_ADVANCED_AP],
                'backs'       => 'finance/wht-certificates',
            ],
            self::FINANCE_INVESTOR_MANAGEMENT => [
                'label'       => 'Investors',
                'description' => 'Investor capital accounts and allocations.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [self::FINANCE_ADVANCED_ACCOUNTING],
                'backs'       => 'finance/investors',
            ],
            self::FINANCE_ADVANCED_BIOLOGICAL => [
                'label'       => 'Biological Asset Reporting',
                'description' => 'Herd valuation, group cost pools and costing reports.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [self::FINANCE_ADVANCED_ACCOUNTING],
                'backs'       => 'finance/biological-assets, finance/costing',
            ],
            self::FINANCE_ADVANCED_TREASURY => [
                'label'       => 'Advanced Treasury',
                'description' => 'Consolidated treasury position across accounts.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [self::FINANCE_CASH_BANK_MOMO],
                'backs'       => 'finance/treasury',
            ],
            self::FINANCE_PERIOD_CLOSE => [
                'label'       => 'Period Close & Year End',
                'description' => 'Lock accounting periods and run year-end close.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [self::FINANCE_ADVANCED_ACCOUNTING],
                'backs'       => 'finance/close, finance/year-end',
            ],
            self::FINANCE_CUSTOMER_CREDIT => [
                'label'       => 'Customer Credit',
                'description' => 'Credit limits and credit holds for customers who buy on account.',
                'domain'      => self::DOMAIN_FINANCE,
                'category'    => self::CATEGORY_ADVANCED,
                'default'     => false,
                'requires'    => [self::FINANCE_CONTACTS],
                'backs'       => 'counterparty credit hold',
            ],

            /* ---------------- Storefront ---------------- */

            self::STOREFRONT_ENABLED => [
                'label'       => 'Online Store',
                'description' => 'Turn the public shop on or off.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [],
                'backs'       => 'shop/*',
            ],
            self::STOREFRONT_LIVE_PIGS => [
                'label'       => 'Sell Live Pigs',
                'description' => 'List grower and finisher pigs for sale.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'listing kind live_pig',
            ],
            self::STOREFRONT_PIGLETS => [
                'label'       => 'Sell Piglets',
                'description' => 'List piglet lots for sale.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'listing kind piglet_lot',
            ],
            self::STOREFRONT_BREEDING_STOCK => [
                'label'       => 'Sell Breeding Stock',
                'description' => 'List boars and sows as breeding stock.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'listing kind breeding_stock',
            ],
            self::STOREFRONT_FEED_SALES => [
                'label'       => 'Sell Feed',
                'description' => 'List feed for sale to other farms.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'listing kind feed',
            ],
            self::STOREFRONT_MANURE_SALES => [
                'label'       => 'Sell Manure',
                'description' => 'List manure and compost for sale.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => false,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'listing kind manure',
            ],
            self::STOREFRONT_SERVICES => [
                'label'       => 'Sell Services',
                'description' => 'Offer farm services such as transport or consultancy.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => false,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'listing kind service',
            ],
            self::STOREFRONT_QUOTES => [
                'label'       => 'Quote Requests',
                'description' => 'Let buyers request a price for bulk or non-fixed-price items.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'storefront-admin/quotes',
            ],
            self::STOREFRONT_GUEST_CHECKOUT => [
                'label'       => 'Guest Checkout',
                'description' => 'Let buyers order without creating an account first.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'Phase 9 guest checkout',
            ],
            self::STOREFRONT_PAYSTACK => [
                'label'       => 'Pay Online (Paystack)',
                'description' => 'Accept card and mobile money payments through Paystack.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'PaystackClient, webhook',
            ],
            self::STOREFRONT_COD => [
                'label'       => 'Cash on Delivery',
                'description' => 'Accept cash when the buyer collects or receives the order.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'COD settlement',
            ],
            self::STOREFRONT_DELIVERY => [
                'label'       => 'Delivery',
                'description' => 'Offer delivery as well as pickup.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => false,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'storefront delivery settings',
            ],
            self::STOREFRONT_CUSTOMER_ACCOUNTS => [
                'label'       => 'Customer Accounts',
                'description' => 'Let buyers create an account to track their orders.',
                'domain'      => self::DOMAIN_STOREFRONT,
                'category'    => self::CATEGORY_ESSENTIAL,
                'default'     => true,
                'requires'    => [self::STOREFRONT_ENABLED],
                'backs'       => 'account/*',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function isKnown(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertKnown(string $key): void
    {
        if (! self::isKnown($key)) {
            throw new \InvalidArgumentException(
                "Unknown feature key [{$key}]. Add it to FeatureCatalog before toggling it."
            );
        }
    }

    /** @return array{label:string,description:string,domain:string,category:string,default:bool,requires:list<string>,backs:string} */
    public static function definition(string $key): array
    {
        self::assertKnown($key);

        return self::all()[$key];
    }

    public static function defaultFor(string $key): bool
    {
        return self::definition($key)['default'];
    }

    /**
     * Features this one directly depends on.
     *
     * @return list<string>
     */
    public static function requires(string $key): array
    {
        return self::definition($key)['requires'];
    }

    /**
     * Full transitive dependency closure, nearest first.
     *
     * Enabling Withholding Tax must also enable Advanced Payables and, through
     * it, Simple Purchase. Returning the closure rather than only direct
     * parents keeps the service from having to loop.
     *
     * @return list<string>
     */
    public static function requiresDeep(string $key): array
    {
        $seen    = [];
        $ordered = [];
        $walk    = function (string $k) use (&$walk, &$seen, &$ordered): void {
            foreach (self::requires($k) as $parent) {
                if (isset($seen[$parent])) {
                    continue;
                }
                $seen[$parent] = true;
                $ordered[]     = $parent;
                $walk($parent);
            }
        };
        $walk($key);

        return $ordered;
    }

    /**
     * Features that depend on this one, transitively.
     *
     * Disabling a feature must cascade to its dependants, otherwise the
     * settings screen can leave WHT enabled while Advanced Payables — the thing
     * that produces the payments WHT certifies — is off.
     *
     * @return list<string>
     */
    public static function dependentsDeep(string $key): array
    {
        $out = [];
        foreach (self::keys() as $candidate) {
            if ($candidate === $key) {
                continue;
            }
            if (in_array($key, self::requiresDeep($candidate), true)) {
                $out[] = $candidate;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function inDomain(string $domain): array
    {
        return array_keys(array_filter(
            self::all(),
            static fn (array $d): bool => $d['domain'] === $domain,
        ));
    }

    /** @return list<string> */
    public static function inCategory(string $category): array
    {
        return array_keys(array_filter(
            self::all(),
            static fn (array $d): bool => $d['category'] === $category,
        ));
    }
}
