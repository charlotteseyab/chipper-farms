<?php

namespace App\Jobs;

use App\Finance\Outbox\DomainEventProcessor;
use App\Finance\Outbox\OutboxClaimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes pending domain_events through their registered finance
 * subscribers.
 *
 * Lifecycle:
 *   - Run on a schedule (every minute via the scheduler).
 *   - Recover any claims abandoned by dead workers.
 *   - ATOMICALLY CLAIM up to $batchSize eligible events, ordered by occurred_at.
 *   - For each event:
 *       - Look up subscribers in SubscriberRegistry for event_type.
 *       - Instantiate each subscriber. Bind organization context.
 *       - Call subscriber->handle($event->id).
 *       - Subscriber's base class handles the success/failure status update.
 *   - Continue to next event (one event's failure must not abort others).
 *
 * Why a job rather than a simple foreach in a command?
 *   - Jobs can be queued, scheduled, and monitored via Horizon/Telescope.
 *   - Jobs can fail-and-retry independently.
 *   - The scheduler can fire this every minute without blocking other work.
 *
 * Why not use Laravel's built-in event broadcasting (Event::listen)?
 *   - The outbox decouples dispatch from processing. We want subscribers
 *     to run AFTER the source transaction commits, not inside it. Laravel
 *     events fire synchronously inside the dispatcher's call.
 *   - The outbox pattern also gives us a durable record we can replay.
 *
 * Handling per-event failure:
 *   - If subscriber throws PostingException (other than Duplicate), the
 *     subscriber's base class has already marked the event 'failed'. We
 *     log and move on.
 *   - If subscriber throws unrelated Throwable, base class has marked
 *     'failed' and re-thrown. We catch here, log, move on.
 *   - DuplicatePostingException is caught inside Subscriber and treated
 *     as success — never bubbles here.
 *
 * Concurrency and retry (CF-P1-015):
 *   - Events are now ATOMICALLY CLAIMED via OutboxClaimer before processing, so
 *     two workers never process the same event concurrently. See OutboxClaimer
 *     for why a guarded UPDATE is used rather than SELECT ... FOR UPDATE
 *     SKIP LOCKED.
 *   - The previous design's duplicate-safety argument was sound as far as it
 *     went: journal_postings carries a real UNIQUE idempotency key and
 *     Subscriber converts DuplicatePostingException into success, so racing
 *     workers could not produce duplicate journals. Claiming replaces wasted
 *     duplicate effort, but it is not what made this urgent.
 *   - What made it urgent was that FAILURE WAS TERMINAL. With $tries = 1 and no
 *     retry scheduling, any transient fault — a lock-wait timeout, a dropped
 *     connection, a deadlock — moved the event to 'failed' forever, silently
 *     dropping a finance posting. Transient failures now return to 'pending'
 *     with a backoff, and only exhausted events are dead-lettered to 'failed'.
 *   - Abandoned claims (worker OOM, SIGKILL during deploy) are recovered at the
 *     start of each run; without that they would sit in 'processing' invisible
 *     to the claim query forever.
 *
 * $tries remains 1 deliberately: retry is now the OUTBOX's responsibility, at
 * per-event granularity with its own attempt counter and backoff. Retrying the
 * whole job would re-run unrelated events and double-count attempts.
 */
class ProcessPendingDomainEventsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    // The whole job is not retried, because retry now lives at per-event
    // granularity in OutboxClaimer: a failed event returns to 'pending' with an
    // attempt count and a backoff, and only becomes 'failed' once its attempts
    // are exhausted. Retrying the job instead would re-run unrelated events in
    // the same batch and double-count their attempts.
    //
    // (The previous comment here justified $tries = 1 partly on the grounds that
    // 'failed' was "already at terminal state". That is no longer true and was
    // the defect: a transient fault permanently dropped a finance posting.)

    public function __construct(public int $batchSize = 50)
    {
    }

    public function handle(
        OutboxClaimer $claimer,
        DomainEventProcessor $processor,
    ): void {
        // Return work abandoned by dead workers to the pool before claiming.
        // Cheap when there is nothing to recover, and without it such events are
        // invisible to the claim query indefinitely.
        $recovered = $claimer->recoverStaleClaims();

        $events = $claimer->claim($this->batchSize);

        if ($events->isEmpty()) {
            if ($recovered > 0) {
                Log::info('ProcessPendingDomainEventsJob recovered stale claims but claimed no events', [
                    'recovered' => $recovered,
                ]);
            }

            return;
        }

        $processed = 0;
        $failed = 0;
        $skipped = 0;
        $retrying = 0;

        foreach ($events as $event) {
            match ($processor->process($event, $claimer)) {
                DomainEventProcessor::PROCESSED => $processed++,
                DomainEventProcessor::RETRYING  => $retrying++,
                DomainEventProcessor::FAILED    => $failed++,
                DomainEventProcessor::SKIPPED   => $skipped++,
            };
        }

        Log::info("ProcessPendingDomainEventsJob complete", [
            'worker'        => $claimer->token(),
            'claimed'       => $events->count(),
            'processed'     => $processed,
            'retrying'      => $retrying,
            'dead_lettered' => $failed,
            'skipped'       => $skipped,
            'recovered'     => $recovered,
        ]);
    }
}
