<?php

namespace App\Domain\Events\Sales;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a feed sale is recorded. Triggers revenue recognition + AR (or
 * cash) and COGS + finished-feed inventory decrement, via two subscribers
 * that mirror the pig-sale spine.
 */
class FeedSaleRecorded extends DomainEvent
{
    public const TYPE = 'feed_sale.recorded';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $feedSaleId,
        public readonly int $feedTypeId,
        public readonly float $quantityKg,
        public readonly float $unitPrice,
        public readonly float $saleTotal,
        public readonly string $channel,        // 'direct' | 'storefront'
        public readonly string $paymentStatus,  // 'pending' | 'paid' | 'partial'
        public readonly ?string $paymentMethod,
        public readonly ?int $counterpartyId,
        public readonly ?string $buyerName,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'feed_sale_id'    => $this->feedSaleId,
            'feed_type_id'    => $this->feedTypeId,
            'quantity_kg'     => $this->quantityKg,
            'unit_price'      => $this->unitPrice,
            'sale_total'      => $this->saleTotal,
            'channel'         => $this->channel,
            'payment_status'  => $this->paymentStatus,
            'payment_method'  => $this->paymentMethod,
            'counterparty_id' => $this->counterpartyId,
            'buyer_name'      => $this->buyerName,
        ];
    }
}
