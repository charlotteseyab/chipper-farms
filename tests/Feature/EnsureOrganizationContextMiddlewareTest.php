<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureOrganizationContext;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Covers the EnsureOrganizationContext middleware:
 *   - Unauthenticated requests pass through (auth is a separate concern).
 *   - Authenticated user without organization_id → 403.
 *   - Authenticated user whose organization is soft-deleted → 403.
 *   - Authenticated user whose organization is inactive → 403.
 *   - Happy path: organization is bound on the resolver for the request.
 */
class EnsureOrganizationContextMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a simple test route that runs the middleware and returns
        // whatever the CurrentOrganization resolver reports, so assertions
        // can inspect the bound organization.
        Route::middleware([
            'web',
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            EnsureOrganizationContext::class,
        ])->get('/__test__/org-context', function (CurrentOrganization $current) {
            return response()->json([
                'organization_id' => $current->id(),
            ]);
        });
    }

    public function test_unauthenticated_request_passes_through(): void
    {
        $response = $this->get('/__test__/org-context');

        $response->assertOk();
        $response->assertExactJson(['organization_id' => null]);
    }

    public function test_user_without_organization_id_is_rejected(): void
    {
        // NOT persisted. users.organization_id is NOT NULL, so this row cannot
        // exist — organisation scoping is enforced at the database level, which
        // is a stronger guarantee than the middleware alone.
        //
        // The middleware's rejection branch is still worth keeping and testing:
        // it guards against a model hydrated from a raw query, a future nullable
        // column, or a guard that returns a user built in memory. An unsaved
        // instance exercises that branch honestly, rather than asserting the
        // database permits a row it rejects.
        $user = new User();
        $user->forceFill([
            'id'              => 999999,
            'name'            => 'No Org',
            'email'           => 'noorg@example.com',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/__test__/org-context');

        $response->assertForbidden();
    }

    public function test_user_whose_organization_is_soft_deleted_is_rejected(): void
    {
        $org = Organization::create(['slug' => 'ghost', 'name' => 'Ghost']);

        $user = User::forceCreate([
            'name' => 'Ghost User',
            'email' => 'ghost@example.com',
            'password' => bcrypt('x'),
            'organization_id' => $org->id,
        ]);

        $org->delete();

        $response = $this->actingAs($user)->get('/__test__/org-context');

        $response->assertForbidden();
    }

    public function test_user_whose_organization_is_inactive_is_rejected(): void
    {
        $org = Organization::create([
            'slug' => 'dormant',
            'name' => 'Dormant',
            'is_active' => false,
        ]);

        $user = User::forceCreate([
            'name' => 'Dormant User',
            'email' => 'dormant@example.com',
            'password' => bcrypt('x'),
            'organization_id' => $org->id,
        ]);

        $response = $this->actingAs($user)->get('/__test__/org-context');

        $response->assertForbidden();
    }

    public function test_happy_path_binds_organization_on_resolver(): void
    {
        $org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        $user = User::forceCreate([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('x'),
            'organization_id' => $org->id,
        ]);

        $response = $this->actingAs($user)->get('/__test__/org-context');

        $response->assertOk();
        $response->assertExactJson(['organization_id' => $org->id]);
    }
}