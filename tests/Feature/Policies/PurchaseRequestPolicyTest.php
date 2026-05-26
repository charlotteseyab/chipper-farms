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
 * Only APPROVED requests can be markPaid.
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

    public function test_admin_can_approve_pending_request(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $pr = new PurchaseRequest(['status' => 'pending']);
        $this->assertTrue(Gate::allows('approve', $pr));
    }

    public function test_admin_cannot_approve_already_approved_request(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $pr = new PurchaseRequest(['status' => 'approved']);
        $this->assertFalse(Gate::allows('approve', $pr));
    }

    public function test_admin_cannot_approve_declined_request(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $pr = new PurchaseRequest(['status' => 'declined']);
        $this->assertFalse(Gate::allows('approve', $pr));
    }

    public function test_manager_cannot_approve_even_pending_request(): void
    {
        $this->actingAs($this->makeUser('farmmanager'));
        $pr = new PurchaseRequest(['status' => 'pending']);
        $this->assertFalse(Gate::allows('approve', $pr));
    }

    public function test_accountant_can_mark_approved_request_paid(): void
    {
        $this->actingAs($this->makeUser('accountant'));
        $pr = new PurchaseRequest(['status' => 'approved']);
        $this->assertTrue(Gate::allows('markPaid', $pr));
    }

    public function test_accountant_cannot_mark_pending_request_paid(): void
    {
        $this->actingAs($this->makeUser('accountant'));
        $pr = new PurchaseRequest(['status' => 'pending']);
        $this->assertFalse(Gate::allows('markPaid', $pr));
    }

    public function test_manager_cannot_mark_paid(): void
    {
        $this->actingAs($this->makeUser('farmmanager'));
        $pr = new PurchaseRequest(['status' => 'approved']);
        $this->assertFalse(Gate::allows('markPaid', $pr));
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
        $pr = new PurchaseRequest(['status' => 'approved']);
        $this->assertFalse(Gate::allows('update', $pr));
    }

    public function test_admin_cannot_delete_approved_request(): void
    {
        $this->actingAs($this->makeUser('admin'));
        $pr = new PurchaseRequest(['status' => 'approved']);
        $this->assertFalse(Gate::allows('delete', $pr));
    }
}