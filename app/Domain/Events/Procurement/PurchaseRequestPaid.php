<?php

namespace App\Domain\Events\Procurement;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when an approved purchase request is marked paid. In Phase 1 this
 * is the trigger for the cash-out posting (dr: AP, cr: cash/bank). Today
 * there is no proper AP bill underneath — the app currently treats the
 * purchase request as the end-to-end procurement record. That is a
 * simplification Phase 1 will refine (bill + payment, not a single toggle).
 */
class PurchaseRequestPaid extends DomainEvent
{
    public const TYPE = 'purchase_request.paid';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly string $requestNumber,
        public readonly float $totalAmount,
        public readonly int $markedPaidByUserId,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'request_number'          => $this->requestNumber,
            'total_amount'            => $this->totalAmount,
            'marked_paid_by_user_id'  => $this->markedPaidByUserId,
        ];
    }
}