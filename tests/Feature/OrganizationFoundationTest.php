<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Foundational coverage for the Phase 0.1 organization layer:
 *   - Organization model creation and soft-delete
 *   - User → Organization relation
 *   - BelongsToOrganization trait on User (forOrganization scope)
 *   - CurrentOrganization resolver semantics (bind/get/requireId/reset)
 */
class OrganizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_is_created_with_expected_defaults(): void
    {
        $org = Organization::create([
            'slug' => 'chipper-farms',
            'name' => 'Chipper Farms',
        ]);

        $this->assertSame('GHS', $org->base_currency);
        $this->assertSame(2, $org->currency_decimals);
        $this->assertSame('GH', $org->country_code);
        $this->assertSame('Africa/Accra', $org->timezone);
        $this->assertTrue($org->is_active);
        $this->assertFalse($org->trashed());
    }

    public function test_organization_slug_is_unique(): void
    {
        Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Organization::create(['slug' => 'chipper-farms', 'name' => 'Duplicate']);
    }

    public function test_active_scope_excludes_inactive_and_trashed_orgs(): void
    {
        $active = Organization::create(['slug' => 'alpha', 'name' => 'Alpha']);
        Organization::create(['slug' => 'inactive', 'name' => 'Inactive', 'is_active' => false]);
        $trashed = Organization::create(['slug' => 'trashed', 'name' => 'Trashed']);
        $trashed->delete();

        $ids = Organization::active()->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_user_belongs_to_organization(): void
    {
        $org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        $user = User::forceCreate([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
            'organization_id' => $org->id,
        ]);

        $this->assertTrue($user->organization->is($org));
    }

    public function test_for_organization_scope_filters_by_organization_id(): void
    {
        $orgA = Organization::create(['slug' => 'a', 'name' => 'A']);
        $orgB = Organization::create(['slug' => 'b', 'name' => 'B']);

        User::forceCreate([
            'name' => 'A1', 'email' => 'a1@example.com', 'password' => bcrypt('x'),
            'organization_id' => $orgA->id,
        ]);
        User::forceCreate([
            'name' => 'A2', 'email' => 'a2@example.com', 'password' => bcrypt('x'),
            'organization_id' => $orgA->id,
        ]);
        User::forceCreate([
            'name' => 'B1', 'email' => 'b1@example.com', 'password' => bcrypt('x'),
            'organization_id' => $orgB->id,
        ]);

        $this->assertSame(2, User::forOrganization($orgA)->count());
        $this->assertSame(1, User::forOrganization($orgB->id)->count());
        // Null is a no-op — all rows returned.
        $this->assertSame(3, User::forOrganization(null)->count());
    }

    public function test_current_organization_resolver_returns_null_without_auth(): void
    {
        /** @var CurrentOrganization $resolver */
        $resolver = $this->app->make(CurrentOrganization::class);
        $resolver->reset();

        $this->assertNull($resolver->get());
        $this->assertNull($resolver->id());
    }

    public function test_current_organization_resolver_honours_explicit_bind(): void
    {
        $org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        /** @var CurrentOrganization $resolver */
        $resolver = $this->app->make(CurrentOrganization::class);
        $resolver->reset();
        $resolver->bind($org);

        $this->assertTrue($resolver->get()->is($org));
        $this->assertSame($org->id, $resolver->id());
        $this->assertSame($org->id, $resolver->requireId());
    }

    public function test_current_organization_resolver_falls_back_to_auth_user_org(): void
    {
        $org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        $user = User::forceCreate([
            'name' => 'Jane', 'email' => 'jane@example.com', 'password' => bcrypt('x'),
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user);

        /** @var CurrentOrganization $resolver */
        $resolver = $this->app->make(CurrentOrganization::class);
        $resolver->reset();

        $this->assertSame($org->id, $resolver->id());
    }

    public function test_require_id_throws_when_no_context_is_available(): void
    {
        /** @var CurrentOrganization $resolver */
        $resolver = $this->app->make(CurrentOrganization::class);
        $resolver->reset();

        $this->expectException(\RuntimeException::class);
        $resolver->requireId();
    }

    public function test_resolver_reset_clears_prior_bind(): void
    {
        $org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        /** @var CurrentOrganization $resolver */
        $resolver = $this->app->make(CurrentOrganization::class);
        $resolver->bind($org);
        $this->assertSame($org->id, $resolver->id());

        $resolver->reset();
        $this->assertNull($resolver->id());
    }

    public function test_accountant_role_is_seeded_by_migration(): void
    {
        $this->assertTrue(
            Role::where('name', 'accountant')->exists(),
            'The accountant role should be seeded by the 2026_04_23_100200 migration.'
        );
    }

    public function test_user_is_accountant_helper_works_via_role_relation(): void
    {
        $org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);
        $role = Role::firstOrCreate(['name' => 'accountant']);

        $user = User::forceCreate([
            'name' => 'Acc',
            'email' => 'acc@example.com',
            'password' => bcrypt('x'),
            'role' => 'accountant',
            'role_id' => $role->id,
            'organization_id' => $org->id,
        ]);

        $this->assertTrue($user->isAccountant());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isSuperuser());
    }

    public function test_normalize_role_name_accepts_accounting_aliases(): void
    {
        $this->assertSame('accountant', User::normalizeRoleName('accounts'));
        $this->assertSame('accountant', User::normalizeRoleName('accounting'));
        $this->assertSame('accountant', User::normalizeRoleName('ACCOUNTANT'));
    }

    public function test_sync_role_fields_accepts_accountant(): void
    {
        $org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        $user = User::forceCreate([
            'name' => 'Temp', 'email' => 'temp@example.com', 'password' => bcrypt('x'),
            'organization_id' => $org->id,
        ]);

        $user->syncRoleFields('accountant');
        $user->save();

        $this->assertTrue($user->fresh()->isAccountant());
    }

    public function test_sync_role_fields_rejects_unknown_roles(): void
    {
        $org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        $user = User::forceCreate([
            'name' => 'Temp', 'email' => 'temp@example.com', 'password' => bcrypt('x'),
            'organization_id' => $org->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $user->syncRoleFields('godmode');
    }
}