<?php

namespace App\Domain\Events\Costing;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Phase 5 — emitted when feed is issued to a pig group.
 *
 * Source is FeedIssue::class. Subscriber: FeedIssuedSubscriber posts
 * Dr 1230 Live Pig Inventory / Cr 1220 Finished Feed Inventory at
 * total_cost (which is unit_cost_at_issuance × quantity_kg).
 */
class FeedIssued extends DomainEvent
{
    public const TYPE = 'feed.issued';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $feedIssueId,
        public readonly int $groupId,
        public readonly int $feedTypeId,
        public readonly string $feedTypeName,
        public readonly float $quantityKg,
        public readonly float $unitCost,
        public readonly float $totalCost,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'feed_issue_id'  => $this->feedIssueId,
            'group_id'       => $this->groupId,
            'feed_type_id'   => $this->feedTypeId,
            'feed_type_name' => $this->feedTypeName,
            'quantity_kg'    => $this->quantityKg,
            'unit_cost'      => $this->unitCost,
            'total_cost'     => $this->totalCost,
        ];
    }
}
