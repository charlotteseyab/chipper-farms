<?php

namespace App\Storefront\Support;

use App\Models\Storefront\StorefrontListing;
use App\Storefront\Services\ListingAvailabilityService;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the read-only display payload for a public listing detail page,
 * resolving the operational source defensively (it may be trashed/missing for
 * a sold pig). Nothing here mutates operational data.
 *
 * For pig-backed listings it surfaces traceability + husbandry attributes
 * (tag, breed, sex, age, weight, location, health summary). For product
 * listings (feed/manure/service) it surfaces SKU/pack-size.
 */
class ListingDetailPresenter
{
    public function __construct(protected ListingAvailabilityService $availability)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function present(StorefrontListing $listing): array
    {
        $available = $this->availability->isSourceAvailable($listing);

        $base = [
            'available'        => $available,
            'orderable'        => $available && $listing->isOrderable(),
            'quote_only'       => $listing->isQuoteOnly(),
            'price'            => $listing->displayPrice(),
            'price_basis'      => $listing->pricing_basis,
            'currency'         => $listing->currency_code,
            'fulfilment'       => [
                'pickup'       => (bool) $listing->pickup_allowed,
                'delivery'     => (bool) $listing->delivery_allowed,
                'delivery_fee' => $listing->delivery_fee,
                'notes'        => $listing->fulfilment_notes,
            ],
            'quantity'         => $listing->quantity_available,
            'quantity_unit'    => $listing->displayQuantityUnit((string) $listing->quantity_available),
            'attributes'       => [],
        ];

        if ($listing->isPigBacked()) {
            $base['attributes'] = $this->pigAttributes($listing);
        } else {
            $base['attributes'] = $this->productAttributes($listing);
        }

        return $base;
    }

    /**
     * @return array<string,mixed>
     */
    protected function pigAttributes(StorefrontListing $listing): array
    {
        if ($listing->usesPigPool()) {
            return [
                'pool_size'       => $listing->listingPigs()->count(),
                'available_stock' => $listing->availableListingPigs()->count(),
                'sold_stock'      => $listing->soldListingPigs()->count(),
            ];
        }

        $pig = $this->availability->resolveSource($listing);

        if (! $pig instanceof Model) {
            return [
                'traceability_id' => null,
                'note'            => 'Source record no longer available.',
            ];
        }

        $attrs = [
            'traceability_id' => $pig->ear_tag_number ?? null,
            'breed'           => $this->cleanBreed($pig->breed ?? null),
            'sex'             => $pig->gender ?? null,
            'class'           => $pig->stock_type ?? null,
            'date_of_birth'   => optional($pig->date_of_birth ?? null)?->format('Y-m-d'),
            'weight_kg'       => $listing->estimated_weight_kg ?? ($pig->current_weight_kg_cache ?? null),
            'weighed_at'      => optional($pig->weight_cache_recorded_at ?? null)?->format('Y-m-d'),
        ];

        // Age band (defensive accessor access).
        try {
            $days = $pig->current_age_in_days;
            if (is_numeric($days)) {
                $attrs['age_days'] = (int) $days;
            }
        } catch (\Throwable) {
            // accessor not resolvable — skip.
        }

        // Location summary.
        try {
            $attrs['location'] = $pig->room?->name ?? $pig->group?->name ?? null;
        } catch (\Throwable) {
            $attrs['location'] = null;
        }

        // Health summary: count of health records (cheap, defensive).
        try {
            $attrs['health_records'] = $pig->healthRecords()->count();
        } catch (\Throwable) {
            $attrs['health_records'] = null;
        }

        return $attrs;
    }

    /**
     * @return array<string,mixed>
     */
    protected function productAttributes(StorefrontListing $listing): array
    {
        return [
            'sku'        => $listing->sku,
            'pack_size'  => $listing->pack_size,
        ];
    }

    protected function cleanBreed(?string $breed): ?string
    {
        if ($breed === null || $breed === '' || strtolower($breed) === 'unknown') {
            return null;
        }

        return \App\Models\Setting::breedName($breed);
    }
}
