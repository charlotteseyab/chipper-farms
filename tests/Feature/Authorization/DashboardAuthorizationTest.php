<?php

namespace Tests\Feature\Authorization;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E3 — verifies the dashboard route role middleware actually denies
 * cross-role access.
 *
 * Before E3, every authenticated user could hit any dashboard URL by
 * typing it directly. After E3, role middleware enforces the right access
 * matrix:
 *
 *   /admin/dashboard      admin, superuser
 *   /manager/dashboard    manager, admin, superuser
 *   /superuser/dashboard  superuser only
 *   /dataentry/dashboard  dataentry, admin, superuser
 */
class DashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        return User::forceCreate([
            'name' => ucfirst($roleName) . ' User',
            'email' => $roleName . '_' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }

    // ---------- /admin/dashboard ----------

    public function test_admin_can_access_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_superuser_can_access_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser('superuser'))
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_manager_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser('manager'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_dataentry_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser('dataentry'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_accountant_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser('accountant'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    // ---------- /superuser/dashboard ----------

    public function test_only_superuser_can_access_superuser_dashboard(): void
    {
        // superuser ALLOWED
        $this->actingAs($this->makeUser('superuser'))
            ->get('/superuser/dashboard')
            ->assertOk();
    }

    public function test_admin_cannot_access_superuser_dashboard(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get('/superuser/dashboard')
            ->assertForbidden();
    }

    public function test_manager_cannot_access_superuser_dashboard(): void
    {
        $this->actingAs($this->makeUser('manager'))
            ->get('/superuser/dashboard')
            ->assertForbidden();
    }

    public function test_dataentry_cannot_access_superuser_dashboard(): void
    {
        $this->actingAs($this->makeUser('dataentry'))
            ->get('/superuser/dashboard')
            ->assertForbidden();
    }

    public function test_accountant_cannot_access_superuser_dashboard(): void
    {
        $this->actingAs($this->makeUser('accountant'))
            ->get('/superuser/dashboard')
            ->assertForbidden();
    }

    // ---------- /manager/dashboard ----------

    public function test_manager_can_access_manager_dashboard(): void
    {
        $this->actingAs($this->makeUser('manager'))
            ->get('/manager/dashboard')
            ->assertOk();
    }

    public function test_dataentry_cannot_access_manager_dashboard(): void
    {
        $this->actingAs($this->makeUser('dataentry'))
            ->get('/manager/dashboard')
            ->assertForbidden();
    }

    public function test_accountant_cannot_access_manager_dashboard(): void
    {
        $this->actingAs($this->makeUser('accountant'))
            ->get('/manager/dashboard')
            ->assertForbidden();
    }

    // ---------- /dataentry/dashboard ----------

    public function test_dataentry_can_access_dataentry_dashboard(): void
    {
        $this->actingAs($this->makeUser('dataentry'))
            ->get('/dataentry/dashboard')
            ->assertOk();
    }

    public function test_accountant_cannot_access_dataentry_dashboard(): void
    {
        $this->actingAs($this->makeUser('accountant'))
            ->get('/dataentry/dashboard')
            ->assertForbidden();
    }

    public function test_manager_cannot_access_dataentry_dashboard(): void
    {
        $this->actingAs($this->makeUser('manager'))
            ->get('/dataentry/dashboard')
            ->assertForbidden();
    }

    // ---------- /admin/weight-trends-data API ----------

    public function test_dataentry_cannot_access_admin_api(): void
    {
        $this->actingAs($this->makeUser('dataentry'))
            ->get('/admin/weight-trends-data')
            ->assertForbidden();
    }

    public function test_accountant_cannot_access_admin_api(): void
    {
        $this->actingAs($this->makeUser('accountant'))
            ->get('/admin/weight-trends-data')
            ->assertForbidden();
    }
}
