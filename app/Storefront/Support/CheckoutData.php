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
        // Phase 8: which delivery zone the buyer picked. Only meaningful on a
        // delivery order, and only when the organisation has configured zones.
        public readonly ?int $deliveryZoneId = null,

        // Phase 9: who the buyer is when they have no account. Ignored for a
        // signed-in buyer, whose identity comes from their profile — a form
        // field must not be able to re-address someone else's order.
        public readonly ?string $guestName = null,
        public readonly ?string $guestEmail = null,
        public readonly ?string $guestPhone = null,
    ) {
    }

    /**
     * A guest must leave at least one way to be reached. An order nobody can be
     * told about is one the farm cannot confirm and the buyer cannot chase.
     */
    public function hasGuestContact(): bool
    {
        return ($this->guestEmail !== null && trim($this->guestEmail) !== '')
            || ($this->guestPhone !== null && trim($this->guestPhone) !== '');
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
            deliveryZoneId:    isset($data['delivery_zone_id']) && $data['delivery_zone_id'] !== ''
                ? (int) $data['delivery_zone_id']
                : null,
            guestName:  $data['guest_name'] ?? null,
            guestEmail: $data['guest_email'] ?? null,
            guestPhone: $data['guest_phone'] ?? null,
        );
    }
}
