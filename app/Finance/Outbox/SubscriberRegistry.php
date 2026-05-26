<?php

namespace App\Finance\Outbox;

/**
 * In-memory registry mapping domain event types to subscriber classes.
 *
 * Registered in AppServiceProvider during boot. The outbox processor
 * (ProcessPendingDomainEventsJob) reads this to know which subscribers
 * to instantiate for each pending event.
 *
 * Multiple subscribers per event_type are supported (a single sale event
 * could trigger separate revenue + COGS subscribers, for example, though
 * for Phase 1c we pack both into one subscriber for simplicity).
 *
 * Registration is idempotent — registering the same (type, class) twice
 * is a no-op.
 */
class SubscriberRegistry
{
    /** @var array<string, list<class-string>> */
    protected array $map = [];

    public function register(string $eventType, string $subscriberClass): void
    {
        if (! isset($this->map[$eventType])) {
            $this->map[$eventType] = [];
        }

        if (in_array($subscriberClass, $this->map[$eventType], true)) {
            return;
        }

        $this->map[$eventType][] = $subscriberClass;
    }

    /**
     * @return list<class-string>  Subscribers registered for this event type, or [].
     */
    public function subscribersFor(string $eventType): array
    {
        return $this->map[$eventType] ?? [];
    }

    /**
     * @return list<string>  All registered event types.
     */
    public function registeredTypes(): array
    {
        return array_keys($this->map);
    }

    /**
     * Reset registry. Used by tests.
     */
    public function reset(): void
    {
        $this->map = [];
    }
}
