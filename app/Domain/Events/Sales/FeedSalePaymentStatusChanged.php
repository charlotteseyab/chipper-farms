<?php

namespace App\Domain\Events\Sales;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a feed sale's payment status changes. The feed analogue of
 * PigSalePaymentStatusChanged — closes the gap that previously left a pending
 * feed AR with no event-driven settlement path. Translated into a cash receipt
 * (Dr Cash / Cr AR 1120) by FeedSalePaymentReceivedSubscriber on pending→paid.
 */
class FeedSalePaymentStatusChanged extends DomainEvent
{
    public const TYPE = 'feed_sale.payment_status_changed';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        public readonly float $saleTotal,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function idempotencySuffix(): ?string
    {
        return "{$this->fromStatus}-{$this->toStatus}-{$this->occurredAt->getTimestamp()}";
    }

    public function payload(): array
    {
        return [
            'from_status' => $this->fromStatus,
            'to_status'   => $this->toStatus,
            'sale_total'  => $this->saleTotal,
        ];
    }
}
