<?php

namespace App\Domain\Events\Inventory;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * A physical count moved finished-feed stock to the counted quantity.
 * The GL amount comes from the inventory movement, not this payload.
 */
class FinishedFeedCounted extends DomainEvent
{
    public const TYPE = 'finished_feed.counted';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $feedTypeId,
        public readonly string $feedTypeName,
        public readonly float $quantityDelta,
        public readonly float $countedQuantity,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'feed_type_id'     => $this->feedTypeId,
            'feed_type_name'   => $this->feedTypeName,
            'quantity_delta'   => $this->quantityDelta,
            'counted_quantity' => $this->countedQuantity,
        ];
    }
}
