<?php

namespace App\Finance\Subscribers;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Exceptions\DuplicatePostingException;
use App\Finance\Exceptions\PostingException;
use App\Finance\Posting\PostingEngine;
use App\Models\DomainEvent;
use App\Models\Finance\JournalBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base for finance subscribers that listen to domain events
 * and post journals.
 *
 * Each concrete subscriber:
 *   1. Declares which event_type it handles via $eventType.
 *   2. Implements buildDraft() to construct the JournalBatchDraft from
 *      the event payload.
 *   3. Optionally overrides shouldHandle() to skip events conditionally.
 *
 * The base handles:
 *   - Queue plumbing (ShouldQueue + InteractsWithQueue).
 *   - Loading the DomainEvent row from the event id.
 *   - Calling PostingEngine::post().
 *   - Catching DuplicatePostingException and treating as success (no retry).
 *   - Catching transient PostingException and releasing back to queue.
 *   - Catching permanent PostingException and failing the job.
 *   - Updating DomainEvent status (processed | failed).
 *
 * Concrete subscribers in Phase 1c onwards extend this and only need
 * to implement buildDraft().
 */
abstract class Subscriber implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The domain event type this subscriber handles.
     * E.g. 'pig_sale.recorded', 'feed_batch.produced'.
     */
    abstract protected function eventType(): string;

    /**
     * Build the batch draft from the domain event payload.
     * Return null to skip posting (e.g. event has insufficient data and
     * we want to mark it 'skipped' rather than 'failed').
     */
    abstract protected function buildDraft(DomainEvent $event): ?JournalBatchDraft;

    /**
     * Override to add per-event filtering. Default: handle all events of
     * the declared type.
     */
    protected function shouldHandle(DomainEvent $event): bool
    {
        return $event->event_type === $this->eventType();
    }

    /**
     * Entry point — Laravel's event dispatcher calls this with the
     * domain_event_id (NOT the model itself, to keep queue payloads small).
     */
    public function handle(int $domainEventId): void
    {
        $event = DomainEvent::find($domainEventId);

        if (! $event) {
            $class = static::class;Log::warning("Subscriber {$class}: domain_event #{$domainEventId} not found");
            return;
        }

        if ($event->status === DomainEvent::STATUS_PROCESSED) {
            // Already done. Likely a queue retry. No-op.
            return;
        }

        if (! $this->shouldHandle($event)) {
            return;
        }

        try {
            $draft = $this->buildDraft($event);

            if ($draft === null) {
                $this->markEvent($event, DomainEvent::STATUS_SKIPPED);
                return;
            }

            $batch = app(PostingEngine::class)->post($draft);

            $this->markEvent($event, DomainEvent::STATUS_PROCESSED);

            $this->onPostedSuccessfully($event, $batch);
        } catch (DuplicatePostingException $e) {
            // The event was already posted (likely by a previous attempt).
            // Mark as processed and exit — this is the success path for
            // duplicate delivery.
            $class = static::class;
            Log::info("Subscriber {$class}: duplicate posting for event #{$event->id} — already posted as batch #{$e->existingBatchId}");
            $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
        } catch (PostingException $e) {
            $this->markEvent($event, DomainEvent::STATUS_FAILED, $e->getMessage());

            if ($e->isTransient()) {
                // Release back to queue for retry.
                $this->release(60);
                return;
            }

            // Permanent failure. Fail the job (Laravel will move it to failed_jobs).
            throw $e;
        } catch (\Throwable $e) {
            $this->markEvent($event, DomainEvent::STATUS_FAILED, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Hook for concrete subscribers that need to perform side effects
     * after a successful post (e.g. updating the source row's is_posted flag).
     */
    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        // Default no-op.
    }

    protected function markEvent(DomainEvent $event, string $status, ?string $error = null): void
    {
        $event->update([
            'status'               => $status,
            'processed_at'         => now(),
            'processing_error'     => $error,
            'processing_attempts'  => ($event->processing_attempts ?? 0) + 1,
        ]);
    }
}
