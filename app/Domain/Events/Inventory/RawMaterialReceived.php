<?php

namespace App\Domain\Events\Inventory;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when raw material stock is received (initial opening stock or
 * subsequent receipts). Phase 1 finance will translate into an inventory
 * debit (and, if the receipt is tied to an approved purchase request,
 * into an AP credit; otherwise a cash/clearing credit).
 *
 * The idempotency suffix is the stock log id, not the raw material id,
 * because a single raw material may have many distinct receipts.
 */
class RawMaterialReceived extends DomainEvent
{
    public const TYPE = 'raw_material.received';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $rawMaterialId,
        public readonly string $rawMaterialName,
        public readonly float $quantity,
        public readonly string $unit,
        public readonly float $unitCost,
        public readonly float $totalCost,
        public readonly ?string $reference,    // e.g. GRN number, "Opening Stock"
        public readonly ?int $purchaseRequestId,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'raw_material_id'     => $this->rawMaterialId,
            'raw_material_name'   => $this->rawMaterialName,
            'quantity'            => $this->quantity,
            'unit'                => $this->unit,
            'unit_cost'           => $this->unitCost,
            'total_cost'          => $this->totalCost,
            'reference'           => $this->reference,
            'purchase_request_id' => $this->purchaseRequestId,
        ];
    }
}