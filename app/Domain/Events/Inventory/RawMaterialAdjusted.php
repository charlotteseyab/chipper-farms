<?php

namespace App\Domain\Events\Inventory;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when raw material stock is adjusted by hand (not a receipt, not a
 * feed batch usage). Typically shrinkage, count corrections, spoilage.
 *
 * Quantity carries sign: positive for an increase, negative for a decrease.
 * Phase 1 finance will post a gain or loss on inventory adjustment.
 */
class RawMaterialAdjusted extends DomainEvent
{
    public const TYPE = 'raw_material.adjusted';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $rawMaterialId,
        public readonly string $rawMaterialName,
        public readonly string $adjustmentType,  // 'add' | 'deduct' | 'set'
        public readonly float $quantityDelta,    // signed: + or -
        public readonly float $newQuantityOnHand,
        public readonly float $unitCost,
        public readonly ?string $reference,
        public readonly ?string $notes,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'raw_material_id'       => $this->rawMaterialId,
            'raw_material_name'     => $this->rawMaterialName,
            'adjustment_type'       => $this->adjustmentType,
            'quantity_delta'        => $this->quantityDelta,
            'new_quantity_on_hand'  => $this->newQuantityOnHand,
            'unit_cost'             => $this->unitCost,
            'reference'             => $this->reference,
            'notes'                 => $this->notes,
        ];
    }
}