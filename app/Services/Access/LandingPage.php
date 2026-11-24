<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Where a user lands: after login and from the sidebar's Dashboard link.
 *
 * Each role has a usual dashboard, but a farm can customise a role's
 * permissions (Settings → Roles & access): an accountant may hold livestock
 * access and no finance access at all. Landing on a page that then answers 403
 * is a dead end, so the usual dashboard is used only when it would open, then
 * the first other dashboard that would, and finally the generic dashboard,
 * which every signed-in user can open and which shows only what they may see.
 *
 * The role dashboards (admin, manager, data entry, superuser) are admitted by
 * role alone and filter their own panels, so they always open for their role.
 */
class LandingPage
{
    /** Permission-gated dashboards, tried in this order when the usual one is closed. */
    private const FALLBACKS = ['finance.overview', 'storefront.admin.dashboard'];

    public const GENERIC = 'dashboard';

    public static function routeName(User $user): string
    {
        $usual = match (true) {
            $user->isSuperuser() => 'superuser.dashboard',
            $user->isAdmin() => 'admin.dashboard',
            $user->isManager() => 'manager.dashboard',
            $user->isDataEntry() => 'dataentry.dashboard',
            $user->isAccountant() || $user->isTreasurer() => 'finance.overview',
            $user->isCustomer() => 'storefront.account.dashboard',
            $user->isSalesperson() => 'storefront.admin.dashboard',
            default => self::GENERIC,
        };

        $access = DashboardAccess::for($user);
        foreach (array_unique([$usual, ...self::FALLBACKS]) as $route) {
            if (Route::has($route) && $access->canOpen($route)) {
                return $route;
            }
        }

        return self::GENERIC;
    }

    /**
     * Sidebar patterns that mark the Dashboard link active.
     *
     * @return list<string>
     */
    public static function activePatterns(string $routeName): array
    {
        return match ($routeName) {
            'superuser.dashboard' => ['superuser/dashboard*'],
            'admin.dashboard' => ['admin/dashboard*'],
            'manager.dashboard' => ['manager/dashboard*'],
            'dataentry.dashboard' => ['dataentry/dashboard*'],
            'finance.overview' => ['finance*'],
            'storefront.admin.dashboard' => ['storefront-admin'],
            default => ['dashboard'],
        };
    }
}
