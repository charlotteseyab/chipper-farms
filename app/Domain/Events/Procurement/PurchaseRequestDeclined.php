<?php

namespace App\Domain\Events\Procurement;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a purchase request is declined. Does not post any journal
 * on its own — recorded for audit + procurement reporting.
 */
class PurchaseRequestDeclined extends DomainEvent
{
    public const TYPE = 'purchase_request.declined';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly string $requestNumber,
        public readonly float $totalAmount,
        public readonly int $declinedByUserId,
        public readonly ?string $declineReason,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'request_number'      => $this->requestNumber,
            'total_amount'        => $this->totalAmount,
            'declined_by_user_id' => $this->declinedByUserId,
            'decline_reason'      => $this->declineReason,
        ];
    }
}