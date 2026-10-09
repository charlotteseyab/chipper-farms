<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The feed module's four pages, grouped by who does the work, and the tabs
 * under each. Every existing feed screen is one tab; the sidebar shows only
 * the four pages. Tabs a user may not open are hidden.
 */
class FeedHubs
{
    public const TODAY = 'today';
    public const MILL = 'mill';
    public const STORE = 'store';
    public const SETUP = 'setup';

    /**
     * label, route, active route names, permissions (any one is enough; empty = everyone who reaches the page).
     *
     * @return array<string, array{title: string, tabs: list<array{label: string, route: string, active: list<string>, permissions: list<string>}>}>
     */
    public static function all(): array
    {
        return [
            self::TODAY => ['title' => 'Feed Today', 'tabs' => [
                ['label' => 'Overview', 'route' => 'feed-dashboard.index', 'active' => ['feed-dashboard.index'], 'permissions' => ['view_feed_production']],
                ['label' => 'Record feeding', 'route' => 'feed.issues.create', 'active' => ['feed.issues.create'], 'permissions' => ['manage_feed_issues']],
                ['label' => 'Feedings', 'route' => 'feed.issues.index', 'active' => ['feed.issues.index', 'feed.issues.show'], 'permissions' => ['manage_feed_issues', 'view_group_pools']],
                ['label' => 'Planned vs actual', 'route' => 'feed.planned-vs-actual', 'active' => ['feed.planned-vs-actual', 'planning-reports.index'], 'permissions' => ['view_feed_production']],
                ['label' => 'Feed conversion', 'route' => 'feed-performance.index', 'active' => ['feed-performance.index'], 'permissions' => ['view_feed_production']],
            ]],
            self::MILL => ['title' => 'Mill', 'tabs' => [
                ['label' => 'Plan', 'route' => 'mill.plan', 'active' => ['mill.plan', 'planning-reports.index'], 'permissions' => ['view_feed_production']],
                ['label' => 'Mix & batches', 'route' => 'feed-production.index', 'active' => ['feed-production.index'], 'permissions' => ['view_feed_production']],
            ]],
            self::STORE => ['title' => 'Store', 'tabs' => [
                ['label' => 'Raw materials', 'route' => 'raw-materials.index', 'active' => ['raw-materials.index'], 'permissions' => ['view_raw_materials', 'manage_raw_materials']],
                ['label' => 'Stock counts', 'route' => 'stock-counts.index', 'active' => ['stock-counts.index', 'stock-counts.create', 'stock-counts.show'], 'permissions' => ['manage_raw_materials', 'manage_feed_production']],
                ['label' => 'Purchase requests', 'route' => 'purchase-requests.index', 'active' => ['purchase-requests.*'], 'permissions' => []],
            ]],
            self::SETUP => ['title' => 'Feed Setup', 'tabs' => [
                ['label' => 'Checklist & recipes', 'route' => 'feed-setup.index', 'active' => ['feed-setup.index'], 'permissions' => ['view_feed_production']],
                ['label' => 'Ration history & settings', 'route' => 'feed-setup.settings', 'active' => ['feed-setup.settings', 'planning-reports.index'], 'permissions' => ['view_feed_production']],
            ]],
        ];
    }

    /** The tabs of one page that this user may open, with which one is current. */
    public static function tabsFor(string $hub, ?User $user): array
    {
        return collect(self::all()[$hub]['tabs'] ?? [])
            ->filter(fn (array $tab) => Route::has($tab['route'])
                && ($tab['permissions'] === [] || ($user && $user->hasAnyPermission($tab['permissions']))))
            ->map(fn (array $tab) => $tab + ['current' => request()->routeIs(...$tab['active'])])
            ->values()
            ->all();
    }

    public static function title(string $hub): string
    {
        return self::all()[$hub]['title'] ?? 'Feed';
    }
}
