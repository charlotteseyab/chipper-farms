<?php

namespace App\Helpers;

use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use App\Support\Features\FinanceNavigation;
use Illuminate\Support\Facades\Route;

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

        if ($user->isSuperuser()) {
            return [
                [
                    'title' => 'Platform',
                    'items' => array_values(array_filter([
                        self::dashboardItem($user),
                        self::link('Farm Organizations', 'settings.organizations', 'settings/organizations*', 'fa-solid fa-building', ['superuser']),
                        self::link('User Management', 'settings.users', 'settings/users*', 'fa-solid fa-users-gear', ['superuser']),
                    ])),
                ],
                [
                    'title' => 'System Settings',
                    'items' => array_values(array_filter([
                        self::link('General Settings', 'settings.general', 'settings/general-settings*', 'fa-solid fa-sliders', ['superuser']),
                        self::link('Breeding Setup', 'settings.setup', 'settings/setup*', 'fa-solid fa-gear', ['superuser']),
                        self::link('Email Settings', 'settings.email', 'settings/email*', 'fa-solid fa-envelope', ['superuser']),
                        self::link('Modules & Features', 'settings.modules', 'settings/modules*', 'fa-solid fa-toggle-on', ['superuser']),
                        self::link('System Reports', 'settings.reports', 'settings/reports*', 'fa-solid fa-file-lines', ['superuser']),
                        self::link('Activity Log', 'settings.activity-log', 'settings/activity-log*', 'fa-solid fa-clock-rotate-left', ['superuser']),
                    ])),
                ],
            ];
        }

        if ($user->isAccountant()) {
            return [
                [
                    'title' => 'Finance',
                    'items' => self::financeItems($user),
                ],
            ];
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
                    self::link('Purchased Stock Intake', 'purchase-stock-intakes.index', 'purchase-stock-intakes*', 'fa-solid fa-truck-ramp-box', $managementRoles),
                    self::link('Breeding Stock', 'breeding-stocks.index', 'breeding-stocks*', 'fa-solid fa-venus-mars', $allRoles),
                    self::link('Standard Stock', 'standard-stocks.index', 'standard-stocks*', 'fa-solid fa-paw', $allRoles),
                    self::link('Audit Trail', 'audit-trail.index', ['audit-trail*', 'pigs/archived*'], 'fa-solid fa-clock-rotate-left', ['superuser', 'admin', 'farmmanager', 'dataentry', 'accountant', 'treasurer', 'salesperson', 'mill_operator']),
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
                        self::link('Weight Audit', 'weights.audit.index', 'weights/audit*', 'fa-solid fa-clock-rotate-left', $allRoles),
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
            self::storefrontSection($user),
            [
                'title' => 'Finance',
                // Phase 16 (IMP-070). This section used to be ~240 lines of
                // hardcoded role arrays built right here, while
                // FinanceNavigation — the feature-and-permission-aware builder
                // Phase 3 wrote for exactly this job — was referenced by nothing
                // but its own tests.
                //
                // The consequence was not cosmetic. The menu decided what to
                // show from the user's ROLE and knew nothing about organisation
                // feature flags, so after Phase 14 gated the advanced surfaces a
                // farm on Essentials was still offered Vendor Invoices and Chart
                // of Accounts in its sidebar, and got a 404 for clicking them.
                // That is the §46 dead end, created by the navigation itself.
                'items' => self::financeItems($user),
            ],
            [
                'title' => 'System Settings',
                'items' => array_values(array_filter([
                    self::link('General Settings', 'settings.general', 'settings/general-settings*', 'fa-solid fa-sliders', $adminRoles),
                    self::link('Breeding Setup', 'settings.setup', 'settings/setup*', 'fa-solid fa-gear', $adminRoles),
                    self::link('Bulk Weight Standards', 'weight-standards.bulk-create', 'weight-standards-bulk*', 'fa-solid fa-ruler-combined', ['admin']),
                    self::link('Email Settings', 'settings.email', 'settings/email*', 'fa-solid fa-envelope', $adminRoles),
                    self::link('Modules & Features', 'settings.modules', 'settings/modules*', 'fa-solid fa-toggle-on', ['superuser']),
                    self::link('System Reports', 'settings.reports', 'settings/reports*', 'fa-solid fa-file-lines', $adminRoles),
                    self::link('Activity Log', 'settings.activity-log', 'settings/activity-log*', 'fa-solid fa-clock-rotate-left', ['superuser', 'admin']),
                    self::link('Farm Organizations', 'settings.organizations', 'settings/organizations*', 'fa-solid fa-building', ['superuser']),
                    self::link('User Management', 'settings.users', 'settings/users*', 'fa-solid fa-users-gear', ['superuser', 'admin']),
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
            return route('finance.overview');
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

    /**
     * Every authenticated user gets a Dashboard link.
     *
     * This previously delegated to self::link() passing User::allowedRoleNames()
     * as the permitted roles, intending "all roles". But link() returns null
     * when the current user's role is not in the list, and this method declares
     * a non-nullable array return — so any user whose role is absent from
     * ALLOWED_ROLE_NAMES triggered:
     *
     *   NavigationHelper::dashboardItem(): Return value must be of type array,
     *   null returned (View: layouts/app.blade.php)
     *
     * That is a 500 on EVERY page rendering the app layout, not a missing menu
     * entry. `mill_operator` is exactly such a role: the seeders provision it
     * with real permissions (view_inventory_value, and the feed permissions
     * added in remediation Phase 2) but it is not in ALLOWED_ROLE_NAMES, so the
     * system could provision a user it could not then serve a single page to.
     *
     * The role filter is dropped rather than the role list extended: the intent
     * was "everyone", and dashboardRouteName()/dashboardPatterns() already have
     * default branches that handle any unrecognised role. Building the item
     * directly makes the method total, so no role can 500 the layout again.
     */
    private static function dashboardItem(User $user): array
    {
        return [
            'type' => 'link',
            'label' => 'Dashboard',
            'route' => self::dashboardRouteName($user),
            'active_patterns' => (array) self::dashboardPatterns($user),
            'icon' => 'fa-solid fa-gauge-high',
        ];
    }

    private static function dashboardRouteName(User $user): string
    {
        return match (true) {
            $user->isSuperuser() => 'superuser.dashboard',
            $user->isAdmin() => 'admin.dashboard',
            $user->isManager() => 'manager.dashboard',
            $user->isDataEntry() => 'dataentry.dashboard',
            $user->isTreasurer() || $user->isAccountant() => 'finance.overview',
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

    /**
     * The Finance section, delegated to the builder that knows the rules.
     *
     * `FinanceNavigation::build()` admits an entry only when the organisation's
     * feature is on, the user holds the permission, and the route exists. This
     * method does nothing but translate its shape into the one the sidebar
     * partial renders — the decisions stay in one place.
     *
     * Showing a link is not granting access. Every route here is independently
     * guarded by `feature:` and `permission:` middleware, and it stays that way:
     * a menu that hides an entry protects nothing on its own.
     *
     * @return list<array<string, mixed>>
     */
    private static function financeItems(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $items = [];

        foreach (FinanceNavigation::build($user) as $entry) {
            $children = [];

            foreach ($entry['children'] ?? [] as $child) {
                $children[] = [
                    'type'            => 'link',
                    'label'           => $child['label'],
                    'route'           => $child['route'],
                    'active_patterns' => [$child['active']],
                    'icon'            => 'fa-solid fa-angle-right',
                ];
            }

            $items[] = $children === []
                ? [
                    'type'            => 'link',
                    'label'           => $entry['label'],
                    'route'           => $entry['route'],
                    'active_patterns' => [$entry['active']],
                    'icon'            => $entry['icon'],
                ]
                : [
                    'type'            => 'group',
                    'label'           => $entry['label'],
                    'icon'            => $entry['icon'],
                    'active_patterns' => [$entry['active']],
                    'children'        => $children,
                ];
        }

        return $items;
    }

    /**
     * Storefront back-office navigation, built from route existence, permissions
     * and the feature flags that materially change available workflows.
     *
     * @return array{title:string, items:list<array<string, mixed>>}|null
     */
    private static function storefrontSection(User $user): ?array
    {
        $items = array_values(array_filter([
            self::permissionLinkFor($user, 'Overview', 'storefront.admin.dashboard', 'storefront-admin', 'fa-solid fa-store', 'view_storefront_dashboard'),
            self::permissionLinkFor($user, 'Listings', 'storefront.admin.listings.index', 'storefront-admin/listings*', 'fa-solid fa-tags', 'view_storefront_listings'),
            self::permissionLinkFor($user, 'Push to Storefront', 'storefront.admin.push.create', 'storefront-admin/push*', 'fa-solid fa-arrow-up-right-from-square', 'push_to_storefront'),
            self::permissionLinkFor($user, 'Orders', 'storefront.admin.orders.index', 'storefront-admin/orders*', 'fa-solid fa-receipt', 'view_storefront_orders'),
            self::storefrontQuotesLink($user),
            self::permissionLinkFor($user, 'Placements', 'storefront.admin.placements.index', 'storefront-admin/placements*', 'fa-solid fa-star', 'manage_storefront_placements'),
            self::permissionLinkFor($user, 'Reports', 'storefront.admin.reports.sales', 'storefront-admin/reports*', 'fa-solid fa-chart-column', 'view_storefront_reports'),
            $user->isAdmin()
                ? self::permissionLinkFor($user, 'Settings', 'storefront.admin.settings.index', 'storefront-admin/settings*', 'fa-solid fa-sliders', 'manage_storefront_settings')
                : null,
            self::storefrontShopLink($user),
        ]));

        if ($items === []) {
            return null;
        }

        return [
            'title' => 'Storefront',
            'items' => $items,
        ];
    }

    private static function storefrontQuotesLink(User $user): ?array
    {
        if (! app(OrganizationFeatureService::class)->usable(FeatureCatalog::STOREFRONT_QUOTES)) {
            return null;
        }

        return self::permissionLinkFor(
            $user,
            'Quotes',
            'storefront.admin.quotes.index',
            'storefront-admin/quotes*',
            'fa-solid fa-file-signature',
            'view_storefront_quotes',
        );
    }

    private static function storefrontShopLink(User $user): ?array
    {
        $storefrontPermissions = [
            'view_storefront_dashboard',
            'view_storefront_listings',
            'push_to_storefront',
            'view_storefront_orders',
            'view_storefront_quotes',
            'view_storefront_reports',
            'manage_storefront_settings',
        ];

        if (! Route::has('storefront.home') || ! $user->hasAnyPermission($storefrontPermissions)) {
            return null;
        }

        return [
            'type' => 'link',
            'label' => 'View Store',
            'route' => 'storefront.home',
            'active_patterns' => ['shop*'],
            'icon' => 'fa-solid fa-cart-shopping',
        ];
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

    private static function permissionLinkFor(
        User $user,
        string $label,
        string $route,
        array|string $activePatterns,
        string $icon,
        array|string $permissions,
    ): ?array {
        if (! Route::has($route) || ! $user->hasAnyPermission($permissions)) {
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
