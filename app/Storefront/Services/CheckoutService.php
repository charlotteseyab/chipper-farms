<?php

namespace App\Storefront\Services;

use App\Models\CounterpartyAddress;
use App\Models\Storefront\StorefrontCart;
use App\Models\Storefront\StorefrontDeliveryZone;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\User;
use App\Storefront\Services\Exceptions\CheckoutException;
use App\Storefront\Support\CartOwner;
use App\Storefront\Support\CheckoutData;
use App\Storefront\Support\StorefrontCatalog;
use App\Storefront\Support\StorefrontSettings;
use Illuminate\Support\Facades\DB;

/**
 * Turns an active cart into an order. Re-validates availability and re-prices
 * from the listing at checkout (the authoritative moment), snapshots line
 * items, computes totals with bcmath, sets the initial status from the chosen
 * payment method, marks the cart converted, and writes an order event.
 *
 * It posts NOTHING to finance — that is Phase 4, driven by domain events when
 * an order reaches a finance-relevant status.
 *
 * Initial status:
 *   - COD      → pending_confirmation (staff must confirm; cash collected later)
 *   - Paystack → pending_payment      (awaiting the gateway; verified payment
 *                                       still does NOT auto-accept)
 */
class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected CustomerAccountService $accounts,
        protected ListingAvailabilityService $availability,
        protected OrderService $orders,
    ) {
    }

    public function checkout(CartOwner|User $owner, CheckoutData $data): StorefrontOrder
    {
        $owner = CartOwner::from($owner);

        $cart = $this->cartService->activeFor($owner);
        $cart->load('items.listing');

        if ($cart->items->isEmpty()) {
            throw new CheckoutException('Your cart is empty.');
        }

        // Phase 9 — a guest order still needs an AR counterparty; "no account"
        // means no User, never no counterparty. A signed-in buyer's identity
        // comes from their profile and the guest fields are ignored, so a form
        // field cannot re-address someone else's order.
        $organizationId = $owner->isGuest()
            ? $owner->organizationId
            : $owner->user->organization_id;

        // Phase 8 — what this farm actually offers. Repeated from
        // CheckoutRequest deliberately: the request class guards the HTTP path
        // only, and this service is reachable from tests, console tooling and
        // any later caller. An order taken by a method the farm cannot settle
        // is not a validation slip, it is money it has no process to collect.
        $this->assertOfferedTerms($organizationId, $data);

        $counterpartyId = $this->resolveCounterpartyId($owner, $data, $organizationId);

        return DB::transaction(function () use ($owner, $data, $cart, $counterpartyId, $organizationId) {
            $user = $owner->user;

            // Resolve + snapshot the shipping address for delivery orders.
            $ship = $this->resolveShipping($data, $counterpartyId);

            // Zone-priced delivery, when the organisation has zones configured.
            $zone = $this->resolveDeliveryZone($data, $organizationId);

            $deliveryFee = '0.00';
            $subtotal    = '0.00';

            $lineDrafts = [];
            foreach ($cart->items as $item) {
                $listing = $item->listing;
                if ($listing === null) {
                    throw new CheckoutException('A item in your cart is no longer available.');
                }

                // Re-validate at the authoritative moment.
                if (! $listing->isOrderable() || ! $this->availability->isSourceAvailable($listing)) {
                    throw new CheckoutException("\"{$listing->title}\" is no longer available.");
                }
                if (bccomp((string) $item->quantity, (string) $listing->quantity_available, 4) > 0) {
                    throw new CheckoutException("Not enough stock for \"{$listing->title}\".");
                }

                // Re-price from the listing now (authoritative), not the snapshot.
                $unitPrice = $this->cartService->effectiveUnitPrice($listing);
                $lineTotal = bcmul($unitPrice, (string) $item->quantity, 2);
                $subtotal  = bcadd($subtotal, $lineTotal, 2);

                // Accumulate delivery fee (once per listing that charges one)
                // only when the order is a delivery order AND the organisation
                // prices delivery per listing. When it has zones, the zone fee
                // is the delivery fee and adding these too would charge twice
                // for one journey.
                if ($zone === null && $data->isDelivery() && $listing->delivery_allowed && $listing->delivery_fee !== null) {
                    $deliveryFee = bcadd($deliveryFee, (string) $listing->delivery_fee, 2);
                }

                $lineDrafts[] = [
                    'listing'    => $listing,
                    'quantity'   => (string) $item->quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if ($zone !== null) {
                $deliveryFee = $zone->feeString();
            }

            if (! $data->isDelivery()) {
                $deliveryFee = '0.00';
            }

            $total = bcadd($subtotal, $deliveryFee, 2);

            $isPaystack = $data->isPaystack();

            $order = StorefrontOrder::create([
                'organization_id'   => $organizationId,
                'reference'         => $this->nextReference($organizationId),
                'counterparty_id'   => $counterpartyId,
                'customer_user_id'  => $user?->id,
                'guest_name'        => $owner->isGuest() ? trim((string) $data->guestName) : null,
                'guest_email'       => $owner->isGuest() ? $this->blankToNull($data->guestEmail) : null,
                'guest_phone'       => $owner->isGuest() ? $this->blankToNull($data->guestPhone) : null,
                // Single-use, delivered in the confirmation URL. It is how a
                // guest tracks the order without an account, and how they later
                // prove the order is theirs when claiming it. Cleared on claim
                // so a forwarded link cannot be replayed against a second
                // account.
                'guest_claim_token' => $owner->isGuest() ? bin2hex(random_bytes(32)) : null,
                'status'            => $isPaystack
                    ? StorefrontOrder::STATUS_PENDING_PAYMENT
                    : StorefrontOrder::STATUS_PENDING_CONFIRMATION,
                'payment_status'    => $isPaystack
                    ? StorefrontOrder::PAY_PAYSTACK_PEND
                    : StorefrontOrder::PAY_COD,
                'payment_method'    => $isPaystack ? StorefrontOrder::METHOD_PAYSTACK : StorefrontOrder::METHOD_COD,
                'fulfilment_method' => $data->fulfilmentMethod,
                'shipping_address_id' => $ship['shipping_address_id'] ?? null,
                'ship_to_name'        => $ship['ship_to_name'] ?? null,
                'ship_to_phone'       => $ship['ship_to_phone'] ?? null,
                'ship_address_line_1' => $ship['ship_address_line_1'] ?? null,
                'ship_address_line_2' => $ship['ship_address_line_2'] ?? null,
                'ship_city'           => $ship['ship_city'] ?? null,
                'ship_region'         => $ship['ship_region'] ?? null,
                'ship_ghana_post_gps' => $ship['ship_ghana_post_gps'] ?? null,
                'subtotal'          => $subtotal,
                'delivery_fee'      => $deliveryFee,
                'delivery_zone_id'   => $data->isDelivery() ? $zone?->id : null,
                'delivery_zone_name' => $data->isDelivery() ? $zone?->name : null,
                'total'             => $total,
                'currency_code'     => 'GHS',
                'customer_note'     => $data->customerNote,
                'placed_at'         => now(),
            ]);

            foreach ($lineDrafts as $draft) {
                $listing = $draft['listing'];
                StorefrontOrderItem::create([
                    'organization_id' => $organizationId,
                    'order_id'        => $order->id,
                    'listing_id'      => $listing->id,
                    'source_type'     => $listing->source_type,
                    'source_id'       => $listing->source_id,
                    'kind'            => $listing->kind,
                    'description'     => $listing->title,
                    'quantity'        => $draft['quantity'],
                    'quantity_unit'   => $listing->quantity_unit,
                    'price_basis'     => $listing->pricing_basis,
                    'unit_price'      => $draft['unit_price'],
                    'line_total'      => $draft['line_total'],
                    'weight_kg'       => $listing->estimated_weight_kg,
                ]);
            }

            // Convert the cart.
            $cart->update(['status' => StorefrontCart::STATUS_CONVERTED]);

            $this->orders->recordEvent(
                $order,
                type: 'status_change',
                toStatus: $order->status,
                note: $owner->isGuest() ? 'Order placed by guest.' : 'Order placed by customer.',
                actorId: $user?->id,
            );

            return $order->load('items');
        });
    }

    /**
     * The AR counterparty this order belongs to (Phase 9, blueprint §38).
     *
     * A signed-in buyer's comes from their profile. A guest's is matched or
     * created from what they typed — see
     * `CustomerAccountService::counterpartyForGuest()` for why the match is by
     * email only.
     *
     * The guest fields on `CheckoutData` are read ONLY on the guest branch. A
     * signed-in buyer posting `guest_email` must not be able to re-address
     * their order onto someone else's ledger.
     */
    protected function resolveCounterpartyId(CartOwner $owner, CheckoutData $data, ?int $organizationId): int
    {
        if (! $owner->isGuest()) {
            return (int) $this->accounts->ensureProfile($owner->user)->counterparty_id;
        }

        if (! StorefrontSettings::guestCheckoutEnabled($organizationId)) {
            throw new CheckoutException('This farm asks buyers to create an account before ordering.');
        }

        $name = trim((string) $data->guestName);

        if ($name === '') {
            throw new CheckoutException('Please give your name so we know who the order is for.');
        }

        if (! $data->hasGuestContact()) {
            throw new CheckoutException('Please give a phone number or an email address so we can confirm your order.');
        }

        return (int) $this->accounts->counterpartyForGuest(
            name: $name,
            email: $data->guestEmail,
            phone: $data->guestPhone,
            organizationId: $organizationId,
        )->id;
    }

    protected function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The store must be open, and the payment and fulfilment methods must be
     * ones this organisation offers (Phase 8, blueprint §34).
     */
    protected function assertOfferedTerms(?int $organizationId, CheckoutData $data): void
    {
        if (! StorefrontSettings::enabled($organizationId)) {
            throw new CheckoutException('This shop is not accepting orders at the moment.');
        }

        if (! StorefrontCatalog::isPaymentMethodEnabled($data->paymentMethod, $organizationId)) {
            throw new CheckoutException('That payment method is not available from this farm.');
        }

        if ($data->isDelivery() && ! StorefrontSettings::deliveryEnabled($organizationId)) {
            throw new CheckoutException('This farm does not offer delivery — please choose pickup.');
        }
    }

    /**
     * The delivery zone this order is priced by, or null when the organisation
     * prices delivery per listing instead.
     *
     * Re-read from the database inside checkout rather than trusted from the
     * form: the price the buyer was shown is not the price the farm charges,
     * and a posted `delivery_zone_id` is buyer input like any other. Scoped to
     * the organisation so another farm's zone cannot price this one's delivery,
     * and to active zones so a withdrawn zone stops being chargeable.
     */
    protected function resolveDeliveryZone(CheckoutData $data, ?int $organizationId): ?StorefrontDeliveryZone
    {
        if (! $data->isDelivery()) {
            return null;
        }

        $zones = StorefrontSettings::deliveryZones($organizationId);

        if ($zones->isEmpty()) {
            // No zones configured: per-listing delivery fees apply, exactly as
            // before Phase 8.
            return null;
        }

        if ($data->deliveryZoneId === null) {
            throw new CheckoutException('Choose a delivery area so we can price your delivery.');
        }

        $zone = $zones->firstWhere('id', $data->deliveryZoneId);

        if ($zone === null) {
            throw new CheckoutException('That delivery area is no longer available.');
        }

        return $zone;
    }

    /**
     * @return array<string,mixed>
     */
    protected function resolveShipping(CheckoutData $data, ?int $counterpartyId): array
    {
        if (! $data->isDelivery()) {
            return [];
        }

        // Prefer a saved address (snapshotted onto the order).
        if ($data->shippingAddressId !== null) {
            $addr = CounterpartyAddress::query()
                ->where('id', $data->shippingAddressId)
                ->where('counterparty_id', $counterpartyId)
                ->first();

            if ($addr === null) {
                throw new CheckoutException('Selected delivery address was not found.');
            }

            return [
                'shipping_address_id' => $addr->id,
                'ship_to_name'        => $addr->attention_to,
                'ship_address_line_1' => $addr->address_line_1,
                'ship_address_line_2' => $addr->address_line_2,
                'ship_city'           => $addr->city,
                'ship_region'         => $addr->region,
                'ship_ghana_post_gps' => $addr->ghana_post_gps,
            ];
        }

        // Otherwise require an inline address.
        if (empty($data->shipAddressLine1) || empty($data->shipCity)) {
            throw new CheckoutException('A delivery address is required for delivery orders.');
        }

        return [
            'ship_to_name'        => $data->shipToName,
            'ship_to_phone'       => $data->shipToPhone,
            'ship_address_line_1' => $data->shipAddressLine1,
            'ship_address_line_2' => $data->shipAddressLine2,
            'ship_city'           => $data->shipCity,
            'ship_region'         => $data->shipRegion,
            'ship_ghana_post_gps' => $data->shipGhanaPostGps,
        ];
    }

    /**
     * Allocate the next storefront order reference (CF-P1-014).
     *
     * Was 'SO-' . str_pad(count() + 1), which two concurrent checkouts resolve
     * to the same value — and storefront_orders has a unique key on
     * (organization_id, reference), so one customer's checkout fails at the last
     * step, after payment has already been initialised.
     *
     * Delegated to StorefrontReferenceAllocator so this and
     * QuoteService::nextOrderReference() share ONE sequence. They previously
     * duplicated the same count-based logic and could mint identical references.
     */
    protected function nextReference(?int $organizationId): string
    {
        return app(StorefrontReferenceAllocator::class)->orderReference($organizationId);
    }
}
