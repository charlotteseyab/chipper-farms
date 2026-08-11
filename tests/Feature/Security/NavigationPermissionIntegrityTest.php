<?php

namespace Tests\Feature\Security;

use App\Models\Permission;
use App\Support\Features\FinanceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Phase 18 — a menu entry may only ask for permissions that exist, and only for
 * permissions its own route actually checks.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * Phase 16 found `FinanceNavigation` asking for `view_finance_dashboard`, a
 * permission no seeder creates. A grant that is never seeded is held by nobody,
 * so `hasAnyPermission()` is false for every user except superuser (who bypasses
 * via `Gate::before`) and the entry silently disappears.
 *
 * Phase 16 fixed the two occurrences it found — one by a failing test, one by a
 * sweep. The sweep was broken: it matched middleware by ALIAS (`permission:`)
 * while `route:list` reports the RESOLVED class
 * (`App\Http\Middleware\PermissionMiddleware:...`), so it compared against empty
 * lists and reported no mismatches for the wrong reason.
 *
 * Redone properly in Phase 18, it found **eleven more**. Six menu entries —
 * Chart of Accounts, Journal Entries, Bank Statements, Bank Reconciliation,
 * Payment Runs, Supplier Statements and Vendor Invoices — asked exclusively for
 * permissions that do not exist, and so had been invisible to every non-superuser
 * since Phase 16 wired the sidebar up.
 *
 * TWO RULES, BOTH ENFORCED HERE
 * -----------------------------
 *  1. Every permission a menu entry names must be one a seeder creates.
 *     Otherwise the entry is dead for everyone.
 *
 *  2. At least one of them must be a permission the route itself enforces.
 *     Otherwise the menu is stricter than the page, and hides a screen the user
 *     is entitled to open — which is the same dead end from the other side.
 *
 * Neither rule is about authorization. The route middleware remains the control
 * (SEC-001); these rules only stop the menu from lying about it.
 */
class NavigationPermissionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /**
     * Every (route, permissions) pair the finance menu can offer.
     *
     * Read out of the class rather than restated, so the test cannot drift away
     * from what the menu actually asks for. `build()` filters by the current
     * user, so the raw definitions are read by reflection instead — the point is
     * to check every entry, including ones no test user would see.
     *
     * @return array<string, list<string>>
     */
    protected function navigationPermissions(): array
    {
        $source = file_get_contents(app_path('Support/Features/FinanceNavigation.php'));
        $pairs  = [];

        // Top-level entries: 'route' => '...', ... 'permission' => [...]
        preg_match_all(
            "/'route'\s*=>\s*'([^']+)'.*?'permission'\s*=>\s*\[([^\]]*)\]/s",
            $source,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $pairs[$match[1]] = $this->quotedStrings($match[2]);
        }

        // advanced() and childrenFor() entries: ['Label', 'route', 'active', Feature, [perms]]
        preg_match_all(
            "/\[\s*'[^']+',\s*'(finance\.[^']+)',\s*'[^']*',\s*FeatureCatalog::\w+,\s*\[([^\]]*)\]\s*\]/s",
            $source,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $pairs[$match[1]] = $this->quotedStrings($match[2]);
        }

        return $pairs;
    }

    /** @return list<string> */
    protected function quotedStrings(string $fragment): array
    {
        preg_match_all("/'([^']+)'/", $fragment, $found);

        return $found[1];
    }

    /**
     * The permissions a route's own middleware enforces.
     *
     * Matched on the resolved middleware class, not the alias — matching the
     * alias is precisely how the Phase 16 sweep silently found nothing.
     *
     * @return list<string>
     */
    protected function routePermissions(string $routeName): array
    {
        $route = RouteFacade::getRoutes()->getByName($routeName);

        if ($route === null) {
            return [];
        }

        $permissions = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            // BOTH forms, deliberately. `gatherMiddleware()` returns the ALIAS
            // (`permission:view_finance`) while `route:list` reports the
            // RESOLVED class (`...\PermissionMiddleware:view_finance`).
            //
            // This helper was written matching only the class, and returned an
            // empty list for every route — reproducing, inside the test meant to
            // catch it, the exact bug that let eleven mismatches through in
            // Phase 16. Kept as a two-branch check rather than a tidier one so
            // the next person sees why.
            $isPermission = str_starts_with($middleware, 'permission:')
                || str_contains($middleware, 'PermissionMiddleware:');

            if (! $isPermission) {
                continue;
            }

            foreach (explode(',', explode(':', $middleware, 2)[1]) as $permission) {
                $permissions[] = trim($permission);
            }
        }

        return $permissions;
    }

    public function test_the_menu_never_asks_for_a_permission_that_does_not_exist(): void
    {
        $seeded  = Permission::pluck('name')->all();
        $phantom = [];

        foreach ($this->navigationPermissions() as $route => $permissions) {
            foreach ($permissions as $permission) {
                if (! in_array($permission, $seeded, true)) {
                    $phantom[] = "{$route} asks for '{$permission}'";
                }
            }
        }

        $this->assertSame(
            [],
            $phantom,
            "These menu entries ask for permissions no seeder creates, so nobody holds them and the "
            . "entry is invisible to every user except superuser:\n  " . implode("\n  ", $phantom)
        );
    }

    public function test_every_menu_entry_shares_a_permission_with_its_own_route(): void
    {
        $stricter = [];

        foreach ($this->navigationPermissions() as $route => $menuPermissions) {
            $routePermissions = $this->routePermissions($route);

            // A route with no permission middleware of its own is governed by an
            // outer boundary; there is nothing to agree with.
            if ($routePermissions === []) {
                continue;
            }

            if (array_intersect($menuPermissions, $routePermissions) === []) {
                $stricter[] = sprintf(
                    '%s — menu asks [%s], route enforces [%s]',
                    $route,
                    implode(', ', $menuPermissions),
                    implode(', ', $routePermissions),
                );
            }
        }

        $this->assertSame(
            [],
            $stricter,
            "These menu entries demand something their own route does not check, so the screen is "
            . "hidden from users who are entitled to open it:\n  " . implode("\n  ", $stricter)
        );
    }

    /**
     * The sweep must be reading real entries.
     *
     * A regex that matched nothing would leave both tests above green and
     * meaningless — which is exactly the failure mode this file was written to
     * correct.
     */
    public function test_the_sweep_reads_the_real_navigation_definitions(): void
    {
        $pairs = $this->navigationPermissions();

        $this->assertGreaterThan(
            25,
            count($pairs),
            'Only ' . count($pairs) . ' navigation entries were parsed, which is fewer than '
            . 'FinanceNavigation defines. The sweep is not reading what it thinks it is.'
        );

        $this->assertArrayHasKey('finance.overview', $pairs);
        $this->assertNotEmpty(
            $this->routePermissions('finance.overview'),
            'Route permissions are being read as empty, which is how the Phase 16 sweep '
            . 'reported no mismatches while eleven existed.'
        );
    }
}
