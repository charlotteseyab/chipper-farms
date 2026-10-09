<?php

namespace App\Support\Features;

use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use Illuminate\Support\Facades\Route;

/**
 * The finance menu, built from features and permissions (Phase 3, blueprint §20,
 * §26, §50).
 *
 * WHAT CHANGED
 * ------------
 * `NavigationHelper::sidebarSections()` builds the finance menu from hardcoded
 * role arrays — `['superuser', 'admin', 'accountant']` repeated per link. That
 * has two problems the blueprint names directly:
 *
 *   §26: "Do not rely on hardcoded roles where permissions are more
 *        appropriate." A role list cannot express "whoever may record an
 *        expense", so adding a role means editing every line it should see.
 *
 *   §20: the default menu should be ten farm-language entries with the
 *        ledger-heavy screens gathered under Advanced Accounting, rather than
 *        twenty-plus accounting concepts flat.
 *
 * THREE CONDITIONS, ALL REQUIRED
 * ------------------------------
 * An entry appears only when the feature is on, the user holds the permission,
 * and the route exists. The feature test is presentation; the permission test is
 * the real control and is enforced again by middleware on the route itself.
 * Showing a link is not granting access, and hiding one is not protecting
 * anything.
 *
 * ROUTE EXISTENCE IS CHECKED
 * --------------------------
 * Several targets here are built in later phases. A menu that references a route
 * that does not exist yet throws on every page render for every user, which is
 * exactly the class of defect this programme has already hit once. Entries whose
 * route is not registered are simply omitted until their phase lands.
 */
final class FinanceNavigation
{
    /**
     * @return list<array{
     *   label:string, route:string, icon:string, active:string,
     *   children?:list<array{label:string,route:string,active:string}>
     * }>
     */
    public static function build(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $features = app(OrganizationFeatureService::class);

        $entries = [
            [
                'label'      => 'Overview',
                'route'      => 'finance.overview',
                'icon'       => 'fa-solid fa-gauge',
                'active'     => 'finance',
                'feature'    => FeatureCatalog::FINANCE_OVERVIEW,
                // `view_finance`, because that is what the route enforces.
                //
                // This asked for `view_finance_dashboard` until Phase 16, and no
                // seeder anywhere creates that permission — it existed only in
                // this array and two Needs Attention cards. While this class was
                // unwired the mistake was invisible; wiring it into the sidebar
                // made the Overview entry vanish for everyone, which is how it
                // was found. A menu must ask for the permission the page
                // actually checks, or it hides a page the user can reach.
                'permission' => ['view_finance'],
            ],
            [
                'label'      => 'Expenses',
                'route'      => 'finance.expenses.index',
                'icon'       => 'fa-solid fa-receipt',
                'active'     => 'finance/expenses*',
                'feature'    => FeatureCatalog::FINANCE_QUICK_EXPENSE,
                'permission' => ['record_expenses'],
            ],
            [
                'label'      => 'Purchases',
                'route'      => 'finance.purchases.index',
                'icon'       => 'fa-solid fa-cart-shopping',
                'active'     => 'finance/purchases*',
                'feature'    => FeatureCatalog::FINANCE_SIMPLE_PURCHASE,
                'permission' => ['record_purchases', 'view_finance'],
            ],
            [
                // Phase 7 (§28): points at the Money In workspace rather than
                // the invoice register. Invoices and receipts keep their own
                // screens and are linked from it.
                'label'      => 'Sales & Money In',
                'route'      => 'finance.money-in.index',
                'icon'       => 'fa-solid fa-hand-holding-dollar',
                'active'     => ['finance/money-in*', 'finance/invoices*', 'finance/receipts*', 'finance/feed-sales*'],
                'feature'    => FeatureCatalog::FINANCE_SALES_MONEY_IN,
                'permission' => ['view_finance'],
            ],
            [
                'label'      => 'Cash, Bank & MoMo',
                'route'      => 'finance.bank-accounts.index',
                'icon'       => 'fa-solid fa-wallet',
                'active'     => ['finance/bank-accounts*', 'finance/payments*'],
                'feature'    => FeatureCatalog::FINANCE_CASH_BANK_MOMO,
                'permission' => ['manage_bank_accounts', 'view_bank_accounts'],
            ],
            [
                'label'      => 'Loans',
                'route'      => 'finance.loans.index',
                'icon'       => 'fa-solid fa-landmark',
                'active'     => 'finance/loans*',
                'feature'    => FeatureCatalog::FINANCE_LOANS,
                'permission' => ['manage_loans', 'view_loans'],
            ],
            [
                // IMP-098 (§43): the return-request surface. Sits beside Sales
                // & Money In because a return is money going back the other way.
                'label'      => 'Returns',
                'route'      => 'finance.returns.index',
                'icon'       => 'fa-solid fa-rotate-left',
                'active'     => 'finance/returns*',
                'feature'    => FeatureCatalog::FINANCE_SALES_MONEY_IN,
                'permission' => ['record_sales_returns'],
            ],
            [
                // Phase 7 (§27): points at the unified People & Businesses
                // workspace rather than the customer master. The accountant's
                // customer and supplier screens are still reachable from it and
                // by their own URLs — the menu entry changed, not the surfaces.
                'label'      => 'People & Businesses',
                'route'      => 'finance.contacts.index',
                'icon'       => 'fa-solid fa-address-book',
                'active'     => ['finance/contacts*', 'finance/customers*', 'finance/suppliers*'],
                'feature'    => FeatureCatalog::FINANCE_CONTACTS,
                'permission' => ['view_finance', 'manage_customers', 'manage_suppliers'],
            ],
            [
                'label'      => 'Reports',
                'route'      => 'finance.reports.income-statement',
                'icon'       => 'fa-solid fa-chart-column',
                'active'     => 'finance/reports*',
                'feature'    => FeatureCatalog::FINANCE_REPORTS,
                'permission' => ['view_financial_reports'],
            ],
        ];

        $built = [];
        foreach ($entries as $entry) {
            if (! self::admits($features, $user, $entry)) {
                continue;
            }

            $built[] = array_filter([
                'label'    => $entry['label'],
                'route'    => $entry['route'],
                'icon'     => $entry['icon'],
                'active'   => $entry['active'],
                'children' => self::childrenFor($features, $user, $entry['label']),
            ]);
        }

        $advanced = self::advanced($features, $user);
        if ($advanced !== []) {
            $built[] = [
                'label'    => 'Advanced Accounting',
                'route'    => $advanced[0]['route'],
                'icon'     => 'fa-solid fa-book',
                'active'   => 'finance/advanced*',
                'children' => $advanced,
            ];
        }

        return $built;
    }

    /**
     * The ledger-heavy screens, gathered rather than deleted (blueprint §25).
     *
     * @return list<array{label:string,route:string,active:string}>
     */
    protected static function advanced(OrganizationFeatureService $features, User $user): array
    {
        $entries = [];

        // The ledger dashboard route is intentionally permission-gated but not
        // feature-gated. It is the accountant's stable entry point even when a
        // farm has not enabled optional advanced features yet.
        if ($user->isAccountant()) {
            $entries[] = ['Accounting Dashboard', 'finance.dashboard', 'finance',
                null, ['view_finance']];
        }

        $entries = array_merge($entries, [
            ['Chart of Accounts', 'finance.coa.index', 'finance/coa*',
                FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, ['view_finance']],
            ['Journal Entries', 'finance.journals.index', 'finance/journals*',
                FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, ['view_finance', 'propose_manual_journal', 'approve_journal']],
            ['Trial Balance', 'finance.trial-balance', 'finance/trial-balance*',
                FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, ['view_finance']],
            ['Bank Statements', 'finance.bank-statements.index', 'finance/bank-statements*',
                FeatureCatalog::FINANCE_BANK_RECONCILIATION, ['import_bank_statement', 'reconcile_bank_statement']],
            ['Bank Reconciliation', 'finance.bank-reconciliation.index', 'finance/bank-reconciliation*',
                FeatureCatalog::FINANCE_BANK_RECONCILIATION, ['reconcile_bank_statement', 'import_bank_statement']],
            ['Ageing', 'finance.ageing.index', 'finance/ageing*',
                FeatureCatalog::FINANCE_ADVANCED_AP, ['view_finance']],
            ['Vendor Invoices', 'finance.vendor-invoices.index', 'finance/vendor-invoices*',
                FeatureCatalog::FINANCE_ADVANCED_AP, ['view_finance', 'create_purchase_request_vendor_invoice']],
            ['Payment Runs', 'finance.payment-runs.index', 'finance/payment-runs*',
                FeatureCatalog::FINANCE_ADVANCED_AP, ['view_finance']],
            ['Supplier Statements', 'finance.supplier-statements.index', 'finance/supplier-statements*',
                FeatureCatalog::FINANCE_ADVANCED_AP, ['manage_supplier_statements', 'view_finance']],
            ['WHT Certificates', 'finance.wht-certificates.index', 'finance/wht-certificates*',
                FeatureCatalog::FINANCE_WHT, ['manage_wht_certificates', 'view_finance']],
            // `view_period_close`, because that is what both index routes
            // require. Asking for the manage permission hid the screens from a
            // farm manager who may read them — the same defect as the phantom
            // `view_finance_dashboard`, found the same way in Phase 16.
            ['Period Close', 'finance.close.index', 'finance/close*',
                FeatureCatalog::FINANCE_PERIOD_CLOSE, ['view_period_close', 'manage_period_close']],
            ['Year End', 'finance.year-end.index', 'finance/year-end*',
                FeatureCatalog::FINANCE_PERIOD_CLOSE, ['view_period_close', 'manage_year_end_close']],
            // Investors is three screens, not one. The hardcoded menu offered
            // all three; listing only the index here would have left Cap Table
            // and Allocate Profit with working routes and nothing linking to
            // them (Phase 16 — see childrenFor()). They sit flat rather than
            // nested because the sidebar partial renders two levels, not three.
            ['Investors', 'finance.investors.index', 'finance/investors*',
                FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT, ['manage_investors', 'view_investors']],
            ['Investor Cap Table', 'finance.investors.cap-table', 'finance/investors/cap-table*',
                FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT, ['manage_investors', 'view_investors']],
            ['Allocate Investor Profit', 'finance.investors.allocate.form', 'finance/investors/allocate*',
                FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT, ['view_investors', 'manage_investors']],
            // `finance.costing.index` does not exist and never did — the real
            // routes are costing.group-pools.*, costing.opening-pig-inventory.*
            // and inventory.value.*. `admits()` drops entries whose route is not
            // registered, so this single wrong name silently removed the entire
            // inventory and costing area from the menu the moment Phase 16 wired
            // this builder up. The legacy sidebar had offered all of it.
            ['Inventory Value', 'finance.inventory.value.index', 'finance/inventory/value*',
                FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, ['view_inventory_value']],
            ['Group Cost Pools', 'finance.costing.group-pools.index', 'finance/costing/group-pools*',
                FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, ['view_group_pools', 'view_inventory_value']],
            ['Opening Pig Inventory', 'finance.costing.opening-pig-inventory.index', 'finance/costing/opening-pig-inventory*',
                FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, ['view_group_pools', 'view_inventory_value']],
            ['Mortality Write-offs', 'finance.costing.mortality-writeoffs.index', 'finance/costing/mortality-writeoffs*',
                FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, ['manage_pig_mortality', 'view_inventory_value']],
            ['Account Mappings', 'finance.inventory.account-mappings.index', 'finance/inventory/account-mappings*',
                FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, ['view_inventory_value']],
            ['GR/IR Ageing', 'finance.inventory.gr-ir-ageing.index', 'finance/inventory/gr-ir-ageing*',
                FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, ['view_gr_ir_ageing']],
            ['Biological Assets', 'finance.biological-assets.index', 'finance/biological-assets*',
                FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, ['view_financial_reports', 'view_finance']],
            ['Treasury', 'finance.treasury', 'finance/treasury*',
                FeatureCatalog::FINANCE_ADVANCED_TREASURY, ['view_treasury']],
        ]);

        $out = [];
        foreach ($entries as [$label, $route, $active, $feature, $permissions]) {
            if (! self::admits($features, $user, [
                'route' => $route, 'feature' => $feature, 'permission' => $permissions,
            ])) {
                continue;
            }

            $out[] = ['label' => $label, 'route' => $route, 'active' => $active];
        }

        return $out;
    }

    /**
     * Feature on AND permission held AND route registered.
     *
     * @param array{route:string, feature:?string, permission:list<string>} $entry
     */
    /**
     * Sub-entries for the top-level items that have more than one screen.
     *
     * WHY THIS EXISTS (Phase 16)
     * --------------------------
     * §20 reorganises the finance menu into farm-language entries and §25 is
     * explicit that the ledger screens are "kept... rather than being deleted".
     * When Phase 16 finally wired this builder into the live sidebar (it had
     * been dead code since Phase 3 — see IMP-070), three screens the old
     * hardcoded menu offered had no entry here at all: Balance Sheet, Cash Flow
     * and loan reclassification. Their routes still existed and still worked,
     * but nothing in the application linked to them any more.
     *
     * That is deletion by navigation, and it is the reverse of the §46 dead end
     * this programme keeps closing: not a link to a page that refuses, but a
     * page with no link. Reports and Loans therefore carry their siblings.
     *
     * @return list<array{label:string,route:string,active:string}>
     */
    protected static function childrenFor(
        OrganizationFeatureService $features,
        User $user,
        string $label,
    ): array {
        $candidates = match ($label) {
            'Reports' => [
                ['Income Statement', 'finance.reports.income-statement', 'finance/reports/income-statement*',
                    FeatureCatalog::FINANCE_REPORTS, ['view_financial_reports']],
                ['Balance Sheet', 'finance.reports.balance-sheet', 'finance/reports/balance-sheet*',
                    FeatureCatalog::FINANCE_REPORTS, ['view_financial_reports']],
                ['Cash Flow', 'finance.reports.cash-flow', 'finance/reports/cash-flow*',
                    FeatureCatalog::FINANCE_REPORTS, ['view_financial_reports']],
            ],
            'Loans' => [
                ['All Loans', 'finance.loans.index', 'finance/loans',
                    FeatureCatalog::FINANCE_LOANS, ['view_loans', 'manage_loans']],
                ['Reclassification', 'finance.loans.reclassification', 'finance/loans/reclassification*',
                    FeatureCatalog::FINANCE_LOANS, ['view_loans', 'manage_loans']],
            ],
            default => [],
        };

        $out = [];

        foreach ($candidates as [$childLabel, $route, $active, $feature, $permissions]) {
            if (! self::admits($features, $user, [
                'route' => $route, 'feature' => $feature, 'permission' => $permissions,
            ])) {
                continue;
            }

            $out[] = ['label' => $childLabel, 'route' => $route, 'active' => $active];
        }

        // A single child is the parent repeated — leave it as a plain link.
        return count($out) > 1 ? $out : [];
    }

    protected static function admits(
        OrganizationFeatureService $features,
        User $user,
        array $entry,
    ): bool {
        if (! Route::has($entry['route'])) {
            return false;
        }

        if (($entry['feature'] ?? null) !== null && ! $features->usable($entry['feature'])) {
            return false;
        }

        // OR across permissions: any one of them is enough to see the entry,
        // matching PermissionMiddleware, which also OR-combines.
        return $user->hasAnyPermission($entry['permission']);
    }
}
