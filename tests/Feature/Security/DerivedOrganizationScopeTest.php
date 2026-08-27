<?php

namespace Tests\Feature\Security;

use App\Models\Farrowing;
use App\Models\Mating;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Role;
use App\Models\User;
use App\Models\Weaning;
use App\Policies\FarrowingPolicy;
use App\Policies\MatingPolicy;
use App\Policies\WeaningPolicy;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-tenant isolation for the breeding domain (CF-P0-005).
 *
 * The `farrowing`, `matings` and `weanings` tables have NO organization_id
 * column — they are three of the 41 tables (out of 128) that carry none. Their
 * policies all call EnforcesOrganizationScope::sameOrganization(), which used to
 * return true for any resource without the column. The result was that
 * FarrowingPolicy, MatingPolicy and WeaningPolicy performed a role check and
 * nothing else: an Organization B user could view and mutate Organization A
 * breeding records and every policy agreed.
 *
 * The register describes this as "transitional and permissive", implying the gap
 * closes once backfill lands. For these tables there is nothing to backfill, so
 * it would never have closed. Tenancy is instead DERIVED through the parent pig
 * via the ResolvesOrganization contract, and unresolvable tenancy now denies.
 *
 * These tests instantiate the policies directly rather than going through HTTP,
 * because the defect is in the policy layer and that is where it must be pinned.
 */
class DerivedOrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;
    protected Organization $orgB;
    protected User $managerA;
    protected User $managerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['slug' => 'org-a', 'name' => 'Org A', 'is_active' => true]);
        $this->orgB = Organization::create(['slug' => 'org-b', 'name' => 'Org B', 'is_active' => true]);

        $role = Role::firstOrCreate(['name' => 'farmmanager']);

        $this->managerA = User::forceCreate([
            'name' => 'Manager A', 'email' => 'a_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'farmmanager', 'role_id' => $role->id,
            'organization_id' => $this->orgA->id, 'active_status' => true,
        ]);

        $this->managerB = User::forceCreate([
            'name' => 'Manager B', 'email' => 'b_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'farmmanager', 'role_id' => $role->id,
            'organization_id' => $this->orgB->id, 'active_status' => true,
        ]);
    }

    protected function makePig(Organization $org, string $gender = 'F'): PigManagement
    {
        // Bind the organisation so the BelongsToOrganization creating hook
        // stamps it, which is also a live check that the hook works.
        $this->app->make(CurrentOrganization::class)->bind($org);

        return PigManagement::create([
            'ear_tag_number' => 'TAG-' . uniqid(),
            'code'           => 'PIG-' . uniqid(),
            'breed'          => 'Large White',
            'gender'         => $gender,
            'stock_type'     => 'breeding',
            'date_of_birth'  => now()->subMonths(14)->toDateString(),
        ]);
    }

    public function test_new_organization_starts_without_another_organizations_operational_data(): void
    {
        $pig = $this->makePig($this->orgA);
        PigWeight::create([
            'pig_id' => $pig->id,
            'weight' => 80,
            'weigh_date' => now()->toDateString(),
            'weight_unit' => 'kg',
        ]);

        $this->app->make(CurrentOrganization::class)->bind($this->orgB);

        $this->assertSame(0, PigManagement::count());
        $this->assertSame(0, PigWeight::count());
    }

    public function test_creating_hook_stamps_current_organization_on_pigs(): void
    {
        $pig = $this->makePig($this->orgA);

        $this->assertSame(
            $this->orgA->id,
            $pig->organization_id,
            'A new pig must inherit the bound organisation; otherwise every child record is unresolvable.',
        );
    }

    public function test_explicit_organization_is_not_overwritten_by_hook(): void
    {
        $this->app->make(CurrentOrganization::class)->bind($this->orgA);

        $pig = PigManagement::create([
            'organization_id' => $this->orgB->id,
            'ear_tag_number'  => 'TAG-' . uniqid(),
            'code'            => 'PIG-' . uniqid(),
            'breed'           => 'Large White',
            'gender'          => 'F',
            'stock_type'      => 'standard',
            'date_of_birth'   => now()->subMonths(6)->toDateString(),
        ]);

        $this->assertSame(
            $this->orgB->id,
            $pig->organization_id,
            'An explicitly supplied organisation must win — seeders and imports rely on it.',
        );
    }

    /* ---------------------------------------------------------------- */
    /* Farrowing                                                        */
    /* ---------------------------------------------------------------- */

    protected function makeFarrowing(Organization $org): Farrowing
    {
        $sow = $this->makePig($org, 'F');

        return Farrowing::create([
            'sow_id'             => $sow->id,
            'farrowing_date'     => now()->subDays(10)->toDateString(),
            'total_piglets_born' => 10,
            'live_births'        => 9,
            'stillbirths'        => 1,
            'status'             => 'active',
            'recorded_by'        => $this->managerA->id,
        ]);
    }

    public function test_farrowing_derives_organization_from_sow(): void
    {
        $farrowing = $this->makeFarrowing($this->orgA);

        $this->assertSame($this->orgA->id, $farrowing->resolveOrganizationId());
    }

    public function test_farrowing_is_not_visible_across_organizations(): void
    {
        $farrowing = $this->makeFarrowing($this->orgA);
        $policy    = new FarrowingPolicy();

        $this->assertTrue(
            $policy->view($this->managerA, $farrowing),
            'The owning organisation must retain access.',
        );
        $this->assertFalse(
            $policy->view($this->managerB, $farrowing),
            'An Organization B manager must not view an Organization A farrowing record.',
        );
    }

    public function test_farrowing_cannot_be_updated_across_organizations(): void
    {
        $farrowing = $this->makeFarrowing($this->orgA);
        $policy    = new FarrowingPolicy();

        $this->assertFalse(
            $policy->update($this->managerB, $farrowing),
            'An Organization B manager must not mutate an Organization A farrowing record.',
        );
    }

    /* ---------------------------------------------------------------- */
    /* Mating                                                           */
    /* ---------------------------------------------------------------- */

    protected function makeMating(Organization $org): Mating
    {
        $female = $this->makePig($org, 'F');
        $male   = $this->makePig($org, 'M');

        return Mating::create([
            'female_id'    => $female->id,
            'male_id'      => $male->id,
            'planned_date' => now()->subDays(20)->toDateString(),
            'mating_date'  => now()->subDays(20)->toDateString(),
            // 'pending' is a valid `pregnancy_status`, not a valid `status` —
            // matings.status is enum('planned','completed','cancelled','failed').
            // SQLite has no enum so the fixture passed there; MariaDB truncates
            // the value and errors. The two tests below assert organisation
            // derivation and cross-tenant visibility, so any valid status does.
            'status'       => 'planned',
        ]);
    }

    public function test_mating_derives_organization_from_female(): void
    {
        $mating = $this->makeMating($this->orgA);

        $this->assertSame($this->orgA->id, $mating->resolveOrganizationId());
    }

    public function test_mating_is_not_visible_across_organizations(): void
    {
        $mating = $this->makeMating($this->orgA);
        $policy = new MatingPolicy();

        $this->assertTrue($policy->view($this->managerA, $mating));
        $this->assertFalse(
            $policy->view($this->managerB, $mating),
            'An Organization B manager must not view an Organization A mating record.',
        );
    }

    /* ---------------------------------------------------------------- */
    /* Weaning                                                          */
    /* ---------------------------------------------------------------- */

    protected function makeWeaning(Organization $org): Weaning
    {
        $pig = $this->makePig($org, 'F');

        return Weaning::create([
            'pig_id'              => $pig->id,
            'weaning_date'        => now()->subDays(5)->toDateString(),
            'age_at_weaning_days' => 28,
            'recorded_by'         => $this->managerA->id,
        ]);
    }

    public function test_weaning_derives_organization_from_pig(): void
    {
        $weaning = $this->makeWeaning($this->orgA);

        $this->assertSame($this->orgA->id, $weaning->resolveOrganizationId());
    }

    public function test_weaning_is_not_visible_across_organizations(): void
    {
        $weaning = $this->makeWeaning($this->orgA);
        $policy  = new WeaningPolicy();

        $this->assertTrue($policy->view($this->managerA, $weaning));
        $this->assertFalse(
            $policy->view($this->managerB, $weaning),
            'An Organization B manager must not view an Organization A weaning record.',
        );
    }

    /* ---------------------------------------------------------------- */
    /* Unresolvable tenancy                                             */
    /* ---------------------------------------------------------------- */

    /**
     * A farrowing whose sow is itself unscoped cannot be attributed to any
     * organisation, and must therefore be denied to everyone rather than shown
     * to all. This is the branch that previously returned true.
     */
    public function test_farrowing_with_unscoped_sow_is_denied_to_everyone(): void
    {
        $sowId = \DB::table('pigs_management')->insertGetId([
            'ear_tag_number'  => 'ORPHAN-' . uniqid(),
            'code'            => 'PIG-' . uniqid(),
            'breed'           => 'LW',
            'gender'          => 'F',
            'stock_type'      => 'breeding',
            'date_of_birth'   => now()->subMonths(14)->toDateString(),
            'age_in_days'     => 420,
            'organization_id' => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $farrowing = Farrowing::create([
            'sow_id'             => $sowId,
            'farrowing_date'     => now()->subDays(10)->toDateString(),
            'total_piglets_born' => 8,
            'live_births'        => 8,
            'stillbirths'        => 0,
            'status'             => 'active',
            'recorded_by'        => $this->managerA->id,
        ]);

        $policy = new FarrowingPolicy();

        $this->assertNull($farrowing->fresh()->resolveOrganizationId());
        $this->assertFalse($policy->view($this->managerA, $farrowing));
        $this->assertFalse($policy->view($this->managerB, $farrowing));
    }

    /* ---------------------------------------------------------------- */
    /* Scoped route bindings                                            */
    /* ---------------------------------------------------------------- */

    /**
     * A cross-tenant record must be indistinguishable from a missing one.
     *
     * The route bindings for pig/farrowing/mating/weaning were global
     * findOrFail(). Policies denied the action, but the BINDING resolved the
     * record first, so another tenant's pig returned 403 while a genuinely
     * absent id returned 404. That difference is an enumeration oracle over the
     * whole livestock table: an attacker learns which ids exist without ever
     * being authorised to see them.
     *
     * Both cases must now be 404.
     */
    public function test_cross_tenant_pig_is_indistinguishable_from_missing(): void
    {
        $foreignPig = $this->makePig($this->orgB);

        // Act as an Organization A user; bind A as the current organisation the
        // way EnsureOrganizationContext would.
        $this->app->make(CurrentOrganization::class)->bind($this->orgA);

        $crossTenant = $this->actingAs($this->managerA)
            ->get(route('health.show', $foreignPig->id));

        $absent = $this->actingAs($this->managerA)
            ->get(route('health.show', 99999999));

        $this->assertSame(
            $absent->getStatusCode(),
            $crossTenant->getStatusCode(),
            'A pig belonging to another organisation returned a different status than a '
            . 'non-existent one, which lets an attacker enumerate ids. Both must be 404.',
        );

        $this->assertSame(404, $crossTenant->getStatusCode());
    }

    public function test_own_organization_pig_still_resolves(): void
    {
        $ownPig = $this->makePig($this->orgA);

        $this->app->make(CurrentOrganization::class)->bind($this->orgA);

        // Not asserting 200: health.show requires view_health_records, which this
        // fixture does not seed. What matters is that the BINDING resolved, i.e.
        // the response is not the 404 a scoped-out record would produce.
        $response = $this->actingAs($this->managerA)
            ->get(route('health.show', $ownPig->id));

        $this->assertNotSame(
            404,
            $response->getStatusCode(),
            'A pig in the user\'s own organisation must still bind; scoping must not '
            . 'deny legitimate access.',
        );
    }
}
