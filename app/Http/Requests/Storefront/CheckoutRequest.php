<?php

namespace App\Http\Requests\Storefront;

use App\Storefront\Support\StorefrontCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout input.
 *
 * PHASE 8 — THE CHOICES ARE THE ORGANISATION'S, NOT THE ENUM'S
 * -------------------------------------------------------------
 * `payment_method` and `fulfilment_method` used to accept every value the
 * `StorefrontOrder` constants allow. That meant a farm which had switched
 * Paystack off still accepted `payment_method=paystack` from a hand-posted
 * form, and one that offered pickup only still accepted a delivery order —
 * the settings screen said one thing and the endpoint did another.
 *
 * They are now validated against what this organisation actually offers.
 * `CheckoutService` repeats both checks: a form request only guards the HTTP
 * path, and the service is reachable from tests, console tooling and any future
 * caller.
 */
class CheckoutRequest extends FormRequest
{
    /**
     * Open to guests since Phase 9. Whether this farm actually accepts guest
     * orders is decided by `storefront.guest_checkout` inside
     * `CheckoutService::resolveCounterpartyId()`, where refusing produces a
     * sentence the buyer can act on rather than a 403 on a full basket.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        // A guest's organisation is the one the public shop is serving.
        $organizationId = $user?->organization_id
            ?? app(\App\Storefront\Support\StorefrontSettingsResolver::class)->organizationId();

        $payments   = StorefrontCatalog::enabledPaymentMethods($organizationId);
        $fulfilment = StorefrontCatalog::enabledFulfilmentMethods($organizationId);

        return [
            'payment_method'    => ['required', Rule::in($payments)],
            'fulfilment_method' => ['required', Rule::in($fulfilment)],

            'shipping_address_id' => ['nullable', 'integer', 'exists:counterparty_addresses,id'],
            'ship_to_name'        => ['nullable', 'string', 'max:200'],
            'ship_to_phone'       => ['nullable', 'string', 'max:60'],
            'ship_address_line_1' => ['nullable', 'string', 'max:200', 'required_if:fulfilment_method,delivery'],
            'ship_address_line_2' => ['nullable', 'string', 'max:200'],
            'ship_city'           => ['nullable', 'string', 'max:120', 'required_if:fulfilment_method,delivery'],
            'ship_region'         => ['nullable', 'string', 'max:120'],
            'ship_ghana_post_gps' => ['nullable', 'string', 'max:40'],

            // Scoped to the buyer's own organisation rather than a bare
            // `exists:` — otherwise a posted id from another farm's zone list
            // would price this farm's delivery.
            'delivery_zone_id' => [
                'nullable',
                'integer',
                Rule::exists('storefront_delivery_zones', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true),
            ],

            'customer_note' => ['nullable', 'string', 'max:2000'],

            // Phase 9 — who the guest is. Required only when there is no
            // signed-in buyer; for a signed-in buyer these are ignored by
            // CheckoutService entirely, so a posted guest_email cannot
            // re-address someone else's order.
            'guest_name'  => ['nullable', 'string', 'max:200', Rule::requiredIf($user === null)],
            'guest_email' => ['nullable', 'email', 'max:190', Rule::requiredIf(
                $user === null && ! $this->filled('guest_phone')
            )],
            'guest_phone' => ['nullable', 'string', 'max:60', Rule::requiredIf(
                $user === null && ! $this->filled('guest_email')
            )],
        ];
    }

    public function messages(): array
    {
        return [
            'ship_address_line_1.required_if' => 'A delivery address is required for delivery orders.',
            'ship_city.required_if'           => 'A delivery city is required for delivery orders.',
            'payment_method.in'               => 'That payment method is not available from this farm.',
            'fulfilment_method.in'            => 'That delivery option is not available from this farm.',
            'delivery_zone_id.exists'         => 'Choose a delivery area from the list.',
            'guest_name.required'             => 'Please give your name so we know who the order is for.',
            'guest_email.required'            => 'Give a phone number or an email address so we can confirm your order.',
            'guest_phone.required'            => 'Give a phone number or an email address so we can confirm your order.',
        ];
    }
}
