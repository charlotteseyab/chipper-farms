<?php

namespace App\Domain\Events\Feed;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a feed batch is mixed. Phase 1 finance will translate into:
 *   - dr finished feed inventory (at total_cost)
 *   - cr raw material inventory (per ingredient's usage cost)
 *
 * The ingredient breakdown is kept in the payload so the consumer does not
 * need to re-read the feed_batch_items table (which could be edited later,
 * though that pattern is not supported today).
 */
class FeedBatchProduced extends DomainEvent
{
    public const TYPE = 'feed_batch.produced';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $feedBatchId,
        public readonly int $feedTypeId,
        public readonly string $feedTypeName,
        public readonly float $batchSizeKg,
        public readonly float $totalCost,
        /** @var array<int, array<string, mixed>> */
        public readonly array $ingredients,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'feed_batch_id'   => $this->feedBatchId,
            'feed_type_id'    => $this->feedTypeId,
            'feed_type_name'  => $this->feedTypeName,
            'batch_size_kg'   => $this->batchSizeKg,
            'total_cost'      => $this->totalCost,
            'ingredients'     => $this->ingredients,
        ];
    }
}