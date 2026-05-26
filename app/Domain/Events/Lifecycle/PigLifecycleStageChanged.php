<?php

namespace App\Domain\Events\Lifecycle;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a pig's stock_type changes (promotion, demotion) or its
 * lifecycle stage progresses materially. Not every stage change is material
 * for finance — only those that shift the pig between biological asset
 * categories (e.g. standard stock -> breeding stock) warrant an event.
 *
 * Uses the from/to stages as the idempotency suffix so the same pig can
 * have multiple lifecycle events over its life.
 */
class PigLifecycleStageChanged extends DomainEvent
{
    public const TYPE = 'pig_lifecycle.stage_changed';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $pigId,
        public readonly string $pigCode,
        public readonly string $fromStockType,
        public readonly string $toStockType,
        public readonly string $reason,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function idempotencySuffix(): ?string
    {
        return "{$this->fromStockType}-{$this->toStockType}-{$this->occurredAt->getTimestamp()}";
    }

    public function payload(): array
    {
        return [
            'pig_id'          => $this->pigId,
            'pig_code'        => $this->pigCode,
            'from_stock_type' => $this->fromStockType,
            'to_stock_type'   => $this->toStockType,
            'reason'          => $this->reason,
        ];
    }
}