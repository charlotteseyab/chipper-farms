<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?Organization $organization, string $email): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role]);

        return User::forceCreate([
            'name' => ucfirst($role).' User', 'email' => $email, 'password' => bcrypt('password'),
            'email_verified_at' => now(), 'active_status' => true, 'role' => $role,
            'role_id' => $roleModel->id, 'organization_id' => $organization?->id,
        ]);
    }

    public function test_farm_admin_sees_all_business_activity_for_only_their_organization(): void
    {
        $farmA = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $farmB = Organization::create(['name' => 'Farm B', 'slug' => 'farm-b']);
        $admin = $this->user('admin', $farmA, 'admin@farm-a.test');
        $other = $this->user('admin', $farmB, 'admin@farm-b.test');
        ActivityLog::create(['user_id' => $admin->id, 'organization_id' => $farmA->id, 'module' => 'Finance', 'action' => 'payment_recorded', 'record_label' => 'PAY-A-001']);
        ActivityLog::create(['user_id' => $other->id, 'organization_id' => $farmB->id, 'module' => 'Stock', 'action' => 'created', 'record_label' => 'PIG-B-001']);

        $this->actingAs($admin)->get(route('settings.activity-log'))
            ->assertOk()->assertSee('PAY-A-001')->assertSee('Payment Recorded')
            ->assertDontSee('PIG-B-001')->assertDontSee('All organizations');
    }

    public function test_superuser_can_filter_the_platform_log_by_organization(): void
    {
        $farmA = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $farmB = Organization::create(['name' => 'Farm B', 'slug' => 'farm-b']);
        $adminA = $this->user('admin', $farmA, 'admin-a@test.local');
        $adminB = $this->user('admin', $farmB, 'admin-b@test.local');
        $superuser = $this->user('superuser', null, 'platform@test.local');
        ActivityLog::create(['user_id' => $adminA->id, 'organization_id' => $farmA->id, 'module' => 'Stock', 'action' => 'created', 'record_label' => 'FARM-A-RECORD']);
        ActivityLog::create(['user_id' => $adminB->id, 'organization_id' => $farmB->id, 'module' => 'Stock', 'action' => 'created', 'record_label' => 'FARM-B-RECORD']);

        $this->actingAs($superuser)->get(route('settings.activity-log', ['organization_id' => $farmA->id]))
            ->assertOk()->assertSee('All organizations')->assertSee('FARM-A-RECORD')->assertDontSee('FARM-B-RECORD');
    }

    public function test_non_admin_staff_see_only_their_own_audit_activity(): void
    {
        $farm = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $manager = $this->user('farmmanager', $farm, 'manager@farm-a.test');
        $colleague = $this->user('dataentry', $farm, 'officer@farm-a.test');
        ActivityLog::create(['user_id' => $manager->id, 'organization_id' => $farm->id, 'module' => 'Stock', 'action' => 'updated', 'record_label' => 'MANAGER-OWN']);
        ActivityLog::create(['user_id' => $colleague->id, 'organization_id' => $farm->id, 'module' => 'Stock', 'action' => 'created', 'record_label' => 'COLLEAGUE-PRIVATE']);
        ActivityLog::create(['user_id' => null, 'organization_id' => null, 'module' => 'System', 'action' => 'updated', 'record_label' => 'PLATFORM-PRIVATE']);

        $this->actingAs($manager)->get(route('audit-trail.index'))
            ->assertOk()
            ->assertSee('MANAGER-OWN')
            ->assertDontSee('COLLEAGUE-PRIVATE')
            ->assertDontSee('PLATFORM-PRIVATE');
    }

    public function test_all_farm_staff_roles_can_open_their_personal_audit_trail(): void
    {
        $farm = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $officer = $this->user('dataentry', $farm, 'officer@farm-a.test');
        ActivityLog::create(['user_id' => $officer->id, 'organization_id' => $farm->id, 'module' => 'Stock', 'action' => 'created', 'record_label' => 'OFFICER-OWN']);

        $this->actingAs($officer)->get(route('audit-trail.index', ['tab' => 'archived']))
            ->assertOk()
            ->assertSee('OFFICER-OWN')
            ->assertDontSee('Archived Records');
    }

    public function test_sensitive_audit_values_are_redacted(): void
    {
        ActivityLog::create(['action' => 'updated', 'module' => 'Settings', 'new_values' => [
            'host' => 'smtp.test', 'password' => 'top-secret', 'nested' => ['api_key' => 'abc123'],
        ]]);

        $values = ActivityLog::firstOrFail()->new_values;
        $this->assertSame('smtp.test', $values['host']);
        $this->assertSame('[REDACTED]', $values['password']);
        $this->assertSame('[REDACTED]', $values['nested']['api_key']);
    }

    public function test_a_successful_business_mutation_receives_a_fallback_audit_entry(): void
    {
        $farm = Organization::create(['name' => 'Audit Farm', 'slug' => 'audit-farm']);
        $admin = $this->user('admin', $farm, 'audit-admin@test.local');

        $this->actingAs($admin)->post(route('settings.setup.breeds.save'), [
            'new_breed_code' => 'HAM',
            'new_breed_name' => 'Hampshire',
        ])->assertRedirect(route('settings.setup'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'organization_id' => $farm->id,
            'action' => 'created',
        ]);
    }
}
