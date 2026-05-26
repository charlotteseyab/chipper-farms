<?php

namespace Tests\Feature\Finance\Outbox;

use App\Finance\Outbox\SubscriberRegistry;
use App\Jobs\ProcessPendingDomainEventsJob;
use App\Models\DomainEvent as DomainEventRecord;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboxProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->app->make(SubscriberRegistry::class)->reset();
    }

    public function test_event_with_no_registered_subscriber_marked_skipped(): void
    {
        DomainEventRecord::create([
            'organization_id'    => $this->org->id,
            'event_type'         => 'unhandled.event_type',
            'idempotency_key'    => 'unhandled-key-1',
            'source_module'      => 'test',
            'source_type'        => 'TestSource',
            'source_id'          => 1,
            'payload'            => [],
            'payload_version'    => 1,
            'status'             => DomainEventRecord::STATUS_PENDING,
            'occurred_at'        => now(),
        ]);

        ProcessPendingDomainEventsJob::dispatchSync();

        $event = DomainEventRecord::first();
        $this->assertSame(DomainEventRecord::STATUS_SKIPPED, $event->status);
        $this->assertNotNull($event->processed_at);
        $this->assertStringContainsString('No finance subscriber registered', $event->processing_error);
    }

    public function test_already_processed_event_not_reprocessed(): void
    {
        $event = DomainEventRecord::create([
            'organization_id'    => $this->org->id,
            'event_type'         => 'pig_sale.recorded',
            'idempotency_key'    => 'already-done-1',
            'source_module'      => 'sales',
            'source_type'        => 'App\\Models\\PigSale',
            'source_id'          => 99,
            'payload'            => [],
            'payload_version'    => 1,
            'status'             => DomainEventRecord::STATUS_PROCESSED,
            'occurred_at'        => now(),
            'processed_at'       => now()->subMinutes(5),
        ]);

        ProcessPendingDomainEventsJob::dispatchSync();

        $event->refresh();
        $this->assertSame(DomainEventRecord::STATUS_PROCESSED, $event->status);
        $this->assertSame(0, $event->processing_attempts ?? 0);
        // processed_at unchanged from when we set it.
    }

    public function test_empty_pending_queue_is_a_noop(): void
    {
        // No pending events. Job should run cleanly without error.
        ProcessPendingDomainEventsJob::dispatchSync();
        $this->assertSame(0, DomainEventRecord::count());
    }

    public function test_subscriber_registry_returns_empty_for_unknown_type(): void
    {
        $registry = $this->app->make(SubscriberRegistry::class);
        $this->assertSame([], $registry->subscribersFor('something.never_registered'));
    }

    public function test_subscriber_registry_register_is_idempotent(): void
    {
        $registry = $this->app->make(SubscriberRegistry::class);

        $registry->register('test.event', 'App\\Test\\SubscriberA');
        $registry->register('test.event', 'App\\Test\\SubscriberA');  // Duplicate.

        $this->assertSame(['App\\Test\\SubscriberA'], $registry->subscribersFor('test.event'));
    }

    public function test_subscriber_registry_supports_multiple_subscribers_per_type(): void
    {
        $registry = $this->app->make(SubscriberRegistry::class);

        $registry->register('test.event', 'App\\Test\\SubscriberA');
        $registry->register('test.event', 'App\\Test\\SubscriberB');

        $this->assertSame(
            ['App\\Test\\SubscriberA', 'App\\Test\\SubscriberB'],
            $registry->subscribersFor('test.event')
        );
    }
}
