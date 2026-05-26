<?php

namespace App\Domain\Events\Procurement;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a purchase request is approved. Phase 1 finance will use this
 * to open an AP commitment (not yet a posted bill — that comes when the
 * goods are received, which emits a separate event).
 *
 * This is a commitment signal, not a posting trigger. Whether and when it
 * translates to a journal depends on the chosen AP workflow.
 */
class PurchaseRequestApproved extends DomainEvent
{
    public const TYPE = 'purchase_request.approved';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly string $requestNumber,
        public readonly float $totalAmount,
        public readonly int $approvedByUserId,
        public readonly ?string $purpose,
        /** @var array<int, array<string, mixed>> */
        public readonly array $items,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'request_number'      => $this->requestNumber,
            'total_amount'        => $this->totalAmount,
            'approved_by_user_id' => $this->approvedByUserId,
            'purpose'             => $this->purpose,
            'items'               => $this->items,
        ];
    }
}