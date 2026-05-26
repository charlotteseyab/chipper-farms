<?php

namespace App\Domain\Events\Sales;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a pig sale is recorded. The primary trigger for
 * revenue recognition + AR + COGS in Phase 1 finance.
 */
class PigSaleRecorded extends DomainEvent
{
    public const TYPE = 'pig_sale.recorded';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $pigId,
        public readonly string $pigCode,
        public readonly float $saleWeight,
        public readonly string $weightType,     // 'live' | 'carcass'
        public readonly float $pricePerKg,
        public readonly float $saleTotal,
        public readonly string $salePurpose,
        public readonly string $paymentStatus,  // 'pending' | 'paid' | 'partial'
        public readonly ?string $paymentMethod,
        public readonly ?string $buyerName,
        public readonly ?float $transportCost,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'pig_id'         => $this->pigId,
            'pig_code'       => $this->pigCode,
            'sale_weight'    => $this->saleWeight,
            'weight_type'    => $this->weightType,
            'price_per_kg'   => $this->pricePerKg,
            'sale_total'     => $this->saleTotal,
            'sale_purpose'   => $this->salePurpose,
            'payment_status' => $this->paymentStatus,
            'payment_method' => $this->paymentMethod,
            'buyer_name'     => $this->buyerName,
            'transport_cost' => $this->transportCost,
        ];
    }
}