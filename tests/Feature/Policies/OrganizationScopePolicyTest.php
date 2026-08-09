<?php

namespace Tests\Feature\Policies;

use App\Models\Organization;
use App\Models\PigSale;
use App\Models\Role;
use App\Models\User;
use App\Policies\PigSalePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E1 — verifies EnforcesOrganizationScope behaviour through PigSalePolicy.
 *
 * Trait invariants:
 *   1. Same-org user + same-org resource → role check applies normally.
 *   2. Different-org user + scoped resource → DENIED regardless of role.
 *   3. Resource without organization_id → pass-through (role check only).
 *   4. User without organization_id → DENIED (defensive fail-closed).
 *
 * Uses PigSalePolicy because pig_sales has organization_id from Batch D.
 */
class OrganizationScopePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;
    protected Organization $orgB;
    protected User $managerInOrgA;
    protected User $managerInOrgB;
    protected User $orglessUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['slug' => 'org-a', 'name' => 'Org A']);
        $this->orgB = Organization::create(['slug' => 'org-b', 'name' => 'Org B']);

        $managerRole = Role::firstOrCreate(['name' => 'manager']);

        $this->managerInOrgA = User::forceCreate([
            'name' => 'Manager A', 'email' => 'mgr-a@example.com',
            'password' => bcrypt('x'),
            'role' => 'manager', 'role_id' => $managerRole->id,
            'organization_id' => $this->orgA->id,
        ]);

        $this->managerInOrgB = User::forceCreate([
            'name' => 'Manager B', 'email' => 'mgr-b@example.com',
            'password' => bcrypt('x'),
            'role' => 'manager', 'role_id' => $managerRole->id,
            'organization_id' => $this->orgB->id,
        ]);

        // NOT persisted. users.organization_id is NOT NULL, so this row cannot
        // exist — the organisation-scoping remediation closed that hole at the
        // database level.
        //
        // The policy's defensive branch for an org-less user is still worth
        // keeping and still worth testing: it guards against a future nullable
        // column, a raw insert, or a model hydrated from elsewhere. An unsaved
        // instance exercises exactly that branch without asserting the database
        // permits something it forbids.
        //
        // Persisting it with forceCreate is what made every test in this class
        // error on a correctly-constrained database.
        $this->orglessUser = new User();
        $this->orglessUser->forceFill([
            'id'              => 999999,
            'name'            => 'Orgless',
            'email'           => 'orgless@example.com',
            'role'            => 'manager',
            'role_id'         => $managerRole->id,
            'organization_id' => null,
        ]);
    }

    protected function makeSaleInOrg(Organization $org): PigSale
    {
        // Create a minimal PigSale row in the given org. We bypass the model's
        // creation flow because policy tests don't need full sale machinery.
        $pigId = \DB::table('pigs_management')->insertGetId([
            'ear_tag_number' => 'T-' . uniqid(),
            'code'           => 'PIG-' . uniqid(),
            'breed'          => 'LW',
            'gender'         => 'M',
            'stock_type'     => 'standard',
            'date_of_birth'  => now()->subMonths(6)->toDateString(),
            'age_in_days'    => 180,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $saleId = \DB::table('pig_sales')->insertGetId([
            'pig_id'           => $pigId,
            'sale_date'        => now()->toDateString(),
            'buyer_name'       => 'Test Buyer',
            'sale_price'       => 1000.00,
            'sale_purpose'     => 'market',
            'payment_status'   => 'pending',
            'organization_id'  => $org->id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return PigSale::find($saleId);
    }

    public function test_same_org_manager_can_view_sale(): void
    {
        $sale = $this->makeSaleInOrg($this->orgA);
        $policy = new PigSalePolicy();

        $this->assertTrue($policy->view($this->managerInOrgA, $sale));
    }

    public function test_different_org_manager_cannot_view_sale(): void
    {
        $sale = $this->makeSaleInOrg($this->orgA);
        $policy = new PigSalePolicy();

        $this->assertFalse($policy->view($this->managerInOrgB, $sale));
    }

    public function test_different_org_manager_cannot_update_sale(): void
    {
        $sale = $this->makeSaleInOrg($this->orgA);
        $policy = new PigSalePolicy();

        $this->assertFalse($policy->update($this->managerInOrgB, $sale));
    }

    public function test_different_org_manager_cannot_update_payment_status(): void
    {
        $sale = $this->makeSaleInOrg($this->orgA);
        $policy = new PigSalePolicy();

        $this->assertFalse($policy->updatePayment($this->managerInOrgB, $sale));
    }

    public function test_orgless_user_denied_per_instance_check(): void
    {
        $sale = $this->makeSaleInOrg($this->orgA);
        $policy = new PigSalePolicy();

        // Orgless user fails the sameOrganization() check defensively even
        // if their role would otherwise permit the action.
        $this->assertFalse($policy->view($this->orglessUser, $sale));
        $this->assertFalse($policy->update($this->orglessUser, $sale));
        $this->assertFalse($policy->delete($this->orglessUser, $sale));
    }

    public function test_class_level_methods_unaffected_by_org_scope(): void
    {
        // viewAny / create / export are class-level — no resource instance,
        // no scope check. Role is the only gate.
        $policy = new PigSalePolicy();

        $this->assertTrue($policy->viewAny($this->managerInOrgA));
        $this->assertTrue($policy->create($this->managerInOrgA));
        $this->assertTrue($policy->export($this->managerInOrgA));

        // Even an orgless user passes class-level role checks (role is set).
        // Per-instance is the gate that fails closed.
        $this->assertTrue($policy->viewAny($this->orglessUser));
    }

    /**
     * A record whose organisation cannot be resolved is DENIED (CF-P0-005).
     *
     * This test previously asserted the opposite — that an unscoped row "passes
     * through, role decides" — and asserted it for managers in BOTH
     * organisations. That is the vulnerability itself: an Organization B manager
     * reading an Organization A-adjacent sale. The old expectation was written
     * on the premise that unscoped rows were a transient pre-backfill artefact,
     * but 41 of 128 tables carry no organization_id column at all, so for a
     * large part of the schema the exemption was permanent.
     *
     * The assertion is inverted rather than deleted, so the contract is pinned
     * in the direction that is actually safe and a future "tidy-up" cannot
     * quietly restore the pass-through.
     */
    public function test_resource_with_unresolvable_organization_is_denied(): void
    {
        // Create a sale row with NULL organization_id (legacy pre-backfill row).
        $pigId = \DB::table('pigs_management')->insertGetId([
            'ear_tag_number' => 'T-LEGACY',
            'code'           => 'PIG-LEGACY',
            'breed'          => 'LW',
            'gender'         => 'M',
            'stock_type'     => 'standard',
            'date_of_birth'  => now()->subMonths(6)->toDateString(),
            'age_in_days'    => 180,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $saleId = \DB::table('pig_sales')->insertGetId([
            'pig_id'         => $pigId,
            'sale_date'      => now()->toDateString(),
            'buyer_name'     => 'Legacy',
            'sale_price'     => 500.00,
            'sale_purpose'   => 'market',
            'payment_status' => 'pending',
            'organization_id'=> null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $sale = PigSale::find($saleId);
        $policy = new PigSalePolicy();

        // Unresolvable tenancy is a denial, for every organisation — including
        // the one that would "probably" own the row. Guessing is what created
        // the cross-tenant exposure.
        $this->assertFalse(
            $policy->view($this->managerInOrgA, $sale),
            'A sale with no resolvable organisation must not be viewable, even by the likely owner.',
        );
        $this->assertFalse(
            $policy->view($this->managerInOrgB, $sale),
            'A sale with no resolvable organisation must not be viewable across tenants.',
        );
    }
}
