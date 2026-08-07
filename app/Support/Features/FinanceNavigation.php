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
                'permission' => ['view_finance_dashboard'],
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
                'permission' => ['record_purchases', 'manage_vendor_invoices'],
            ],
            [
                'label'      => 'Sales & Money In',
                'route'      => 'finance.invoices.index',
                'icon'       => 'fa-solid fa-hand-holding-dollar',
                'active'     => ['finance/invoices*', 'finance/receipts*', 'finance/feed-sales*'],
                'feature'    => FeatureCatalog::FINANCE_SALES_MONEY_IN,
                'permission' => ['manage_invoices', 'view_invoices'],
            ],
            [
                'label'      => 'Cash, Bank & MoMo',
                'route'      => 'finance.bank-accounts.index',
                'icon'       => 'fa-solid fa-wallet',
                'active'     => ['finance/bank-accounts*', 'finance/payments*'],
                'feature'    => FeatureCatalog::FINANCE_CASH_BANK_MOMO,
                'permission' => ['manage_bank_accounts', 'view_bank_accounts', 'manage_payments'],
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
                'label'      => 'Customers & Suppliers',
                'route'      => 'finance.customers.index',
                'icon'       => 'fa-solid fa-address-book',
                'active'     => ['finance/customers*', 'finance/suppliers*'],
                'feature'    => FeatureCatalog::FINANCE_CONTACTS,
                'permission' => ['manage_customers', 'view_customers', 'manage_suppliers'],
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

            $built[] = [
                'label'  => $entry['label'],
                'route'  => $entry['route'],
                'icon'   => $entry['icon'],
                'active' => $entry['active'],
            ];
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
        $entries = [
            ['Chart of Accounts', 'finance.coa.index', 'finance/coa*',
                FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, ['manage_chart_of_accounts', 'view_chart_of_accounts']],
            ['Journal Entries', 'finance.journals.index', 'finance/journals*',
                FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, ['propose_manual_journal', 'approve_journal', 'view_journals']],
            ['Trial Balance', 'finance.trial-balance', 'finance/trial-balance*',
                FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, ['view_financial_reports']],
            ['Bank Statements', 'finance.bank-statements.index', 'finance/bank-statements*',
                FeatureCatalog::FINANCE_BANK_RECONCILIATION, ['manage_bank_statements', 'view_bank_statements']],
            ['Bank Reconciliation', 'finance.bank-reconciliation.index', 'finance/bank-reconciliation*',
                FeatureCatalog::FINANCE_BANK_RECONCILIATION, ['manage_bank_reconciliation']],
            ['Ageing', 'finance.ageing.index', 'finance/ageing*',
                FeatureCatalog::FINANCE_ADVANCED_AP, ['view_ageing_reports', 'view_financial_reports']],
            ['Vendor Invoices', 'finance.vendor-invoices.index', 'finance/vendor-invoices*',
                FeatureCatalog::FINANCE_ADVANCED_AP, ['manage_vendor_invoices', 'view_vendor_invoices']],
            ['Payment Runs', 'finance.payment-runs.index', 'finance/payment-runs*',
                FeatureCatalog::FINANCE_ADVANCED_AP, ['manage_payment_runs']],
            ['Supplier Statements', 'finance.supplier-statements.index', 'finance/supplier-statements*',
                FeatureCatalog::FINANCE_ADVANCED_AP, ['view_supplier_statements']],
            ['WHT Certificates', 'finance.wht-certificates.index', 'finance/wht-certificates*',
                FeatureCatalog::FINANCE_WHT, ['manage_wht_certificates', 'view_wht_certificates']],
            ['Period Close', 'finance.close.index', 'finance/close*',
                FeatureCatalog::FINANCE_PERIOD_CLOSE, ['manage_period_close']],
            ['Year End', 'finance.year-end.index', 'finance/year-end*',
                FeatureCatalog::FINANCE_PERIOD_CLOSE, ['manage_year_end']],
            ['Investors', 'finance.investors.index', 'finance/investors*',
                FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT, ['manage_investors', 'view_investors']],
            ['Inventory & Costing', 'finance.costing.index', 'finance/costing*',
                FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, ['view_inventory_valuation', 'manage_inventory']],
            ['Biological Assets', 'finance.biological-assets.index', 'finance/biological-assets*',
                FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, ['view_biological_assets', 'view_financial_reports']],
            ['Treasury', 'finance.treasury', 'finance/treasury*',
                FeatureCatalog::FINANCE_ADVANCED_TREASURY, ['view_treasury']],
        ];

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
     * @param array{route:string, feature:string, permission:list<string>} $entry
     */
    protected static function admits(
        OrganizationFeatureService $features,
        User $user,
        array $entry,
    ): bool {
        if (! Route::has($entry['route'])) {
            return false;
        }

        if (! $features->usable($entry['feature'])) {
            return false;
        }

        // OR across permissions: any one of them is enough to see the entry,
        // matching PermissionMiddleware, which also OR-combines.
        return $user->hasAnyPermission($entry['permission']);
    }
}
