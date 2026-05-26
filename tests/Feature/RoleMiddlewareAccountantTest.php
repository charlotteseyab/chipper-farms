<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Covers the accountant role's integration with the existing RoleMiddleware.
 *
 *   - Unauthenticated → redirect to login (existing behaviour preserved).
 *   - Accountant user reaches 'role:accountant' routes.
 *   - Non-accountant users are rejected with 403.
 *   - Multi-role syntax (role:accountant,superuser) works for both.
 */
class RoleMiddlewareAccountantTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        // Routes under test — all protected by 'role:' with various combinations.
        Route::middleware(['web', 'auth', RoleMiddleware::class . ':accountant'])
            ->get('/__test__/accountant-only', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', 'auth', RoleMiddleware::class . ':accountant,superuser'])
            ->get('/__test__/accountant-or-superuser', fn () => response()->json(['ok' => true]));
    }

    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName),
            'email' => $roleName . '@example.com',
            'password' => bcrypt('x'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->get('/__test__/accountant-only');

        $response->assertRedirect(route('login'));
    }

    public function test_accountant_can_access_accountant_only_route(): void
    {
        $user = $this->makeUser('accountant');

        $response = $this->actingAs($user)->get('/__test__/accountant-only');

        $response->assertOk();
        $response->assertExactJson(['ok' => true]);
    }

    public function test_admin_is_denied_on_accountant_only_route(): void
    {
        $user = $this->makeUser('admin');

        $response = $this->actingAs($user)->get('/__test__/accountant-only');

        $response->assertForbidden();
    }

    public function test_data_entry_is_denied_on_accountant_only_route(): void
    {
        $user = $this->makeUser('dataentry');

        $response = $this->actingAs($user)->get('/__test__/accountant-only');

        $response->assertForbidden();
    }

    public function test_farm_manager_is_denied_on_accountant_only_route(): void
    {
        $user = $this->makeUser('farmmanager');

        $response = $this->actingAs($user)->get('/__test__/accountant-only');

        $response->assertForbidden();
    }

    public function test_superuser_is_denied_on_accountant_only_route(): void
    {
        // Deliberate: strict role checks. Superuser elevation is granted
        // explicitly by naming the role in the middleware list, not by
        // universal override. If you want superuser-passes-everything,
        // that is a separate policy decision.
        $user = $this->makeUser('superuser');

        $response = $this->actingAs($user)->get('/__test__/accountant-only');

        $response->assertForbidden();
    }

    public function test_multi_role_syntax_accepts_either_role(): void
    {
        $accountant = $this->makeUser('accountant');
        $this->actingAs($accountant)
            ->get('/__test__/accountant-or-superuser')
            ->assertOk();

        auth()->logout();

        $superuser = $this->makeUser('superuser');
        $this->actingAs($superuser)
            ->get('/__test__/accountant-or-superuser')
            ->assertOk();
    }

    public function test_multi_role_syntax_rejects_users_holding_neither_role(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get('/__test__/accountant-or-superuser')
            ->assertForbidden();
    }
}