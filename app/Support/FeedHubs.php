<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The feed module's tabs: one per job, the same on every feed screen.
 *
 *   Today      feed the animals (every day)
 *   Mix & buy  what to mix and what to buy (once a week)
 *   Stock      the feed and ingredients there are
 *   Setup      feeds, recipes and rations (once)
 *
 * Tabs a user may not open are hidden. A tab may have a fallback route for
 * users who may not open its main one: Today opens the feeding form for
 * whoever records feedings and the read-only overview for whoever reviews
 * them.
 */
class FeedHubs
{
    public const TODAY = 'today';
    public const MILL = 'mill';
    public const STORE = 'store';
    public const SETUP = 'setup';

    /**
     * label, route, active route names, permissions (any one is enough; empty = everyone who reaches the page),
     * and an optional fallback {route, label, permissions} for users who may not open the main route.
     *
     * @return array<string, array{title: string, tabs: list<array{label: string, route: string, active: list<string>, permissions: list<string>, fallback?: array}>}>
     */
    public static function all(): array
    {
        // Every feed screen shares the same four tabs: one per job.
        $tabs = [
            ['label' => 'Today', 'route' => 'feed.issues.create', 'hint' => 'Feed the animals · every day',
                'active' => ['feed.issues.*', 'feed-dashboard.index', 'feed.planned-vs-actual', 'feed-performance.index'], 'permissions' => ['manage_feed_issues'],
                'fallback' => ['route' => 'feed-dashboard.index', 'label' => 'Today', 'permissions' => ['view_feed_production']]],
            ['label' => 'Mix & buy', 'route' => 'feed.mix-buy', 'hint' => 'What to mix and buy · once a week',
                'active' => ['feed.mix-buy', 'mill.*', 'feed-production.*', 'planning-reports.index'], 'permissions' => ['view_feed_production']],
            ['label' => 'Stock', 'route' => 'raw-materials.index', 'hint' => 'Feed and ingredients you have',
                'active' => ['raw-materials.*', 'stock-counts.*', 'purchase-requests.*'], 'permissions' => ['view_raw_materials', 'manage_raw_materials'],
                'fallback' => ['route' => 'purchase-requests.index', 'label' => 'Purchases', 'permissions' => []]],
            ['label' => 'Setup', 'route' => 'feed-setup.index', 'hint' => 'Feeds, recipes and rations · once',
                'active' => ['feed-setup.*'], 'permissions' => ['view_feed_production']],
        ];

        return [
            self::TODAY => ['title' => 'Feed', 'tabs' => $tabs],
            self::MILL => ['title' => 'Feed', 'tabs' => $tabs],
            self::STORE => ['title' => 'Feed', 'tabs' => $tabs],
            self::SETUP => ['title' => 'Feed', 'tabs' => $tabs],
        ];
    }

    /** The tabs of one page that this user may open, with which one is current. */
    public static function tabsFor(string $hub, ?User $user): array
    {
        $may = fn (array $permissions) => $permissions === [] || ($user && $user->hasAnyPermission($permissions));

        return collect(self::all()[$hub]['tabs'] ?? [])
            ->map(function (array $tab) use ($may) {
                if (! $may($tab['permissions']) && isset($tab['fallback']) && $may($tab['fallback']['permissions'])) {
                    $tab = array_merge($tab, $tab['fallback'], ['active' => $tab['active']]);
                }

                return $tab;
            })
            ->filter(fn (array $tab) => Route::has($tab['route']) && $may($tab['permissions']))
            ->map(fn (array $tab) => $tab + ['current' => request()->routeIs(...$tab['active'])])
            ->values()
            ->all();
    }

    public static function title(string $hub): string
    {
        return self::all()[$hub]['title'] ?? 'Feed';
    }
}
