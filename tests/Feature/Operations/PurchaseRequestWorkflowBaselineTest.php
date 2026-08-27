<?php

namespace Tests\Feature\Operations;

use App\Models\DomainEvent;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 0.6 — regression baseline for the purchase request workflow.
 *
 * Exercises the HTTP-facing side of the PurchaseRequestController:
 *   - create
 *   - approve (emits PurchaseRequestApproved)
 *   - decline (emits PurchaseRequestDeclined)
 *   - payment status cannot be changed outside the finance payment flow
 *   - state transitions refused when not in the right prior state
 *
 * Prerequisite: Batch A policies registered, Batch B source integration
 * patches applied.
 */
class PurchaseRequestWorkflowBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected User $dataEntry;
    protected User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        $this->admin      = $this->makeUser('admin');
        $this->dataEntry  = $this->makeUser('dataentry');
        $this->accountant = $this->makeUser('accountant');

        $this->app->make(CurrentOrganization::class)->bind($this->org);
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

    protected function makePendingRequest(User $requester, float $amount = 500.00): PurchaseRequest
    {
        $pr = PurchaseRequest::create([
            'request_no' => 'PR-' . uniqid(),
            'request_date' => now()->toDateString(),
            'requested_by' => $requester->id,
            'status' => 'pending',
            'total_amount' => $amount,
            'purpose' => 'Feed stock top-up',
        ]);

        $pr->items()->create([
            'item_name' => 'Maize bran',
            'description' => 'Maize bran (50kg bag)',
            'quantity' => 10,
            'unit_price' => 50,
            'total_price' => 500,
        ]);

        return $pr->fresh(['items']);
    }

    public function test_new_purchase_request_is_scoped_to_current_organization(): void
    {
        $this->actingAs($this->dataEntry)
            ->post(route('purchase-requests.store'), [
                'request_date' => now()->toDateString(),
                'purpose' => 'Feed stock top-up',
                'items' => [[
                    'description' => 'Maize',
                    'quantity' => 10,
                    'unit_price' => 50,
                    'unit' => 'kg',
                ]],
            ])
            ->assertRedirect(route('purchase-requests.index'));

        $this->assertDatabaseHas('purchase_requests', [
            'organization_id' => $this->org->id,
            'requested_by' => $this->dataEntry->id,
            'purpose' => 'Feed stock top-up',
        ]);
    }

    public function test_admin_can_approve_pending_request_and_event_is_emitted(): void
    {
        $pr = $this->makePendingRequest($this->dataEntry);

        $response = $this->actingAs($this->admin)
            ->post(route('purchase-requests.approve', $pr));

        $response->assertRedirect();
        $this->assertSame('approved', $pr->fresh()->status);

        $event = DomainEvent::where('event_type', 'purchase_request.approved')
            ->where('source_id', $pr->id)
            ->first();

        $this->assertNotNull($event, 'Expected purchase_request.approved event');
        $this->assertSame($this->admin->id, $event->dispatched_by_user_id);
        $this->assertSame(500.0, $event->payload['total_amount']);
    }

    public function test_approving_non_pending_request_is_rejected(): void
    {
        $pr = $this->makePendingRequest($this->dataEntry);
        $pr->update(['status' => 'approved']);

        $this->actingAs($this->admin)
            ->post(route('purchase-requests.approve', $pr))
            ->assertForbidden();

        // Still approved — no state flip, no extra event.
        $this->assertSame('approved', $pr->fresh()->status);
    }

    public function test_admin_can_decline_pending_request_and_event_is_emitted(): void
    {
        $pr = $this->makePendingRequest($this->dataEntry);

        $this->actingAs($this->admin)
            ->post(route('purchase-requests.decline', $pr), [
                'decline_reason' => 'Budget exhausted for this quarter',
            ])
            ->assertRedirect();

        $this->assertSame('declined', $pr->fresh()->status);

        $event = DomainEvent::where('event_type', 'purchase_request.declined')
            ->where('source_id', $pr->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('Budget exhausted for this quarter', $event->payload['decline_reason']);
    }

    public function test_legacy_status_only_mark_paid_route_is_not_available(): void
    {
        $this->assertFalse(Route::has('purchase-requests.mark-paid'));
    }

    public function test_data_entry_cannot_approve(): void
    {
        $pr = $this->makePendingRequest($this->dataEntry);

        $this->actingAs($this->dataEntry)
            ->post(route('purchase-requests.approve', $pr))
            ->assertForbidden();

        $this->assertSame('pending', $pr->fresh()->status);
    }

    public function test_accountant_cannot_approve(): void
    {
        $pr = $this->makePendingRequest($this->dataEntry);

        $this->actingAs($this->accountant)
            ->post(route('purchase-requests.approve', $pr))
            ->assertForbidden();
    }
}
