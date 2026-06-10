<?php

namespace App\Jobs;

use App\Finance\Exceptions\DuplicatePostingException;
use App\Finance\Exceptions\PostingException;
use App\Finance\Outbox\SubscriberRegistry;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Support\CurrentOrganization;
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
 *   - Read up to $batchSize pending events ordered by created_at.
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
 * Concurrency:
 *   - Two workers running this simultaneously could pick up the same event.
 *     The PostingEngine's idempotency_key UNIQUE catches the duplicate
 *     write. The second worker gets DuplicatePostingException, which
 *     Subscriber treats as success — exactly the behaviour we want.
 *   - For belt-and-suspenders, the job can be configured as singleton
 *     (Laravel's WithoutOverlapping middleware). We don't enable that
 *     here because the concurrency-safe design is sufficient and forcing
 *     singleton creates a head-of-line blocking failure mode.
 */
class ProcessPendingDomainEventsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    // We don't retry the whole job — individual events have their own
    // status; retrying the job would re-process events whose status is
    // 'processed' (idempotent skip in Subscriber base) or 'failed'
    // (already at terminal state). Better to log and let the next
    // scheduled run pick up new pending events.

    public function __construct(public int $batchSize = 50)
    {
    }

    public function handle(SubscriberRegistry $registry, CurrentOrganization $currentOrg): void
    {
        $events = DomainEvent::query()
            ->where('status', DomainEvent::STATUS_PENDING)
            ->orderBy('created_at')
            ->limit($this->batchSize)
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $processed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($events as $event) {
            // Bind organization context for this event so subscribers
            // (and PostingEngine within them) operate scoped correctly.
            $org = $event->organization_id
                ? \App\Models\Organization::find($event->organization_id)
                : null;
            $currentOrg->bind($org);

            $subscribers = $registry->subscribersFor($event->event_type);

            if (empty($subscribers)) {
                // No subscriber registered for this event type — mark skipped
                // so it isn't picked up again. Future subscribers can re-process
                // by manually flipping status back to 'pending'.
                $event->update([
                    'status'              => DomainEvent::STATUS_SKIPPED,
                    'processed_at'        => now(),
                    'processing_error'    => 'No finance subscriber registered for event_type ' . $event->event_type,
                    'processing_attempts' => ($event->processing_attempts ?? 0) + 1,
                ]);
                $skipped++;
                continue;
            }

            foreach ($subscribers as $subscriberClass) {
                try {
                    /** @var Subscriber $subscriber */
                    $subscriber = app($subscriberClass);
                    $subscriber->handle($event->id);

                    $event->refresh();
                    if ($event->status === DomainEvent::STATUS_PROCESSED) {
                        $processed++;
                    } elseif ($event->status === DomainEvent::STATUS_FAILED) {
                        $failed++;
                        break;
                    } elseif ($event->status === DomainEvent::STATUS_SKIPPED) {
                        $skipped++;
                    }
                } catch (PostingException $e) {
                    // Subscriber base already marked the event failed.
                    Log::error("ProcessPendingDomainEventsJob: subscriber {$subscriberClass} threw PostingException for event #{$event->id}", [
                        'event_id'   => $event->id,
                        'event_type' => $event->event_type,
                        'subscriber' => $subscriberClass,
                        'error'      => $e->getMessage(),
                    ]);
                    $failed++;
                } catch (\Throwable $e) {
                    Log::error("ProcessPendingDomainEventsJob: subscriber {$subscriberClass} threw unexpected exception for event #{$event->id}", [
                        'event_id'   => $event->id,
                        'event_type' => $event->event_type,
                        'subscriber' => $subscriberClass,
                        'error'      => $e->getMessage(),
                        'trace'      => $e->getTraceAsString(),
                    ]);
                    $failed++;
                }
            }
        }

        Log::info("ProcessPendingDomainEventsJob complete", [
            'total'     => $events->count(),
            'processed' => $processed,
            'failed'    => $failed,
            'skipped'   => $skipped,
        ]);
    }
}
