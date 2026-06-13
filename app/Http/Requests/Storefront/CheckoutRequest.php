<?php

namespace App\Http\Requests\Storefront;

use App\Models\Storefront\StorefrontOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payment_method'    => ['required', Rule::in([StorefrontOrder::METHOD_COD, StorefrontOrder::METHOD_PAYSTACK])],
            'fulfilment_method' => ['required', Rule::in([StorefrontOrder::FULFIL_PICKUP, StorefrontOrder::FULFIL_DELIVERY])],

            'shipping_address_id' => ['nullable', 'integer', 'exists:counterparty_addresses,id'],
            'ship_to_name'        => ['nullable', 'string', 'max:200'],
            'ship_to_phone'       => ['nullable', 'string', 'max:60'],
            'ship_address_line_1' => ['nullable', 'string', 'max:200', 'required_if:fulfilment_method,delivery'],
            'ship_address_line_2' => ['nullable', 'string', 'max:200'],
            'ship_city'           => ['nullable', 'string', 'max:120', 'required_if:fulfilment_method,delivery'],
            'ship_region'         => ['nullable', 'string', 'max:120'],
            'ship_ghana_post_gps' => ['nullable', 'string', 'max:40'],

            'customer_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'ship_address_line_1.required_if' => 'A delivery address is required for delivery orders.',
            'ship_city.required_if'           => 'A delivery city is required for delivery orders.',
        ];
    }
}
