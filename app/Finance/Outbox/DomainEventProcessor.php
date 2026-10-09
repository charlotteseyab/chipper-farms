<?php

namespace App\Finance\Outbox;

use App\Finance\Exceptions\PostingException;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Log;

/**
 * Runs one claimed event through its finance subscribers.
 *
 * Shared by the outbox worker (ProcessPendingDomainEventsJob) and instant
 * posting (InstantEventPoster), so both follow the same rules: organisation
 * context per event, a failure handed to the claimer for a scheduled retry,
 * and an event with no subscriber marked skipped.
 */
class DomainEventProcessor
{
    public const PROCESSED = 'processed';
    public const RETRYING = 'retrying';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    public function __construct(
        protected SubscriberRegistry $registry,
        protected CurrentOrganization $currentOrg,
    ) {
    }

    /** @return string One of the outcome constants. */
    public function process(DomainEvent $event, OutboxClaimer $claimer): string
    {
        // Bind organization context for this event so subscribers
        // (and PostingEngine within them) operate scoped correctly.
        $org = $event->organization_id ? Organization::find($event->organization_id) : null;
        $this->currentOrg->bind($org);

        $subscribers = $this->registry->subscribersFor($event->event_type);

        if (empty($subscribers)) {
            // No subscriber registered for this event type — mark skipped
            // so it isn't picked up again. Future subscribers can re-process
            // by manually flipping status back to 'pending'.
            //
            // processing_attempts is NOT incremented here: OutboxClaimer
            // already incremented it when claiming.
            $event->update([
                'status'           => DomainEvent::STATUS_SKIPPED,
                'processed_at'     => now(),
                'processing_error' => 'No finance subscriber registered for event_type ' . $event->event_type,
                'claimed_by'       => null,
                'claimed_at'       => null,
            ]);

            return self::SKIPPED;
        }

        $outcome = self::SKIPPED;

        foreach ($subscribers as $subscriberClass) {
            try {
                /** @var Subscriber $subscriber */
                $subscriber = app($subscriberClass);
                $subscriber->handle($event->id);

                $event->refresh();
                if ($event->status === DomainEvent::STATUS_PROCESSED) {
                    $claimer->release($event);
                    $outcome = self::PROCESSED;
                } elseif ($event->status === DomainEvent::STATUS_FAILED) {
                    // The Subscriber base class marks 'failed' on error. Hand
                    // it to the claimer so a transient fault becomes a
                    // scheduled retry rather than a permanent loss.
                    return $claimer->fail($event, (string) $event->processing_error) ? self::RETRYING : self::FAILED;
                } elseif ($event->status === DomainEvent::STATUS_SKIPPED) {
                    $claimer->release($event);
                }
            } catch (PostingException $e) {
                // Subscriber base already marked the event failed.
                Log::error("DomainEventProcessor: subscriber {$subscriberClass} threw PostingException for event #{$event->id}", [
                    'event_id'   => $event->id,
                    'event_type' => $event->event_type,
                    'subscriber' => $subscriberClass,
                    'error'      => $e->getMessage(),
                ]);

                return $claimer->fail($event->refresh(), $e->getMessage()) ? self::RETRYING : self::FAILED;
            } catch (\Throwable $e) {
                Log::error("DomainEventProcessor: subscriber {$subscriberClass} threw unexpected exception for event #{$event->id}", [
                    'event_id'   => $event->id,
                    'event_type' => $event->event_type,
                    'subscriber' => $subscriberClass,
                    'error'      => $e->getMessage(),
                    'trace'      => $e->getTraceAsString(),
                ]);

                return $claimer->fail($event->refresh(), $e->getMessage()) ? self::RETRYING : self::FAILED;
            }
        }

        return $outcome;
    }
}
