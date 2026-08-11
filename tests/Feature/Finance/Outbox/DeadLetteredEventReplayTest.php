<?php

namespace Tests\Feature\Finance\Outbox;

use App\Finance\Outbox\OutboxClaimer;
use App\Finance\Outbox\SubscriberRegistry;
use App\Jobs\ProcessPendingDomainEventsJob;
use App\Models\DomainEvent;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 16 (§47) — the gap Phase 15 left open.
 *
 * Phase 15 gave staff a "Retry finance posting" button that returns a
 * dead-lettered outbox event to `pending`. Its tests asserted the event's status
 * changed. Nothing asserted the thing that actually matters to the farm: that a
 * re-queued event is then picked up and processed. A button that sets a column
 * and is never collected again would have passed every test Phase 15 wrote.
 *
 * WHY THIS IS NOT OBVIOUS
 * -----------------------
 * An event only reaches `failed` after `OutboxClaimer::MAX_ATTEMPTS` tries, so a
 * dead-lettered row arrives at the retry button with its attempt counter already
 * at the ceiling. The claim query does not filter on attempts — it filters on
 * status and `next_retry_at` — so the replay is collected. But `fail()` does
 * check the ceiling, which means a replay that fails again dead-letters
 * immediately instead of climbing the backoff ladder a second time.
 *
 * That is the right behaviour and worth pinning down: one press, one attempt.
 * Staff who fixed the cause get an answer straight away, and a farm does not
 * accumulate an invisible queue of events retrying on a ladder nobody can see.
 */
class DeadLetteredEventReplayTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['slug' => 'replay-farm', 'name' => 'Replay Farm']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
        $this->app->make(SubscriberRegistry::class)->reset();
    }

    /**
     * A dead-lettered event, exactly as `OutboxClaimer::fail()` leaves one.
     */
    protected function deadLetteredEvent(array $overrides = []): DomainEvent
    {
        return DomainEvent::create(array_merge([
            'organization_id'     => $this->org->id,
            'event_type'          => 'unhandled.event_type',
            'idempotency_key'     => 'dead-letter-' . uniqid(),
            'source_module'       => 'test',
            'source_type'         => 'TestSource',
            'source_id'           => 1,
            'payload'             => [],
            'payload_version'     => 1,
            'status'              => DomainEvent::STATUS_FAILED,
            'processing_attempts' => OutboxClaimer::MAX_ATTEMPTS,
            'processing_error'    => 'Account 4110 does not exist',
            'occurred_at'         => now(),
        ], $overrides));
    }

    /**
     * The claim query must not skip a row whose attempts are at the ceiling.
     */
    public function test_a_re_queued_dead_letter_is_collected_by_the_claimer(): void
    {
        $event = $this->deadLetteredEvent();

        // What the Phase 15 button does, and nothing more.
        $event->forceFill([
            'status'           => DomainEvent::STATUS_PENDING,
            'claimed_at'       => null,
            'claimed_by'       => null,
            'next_retry_at'    => null,
            'processing_error' => null,
        ])->save();

        $claimed = $this->app->make(OutboxClaimer::class)->claim(10);

        $this->assertCount(
            1,
            $claimed,
            'A dead-lettered event returned to pending was not collected. The retry button '
            . 'would set a column and nothing would ever happen.'
        );
        $this->assertSame($event->id, $claimed->first()->id);
    }

    /**
     * End to end: re-queue, run the real job, and see the event reach a terminal
     * state rather than sitting where it was left.
     */
    public function test_a_replayed_event_is_processed_rather_than_left_pending(): void
    {
        $event = $this->deadLetteredEvent();

        $event->forceFill([
            'status'           => DomainEvent::STATUS_PENDING,
            'claimed_at'       => null,
            'claimed_by'       => null,
            'next_retry_at'    => null,
            'processing_error' => null,
        ])->save();

        ProcessPendingDomainEventsJob::dispatchSync();

        $event->refresh();

        $this->assertNotSame(
            DomainEvent::STATUS_PENDING,
            $event->status,
            'The replayed event was never picked up.'
        );

        // No subscriber is registered for this type, so 'skipped' is the correct
        // terminal state. What matters here is that the outbox reached it — the
        // routing of real events is covered by the subscriber tests.
        $this->assertSame(DomainEvent::STATUS_SKIPPED, $event->status);
        $this->assertNotNull($event->processed_at);
    }

    /**
     * A dead letter left alone stays put.
     *
     * Without this, the test above could pass because the claimer collects
     * `failed` rows too — which would mean the whole dead-letter mechanism was
     * doing nothing and staff were being asked to press a button for an event
     * that would have retried by itself.
     */
    public function test_a_dead_letter_is_not_collected_until_someone_re_queues_it(): void
    {
        $event = $this->deadLetteredEvent();

        $claimed = $this->app->make(OutboxClaimer::class)->claim(10);

        $this->assertCount(0, $claimed);
        $this->assertSame(DomainEvent::STATUS_FAILED, $event->fresh()->status);
    }

    /**
     * One press, one attempt.
     *
     * The replay comes back with its counter at the ceiling, so a second failure
     * dead-letters straight away instead of starting the backoff ladder again.
     */
    public function test_a_replay_that_fails_again_dead_letters_immediately(): void
    {
        $event = $this->deadLetteredEvent();

        $event->forceFill([
            'status'              => DomainEvent::STATUS_PENDING,
            'claimed_at'          => null,
            'claimed_by'          => null,
            'next_retry_at'       => null,
            'processing_error'    => null,
        ])->save();

        $claimer = $this->app->make(OutboxClaimer::class);
        $claimed = $claimer->claim(10)->first();

        $requeued = $claimer->fail($claimed, 'Account 4110 still does not exist');

        $this->assertFalse($requeued, 'A replay that fails again should not re-enter the backoff ladder.');

        $event->refresh();
        $this->assertSame(DomainEvent::STATUS_FAILED, $event->status);
        $this->assertNull($event->next_retry_at);
        $this->assertStringContainsString('still does not exist', $event->processing_error);
    }
}
