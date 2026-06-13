<?php

namespace App\Storefront\Support;

use App\Models\Storefront\StorefrontQuote;

/**
 * Validated input for QuoteService::request(). Built from the public quote
 * form's FormRequest.
 */
final class QuoteRequestData
{
    /**
     * @param  list<array{listing_id?:int|null,kind?:string|null,description:string,quantity:string,quantity_unit?:string,line_note?:string|null}>  $items
     */
    public function __construct(
        public readonly string $contactName,
        public readonly array $items,
        public readonly ?string $contactEmail = null,
        public readonly ?string $contactPhone = null,
        public readonly string $source = StorefrontQuote::SOURCE_LISTING,
        public readonly string $fulfilmentPreference = 'pickup',
        public readonly ?string $deliveryCity = null,
        public readonly ?string $deliveryRegion = null,
        public readonly ?string $message = null,
        public readonly string $currencyCode = 'GHS',
    ) {
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $items = array_map(static function (array $item): array {
            return [
                'listing_id'    => isset($item['listing_id']) ? (int) $item['listing_id'] : null,
                'kind'          => $item['kind'] ?? null,
                'description'   => (string) $item['description'],
                'quantity'      => (string) ($item['quantity'] ?? '1'),
                'quantity_unit' => (string) ($item['quantity_unit'] ?? 'unit'),
                'line_note'     => $item['line_note'] ?? null,
            ];
        }, $data['items'] ?? []);

        return new self(
            contactName:           (string) $data['contact_name'],
            items:                 $items,
            contactEmail:          $data['contact_email'] ?? null,
            contactPhone:          $data['contact_phone'] ?? null,
            source:                (string) ($data['source'] ?? StorefrontQuote::SOURCE_LISTING),
            fulfilmentPreference:  (string) ($data['fulfilment_preference'] ?? 'pickup'),
            deliveryCity:          $data['delivery_city'] ?? null,
            deliveryRegion:        $data['delivery_region'] ?? null,
            message:               $data['message'] ?? null,
            currencyCode:          (string) ($data['currency_code'] ?? 'GHS'),
        );
    }
}
