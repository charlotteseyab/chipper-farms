<?php

namespace Tests\Feature\Services;

use App\Models\DomainEvent;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Procurement\PurchaseRequestLifecycleService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->actor = User::forceCreate([
            'name' => 'Admin', 'email' => 'admin_' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'role' => 'admin', 'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    protected function makePR(string $status = 'pending'): PurchaseRequest
    {
        $pr = PurchaseRequest::create([
            'request_no' => 'PR-' . uniqid(),
            'request_date' => now()->toDateString(),
            'requested_by' => $this->actor->id,
            'status' => $status,
            'total_amount' => 1500.00,
            'organization_id' => $this->org->id,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => 'Maize',
            'description' => 'Maize meal',
            'quantity' => 10,
            'unit_price' => 150,
            'total_price' => 1500,
        ]);

        return $pr->fresh()->load('items');
    }

    public function test_approve_persists_status_and_emits_event(): void
    {
        $pr = $this->makePR('pending');
        $service = $this->app->make(PurchaseRequestLifecycleService::class);

        $service->approve($pr, $this->actor);

        $this->assertSame('approved', $pr->fresh()->status);
        $this->assertSame($this->actor->id, $pr->fresh()->approved_by);
        $this->assertNotNull($pr->fresh()->approved_at);

        $this->assertSame(1, DomainEvent::where('event_type', 'purchase_request.approved')
            ->where('source_id', $pr->id)
            ->count());
    }

    public function test_decline_persists_reason_and_emits_event(): void
    {
        $pr = $this->makePR('pending');
        $service = $this->app->make(PurchaseRequestLifecycleService::class);

        $service->decline($pr, $this->actor, 'Budget exceeded');

        $fresh = $pr->fresh();
        $this->assertSame('declined', $fresh->status);
        $this->assertSame('Budget exceeded', $fresh->decline_reason);

        $event = DomainEvent::where('event_type', 'purchase_request.declined')
            ->where('source_id', $pr->id)
            ->latest()
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('Budget exceeded', $event->payload['decline_reason']);
    }

    public function test_mark_as_paid_persists_status_and_emits_event(): void
    {
        $pr = $this->makePR('approved');
        $service = $this->app->make(PurchaseRequestLifecycleService::class);

        $service->markAsPaid($pr, $this->actor);

        $this->assertSame('paid', $pr->fresh()->status);

        $this->assertSame(1, DomainEvent::where('event_type', 'purchase_request.paid')
            ->where('source_id', $pr->id)
            ->count());
    }

    public function test_event_payload_includes_organization_id(): void
    {
        $pr = $this->makePR('pending');
        $service = $this->app->make(PurchaseRequestLifecycleService::class);

        $service->approve($pr, $this->actor);

        $event = DomainEvent::where('event_type', 'purchase_request.approved')
            ->where('source_id', $pr->id)
            ->first();

        $this->assertSame($this->org->id, (int) $event->organization_id);
    }
}
