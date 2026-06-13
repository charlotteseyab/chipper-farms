<?php

namespace App\Storefront\Support;

use App\Models\Storefront\StorefrontOrder;

/**
 * Validated checkout input. Carries the buyer's chosen fulfilment, payment
 * method and (for delivery) the shipping address snapshot.
 */
final class CheckoutData
{
    public function __construct(
        public readonly string $paymentMethod,      // cod|paystack
        public readonly string $fulfilmentMethod,   // pickup|delivery
        public readonly ?int $shippingAddressId = null,
        public readonly ?string $shipToName = null,
        public readonly ?string $shipToPhone = null,
        public readonly ?string $shipAddressLine1 = null,
        public readonly ?string $shipAddressLine2 = null,
        public readonly ?string $shipCity = null,
        public readonly ?string $shipRegion = null,
        public readonly ?string $shipGhanaPostGps = null,
        public readonly ?string $customerNote = null,
    ) {
    }

    public function isDelivery(): bool
    {
        return $this->fulfilmentMethod === StorefrontOrder::FULFIL_DELIVERY;
    }

    public function isPaystack(): bool
    {
        return $this->paymentMethod === StorefrontOrder::METHOD_PAYSTACK;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            paymentMethod:     (string) $data['payment_method'],
            fulfilmentMethod:  (string) $data['fulfilment_method'],
            shippingAddressId: isset($data['shipping_address_id']) ? (int) $data['shipping_address_id'] : null,
            shipToName:        $data['ship_to_name'] ?? null,
            shipToPhone:       $data['ship_to_phone'] ?? null,
            shipAddressLine1:  $data['ship_address_line_1'] ?? null,
            shipAddressLine2:  $data['ship_address_line_2'] ?? null,
            shipCity:          $data['ship_city'] ?? null,
            shipRegion:        $data['ship_region'] ?? null,
            shipGhanaPostGps:  $data['ship_ghana_post_gps'] ?? null,
            customerNote:      $data['customer_note'] ?? null,
        );
    }
}
