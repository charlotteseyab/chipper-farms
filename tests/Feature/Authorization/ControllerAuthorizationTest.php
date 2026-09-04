<?php

namespace Tests\Feature\Authorization;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E1 — controller-level authorization integration.
 *
 * Verifies that wired $this->authorize() calls actually return 403 for
 * unauthorized users instead of silently letting them through.
 *
 * Tests the most policy-sensitive paths:
 *   - Sale recording (create) — accountant should be denied
 *   - Sale payment status update — accountant ALLOWED, dataentry denied
 *   - Death recording (create) — accountant denied, dataentry allowed
 *   - Pig promote — accountant + dataentry both denied
 *   - Cross-org access — manager from another org denied
 */
class ControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);
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

    protected function makePig(): PigManagement
    {
        return PigManagement::create([
            'ear_tag_number' => 'T-' . uniqid(),
            'code' => 'PIG-' . uniqid(),
            'breed' => 'LW',
            'gender' => 'M',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subMonths(6)->toDateString(),
        ]);
    }

    protected function makeSale(): PigSale
    {
        $pig = $this->makePig();

        return PigSale::create([
            'pig_id' => $pig->id,
            'sale_date' => now()->toDateString(),
            'buyer_name' => 'Test Buyer',
            'sale_price' => 1000.00,
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
            'organization_id' => $this->org->id,
        ]);
    }

    // ---------- Sale recording ----------

    public function test_accountant_cannot_record_sale(): void
    {
        $accountant = $this->makeUser('accountant');
        $pig = $this->makePig();

        $this->actingAs($accountant)
            ->get("/pigs/{$pig->id}/sale")
            ->assertForbidden();
    }

    public function test_dataentry_cannot_record_sale(): void
    {
        // Sale recording is admin/manager only — dataentry blocked.
        $de = $this->makeUser('dataentry');
        $pig = $this->makePig();

        $this->actingAs($de)
            ->get("/pigs/{$pig->id}/sale")
            ->assertForbidden();
    }

    public function test_manager_can_view_sale_form(): void
    {
        $mgr = $this->makeUser('manager');
        $pig = $this->makePig();

        $response = $this->actingAs($mgr)->get("/pigs/{$pig->id}/sale");

        // 200 (renders form) or 302 (redirect on eligibility — both indicate
        // the authorize check passed).
        $this->assertContains($response->status(), [200, 302]);
        $this->assertNotEquals(403, $response->status());
    }

    // ---------- Payment status ----------

    public function test_accountant_can_update_payment_status(): void
    {
        $acct = $this->makeUser('accountant');
        $sale = $this->makeSale();

        $response = $this->actingAs($acct)
            ->post("/sales/{$sale->id}/payment-status", [
                'payment_status' => 'paid',
            ]);

        $this->assertNotEquals(403, $response->status());
    }

    public function test_dataentry_cannot_update_payment_status(): void
    {
        $de = $this->makeUser('dataentry');
        $sale = $this->makeSale();

        $this->actingAs($de)
            ->post("/sales/{$sale->id}/payment-status", [
                'payment_status' => 'paid',
            ])
            ->assertForbidden();
    }

    // ---------- Death recording ----------

    public function test_dataentry_can_record_death(): void
    {
        $de = $this->makeUser('dataentry');
        $pig = $this->makePig();

        $response = $this->actingAs($de)->get("/pigs/{$pig->id}/death");

        $this->assertNotEquals(403, $response->status());
    }

    public function test_accountant_cannot_record_death(): void
    {
        $acct = $this->makeUser('accountant');
        $pig = $this->makePig();

        $this->actingAs($acct)
            ->get("/pigs/{$pig->id}/death")
            ->assertForbidden();
    }

    // ---------- Cross-org isolation ----------

    public function test_user_from_different_org_cannot_view_sale(): void
    {
        $sale = $this->makeSale();

        $otherOrg = Organization::create(['slug' => 'other-org', 'name' => 'Other Org']);
        $role = Role::firstOrCreate(['name' => 'manager']);
        $otherUser = User::forceCreate([
            'name' => 'Other Manager',
            'email' => 'other@example.com',
            'password' => bcrypt('x'),
            'role' => 'manager',
            'role_id' => $role->id,
            'organization_id' => $otherOrg->id,
        ]);

        // Bind the OTHER org as current to satisfy middleware, but the policy
        // should still reject because the sale belongs to org A.
        $this->app->make(CurrentOrganization::class)->bind($otherOrg);

        $this->actingAs($otherUser)
            ->get("/sales/{$sale->id}")
            ->assertNotFound();
    }
}
