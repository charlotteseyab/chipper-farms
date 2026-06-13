<?php

namespace App\Http\Requests\Storefront;

use App\Models\Storefront\StorefrontQuote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a public quote request. Open to guests and authenticated
 * customers, so authorize() returns true; spam/abuse hardening (throttle,
 * captcha) is a Phase 6 concern noted in the README.
 */
class QuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_name'  => ['required', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:200', 'required_without:contact_phone'],
            'contact_phone' => ['nullable', 'string', 'max:60', 'required_without:contact_email'],

            'source'                => ['nullable', Rule::in([StorefrontQuote::SOURCE_LISTING, StorefrontQuote::SOURCE_BULK])],
            'fulfilment_preference' => ['nullable', Rule::in(['pickup', 'delivery'])],
            'delivery_city'         => ['nullable', 'string', 'max:120'],
            'delivery_region'       => ['nullable', 'string', 'max:120'],
            'message'               => ['nullable', 'string', 'max:5000'],

            'items'                  => ['required', 'array', 'min:1', 'max:50'],
            'items.*.listing_id'     => ['nullable', 'integer', 'exists:storefront_listings,id'],
            'items.*.kind'           => ['nullable', 'string', 'max:30'],
            'items.*.description'    => ['required', 'string', 'max:300'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.0001', 'max:99999999'],
            'items.*.quantity_unit'  => ['nullable', 'string', 'max:20'],
            'items.*.line_note'      => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_email.required_without' => 'Provide an email or a phone number so we can reach you.',
            'contact_phone.required_without' => 'Provide a phone number or an email so we can reach you.',
            'items.required' => 'Add at least one item to request a quote.',
        ];
    }

    /**
     * Drop blank item rows (a description-less row is treated as "not filled
     * in") so optional extra lines on the bulk form don't trip validation.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $filtered = array_values(array_filter($items, static function ($item) {
                return is_array($item)
                    && isset($item['description'])
                    && trim((string) $item['description']) !== '';
            }));

            $this->merge(['items' => $filtered]);
        }
    }
}
