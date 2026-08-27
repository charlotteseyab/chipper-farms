<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\UserProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function provisionedUser(string $role = 'admin'): User
    {
        $organization = Organization::create([
            'name' => 'New Farm',
            'slug' => 'new-farm',
        ]);

        return app(UserProvisioningService::class)->provision([
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'email' => "{$role}@new-farm.test",
            'password' => 'correct-horse-battery',
            'role' => $role,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_new_farm_user_is_sent_to_the_one_time_onboarding_page(): void
    {
        $admin = $this->provisionedUser();

        $this->assertTrue($admin->onboarding_required);
        $this->assertNull($admin->onboarding_completed_at);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertRedirect(route('onboarding.show'));

        $this->actingAs($admin)->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Let’s get New Farm ready')
            ->assertSee('The major modules')
            ->assertSee('Readiness checklist')
            ->assertSee('Customize portal');
    }

    public function test_admin_can_customize_the_portal_and_onboarding_does_not_show_again(): void
    {
        $admin = $this->provisionedUser();

        $this->actingAs($admin)->post(route('onboarding.complete'), [
            'organization_name' => 'Green Hills Pig Farm',
            'organization_email' => 'hello@greenhills.test',
            'organization_phone' => '+233 20 000 0000',
            'organization_address' => 'Kumasi, Ghana',
            'date_format' => 'd/m/Y',
            'time_format' => '24',
        ])->assertRedirect(route('admin.dashboard'));

        $admin->refresh();
        $this->assertFalse($admin->fresh()->onboarding_required);
        $this->assertNotNull($admin->onboarding_completed_at);
        $this->assertSame('Green Hills Pig Farm', $admin->organization->fresh()->name);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'organization_id' => $admin->organization_id,
            'action' => 'onboarding_completed',
        ]);

        $this->actingAs($admin)->get(route('onboarding.show'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_existing_accounts_and_platform_superusers_are_not_forced_through_onboarding(): void
    {
        $organization = Organization::create(['name' => 'Existing Farm', 'slug' => 'existing-farm']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::forceCreate([
            'name' => 'Existing Admin',
            'email' => 'existing@test.local',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'active_status' => true,
            'role' => 'admin',
            'role_id' => $role->id,
            'organization_id' => $organization->id,
        ]);

        $this->assertFalse($admin->fresh()->onboarding_required);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $superuser = $this->provisionedUser('superuser');
        $this->assertFalse($superuser->onboarding_required);
    }
}
