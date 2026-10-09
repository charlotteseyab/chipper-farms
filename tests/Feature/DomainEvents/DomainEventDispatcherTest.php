<?php

namespace Tests\Feature\DomainEvents;

use App\Domain\Events\Sales\PigSaleRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Models\DomainEvent;
use App\Models\Organization;
use App\Models\PigSale;
use App\Services\DomainEvents\DispatchDomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the dispatcher core contract:
 *   - writes an outbox row from a DomainEvent instance
 *   - idempotency via unique key
 *   - rolls back with enclosing transaction
 *   - returns existing record on duplicate dispatch
 *   - status helpers on the model (markProcessed, markFailed, markSkipped)
 */
class DomainEventDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        // These tests drive the outbox and individual finance subscribers by
        // hand, so events must stay pending until the test processes them.
        // Instant posting has its own coverage in InstantPostingTest.
        config(['finance.instant_posting.enabled' => false]);
        $this->org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);
    }

    protected function makeEvent(int $saleId = 1): PigSaleRecorded
    {
        return new PigSaleRecorded(
            source: new SourceReference(
                module: 'sales',
                sourceType: PigSale::class,
                sourceId: $saleId,
                organizationId: $this->org->id,
            ),
            occurredAt: CarbonImmutable::parse('2026-01-15 10:00:00'),
            dispatchedByUserId: null,
            pigId: 1,
            pigCode: 'PIG-001',
            saleWeight: 80.0,
            weightType: 'live',
            pricePerKg: 30.0,
            saleTotal: 2400.0,
            salePurpose: 'market',
            paymentStatus: 'pending',
            paymentMethod: null,
            buyerName: 'Market Stall',
            transportCost: null,
        );
    }

    public function test_dispatcher_writes_outbox_row(): void
    {
        $dispatcher = new DispatchDomainEvent();
        $record = $dispatcher->dispatch($this->makeEvent());

        $this->assertDatabaseHas('domain_events', [
            'id' => $record->id,
            'event_type' => 'pig_sale.recorded',
            'source_type' => PigSale::class,
            'source_id' => 1,
            'organization_id' => $this->org->id,
            'status' => 'pending',
        ]);
    }

    public function test_dispatcher_is_idempotent_on_repeated_dispatch(): void
    {
        $dispatcher = new DispatchDomainEvent();

        $first = $dispatcher->dispatch($this->makeEvent());
        $second = $dispatcher->dispatch($this->makeEvent());
        $third = $dispatcher->dispatch($this->makeEvent());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, DomainEvent::count());
    }

    public function test_dispatch_inside_rolled_back_transaction_does_not_persist(): void
    {
        $dispatcher = new DispatchDomainEvent();

        try {
            \DB::transaction(function () use ($dispatcher) {
                $dispatcher->dispatch($this->makeEvent());
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, DomainEvent::count());
    }

    public function test_idempotency_key_format_includes_event_type_and_short_source(): void
    {
        $dispatcher = new DispatchDomainEvent();
        $record = $dispatcher->dispatch($this->makeEvent(42));

        $this->assertSame('pig_sale.recorded:PigSale:42', $record->idempotency_key);
    }

    public function test_different_source_ids_produce_different_outbox_rows(): void
    {
        $dispatcher = new DispatchDomainEvent();

        $dispatcher->dispatch($this->makeEvent(1));
        $dispatcher->dispatch($this->makeEvent(2));

        $this->assertSame(2, DomainEvent::count());
    }

    public function test_payload_is_stored_as_structured_json(): void
    {
        $dispatcher = new DispatchDomainEvent();
        $record = $dispatcher->dispatch($this->makeEvent());

        $this->assertIsArray($record->payload);
        $this->assertSame(2400.0, $record->payload['sale_total']);
        $this->assertSame('market', $record->payload['sale_purpose']);
        $this->assertSame('PIG-001', $record->payload['pig_code']);
    }

    public function test_mark_processed_updates_status_and_timestamp(): void
    {
        $dispatcher = new DispatchDomainEvent();
        $record = $dispatcher->dispatch($this->makeEvent());

        $record->markProcessed();

        $fresh = $record->fresh();
        $this->assertSame('processed', $fresh->status);
        $this->assertNotNull($fresh->processed_at);
    }

    public function test_mark_failed_increments_attempts_and_records_error(): void
    {
        $dispatcher = new DispatchDomainEvent();
        $record = $dispatcher->dispatch($this->makeEvent());

        $record->markFailed('connection timeout');
        $record->refresh();

        $this->assertSame('failed', $record->status);
        $this->assertSame('connection timeout', $record->processing_error);
        $this->assertSame(1, $record->processing_attempts);

        $record->markFailed('connection timeout');
        $record->refresh();
        $this->assertSame(2, $record->processing_attempts);
    }

    public function test_mark_skipped_records_reason_and_processed_at(): void
    {
        $dispatcher = new DispatchDomainEvent();
        $record = $dispatcher->dispatch($this->makeEvent());

        $record->markSkipped('void posted elsewhere');

        $fresh = $record->fresh();
        $this->assertSame('skipped', $fresh->status);
        $this->assertNotNull($fresh->processed_at);
        $this->assertSame('void posted elsewhere', $fresh->processing_error);
    }

    public function test_pending_scope_filters_correctly(): void
    {
        $dispatcher = new DispatchDomainEvent();

        $pending = $dispatcher->dispatch($this->makeEvent(1));
        $processed = $dispatcher->dispatch($this->makeEvent(2));
        $failed = $dispatcher->dispatch($this->makeEvent(3));

        $processed->markProcessed();
        $failed->markFailed('x');

        $ids = DomainEvent::pending()->pluck('id')->all();
        $this->assertSame([$pending->id], $ids);
    }

    public function test_source_reference_rejects_missing_organization_id(): void
    {
        $fakeModel = new PigSale();
        // No organization_id on the model and none passed — should throw.
        $this->expectException(\InvalidArgumentException::class);
        SourceReference::fromModel($fakeModel, 'sales');
    }

    public function test_idempotency_suffix_allows_multiple_events_per_source(): void
    {
        $dispatcher = new DispatchDomainEvent();

        // Two payment status change events on the same sale should not collide.
        $transitionA = new \App\Domain\Events\Sales\PigSalePaymentStatusChanged(
            source: new SourceReference('sales', PigSale::class, 7, $this->org->id),
            occurredAt: CarbonImmutable::parse('2026-01-15 10:00:00'),
            dispatchedByUserId: null,
            fromStatus: 'pending',
            toStatus:   'paid',
            saleTotal:  1000.0,
        );

        $transitionB = new \App\Domain\Events\Sales\PigSalePaymentStatusChanged(
            source: new SourceReference('sales', PigSale::class, 7, $this->org->id),
            occurredAt: CarbonImmutable::parse('2026-01-16 10:00:00'),
            dispatchedByUserId: null,
            fromStatus: 'paid',
            toStatus:   'pending',
            saleTotal:  1000.0,
        );

        $dispatcher->dispatch($transitionA);
        $dispatcher->dispatch($transitionB);

        $this->assertSame(2, DomainEvent::where('event_type', 'pig_sale.payment_status_changed')->count());
    }
}