<?php

namespace App\Http\Requests\Storefront;

use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the Push-to-Storefront wizard payload (also reused for manual
 * product listings). Authorisation is the push_to_storefront permission;
 * route middleware enforces it too, but we double-check here so the request
 * is safe if ever dispatched outside the gated route.
 */
class PushToStorefrontRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission('push_to_storefront');
    }

    public function rules(): array
    {
        return [
            'kind'          => ['required', Rule::in(StorefrontListing::KINDS)],
            'sale_mode'     => ['required', Rule::in(StorefrontListing::MODES)],
            'pricing_basis' => ['required', Rule::in(StorefrontListing::BASES)],

            'category_id'   => ['nullable', 'integer', 'exists:storefront_categories,id'],
            'title'         => ['required', 'string', 'max:200'],
            'public_description' => ['nullable', 'string', 'max:5000'],
            'seller_note'   => ['nullable', 'string', 'max:2000'],
            'badges'        => ['nullable', 'array'],
            'badges.*'      => ['string', 'max:40'],

            // Pricing — conditional rules handled in withValidator().
            'price_per_kg'  => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fixed_price'   => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency_code' => ['nullable', 'string', 'size:3'],

            // Standard Stock pooled into a pig-backed listing. Existence is
            // checked here; whether each animal may actually be SOLD is
            // PigListingEligibilityService's decision, made in the pool service
            // where it applies to every path rather than just this form.
            'pool_pig_ids'   => ['nullable', 'array'],
            'pool_pig_ids.*' => ['integer', 'exists:pigs_management,id'],

            'quantity_available' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'quantity_unit'      => ['nullable', 'string', 'max:20'],
            'estimated_weight_kg'=> ['nullable', 'numeric', 'min:0', 'max:99999999'],

            'sku'        => ['nullable', 'string', 'max:60'],
            'pack_size'  => ['nullable', 'string', 'max:60'],

            'pickup_allowed'   => ['boolean'],
            'delivery_allowed' => ['boolean'],
            'delivery_fee'     => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fulfilment_notes' => ['nullable', 'string', 'max:2000'],

            'available_from'  => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],

            'publish_now' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $mode  = $this->input('sale_mode');
            $basis = $this->input('pricing_basis');

            // Quote-only listings need no price. Fixed-price commerce listings
            // must carry a price appropriate to the chosen basis.
            if ($mode === StorefrontListing::MODE_FIXED) {
                if ($basis === StorefrontListing::BASIS_FIXED) {
                    if ($this->input('fixed_price') === null || $this->input('fixed_price') === '') {
                        $v->errors()->add('fixed_price', 'A fixed price is required for a fixed-price listing.');
                    }
                } else {
                    // live/carcass: per-kg price required (the wizard may default
                    // it from settings, but if a basis is chosen with no default
                    // and no value, fail loudly rather than publish a £0 listing).
                    if ($this->input('price_per_kg') === null || $this->input('price_per_kg') === '') {
                        $v->errors()->add('price_per_kg', 'A price per kg is required for a live/carcass priced listing (or leave the basis as fixed).');
                    }
                }
            }

            // Delivery fee only meaningful when delivery is allowed.
            if (! $this->boolean('delivery_allowed') && $this->input('delivery_fee') !== null && $this->input('delivery_fee') !== '') {
                $v->errors()->add('delivery_fee', 'Delivery fee can only be set when delivery is allowed.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Normalise checkbox booleans which arrive as "on"/absent from forms.
        $this->merge([
            'pickup_allowed'   => $this->boolean('pickup_allowed'),
            'delivery_allowed' => $this->boolean('delivery_allowed'),
            'publish_now'      => $this->boolean('publish_now'),
        ]);
    }

    /**
     * Kinds permitted for a given source category (used by the controller to
     * cross-check the chosen category matches the kind).
     */
    public function categoryKindMatches(): bool
    {
        $categoryId = $this->input('category_id');
        if ($categoryId === null) {
            return true;
        }

        $category = StorefrontCategory::find($categoryId);

        return $category === null || $category->kind === $this->input('kind');
    }
}
