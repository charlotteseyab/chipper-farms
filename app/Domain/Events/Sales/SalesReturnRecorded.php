<?php

namespace App\Domain\Events\Sales;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a sales return is recorded (CF-P0-008).
 *
 * Deliberately ONE event for both feed and pig returns, dispatched with
 * different subscribers keyed off returnable_type. The alternative — separate
 * event types — would duplicate the orchestration and make it possible for the
 * two paths to drift, which is how the original refund handoff ended up
 * partially automated in the first place.
 *
 * The payload carries the DECISION, not the computed amounts. Amounts are
 * derived by the subscriber from the original sale and its inventory movement,
 * because:
 *
 *   - the cost basis must come from the movement the sale actually created, not
 *     from anything recomputed at return time. Weighted-average cost moves;
 *     returning stock at today's WAC books a valuation gain or loss that never
 *     happened;
 *   - a payload amount could disagree with the subledger, and the subscriber is
 *     the only place that can see both.
 *
 * `restock` is in the payload because it is a business decision that cannot be
 * inferred: goods that come back re-enter inventory and reverse COGS, goods that
 * do not (spoiled feed, an animal that died in transit, an item the customer
 * keeps) leave the cost consumed and reverse revenue only.
 */
class SalesReturnRecorded extends DomainEvent
{
    public const TYPE = 'sales_return.recorded';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $salesReturnId,
        public readonly string $returnableType,
        public readonly int $returnableId,
        /** Quantity returned, as a decimal string (bcmath, never float). */
        public readonly string $quantity,
        /** Proportion of the original sale, 0 < p <= 1, as a decimal string. */
        public readonly string $proportion,
        public readonly bool $restock,
        public readonly ?int $storefrontOrderId,
        public readonly ?string $reason,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'sales_return_id'      => $this->salesReturnId,
            'returnable_type'      => $this->returnableType,
            'returnable_id'        => $this->returnableId,
            'quantity'             => $this->quantity,
            'proportion'           => $this->proportion,
            'restock'              => $this->restock,
            'storefront_order_id'  => $this->storefrontOrderId,
            'reason'               => $this->reason,
        ];
    }
}
