<?php

namespace Tests\Feature\Policies;

use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * PurchaseRequestPolicy: covers the state-dependent rules.
 *
 * Only PENDING requests can be updated/approved/declined/deleted.
 * Approved or paid requests can be completed by finance. Payment itself is
 * recorded through the linked finance payment workflow, not a status-only
 * mark-paid action.
 *
 * This test set locks down those transitions so accidental regressions
 * (e.g. removing a status guard) fail immediately.
 */
class PurchaseRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'chipper-farms',
            'name' => 'Chipper Farms',
        ]);
    }

    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName),
            'email' => $roleName . '@example.com',
            'password' => bcrypt('x'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }

    /*
     * NOTE: every in-memory PurchaseRequest stand-in below carries an
     * organization_id. sameOrganization() now fails closed (CF-P0-005), so an
     * unscoped stand-in is denied on tenancy grounds. For the positive cases
     * that produced an outright failure; more insidiously, the NEGATIVE cases
     * would have kept passing for the wrong reason -- denied on tenancy rather
     * than on the status or role rule each test exists to pin.
     */
    public function test_admin_can_approve_pending_request(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $pr = new PurchaseRequest(['status' => 'pending', 'organization_id' => $this->org->id]);
        $this->assertTrue(Gate::allows('approve', $pr));
    }

    public function test_admin_cannot_approve_already_approved_request(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $pr = new PurchaseRequest(['status' => 'approved', 'organization_id' => $this->org->id]);
        $this->assertFalse(Gate::allows('approve', $pr));
    }

    public function test_admin_cannot_approve_declined_request(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $pr = new PurchaseRequest(['status' => 'declined', 'organization_id' => $this->org->id]);
        $this->assertFalse(Gate::allows('approve', $pr));
    }

    public function test_manager_cannot_approve_even_pending_request(): void
    {
        $this->actingAs($this->makeUser('farmmanager'));
        $pr = new PurchaseRequest(['status' => 'pending', 'organization_id' => $this->org->id]);
        $this->assertFalse(Gate::allows('approve', $pr));
    }

    public function test_accountant_can_complete_approved_request(): void
    {
        $this->actingAs($this->makeUser('accountant'));
        $pr = new PurchaseRequest(['status' => 'approved', 'organization_id' => $this->org->id]);
        $this->assertTrue(Gate::allows('complete', $pr));
    }

    public function test_accountant_cannot_complete_pending_request(): void
    {
        $this->actingAs($this->makeUser('accountant'));
        $pr = new PurchaseRequest(['status' => 'pending', 'organization_id' => $this->org->id]);
        $this->assertFalse(Gate::allows('complete', $pr));
    }

    public function test_manager_cannot_complete_request(): void
    {
        $this->actingAs($this->makeUser('farmmanager'));
        $pr = new PurchaseRequest(['status' => 'approved', 'organization_id' => $this->org->id]);
        $this->assertFalse(Gate::allows('complete', $pr));
    }

    public function test_dataentry_can_create(): void
    {
        $this->actingAs($this->makeUser('dataentry'));
        $this->assertTrue(Gate::allows('create', PurchaseRequest::class));
    }

    public function test_accountant_cannot_create(): void
    {
        $this->actingAs($this->makeUser('accountant'));
        $this->assertFalse(Gate::allows('create', PurchaseRequest::class));
    }

    public function test_admin_cannot_update_approved_request(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $pr = new PurchaseRequest(['status' => 'approved', 'organization_id' => $this->org->id]);
        $this->assertFalse(Gate::allows('update', $pr));
    }

    public function test_admin_cannot_delete_approved_request(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $pr = new PurchaseRequest(['status' => 'approved', 'organization_id' => $this->org->id]);
        $this->assertFalse(Gate::allows('delete', $pr));
    }
}
