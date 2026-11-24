<?php

namespace App\Domain\Events\Lifecycle;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Animals bought through a purchased-stock intake that died or were put down
 * in quarantine. They were paid for but never entered stock, so their share
 * of the purchase cost is a loss.
 */
class PurchaseQuarantineLoss extends DomainEvent
{
    public const TYPE = 'purchase_stock.quarantine_loss';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly string $batchNumber,
        public readonly int $quantity,
        public readonly float $amount,
        public readonly string $disposition,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'batch_number' => $this->batchNumber,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'disposition' => $this->disposition,
        ];
    }
}
