<?php

namespace App\Services\DomainEvents;

use App\Domain\Events\Contracts\DomainEvent;
use App\Models\DomainEvent as DomainEventRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The one and only way to record a domain event.
 *
 * Guarantees:
 *
 *   1. Transactional coupling. If the caller's enclosing DB transaction
 *      rolls back, the outbox row rolls back with it. This is why the
 *      service uses the current connection rather than opening its own
 *      transaction — it's designed to be called *inside* the source
 *      module's own transaction.
 *
 *   2. Idempotent. The idempotency_key unique constraint prevents duplicates.
 *      If a duplicate is attempted, the service returns the existing record
 *      rather than throwing. This makes replay and retry safe.
 *
 *   3. Typed. Callers pass a DomainEvent instance, not loose arrays.
 *
 * USAGE INSIDE A SOURCE TRANSACTION
 *
 *     DB::transaction(function () use ($service, $dispatcher) {
 *         $sale = PigSale::create([...]);
 *
 *         $dispatcher->dispatch(new PigSaleRecorded(
 *             source:   SourceReference::fromModel($sale, 'sales', $orgId),
 *             occurredAt: CarbonImmutable::now(),
 *             dispatchedByUserId: $actor->id,
 *             saleTotal: (float) $sale->sale_price,
 *             // ... event-specific fields
 *         ));
 *     });
 */
class DispatchDomainEvent
{
    /**
     * Record the given domain event in the outbox.
     *
     * Returns the persisted DomainEventRecord (either freshly created or the
     * pre-existing one if the idempotency key was already present).
     */
    public function dispatch(DomainEvent $event): DomainEventRecord
    {
        $key = $event->source->idempotencyKeyFor($event->type(), $event->idempotencySuffix());

        // Fast path: if a record already exists for this key, return it.
        // Under concurrent calls the race between this read and the insert
        // is handled by the UNIQUE constraint + the catch below.
        $existing = DomainEventRecord::where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DomainEventRecord::create([
                'organization_id'        => $event->source->organizationId,
                'event_type'             => $event->type(),
                'idempotency_key'        => $key,
                'source_module'          => $event->source->module,
                'source_type'            => $event->source->sourceType,
                'source_id'              => $event->source->sourceId,
                'payload'                => $event->payload(),
                'payload_version'        => 1,
                'status'                 => DomainEventRecord::STATUS_PENDING,
                'occurred_at'            => $event->occurredAt,
                'dispatched_by_user_id'  => $event->dispatchedByUserId,
            ]);
        } catch (QueryException $e) {
            // Race with another concurrent dispatch of the same event.
            // The unique constraint won. Return the record the other actor
            // created so the caller still gets the outbox row.
            if ($this->isUniqueViolation($e)) {
                $existing = DomainEventRecord::where('idempotency_key', $key)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $e;
        }
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        // MySQL: 23000 / 1062.  SQLite: 23000 / 19.  Postgres: 23505.
        $code = $e->getCode();
        $sqlState = (string) $code;

        if ($sqlState === '23000' || $sqlState === '23505') {
            return true;
        }

        $message = $e->getMessage();
        return str_contains($message, 'UNIQUE')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'duplicate key');
    }
}