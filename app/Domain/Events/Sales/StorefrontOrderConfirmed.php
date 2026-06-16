<?php

namespace App\Domain\Events\Sales;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a storefront order is CONFIRMED by staff (the binding-acceptance
 * point). This is the storefront's finance trigger — never order creation.
 *
 * The StorefrontOrderConfirmedSubscriber consumes it and routes each order line
 * into the existing sale-recording spine (RecordFeedSaleService with
 * channel='storefront'; RecordPigSaleService with an explicit agreed price),
 * which in turn dispatches FeedSaleRecorded / PigSaleRecorded so the existing
 * revenue/AR + COGS/inventory subscribers post the GL. The storefront never
 * posts to the ledger directly — zero drift, full reuse.
 *
 * `paymentStatus` is the settlement state to record on the underlying sale:
 *   - 'paid'    → Paystack already verified before confirmation (cash sale)
 *   - 'pending' → COD; AR is recognised, cash recorded at collection (Phase 5)
 */
class StorefrontOrderConfirmed extends DomainEvent
{
    public const TYPE = 'storefront_order.confirmed';

    /**
     * @param  list<array<string,mixed>>  $lines  Snapshot of order line data.
     */
    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $orderId,
        public readonly string $orderReference,
        public readonly ?int $counterpartyId,
        public readonly string $paymentStatus,    // 'paid' | 'pending'
        public readonly ?string $paymentMethod,
        public readonly string $fulfilmentMethod,
        public readonly float $total,
        public readonly ?string $buyerName,
        public readonly array $lines,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'order_id'          => $this->orderId,
            'order_reference'   => $this->orderReference,
            'counterparty_id'   => $this->counterpartyId,
            'payment_status'    => $this->paymentStatus,
            'payment_method'    => $this->paymentMethod,
            'fulfilment_method' => $this->fulfilmentMethod,
            'total'             => $this->total,
            'buyer_name'        => $this->buyerName,
            'lines'             => $this->lines,
        ];
    }
}
