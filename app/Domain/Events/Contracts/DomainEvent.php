<?php

namespace App\Domain\Events\Contracts;

use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Base class for every domain event dispatched through the outbox.
 *
 * Concrete events extend this class, set a constant TYPE (the dotted machine
 * name that consumers subscribe by), and implement payload() to return the
 * snapshot JSON-safe array.
 *
 * An idempotency suffix is optional — required only when the same source +
 * event type could be dispatched more than once legitimately. If the event
 * should be one-per-source (which is the usual case), leave it null.
 */
abstract class DomainEvent
{
    /**
     * Dotted machine name, e.g. 'pig_sale.recorded'. Overridden by subclasses.
     */
    public const TYPE = '';

    public function __construct(
        public readonly SourceReference $source,
        public readonly CarbonImmutable $occurredAt,
        public readonly ?int $dispatchedByUserId = null,
    ) {
    }

    /**
     * JSON-serialisable snapshot of the event. Must not depend on mutable
     * external state — the consumer may run hours after the source write,
     * and the source row may have changed by then.
     */
    abstract public function payload(): array;

    /**
     * Optional suffix for the idempotency key when multiple events per
     * source + type are legitimate. Default null = one event per source + type.
     */
    public function idempotencySuffix(): ?string
    {
        return null;
    }

    public function type(): string
    {
        return static::TYPE;
    }
}