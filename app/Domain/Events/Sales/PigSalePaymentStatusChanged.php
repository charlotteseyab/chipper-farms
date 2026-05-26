<?php

namespace App\Domain\Events\Sales;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when the payment status on a sale changes. Phase 1 finance will
 * translate this into AR receipt postings (pending -> paid) or reversals
 * (paid -> pending, rare).
 *
 * Uses an idempotency suffix derived from from/to status so repeated toggles
 * of the same status don't collapse into one outbox row.
 */
class PigSalePaymentStatusChanged extends DomainEvent
{
    public const TYPE = 'pig_sale.payment_status_changed';

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
        // Allow multiple transitions across the lifecycle of one sale.
        // Suffix = from-to-timestamp so each transition has its own key.
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