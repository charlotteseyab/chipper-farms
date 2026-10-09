<?php

namespace App\Finance\Outbox;

use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Log;

/**
 * Posts an event to finance straight after the save that created it commits,
 * instead of waiting for the next outbox run.
 *
 * The outbox row is still written first and stays the safety net: if posting
 * here fails (closed period, missing account mapping), the claimer schedules a
 * retry exactly as a worker would, and the user's save is unaffected. The
 * guarded claim means the event is posted once, whichever path reaches it.
 */
class InstantEventPoster
{
    public function __construct(
        protected OutboxClaimer $claimer,
        protected DomainEventProcessor $processor,
        protected CurrentOrganization $currentOrg,
    ) {
    }

    public static function appliesTo(string $eventType): bool
    {
        return (bool) config('finance.instant_posting.enabled')
            && in_array($eventType, (array) config('finance.instant_posting.event_types', []), true);
    }

    public function post(int $eventId): void
    {
        // Processing binds each event's organisation; give the request its own back.
        $requestOrganization = $this->currentOrg->get();

        try {
            $event = $this->claimer->claimOne($eventId);
            if ($event !== null) {
                $this->processor->process($event, $this->claimer);
            }
        } catch (\Throwable $e) {
            // Never surface a posting problem as a failed save: the record is
            // already committed and the outbox will retry the event.
            Log::warning('Instant posting failed; the outbox will retry.', ['event_id' => $eventId, 'error' => $e->getMessage()]);
        } finally {
            $this->currentOrg->bind($requestOrganization);
        }
    }
}
