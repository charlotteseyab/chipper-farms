<?php

namespace App\Http\Requests\Storefront;

use App\Models\Storefront\StorefrontListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates back-office edits to a listing's content/commerce fields.
 * Authorisation is delegated to the StorefrontListingPolicy via the route.
 */
class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $this->user() !== null
            && $listing instanceof StorefrontListing
            && $this->user()->can('update', $listing);
    }

    public function rules(): array
    {
        return [
            'title'              => ['required', 'string', 'max:200'],
            'category_id'        => ['nullable', 'integer', 'exists:storefront_categories,id'],
            'public_description' => ['nullable', 'string', 'max:5000'],
            'seller_note'        => ['nullable', 'string', 'max:2000'],
            'badges'             => ['nullable', 'array'],
            'badges.*'           => ['string', 'max:40'],

            'sale_mode'     => ['required', Rule::in(StorefrontListing::MODES)],
            'pricing_basis' => ['required', Rule::in(StorefrontListing::BASES)],
            'price_per_kg'  => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fixed_price'   => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency_code' => ['nullable', 'string', 'size:3'],

            'quantity_available'  => ['required', 'numeric', 'min:0', 'max:99999999'],
            'quantity_unit'       => ['nullable', 'string', 'max:20'],
            'estimated_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'sku'                 => ['nullable', 'string', 'max:60'],
            'pack_size'           => ['nullable', 'string', 'max:60'],

            'pickup_allowed'   => ['boolean'],
            'delivery_allowed' => ['boolean'],
            'delivery_fee'     => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fulfilment_notes' => ['nullable', 'string', 'max:2000'],

            'available_from'  => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pickup_allowed'   => $this->boolean('pickup_allowed'),
            'delivery_allowed' => $this->boolean('delivery_allowed'),
        ]);
    }
}
