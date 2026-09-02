<?php

namespace Tests\Feature\Weights;

use App\Helpers\NavigationHelper;
use App\Models\ExpectedWeightStandard;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkWeightStandardsTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?Organization $organization, string $email): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role]);

        return User::forceCreate([
            'name' => ucfirst($role).' User',
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'active_status' => true,
            'role' => $role,
            'role_id' => $roleModel->id,
            'organization_id' => $organization?->id,
            'onboarding_required' => false,
            'onboarding_completed_at' => now(),
        ]);
    }

    public function test_only_a_farm_admin_can_open_or_submit_bulk_weight_standards(): void
    {
        $farm = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $admin = $this->user('admin', $farm, 'admin@farm-a.test');
        $manager = $this->user('farmmanager', $farm, 'manager@farm-a.test');
        $superuser = $this->user('superuser', null, 'platform@test.local');

        $this->actingAs($admin)->get(route('weight-standards.bulk-create'))->assertOk();
        $this->flushSession();
        $this->actingAs($manager)->get(route('weight-standards.bulk-create'))->assertForbidden();
        $this->flushSession();
        $this->actingAs($manager)->post(route('weight-standards.bulk-store'), [])->assertForbidden();
        $this->flushSession();
        $this->actingAs($superuser)->get(route('weight-standards.bulk-create'))->assertForbidden();
    }

    public function test_bulk_creation_never_reads_or_overwrites_another_farms_standard(): void
    {
        $farmA = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $farmB = Organization::create(['name' => 'Farm B', 'slug' => 'farm-b']);
        $adminA = $this->user('admin', $farmA, 'admin@farm-a.test');
        $adminB = $this->user('admin', $farmB, 'admin@farm-b.test');

        app(CurrentOrganization::class)->bind($farmB);
        Setting::setAnimalBreeds(['LW' => 'Large White']);
        $otherStandard = ExpectedWeightStandard::create([
            'organization_id' => $farmB->id,
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'breeding',
            'week_number' => 10,
            'expected_min_weight' => 99,
            'expected_max_weight' => 120,
            'optimal_weight' => 110,
            'created_by' => $adminB->id,
            'is_active' => true,
        ]);

        app(CurrentOrganization::class)->reset();
        app(CurrentOrganization::class)->bind($farmA);
        Setting::setAnimalBreeds(['LW' => 'Large White']);

        $this->actingAs($adminA)->post(route('weight-standards.bulk-store'), [
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'breeding',
            'week_from' => 10,
            'week_to' => 11,
            'min_weight_start' => 20,
            'max_weight_start' => 30,
            'optimal_weight_start' => 25,
            'weekly_growth_rate' => 2,
            'notes' => 'Farm A curve',
        ])->assertRedirect(route('weight-standards.index'));

        $this->assertDatabaseHas('expected_weight_standards', [
            'organization_id' => $farmA->id,
            'week_number' => 10,
            'expected_min_weight' => 20,
        ]);
        $this->assertDatabaseHas('expected_weight_standards', [
            'organization_id' => $farmA->id,
            'week_number' => 11,
            'expected_min_weight' => 22,
        ]);
        $this->assertDatabaseHas('expected_weight_standards', [
            'id' => $otherStandard->id,
            'organization_id' => $farmB->id,
            'expected_min_weight' => 99,
        ]);
        $this->assertSame(2, ExpectedWeightStandard::count());
    }

    public function test_only_admin_navigation_contains_the_bulk_setup_link(): void
    {
        $farm = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $admin = $this->user('admin', $farm, 'admin@farm-a.test');
        $manager = $this->user('farmmanager', $farm, 'manager@farm-a.test');

        $this->actingAs($admin);
        $adminRoutes = collect(NavigationHelper::sidebarSections($admin))->pluck('items')->flatten(1)->pluck('route');
        $this->assertTrue($adminRoutes->contains('weight-standards.bulk-create'));

        $this->actingAs($manager);
        $managerRoutes = collect(NavigationHelper::sidebarSections($manager))->pluck('items')->flatten(1)->pluck('route');
        $this->assertFalse($managerRoutes->contains('weight-standards.bulk-create'));
    }
}
