<?php

namespace App\Helpers;

use App\Models\User;

class NavigationHelper
{
    /**
     * Get the previous URL or a fallback URL.
     */
    public static function getPreviousUrl($fallback = '/')
    {
        $previous = url()->previous();
        $current = url()->current();

        if ($previous === $current || empty($previous) || $previous === url('/')) {
            return $fallback;
        }

        return $previous;
    }

    /**
     * Get back URL with intelligent fallback based on context.
     */
    public static function getContextualBackUrl($context = null)
    {
        $previous = url()->previous();
        $current = url()->current();
        $storedUrl = session('last_list_url');

        if ($previous !== $current && !empty($previous) && $previous !== url('/')) {
            if ($storedUrl && in_array($context, ['pig-detail', 'sale-form'], true) && $storedUrl !== $current) {
                return $storedUrl;
            }

            return $previous;
        }

        if ($storedUrl && $storedUrl !== $current) {
            return $storedUrl;
        }

        return match ($context) {
            'pig-detail' => self::getPigDetailFallback(),
            'sale-form' => self::getSaleFormFallback(),
            'mating' => route('mating.index'),
            'breeding-stocks' => route('breeding-stocks.index'),
            'standard-stocks' => route('standard-stocks.index'),
            'all-stocks' => route('all-stocks.index'),
            'sales' => route('sales.index'),
            'gestation' => route('gestation.index'),
            default => route('dashboard'),
        };
    }

    /**
     * Build the shared sidebar navigation based on the current user.
     */
    public static function sidebarSections(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $allRoles = ['superuser', 'admin', 'farmmanager', 'dataentry'];
        $managementRoles = ['superuser', 'admin', 'farmmanager'];
        $adminRoles = ['superuser', 'admin'];

        return array_values(array_filter([
            [
                'title' => 'Overview',
                'items' => array_values(array_filter([
                    self::dashboardItem($user),
                    
                ])),
            ],
            [
                'title' => 'Pig Operations',
                'items' => array_values(array_filter([
                    self::link('All Stock', 'all-stocks.index', 'all-stocks*', 'fa-solid fa-list', $allRoles),
                    self::link('Breeding Stock', 'breeding-stocks.index', 'breeding-stocks*', 'fa-solid fa-venus-mars', $allRoles),
                    self::link('Standard Stock', 'standard-stocks.index', 'standard-stocks*', 'fa-solid fa-paw', $allRoles),
                    self::link('Pig Details Archive', 'pigs.archived', 'pigs/archived*', 'fa-solid fa-box-archive', $managementRoles),
                    self::link('Structures & Rooms', 'structures.index', ['structures*', 'rooms*'], 'fa-solid fa-warehouse', $managementRoles),
                ])),
            ],
            [
                'title' => 'Breeding & Lifecycle',
                'items' => array_values(array_filter([
                    self::group('Mating Planner', 'fa-solid fa-heart-circle-plus', ['mating*'], [
                        self::link('Plan Mating', 'mating.index', 'mating', 'fa-solid fa-plus', $allRoles),
                        self::link('Manage Matings', 'mating.manage', 'mating/manage*', 'fa-solid fa-list-check', $allRoles),
                    ], $allRoles),
                    self::link('Gestation', 'gestation.index', 'gestation*', 'fa-regular fa-clock', $allRoles),
                    self::link('Farrowing', 'farrowing.index', 'farrowing*', 'fa-solid fa-file-medical', $allRoles),
                    self::link('Piglet Tracking', 'piglets.tracking', 'piglets*', 'fa-solid fa-chart-column', $allRoles),
                    self::link('Weaning', 'weaning.dashboard', 'weaning*', 'fa-solid fa-seedling', $allRoles),
                ])),
            ],
            [
                'title' => 'Monitoring',
                'items' => array_values(array_filter([
                    self::group('Weight Management', 'fa-solid fa-weight-scale', ['weights*', 'weight-alerts*', 'weight-standards*'], [
                        self::link('All Weights', 'weights.index', 'weights', 'fa-solid fa-list', $allRoles),
                        self::link('Record Weight', 'weights.create', 'weights/create*', 'fa-solid fa-plus', $allRoles),
                        self::link('Weight Reports', 'weights.reports', 'weights/reports*', 'fa-solid fa-chart-line', $allRoles),
                        self::link('Weight Alerts', 'weight-alerts.index', ['weight-alerts*', 'weight-alerts-dashboard*'], 'fa-solid fa-triangle-exclamation', $allRoles),
                        self::link('Weight Standards', 'weight-standards.index', 'weight-standards*', 'fa-solid fa-ruler-combined', $managementRoles),
                    ], $allRoles),
                    self::group('Health & Medication', 'fa-solid fa-heart-pulse', ['health*', 'medications*', 'health-reports*'], [
                        self::link('Health Records', 'health.index', 'health*', 'fa-solid fa-heart', $allRoles),
                        self::link('Medications', 'medications.index', 'medications*', 'fa-solid fa-capsules', $allRoles),
                        self::link('Health Reports', 'health.reports', 'health-reports*', 'fa-solid fa-notes-medical', $allRoles),
                    ], $allRoles),
                    self::link('Deaths', 'deaths.index', 'deaths*', 'fa-solid fa-skull-crossbones', $allRoles),
                    self::link('Sales', 'sales.index', 'sales*', 'fa-solid fa-coins', $allRoles),
                    self::link('Performance Metrics', 'performance_metrics.index', 'metrics*', 'fa-solid fa-chart-pie', $adminRoles),
                ])),
            ],
            [
                'title' => 'Feed Management',
                'items' => array_values(array_filter([
                    self::link('Purchase Requests', 'purchase-requests.index', 'purchase-requests*', 'fa-solid fa-file-invoice', $allRoles),
                    self::link('Raw Materials', 'raw-materials.index', 'raw-materials-stock*', 'fa-solid fa-boxes-stacked', $allRoles),
                    self::link('Feed Production', 'feed-production.index', 'feed-production*', 'fa-solid fa-industry', $allRoles),
                    self::link('Planning Reports', 'planning-reports.index', 'planning-reports*', 'fa-solid fa-chart-column', $allRoles),
                ])),
            ],
            [
                'title' => 'Finance',
                'items' => array_values(array_filter([
                    self::link('Dashboard', 'finance.dashboard', 'finance', 'fa-solid fa-chart-line', ['superuser', 'admin', 'accountant', 'treasurer', 'farmmanager', 'dataentry']),
                    self::link('Customers', 'finance.customers.index', 'finance/customers*', 'fa-solid fa-address-book', ['superuser', 'admin', 'accountant']),
                    self::link('Invoices', 'finance.invoices.index', 'finance/invoices*', 'fa-solid fa-file-invoice', ['superuser', 'admin', 'accountant']),
                    self::link('Suppliers', 'finance.suppliers.index', 'finance/suppliers*', 'fa-solid fa-truck-fast', ['superuser', 'admin', 'accountant']),
                    self::link('Vendor Invoices', 'finance.vendor-invoices.index', 'finance/vendor-invoices*', 'fa-solid fa-file-invoice-dollar', ['superuser', 'admin', 'accountant', 'treasurer']),
                    self::link('Payments', 'finance.payments.index', 'finance/payments*', 'fa-solid fa-money-bill-transfer', ['superuser', 'admin', 'accountant', 'treasurer']),
                    self::link('Payment Runs', 'finance.payment-runs.index', 'finance/payment-runs*', 'fa-solid fa-money-check-dollar', ['superuser', 'admin', 'accountant', 'treasurer']),
                    self::link('Chart of Accounts', 'finance.coa.index', 'finance/coa*', 'fa-solid fa-list-ol', ['superuser', 'admin', 'accountant']),
                    [
                        'label' => 'AR Ageing',
                        'route' => 'finance.ageing.index',
                        'icon'  => 'fa-solid fa-chart-line',
                        'roles' => ['superuser', 'admin', 'accountant'],
                    ],
                    [
                        'label' => 'Refunds',
                        'route' => 'finance.refunds.index',
                        'icon'  => 'fa-solid fa-rotate-left',
                        'roles' => ['superuser', 'admin', 'accountant'],
                    ],
                    [
                        'label' => 'Receipts',
                        'route' => 'finance.receipts.index',
                        'icon'  => 'fa-solid fa-cash-register',
                        'roles' => ['superuser', 'admin', 'accountant', 'farmmanager'],
                    ],
                    self::link('Journal Entries', 'finance.journals.index', 'finance/journals*', 'fa-solid fa-book', ['superuser', 'admin', 'accountant']),
                    self::link('Trial Balance', 'finance.trial-balance', 'finance/trial-balance*', 'fa-solid fa-scale-balanced', ['superuser', 'admin', 'accountant', 'farmmanager']),
                    self::permissionGroup('Inventory & Costing', 'fa-solid fa-boxes-stacked', [
                        'finance/inventory*',
                        'finance/procurement*',
                        'finance/feed/issues*',
                        'finance/costing*',
                        'finance/biological-assets*',
                        'finance/feed-sales*',
                    ], [
                        self::permissionLink(
                            'Inventory',
                            'finance.inventory.value.index',
                            'finance/inventory/value*',
                            'fa-solid fa-warehouse',
                            'view_inventory_value',
                        ),
                        self::permissionLink(
                            'Account Mappings',
                            'finance.inventory.account-mappings.index',
                            'finance/inventory/account-mappings*',
                            'fa-solid fa-code-branch',
                            'view_inventory_value',
                        ),
                        self::permissionLink(
                            'Opening Balances',
                            'finance.inventory.opening-balances.index',
                            'finance/inventory/opening-balances*',
                            'fa-solid fa-scale-unbalanced-flip',
                            'view_inventory_value',
                        ),
                        self::permissionLink(
                            'GR/IR Ageing',
                            'finance.inventory.gr-ir-ageing.index',
                            'finance/inventory/gr-ir-ageing*',
                            'fa-solid fa-clock',
                            'view_gr_ir_ageing',
                        ),
                        self::permissionLink(
                            'Purchase Receiving',
                            'purchase-requests.index',
                            ['purchase-requests*', 'finance/procurement*'],
                            'fa-solid fa-truck-ramp-box',
                            'receive_against_pr',
                        ),
                        self::permissionLink(
                            'Feed Issues',
                            'finance.feed.issues.index',
                            'finance/feed/issues*',
                            'fa-solid fa-bowl-food',
                            ['view_group_pools', 'manage_feed_issues'],
                        ),
                        self::permissionLink(
                            'Group Cost Pools',
                            'finance.costing.group-pools.index',
                            'finance/costing/group-pools*',
                            'fa-solid fa-layer-group',
                            'view_group_pools',
                        ),
                        self::permissionLink(
                            'Opening Pig Inventory',
                            'finance.costing.opening-pig-inventory.index',
                            'finance/costing/opening-pig-inventory*',
                            'fa-solid fa-piggy-bank',
                            'view_group_pools',
                        ),
                        self::permissionLink(
                            'Mortality Write-offs',
                            'finance.costing.mortality-writeoffs.index',
                            'finance/costing/mortality-writeoffs*',
                            'fa-solid fa-file-circle-minus',
                            'manage_pig_mortality',
                        ),
                        self::permissionLink(
                            'Biological Assets',
                            'finance.biological-assets.index',
                            'finance/biological-assets*',
                            'fa-solid fa-chart-area',
                            'view_financial_reports',
                        ),
                        self::permissionLink(
                            'Feed Sales',
                            'finance.feed-sales.index',
                            'finance/feed-sales*',
                            'fa-solid fa-sack-dollar',
                            'view_feed_sales',
                        ),
                    ]),
                    [
                        'label' => 'AP Ageing',
                        'route' => 'finance.ageing.ap',
                        'icon'  => 'fa-solid fa-clock-rotate-left',
                        'permissions' => ['admin', 'accountant', 'superuser'],
                        // OR if using fine-grained permission strings:
                        'permission' => 'view_ap_ageing',
                    ],
                    [
                        'label' => 'Supplier Statements',
                        'route' => 'finance.supplier-statements.index',
                        'icon'  => 'fa-solid fa-file-invoice',
                        'permissions' => ['admin', 'accountant', 'superuser'],
                        'permission' => 'manage_supplier_statements',
                    ],
                    [
                        'label' => 'WHT Certificates',
                        'route' => 'finance.wht-certificates.index',
                        'icon'  => 'fa-solid fa-certificate',
                        'permissions' => ['admin', 'accountant', 'superuser'],
                        'permission' => 'manage_wht_certificates',
                    ],

                    [
                        'label' => 'Bank Accounts',
                        'route' => 'finance.bank-accounts.index',
                        'icon'  => 'fa-solid fa-building-columns',
                        'permission' => 'view_bank_accounts',
                    ],
                    [
                        'label' => 'Bank Statements',
                        'route' => 'finance.bank-statements.index',
                        'icon'  => 'fa-solid fa-file-csv',
                        'permission' => 'import_bank_statement',
                    ],
                    [
                        'label' => 'Bank Reconciliation',
                        'route' => 'finance.bank-reconciliation.index',
                        'icon'  => 'fa-solid fa-scale-balanced',
                        'permission' => 'reconcile_bank_statement',
                    ],
                ])),
            ],
            [
                'title' => 'System Settings',
                'items' => array_values(array_filter([
                    self::link('General Settings', 'settings.general', 'settings/general-settings*', 'fa-solid fa-sliders', $adminRoles),
                    self::link('Breeding Setup', 'settings.setup', 'settings/setup*', 'fa-solid fa-gear', $adminRoles),
                    self::link('Email Settings', 'settings.email', 'settings/email*', 'fa-solid fa-envelope', $adminRoles),
                    self::link('System Reports', 'settings.reports', 'settings/reports*', 'fa-solid fa-file-lines', $adminRoles),
                    self::link('Activity Log', 'settings.activity-log', 'settings/activity-log*', 'fa-solid fa-clock-rotate-left', ['superuser']),
                    self::link('User Management', 'settings.users', 'settings/users*', 'fa-solid fa-users-gear', ['superuser']),
                ])),
            ],
        ]));
    }

    public static function dashboardRoute(?User $user): string
    {
        if (! $user) {
            return route('dashboard');
        }

        if ($user->isSuperuser()) {
            return route('superuser.dashboard');
        }

        if ($user->isAdmin()) {
            return route('admin.dashboard');
        }

        if ($user->isManager()) {
            return route('manager.dashboard');
        }

        if ($user->isDataEntry()) {
            return route('dataentry.dashboard');
        }

        if ($user->isTreasurer() || $user->isAccountant()) {
            return route('finance.dashboard');
        }

        return route('dashboard');
    }

    public static function displayRole(?User $user): string
    {
        if (! $user) {
            return 'User';
        }

        return match (true) {
            $user->isSuperuser() => 'Superuser',
            $user->isAdmin() => 'Admin',
            $user->isManager() => 'Farm Manager',
            $user->isDataEntry() => 'Data Entry',
            $user->isAccountant() => 'Accountant',
            $user->isTreasurer() => 'Treasurer',
            default => 'User',
        };
    }

    /**
     * Get fallback URL for pig detail pages.
     */
    private static function getPigDetailFallback()
    {
        $referer = request()->headers->get('referer');

        if ($referer) {
            if (str_contains($referer, 'breeding-stocks')) {
                return route('breeding-stocks.index');
            }
            if (str_contains($referer, 'standard-stocks')) {
                return route('standard-stocks.index');
            }
            if (str_contains($referer, 'all-stocks')) {
                return route('all-stocks.index');
            }
        }

        return route('all-stocks.index');
    }

    /**
     * Get fallback URL for sale form pages.
     */
    private static function getSaleFormFallback()
    {
        return route('standard-stocks.index');
    }

    /**
     * Store the current URL as the intended back URL.
     */
    public static function storeBackUrl($key = 'intended_back_url')
    {
        session([$key => url()->current()]);
    }

    /**
     * Get stored back URL.
     */
    public static function getStoredBackUrl($key = 'intended_back_url', $fallback = null)
    {
        return session($key, $fallback);
    }

    /**
     * Clear stored back URL.
     */
    public static function clearStoredBackUrl($key = 'intended_back_url')
    {
        session()->forget($key);
    }

    /**
     * Generate back button HTML with dynamic URL.
     */
    public static function backButton($context = null, $classes = 'text-gray-500 hover:text-gray-700', $text = null)
    {
        $backUrl = self::getContextualBackUrl($context);
        $buttonText = $text ?? '';

        return sprintf(
            '<a href="%s" class="%s">\n                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">\n                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>\n                </svg>\n                %s\n            </a>',
            $backUrl,
            $classes,
            $buttonText,
        );
    }

    /**
     * Check if we should show a back button (not on home/dashboard).
     */
    public static function shouldShowBackButton()
    {
        $current = request()->route()?->getName();
        $excludedRoutes = ['dashboard', 'home', 'login', 'register'];

        return ! in_array($current, $excludedRoutes, true);
    }

    private static function dashboardItem(User $user): array
    {
        return self::link('Dashboard', self::dashboardRouteName($user), self::dashboardPatterns($user), 'fa-solid fa-gauge-high', User::allowedRoleNames());
    }

    private static function dashboardRouteName(User $user): string
    {
        return match (true) {
            $user->isSuperuser() => 'superuser.dashboard',
            $user->isAdmin() => 'admin.dashboard',
            $user->isManager() => 'manager.dashboard',
            $user->isDataEntry() => 'dataentry.dashboard',
            $user->isTreasurer() || $user->isAccountant() => 'finance.dashboard',
            default => 'dashboard',
        };
    }

    private static function dashboardPatterns(User $user): array|string
    {
        return match (true) {
            $user->isSuperuser() => ['superuser/dashboard*'],
            $user->isAdmin() => ['admin/dashboard*'],
            $user->isManager() => ['manager/dashboard*'],
            $user->isDataEntry() => ['dataentry/dashboard*'],
            $user->isTreasurer() || $user->isAccountant() => ['finance*'],
            default => ['dashboard'],
        };
    }

    private static function link(string $label, string $route, array|string $activePatterns, string $icon, array $roles): ?array
    {
        if (! self::currentUserHasAnyRole($roles)) {
            return null;
        }

        return [
            'type' => 'link',
            'label' => $label,
            'route' => $route,
            'active_patterns' => (array) $activePatterns,
            'icon' => $icon,
        ];
    }

    private static function group(string $label, string $icon, array|string $activePatterns, array $children, array $roles): ?array
    {
        if (! self::currentUserHasAnyRole($roles)) {
            return null;
        }

        $children = array_values(array_filter($children));

        if ($children === []) {
            return null;
        }

        return [
            'type' => 'group',
            'label' => $label,
            'icon' => $icon,
            'active_patterns' => (array) $activePatterns,
            'children' => $children,
        ];
    }

    private static function permissionLink(
        string $label,
        string $route,
        array|string $activePatterns,
        string $icon,
        array|string $permissions,
    ): ?array {
        $user = auth()->user();

        if (! $user || ! $user->hasAnyPermission($permissions)) {
            return null;
        }

        return [
            'type' => 'link',
            'label' => $label,
            'route' => $route,
            'active_patterns' => (array) $activePatterns,
            'icon' => $icon,
        ];
    }

    private static function permissionGroup(
        string $label,
        string $icon,
        array|string $activePatterns,
        array $children,
    ): ?array {
        $children = array_values(array_filter($children));

        if ($children === []) {
            return null;
        }

        return [
            'type' => 'group',
            'label' => $label,
            'icon' => $icon,
            'active_patterns' => (array) $activePatterns,
            'children' => $children,
        ];
    }

    private static function currentUserHasAnyRole(array $roles): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach ($roles as $role) {
            if ($user->hasRoleName($role)) {
                return true;
            }
        }

        return false;
    }
}
