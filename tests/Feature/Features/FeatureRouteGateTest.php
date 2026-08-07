<?php

namespace Tests\Feature\Features;

use App\Models\Organization;
use App\Models\OrganizationFeatureFlag;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Route-level feature gating — Phase 1 (prompt §110).
 *
 * Two separate properties are under test, and conflating them is the mistake
 * this file exists to prevent:
 *
 *   1. A disabled feature must not be reachable by typing the URL. Hiding a
 *      nav link is not gating.
 *
 *   2. An ENABLED feature must still enforce permission. This is the one that
 *      matters. Blueprint §32 and prompt §11 both warn that feature flags can
 *      be mistaken for authorization (logged as SEC-001). If someone ever
 *      "simplifies" a route by dropping `permission:` because `feature:` is
 *      already there, an administrator's presentation choice silently becomes
 *      the only thing standing between a user and the ledger.
 *      test_enabled_feature_still_enforces_permission is the regression guard.
 */
class FeatureRouteGateTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'cf-' . uniqid(), 'name' => 'Chipper Farms', 'is_active' => true,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        OrganizationFeatureFlag::query()->delete();

        Route::middleware(['web', 'auth', 'feature:' . FeatureCatalog::FINANCE_WHT])
            ->get('/__test/feature-only', fn () => response('reached', 200));

        Route::middleware([
            'web', 'auth',
            'feature:' . FeatureCatalog::FINANCE_WHT,
            'permission:manage_wht_certificates',
        ])->get('/__test/feature-and-permission', fn () => response('reached', 200));
    }

    protected function service(): OrganizationFeatureService
    {
        return app(OrganizationFeatureService::class);
    }

    protected function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'tester_' . uniqid()]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'Test permission'],
            );
            $role->permissions()->attach($permission->id);
        }

        return User::forceCreate([
            'name' => 'Tester', 'email' => 'tester_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => $role->name,
            'role_id' => $role->id,
            'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }

    /* ------------------------------------------------------------------ */

    public function test_disabled_feature_is_not_reachable_by_typing_the_url(): void
    {
        $this->service()->disable(FeatureCatalog::FINANCE_WHT);

        $this->actingAs($this->userWithPermissions(['manage_wht_certificates']))
            ->get('/__test/feature-only')
            ->assertNotFound();
    }

    public function test_enabled_feature_is_reachable(): void
    {
        $this->service()->enable(FeatureCatalog::FINANCE_WHT);

        $this->actingAs($this->userWithPermissions(['manage_wht_certificates']))
            ->get('/__test/feature-only')
            ->assertOk()
            ->assertSee('reached');
    }

    /**
     * The security-critical case. A feature being switched ON must not grant
     * anything. Permission remains authoritative.
     */
    public function test_enabled_feature_still_enforces_permission(): void
    {
        $this->service()->enable(FeatureCatalog::FINANCE_WHT);

        $this->actingAs($this->userWithPermissions([]))   // no permissions at all
            ->get('/__test/feature-and-permission')
            ->assertForbidden();
    }

    public function test_feature_and_permission_together_admit_the_user(): void
    {
        $this->service()->enable(FeatureCatalog::FINANCE_WHT);

        $this->actingAs($this->userWithPermissions(['manage_wht_certificates']))
            ->get('/__test/feature-and-permission')
            ->assertOk();
    }

    /**
     * Order of failure matters less than the fact that BOTH gates hold. A user
     * with no permission hitting a disabled feature must be refused, not
     * admitted by whichever check happens to run second.
     */
    public function test_neither_gate_alone_is_sufficient(): void
    {
        $this->service()->disable(FeatureCatalog::FINANCE_WHT);

        $response = $this->actingAs($this->userWithPermissions([]))
            ->get('/__test/feature-and-permission');

        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            'A user with neither the feature nor the permission was admitted.',
        );
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_a_disabled_ancestor_closes_the_route_even_when_the_leaf_is_on(): void
    {
        $svc = $this->service();
        $svc->enable(FeatureCatalog::FINANCE_WHT);

        // Break the chain directly, as a hand-edited row would.
        OrganizationFeatureFlag::where('organization_id', $this->org->id)
            ->where('feature_key', FeatureCatalog::FINANCE_ADVANCED_AP)
            ->update(['enabled' => false]);
        $svc->forget();

        $this->actingAs($this->userWithPermissions(['manage_wht_certificates']))
            ->get('/__test/feature-only')
            ->assertNotFound();
    }

    public function test_unauthenticated_access_is_refused(): void
    {
        $this->service()->enable(FeatureCatalog::FINANCE_WHT);

        $this->get('/__test/feature-only')->assertRedirect();
    }

    /**
     * A typo in a route's feature key must fail loudly at request time rather
     * than resolving to "off" and producing a 404 nobody can explain.
     */
    public function test_an_unknown_feature_key_in_a_route_is_a_hard_error(): void
    {
        Route::middleware(['web', 'auth', 'feature:finance.typo_here'])
            ->get('/__test/bad-key', fn () => response('reached', 200));

        $this->expectException(\InvalidArgumentException::class);

        $this->withoutExceptionHandling()
            ->actingAs($this->userWithPermissions([]))
            ->get('/__test/bad-key');
    }
}
