<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Mail\TemplatedEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrganizationAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?Organization $organization, string $email): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role]);

        return User::forceCreate([
            'name' => ucfirst($role),
            'first_name' => ucfirst($role),
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'active_status' => true,
            'role' => $role,
            'role_id' => $roleModel->id,
            'organization_id' => $organization?->id,
        ]);
    }

    public function test_farm_admin_only_sees_users_in_their_farm(): void
    {
        $one = Organization::create(['name' => 'Farm One', 'slug' => 'farm-one']);
        $two = Organization::create(['name' => 'Farm Two', 'slug' => 'farm-two']);
        $admin = $this->user('admin', $one, 'admin@one.test');
        $this->user('dataentry', $one, 'staff@one.test');
        $this->user('dataentry', $two, 'staff@two.test');

        $this->actingAs($admin)->get(route('settings.users'))
            ->assertOk()
            ->assertSee('staff@one.test')
            ->assertDontSee('staff@two.test');
    }

    public function test_farm_admin_cannot_manage_another_farms_user(): void
    {
        $one = Organization::create(['name' => 'Farm One', 'slug' => 'farm-one']);
        $two = Organization::create(['name' => 'Farm Two', 'slug' => 'farm-two']);
        $admin = $this->user('admin', $one, 'admin@one.test');
        $outsider = $this->user('dataentry', $two, 'staff@two.test');

        $this->actingAs($admin)->patch(route('settings.users.status', $outsider), ['active_status' => false])
            ->assertNotFound();
    }

    public function test_farm_admin_permanently_deletes_a_user_in_their_farm(): void
    {
        $organization = Organization::create(['name' => 'Farm One', 'slug' => 'farm-one']);
        $admin = $this->user('admin', $organization, 'admin@one.test');
        $staff = $this->user('dataentry', $organization, 'reusable@one.test');

        $this->actingAs($admin)
            ->delete(route('settings.users.delete', $staff))
            ->assertRedirect(route('settings.users'));

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertNull(User::withTrashed()->find($staff->id));

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'account_scope' => 'farm',
            'first_name' => 'Replacement',
            'last_name' => 'User',
            'email' => 'reusable@one.test',
            'role' => 'dataentry',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('settings.users'));

        $this->assertDatabaseHas('users', [
            'email' => 'reusable@one.test',
            'organization_id' => $organization->id,
        ]);
    }

    public function test_farm_admin_can_reuse_email_left_by_a_legacy_soft_deleted_user(): void
    {
        Mail::fake();
        $organization = Organization::create(['name' => 'Farm One', 'slug' => 'farm-one']);
        $admin = $this->user('admin', $organization, 'admin@one.test');
        $retired = $this->user('dataentry', $organization, 'legacy@one.test');
        $retired->delete(); // Simulate deletion by an older application build.

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'account_scope' => 'farm',
            'first_name' => 'New',
            'last_name' => 'Officer',
            'email' => 'LEGACY@one.test',
            'role' => 'dataentry',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('settings.users'))
            ->assertSessionHasNoErrors();

        $this->assertNull(User::withTrashed()->find($retired->id));
        $this->assertDatabaseHas('users', [
            'email' => 'legacy@one.test',
            'organization_id' => $organization->id,
            'deleted_at' => null,
        ]);
    }

    public function test_farm_admin_cannot_clear_another_farms_retired_email(): void
    {
        $one = Organization::create(['name' => 'Farm One', 'slug' => 'farm-one']);
        $two = Organization::create(['name' => 'Farm Two', 'slug' => 'farm-two']);
        $admin = $this->user('admin', $one, 'admin@one.test');
        $retired = $this->user('dataentry', $two, 'reserved@two.test');
        $retired->delete();

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'account_scope' => 'farm',
            'first_name' => 'Wrong',
            'last_name' => 'Farm',
            'email' => 'reserved@two.test',
            'role' => 'dataentry',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertNotNull(User::withTrashed()->find($retired->id));
    }

    public function test_platform_superuser_can_create_farm_with_first_admin(): void
    {
        Mail::fake();
        $superuser = $this->user('superuser', null, 'platform@test.local');

        $this->actingAs($superuser)->post(route('settings.organizations.store'), [
            'name' => 'North Farm',
            'admin_first_name' => 'Ama',
            'admin_last_name' => 'Owusu',
            'admin_email' => 'ama@north.test',
            'admin_password' => 'correct-horse-battery',
            'admin_password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('settings.organizations'));

        $organization = Organization::where('slug', 'north-farm')->firstOrFail();
        $this->assertDatabaseHas('users', [
            'email' => 'ama@north.test',
            'role' => 'admin',
            'organization_id' => $organization->id,
        ]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'ama@north.test']);
        // The new farm gets its own chart of accounts, so it can post from day one.
        $this->assertSame(
            \DB::table('chart_of_accounts')->whereNull('organization_id')->count(),
            \DB::table('chart_of_accounts')->where('organization_id', $organization->id)->count(),
        );
        $this->assertDatabaseHas('email_templates', [
            'organization_id' => $organization->id,
            'key' => 'users.organization_admin_welcome',
            'is_active' => true,
        ]);
        Mail::assertSent(TemplatedEmail::class, function (TemplatedEmail $mail): bool {
            return $mail->hasTo('ama@north.test')
                && str_contains($mail->emailSubject, 'North Farm')
                && str_contains($mail->body, 'ama@north.test')
                && str_contains($mail->body, 'correct-horse-battery')
                && str_contains((string) $mail->actionUrl, '/reset-password/');
        });
    }

    public function test_platform_superuser_cannot_enter_farm_operations(): void
    {
        $superuser = $this->user('superuser', null, 'platform@test.local');

        $this->actingAs($superuser)->get(route('all-stocks.index'))->assertForbidden();
    }

    public function test_platform_superuser_can_create_another_platform_superuser(): void
    {
        $superuser = $this->user('superuser', null, 'platform@test.local');

        $this->actingAs($superuser)->post(route('settings.users.store'), [
            'account_scope' => 'platform',
            'first_name' => 'Platform',
            'last_name' => 'Two',
            'email' => 'platform.two@test.local',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('settings.users'));

        $this->assertDatabaseHas('users', ['email' => 'platform.two@test.local', 'role' => 'superuser', 'organization_id' => null]);
    }

    public function test_new_farm_staff_receive_login_and_password_setup_instructions(): void
    {
        Mail::fake();
        $organization = Organization::create([
            'name' => 'Green Valley Farm',
            'slug' => 'green-valley-farm',
            'email' => 'support@green-valley.test',
        ]);
        $superuser = $this->user('superuser', null, 'platform.invites@test.local');

        $this->actingAs($superuser)->post(route('settings.users.store'), [
            'account_scope' => 'farm',
            'organization_id' => $organization->id,
            'first_name' => 'Kojo',
            'last_name' => 'Mensah',
            'email' => 'kojo@green-valley.test',
            'role' => 'dataentry',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('settings.users'));

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'kojo@green-valley.test']);
        $this->assertDatabaseHas('email_templates', [
            'organization_id' => $organization->id,
            'key' => 'users.account_created',
            'is_active' => true,
        ]);
        Mail::assertSent(TemplatedEmail::class, function (TemplatedEmail $mail): bool {
            return $mail->hasTo('kojo@green-valley.test')
                && str_contains($mail->emailSubject, 'Green Valley Farm')
                && str_contains($mail->body, 'Data Entry Officer')
                && str_contains($mail->body, 'kojo@green-valley.test')
                && str_contains($mail->body, 'correct-horse-battery')
                && str_contains((string) $mail->actionUrl, '/reset-password/');
        });
    }

    public function test_superuser_can_view_an_organization_summary_and_must_give_a_reason_to_remove_it(): void
    {
        Mail::fake();
        $organization = Organization::create([
            'name' => 'Retiring Farm',
            'slug' => 'retiring-farm',
            'email' => 'farm@example.com',
        ]);
        $admin = $this->user('admin', $organization, 'admin@retiring.test');
        $staff = $this->user('dataentry', $organization, 'staff@retiring.test');
        $superuser = $this->user('superuser', null, 'retirement@test.local');

        $this->actingAs($superuser)->get(route('settings.organizations.show', $organization))
            ->assertOk()
            ->assertSee('Retiring Farm')
            ->assertSee('Organization profile')
            ->assertSee('admin@retiring.test');

        $this->actingAs($superuser)->delete(route('settings.organizations.destroy', $organization), [
            'deletion_reason' => '',
            'confirmation_name' => 'Retiring Farm',
        ])->assertSessionHasErrors('deletion_reason');
        $this->assertNotSoftDeleted('organizations', ['id' => $organization->id]);

        $this->actingAs($superuser)->delete(route('settings.organizations.destroy', $organization), [
            'deletion_reason' => 'The customer requested closure of this farm account.',
            'confirmation_name' => 'Retiring Farm',
        ])->assertRedirect(route('settings.organizations'));

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('users', ['id' => $superuser->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'organization_retired',
            'record_label' => 'Retiring Farm',
        ]);

        $this->actingAs($superuser)->post(route('settings.organizations.store'), [
            'name' => 'Retiring Farm',
            'admin_first_name' => 'New',
            'admin_last_name' => 'Admin',
            'admin_email' => 'admin@retiring.test',
            'admin_password' => 'correct-horse-battery',
            'admin_password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('settings.organizations'));

        $recreated = Organization::where('slug', 'retiring-farm')->firstOrFail();
        $this->assertNotSame($organization->id, $recreated->id);
        $this->assertDatabaseHas('users', [
            'email' => 'admin@retiring.test',
            'organization_id' => $recreated->id,
        ]);
    }

    public function test_platform_superuser_can_access_system_settings(): void
    {
        Organization::create(['name' => 'Reporting Farm', 'slug' => 'reporting-farm']);
        $superuser = $this->user('superuser', null, 'platform@test.local');

        $this->actingAs($superuser)->get(route('settings.general'))->assertOk();
        $this->actingAs($superuser)->get(route('settings.setup'))->assertOk();
        $this->actingAs($superuser)->get(route('settings.email'))->assertOk();
        $this->actingAs($superuser)->get(route('settings.modules'))->assertOk();
        $this->actingAs($superuser)->get(route('settings.reports'))->assertOk();
    }

    public function test_profile_explains_the_users_role_and_organization_scope(): void
    {
        $organization = Organization::create(['name' => 'Green Valley Farm', 'slug' => 'green-valley-farm']);
        $admin = $this->user('admin', $organization, 'admin@green.test');

        $this->actingAs($admin)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Organization Administrator')
            ->assertSee('Green Valley Farm')
            ->assertSee('Your organization, role and module permissions are controlled by your organization administrator.');

        $this->flushSession();
        $superuser = $this->user('superuser', null, 'platform.profile@test.local');

        $this->actingAs($superuser)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('System Superuser')
            ->assertSee('Not assigned — platform-wide')
            ->assertSee('Platform governance and system-wide oversight');
    }

    public function test_platform_dashboard_and_reports_default_to_all_organizations(): void
    {
        Organization::create(['name' => 'North Farm', 'slug' => 'north-farm']);
        Organization::create(['name' => 'South Farm', 'slug' => 'south-farm']);
        $superuser = $this->user('superuser', null, 'platform@test.local');

        $this->actingAs($superuser)->get(route('superuser.dashboard'))
            ->assertOk()
            ->assertSee('Platform command centre')
            ->assertSee('Platform trends')
            ->assertSee('Organization financial volume')
            ->assertSee('North Farm')
            ->assertSee('South Farm');

        $this->actingAs($superuser)->get(route('settings.reports'))
            ->assertOk()
            ->assertSee('System reports')
            ->assertSee('Livestock &amp; stock', false)
            ->assertSee('Breeding &amp; piglets', false)
            ->assertSee('Finance')
            ->assertSee('North Farm')
            ->assertSee('South Farm');

        $this->actingAs($superuser)->get(route('settings.reports', ['tab' => 'health']))
            ->assertOk()
            ->assertSee('Health and medication oversight')
            ->assertSee('How standing is calculated');

        $this->actingAs($superuser)->get(route('settings.reports.export.csv', ['organization_id' => 'all']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
