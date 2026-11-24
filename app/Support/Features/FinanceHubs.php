<?php

namespace App\Support\Features;

use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use Illuminate\Support\Facades\Route;

/**
 * Finance as a few pages, one per job, each with its screens as tabs.
 *
 *   Overview      how the farm is doing for money
 *   Money in      sales and who owes the farm, feed sales, returns
 *   Money out     expenses and purchases
 *   Cash & bank   accounts and supplier payments
 *   People        customers, suppliers, loans
 *   Reports       profit & loss
 *
 *   Advanced Accounting   the paid add-on: ledger, formal statements,
 *                         credit notes and refunds, payables, bank
 *                         reconciliation, pig and stock value, investors,
 *                         month and year end
 *
 * Tax settings are set once and live under System Settings.
 *
 * The first six hold only the screens every farm gets. Every screen behind
 * an advanced (add-on) feature is on the Advanced Accounting page, so what a
 * farm pays for stays one thing in the menu; a test holds both rules.
 *
 * Every finance screen belongs to exactly one tab, so the sidebar needs one
 * entry per page and the tabs take the reader to the rest. A tab with
 * `children` shows them as a second row while it is open.
 *
 * A tab appears only when this user can open it: its route's own `feature:`
 * and `permission:` middleware are read and checked, so the tabs never offer
 * a screen that refuses, and never hide one the user may reach. Middleware
 * still guards each route; hiding a tab protects nothing.
 */
final class FinanceHubs
{
    public const ADVANCED = 'advanced';

    /**
     * key => title, sidebar icon, tabs and whether it is the add-on. A tab:
     * label, route, the route names it covers (`active`) and optional
     * children (same shape).
     *
     * @return array<string, array{title: string, icon: string, addon: bool, tabs: list<array>}>
     */
    public static function all(): array
    {
        return [
            'overview' => self::hub('Overview', 'fa-solid fa-gauge', [
                self::tab('Overview', 'finance.overview'),
            ]),
            // Invoices and receipts are opened from Money in, and customer
            // and supplier lists from People; they light up those tabs.
            'money-in' => self::hub('Money in', 'fa-solid fa-hand-holding-dollar', [
                self::tab('Money in', 'finance.money-in.index', ['finance.money-in.*', 'finance.invoices.*', 'finance.receipts.*']),
                self::tab('Feed sales', 'finance.feed-sales.index', ['finance.feed-sales.*']),
                self::tab('Returns', 'finance.returns.index', ['finance.returns.*']),
            ]),
            'money-out' => self::hub('Money out', 'fa-solid fa-receipt', [
                self::tab('Expenses', 'finance.expenses.index', ['finance.expenses.*']),
                self::tab('Purchases', 'finance.purchases.index', ['finance.purchases.*', 'finance.procurement.*']),
            ]),
            'cash-bank' => self::hub('Cash & bank', 'fa-solid fa-wallet', [
                self::tab('Accounts', 'finance.bank-accounts.index', ['finance.bank-accounts.*']),
                self::tab('Supplier payments', 'finance.payments.index', ['finance.payments.*']),
            ]),
            'people' => self::hub('People', 'fa-solid fa-address-book', [
                self::tab('Customers', 'finance.customers.index', ['finance.customers.*', 'finance.statements.*', 'finance.contacts.*']),
                self::tab('Suppliers', 'finance.suppliers.index', ['finance.suppliers.*']),
                self::tab('Loans', 'finance.loans.index', ['finance.loans.index', 'finance.loans.create', 'finance.loans.show']),
            ]),
            'reports' => self::hub('Reports', 'fa-solid fa-chart-column', [
                self::tab('Profit & loss', 'finance.reports.income-statement'),
            ]),

            // The paid add-on: every screen here is behind an add-on feature.
            self::ADVANCED => self::hub('Advanced Accounting', 'fa-solid fa-book', [
                self::tab('Ledger', 'finance.dashboard', [], [
                    self::tab('Accounting dashboard', 'finance.dashboard'),
                    self::tab('Chart of accounts', 'finance.coa.index', ['finance.coa.*']),
                    self::tab('Journals', 'finance.journals.index', ['finance.journals.*']),
                    self::tab('Trial balance', 'finance.trial-balance'),
                ]),
                self::tab('Statements', 'finance.reports.balance-sheet', [], [
                    self::tab('Balance sheet', 'finance.reports.balance-sheet'),
                    self::tab('Cash flow', 'finance.reports.cash-flow'),
                    self::tab('Loan reclassification', 'finance.loans.reclassification'),
                ]),
                self::tab('Credits & refunds', 'finance.credit-notes.index', [], [
                    self::tab('Credit notes', 'finance.credit-notes.index', ['finance.credit-notes.*']),
                    self::tab('Refunds', 'finance.refunds.index', ['finance.refunds.*']),
                ]),
                self::tab('Payables', 'finance.vendor-invoices.index', [], [
                    self::tab('Supplier bills', 'finance.vendor-invoices.index', ['finance.vendor-invoices.*']),
                    self::tab('Payment runs', 'finance.payment-runs.index', ['finance.payment-runs.*']),
                    self::tab('Supplier statements', 'finance.supplier-statements.index', ['finance.supplier-statements.*']),
                    self::tab('Who owes what', 'finance.ageing.index', ['finance.ageing.*']),
                    self::tab('WHT certificates', 'finance.wht-certificates.index', ['finance.wht-certificates.*']),
                ]),
                self::tab('Bank', 'finance.bank-statements.index', [], [
                    self::tab('Bank statements', 'finance.bank-statements.index', ['finance.bank-statements.*']),
                    self::tab('Reconcile', 'finance.bank-reconciliation.index', ['finance.bank-reconciliation.*']),
                    self::tab('Treasury', 'finance.treasury'),
                ]),
                self::tab('Pig & stock value', 'finance.inventory.value.index', [], [
                    self::tab('Stock value', 'finance.inventory.value.index', ['finance.inventory.value.*']),
                    self::tab('Pigs (biological assets)', 'finance.biological-assets.index', ['finance.biological-assets.*']),
                    self::tab('Group cost pools', 'finance.costing.group-pools.index', ['finance.costing.group-pools.*']),
                    self::tab('Mortality write-offs', 'finance.costing.mortality-writeoffs.index', ['finance.costing.mortality-writeoffs.*']),
                    self::tab('Opening pigs', 'finance.costing.opening-pig-inventory.index', ['finance.costing.opening-pig-inventory.*']),
                    self::tab('Opening stock', 'finance.inventory.opening-balances.index', ['finance.inventory.opening-balances.*']),
                    self::tab('Goods not billed', 'finance.inventory.gr-ir-ageing.index', ['finance.inventory.gr-ir-ageing.*']),
                    self::tab('Account mappings', 'finance.inventory.account-mappings.index', ['finance.inventory.account-mappings.*']),
                ]),
                self::tab('Investors', 'finance.investors.index', [], [
                    self::tab('Investors', 'finance.investors.index', ['finance.investors.index', 'finance.investors.create', 'finance.investors.show']),
                    self::tab('Cap table', 'finance.investors.cap-table'),
                    self::tab('Share profit', 'finance.investors.allocate.form', ['finance.investors.allocate.*']),
                ]),
                self::tab('Close', 'finance.close.index', [], [
                    self::tab('Month end', 'finance.close.index', ['finance.close.*']),
                    self::tab('Year end', 'finance.year-end.index', ['finance.year-end.*']),
                ]),
            ], addon: true),
        ];
    }

    /**
     * The hubs this user can open, each with only the tabs they can open.
     * A tab whose children are all hidden is dropped; one whose own route is
     * hidden opens its first visible child instead.
     *
     * @return array<string, array{title: string, icon: string, route: string, active: list<string>, tabs: list<array>}>
     */
    public static function visible(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $hubs = [];
        foreach (self::all() as $key => $hub) {
            $tabs = [];
            foreach ($hub['tabs'] as $tab) {
                if ($tab['children'] !== []) {
                    $tab['children'] = array_values(array_filter($tab['children'], fn ($c) => self::canOpen($c['route'], $user)));
                    if ($tab['children'] === []) {
                        continue;
                    }
                    $tab['route'] = $tab['children'][0]['route'];
                    $tab['active'] = array_merge(...array_column($tab['children'], 'active'));
                } elseif (! self::canOpen($tab['route'], $user)) {
                    continue;
                }
                $tabs[] = $tab;
            }
            if ($tabs === []) {
                continue;
            }

            $hubs[$key] = $hub + [
                'route' => $tabs[0]['route'],
                'active' => array_merge(...array_column($tabs, 'active')),
            ];
            $hubs[$key]['tabs'] = $tabs;
        }

        return $hubs;
    }

    /** The hub the current page belongs to, with its tabs marked current. */
    public static function current(?User $user): ?array
    {
        $mark = function (array $tabs): array {
            $marked = [];
            foreach ($tabs as $tab) {
                $tab['current'] = request()->routeIs(...$tab['active']);
                if ($tab['children'] !== []) {
                    $tab['children'] = array_map(fn ($c) => $c + ['current' => request()->routeIs(...$c['active'])], $tab['children']);
                }
                $marked[] = $tab;
            }

            return $marked;
        };

        foreach (self::visible($user) as $hub) {
            if (request()->routeIs(...$hub['active'])) {
                $hub['tabs'] = $mark($hub['tabs']);

                return $hub;
            }
        }

        return null;
    }

    /**
     * Whether the user gets past the route's feature and permission gates.
     * Each `permission:` middleware needs any one of its permissions; each
     * `feature:` middleware needs all of its features on.
     */
    public static function canOpen(string $routeName, User $user): bool
    {
        $route = Route::getRoutes()->getByName($routeName);
        if ($route === null) {
            return false;
        }

        $features = app(OrganizationFeatureService::class);
        $excluded = $route->excludedMiddleware();
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_contains($middleware, ':') || in_array($middleware, $excluded, true)) {
                continue;
            }
            [$name, $args] = explode(':', $middleware, 2);
            $args = explode(',', $args);

            if ($name === 'permission' && ! $user->hasAnyPermission($args)) {
                return false;
            }
            if ($name === 'feature') {
                foreach ($args as $feature) {
                    if (! $features->usable($feature)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private static function hub(string $title, string $icon, array $tabs, bool $addon = false): array
    {
        return ['title' => $title, 'icon' => $icon, 'addon' => $addon, 'tabs' => $tabs];
    }

    private static function tab(string $label, string $route, array $active = [], array $children = []): array
    {
        return ['label' => $label, 'route' => $route, 'active' => $active ?: [$route], 'children' => $children];
    }
}
