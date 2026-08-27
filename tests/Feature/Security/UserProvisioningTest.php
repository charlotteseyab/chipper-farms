<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\UserProvisioningService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * CF-P0-001 — user provisioning must always produce a usable account.
 *
 * The original defect: SettingsController::storeUser() built the User inline,
 * never setting organization_id and defaulting active_status to false. The
 * account was then rejected on its very first authenticated request by
 * EnsureOrganizationContext (403 on empty organization_id) and by
 * EnsureUserIsActive. Fortify's CreateNewUser had the correct organisation
 * logic but was not reused, so the two paths disagreed on exactly the two
 * fields that decide whether the account works.
 *
 * The assertion that matters is not "organization_id is set" — it is that a
 * freshly provisioned user can complete an authenticated request. That is what
 * these tests check.
 */
class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $superuser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'cf',
            'name' => 'Chipper Farms',
            'is_active' => true,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        foreach (['superuser', 'admin', 'farmmanager', 'dataentry', 'accountant'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        $this->superuser = User::forceCreate([
            'name'            => 'Root',
            'email'           => 'root@example.com',
            'password'        => bcrypt('x'),
            'role'            => 'superuser',
            'role_id'         => Role::where('name', 'superuser')->first()->id,
            'organization_id' => null,
            'active_status'   => true,
        ]);
    }

    protected function service(): UserProvisioningService
    {
        return app(UserProvisioningService::class);
    }

    /** @return array<string,mixed> */
    protected function validInput(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ama',
            'last_name'  => 'Mensah',
            'email'      => 'ama_' . uniqid() . '@example.com',
            'role'       => 'farmmanager',
            'password'   => 'correct-horse-battery',
            'organization_id' => $this->org->id,
        ], $overrides);
    }

    public function test_platform_superuser_targets_an_explicit_organization(): void
    {
        $user = $this->service()->provision($this->validInput(), $this->superuser);

        $this->assertSame($this->org->id, $user->organization_id);
    }

    public function test_provisioned_user_is_active_by_default(): void
    {
        $user = $this->service()->provision($this->validInput(), $this->superuser);

        $this->assertTrue(
            (bool) $user->active_status,
            'A provisioned account must be active unless explicitly requested otherwise; '
            . 'defaulting to inactive was half of CF-P0-001.',
        );
    }

    public function test_inactive_can_still_be_requested_explicitly(): void
    {
        $user = $this->service()->provision(
            $this->validInput(['active_status' => false]),
            $this->superuser,
        );

        $this->assertFalse((bool) $user->active_status);
    }

    public function test_role_is_assigned(): void
    {
        $user = $this->service()->provision($this->validInput(), $this->superuser);

        $this->assertNotNull($user->role_id);
        $this->assertSame('farmmanager', $user->role);
    }

    /**
     * The end-to-end assertion for CF-P0-001: the new account can make an
     * authenticated request. Before remediation this returned 403 because the
     * user had no organisation and was inactive.
     */
    public function test_provisioned_user_can_complete_an_authenticated_request(): void
    {
        $user = $this->service()->provision($this->validInput(), $this->superuser);

        $response = $this->actingAs($user->fresh())->get(route('dashboard'));

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            'A freshly provisioned user was rejected on its first authenticated request — '
            . 'this is precisely the CF-P0-001 defect.',
        );
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $input = $this->validInput(['email' => 'dupe@example.com']);
        $this->service()->provision($input, $this->superuser);

        $this->expectException(ValidationException::class);
        $this->service()->provision($input, $this->superuser);
    }

    public function test_email_is_normalised_to_lowercase(): void
    {
        $user = $this->service()->provision(
            $this->validInput(['email' => 'MiXeD@Example.COM']),
            $this->superuser,
        );

        $this->assertSame('mixed@example.com', $user->email);
    }

    public function test_unsupported_role_creates_no_user(): void
    {
        $before = User::count();

        try {
            $this->service()->provision(
                $this->validInput(['role' => 'not-a-real-role']),
                $this->superuser,
            );
            $this->fail('Expected ValidationException for an unsupported role.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(
            $before,
            User::count(),
            'A failed role assignment must leave no partially-created user behind.',
        );
    }

    /**
     * A tenant-scoped actor must not be able to plant an account in another
     * organisation. Only a superuser may target an explicit organisation.
     */
    public function test_non_superuser_cannot_provision_into_another_organization(): void
    {
        $other = Organization::create(['slug' => 'other', 'name' => 'Other Farm', 'is_active' => true]);

        $admin = User::forceCreate([
            'name'            => 'Admin',
            'email'           => 'admin@example.com',
            'password'        => bcrypt('x'),
            'role'            => 'admin',
            'role_id'         => Role::where('name', 'admin')->first()->id,
            'organization_id' => $this->org->id,
            'active_status'   => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->provision(
            $this->validInput(['organization_id' => $other->id]),
            $admin,
        );
    }

    public function test_superuser_may_target_an_explicit_organization(): void
    {
        $other = Organization::create(['slug' => 'other2', 'name' => 'Other Farm 2', 'is_active' => true]);

        $user = $this->service()->provision(
            $this->validInput(['organization_id' => $other->id]),
            $this->superuser,
        );

        $this->assertSame($other->id, $user->organization_id);
    }

    public function test_inactive_target_organization_is_rejected(): void
    {
        $dormant = Organization::create(['slug' => 'dormant', 'name' => 'Dormant', 'is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->service()->provision(
            $this->validInput(['organization_id' => $dormant->id]),
            $this->superuser,
        );
    }

    /**
     * The settings UI must go through the same service. This asserts the
     * HTTP-level outcome, which is what CF-P0-001 was reported against.
     */
    public function test_settings_user_creation_produces_a_usable_account(): void
    {
        if (! \Illuminate\Support\Facades\Route::has('settings.users.store')) {
            $this->markTestSkipped('settings.users.store route not registered in this build.');
        }

        $email = 'via_settings_' . uniqid() . '@example.com';

        $this->actingAs($this->superuser)->post(route('settings.users.store'), [
            'account_scope'          => 'farm',
            'first_name'            => 'Kofi',
            'last_name'             => 'Boateng',
            'email'                 => $email,
            'role'                  => 'dataentry',
            'password'              => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'organization_id'       => $this->org->id,
        ]);

        $created = User::where('email', $email)->first();

        $this->assertNotNull($created, 'Settings user creation did not persist a user.');
        $this->assertNotNull(
            $created->organization_id,
            'Settings-created user has no organization_id — CF-P0-001 has regressed.',
        );
        $this->assertTrue(
            (bool) $created->active_status,
            'Settings-created user is inactive — CF-P0-001 has regressed.',
        );
    }

    public function test_superuser_is_platform_scoped_without_an_organization(): void
    {
        $platformUser = $this->service()->provision($this->validInput([
            'email' => 'platform@example.com',
            'role' => 'superuser',
            'organization_id' => $this->org->id,
        ]), $this->superuser);

        $this->assertNull($platformUser->organization_id);
    }

    public function test_platform_superuser_must_select_a_farm_for_farm_staff(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->provision($this->validInput(['organization_id' => null]), $this->superuser);
    }
}
