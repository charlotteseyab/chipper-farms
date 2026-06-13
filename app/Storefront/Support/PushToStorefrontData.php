<?php

namespace App\Storefront\Support;

use App\Models\Storefront\StorefrontListing;

/**
 * Immutable, validated input for PushToStorefrontService. Built from the
 * push-to-storefront wizard's FormRequest. Keeps the service signature typed
 * (house style: cf. InvoiceLineDraft / JournalLineDraft) rather than passing
 * loose arrays around.
 *
 * Money/qty are carried as numeric strings to preserve precision end-to-end.
 */
final class PushToStorefrontData
{
    /**
     * @param  list<string>|null  $badges
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $saleMode,
        public readonly string $pricingBasis,
        public readonly string $title,
        public readonly ?int $categoryId = null,
        public readonly ?string $publicDescription = null,
        public readonly ?string $sellerNote = null,
        public readonly ?array $badges = null,
        public readonly ?string $pricePerKg = null,   // numeric string, basis live|carcass
        public readonly ?string $fixedPrice = null,   // numeric string, basis fixed
        public readonly string $currencyCode = 'GHS',
        public readonly string $quantityAvailable = '1',
        public readonly string $quantityUnit = 'unit',
        public readonly ?string $estimatedWeightKg = null,
        public readonly ?string $sku = null,
        public readonly ?string $packSize = null,
        public readonly bool $pickupAllowed = true,
        public readonly bool $deliveryAllowed = false,
        public readonly ?string $deliveryFee = null,
        public readonly ?string $fulfilmentNotes = null,
        public readonly ?string $availableFrom = null,
        public readonly ?string $availableUntil = null,
        public readonly bool $publishNow = false,
    ) {
    }

    public function isFixedPricing(): bool
    {
        return $this->pricingBasis === StorefrontListing::BASIS_FIXED;
    }

    public function isQuoteMode(): bool
    {
        return $this->saleMode === StorefrontListing::MODE_QUOTE;
    }

    /**
     * @param  array<string,mixed>  $data  Validated request data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            kind:              (string) $data['kind'],
            saleMode:          (string) $data['sale_mode'],
            pricingBasis:      (string) $data['pricing_basis'],
            title:             (string) $data['title'],
            categoryId:        isset($data['category_id']) ? (int) $data['category_id'] : null,
            publicDescription: $data['public_description'] ?? null,
            sellerNote:        $data['seller_note'] ?? null,
            badges:            $data['badges'] ?? null,
            pricePerKg:        isset($data['price_per_kg']) && $data['price_per_kg'] !== null ? (string) $data['price_per_kg'] : null,
            fixedPrice:        isset($data['fixed_price']) && $data['fixed_price'] !== null ? (string) $data['fixed_price'] : null,
            currencyCode:      (string) ($data['currency_code'] ?? 'GHS'),
            quantityAvailable: (string) ($data['quantity_available'] ?? '1'),
            quantityUnit:      (string) ($data['quantity_unit'] ?? 'unit'),
            estimatedWeightKg: isset($data['estimated_weight_kg']) && $data['estimated_weight_kg'] !== null ? (string) $data['estimated_weight_kg'] : null,
            sku:               $data['sku'] ?? null,
            packSize:          $data['pack_size'] ?? null,
            pickupAllowed:     (bool) ($data['pickup_allowed'] ?? true),
            deliveryAllowed:   (bool) ($data['delivery_allowed'] ?? false),
            deliveryFee:       isset($data['delivery_fee']) && $data['delivery_fee'] !== null ? (string) $data['delivery_fee'] : null,
            fulfilmentNotes:   $data['fulfilment_notes'] ?? null,
            availableFrom:     $data['available_from'] ?? null,
            availableUntil:    $data['available_until'] ?? null,
            publishNow:        (bool) ($data['publish_now'] ?? false),
        );
    }
}
