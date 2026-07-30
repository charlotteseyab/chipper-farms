<?php

namespace App\Storefront\Services;

use App\Models\CounterpartyAddress;
use App\Models\Storefront\StorefrontCart;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\User;
use App\Storefront\Services\Exceptions\CheckoutException;
use App\Storefront\Support\CheckoutData;
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

    public function checkout(User $user, CheckoutData $data): StorefrontOrder
    {
        $cart = $this->cartService->activeFor($user);
        $cart->load('items.listing');

        if ($cart->items->isEmpty()) {
            throw new CheckoutException('Your cart is empty.');
        }

        $profile = $this->accounts->ensureProfile($user);

        return DB::transaction(function () use ($user, $data, $cart, $profile) {
            $organizationId = $user->organization_id;

            // Resolve + snapshot the shipping address for delivery orders.
            $ship = $this->resolveShipping($data, $profile->counterparty_id);

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

                // Accumulate delivery fee (once per listing that charges one) only
                // when the order is a delivery order.
                if ($data->isDelivery() && $listing->delivery_allowed && $listing->delivery_fee !== null) {
                    $deliveryFee = bcadd($deliveryFee, (string) $listing->delivery_fee, 2);
                }

                $lineDrafts[] = [
                    'listing'    => $listing,
                    'quantity'   => (string) $item->quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if (! $data->isDelivery()) {
                $deliveryFee = '0.00';
            }

            $total = bcadd($subtotal, $deliveryFee, 2);

            $isPaystack = $data->isPaystack();

            $order = StorefrontOrder::create([
                'organization_id'   => $organizationId,
                'reference'         => $this->nextReference($organizationId),
                'counterparty_id'   => $profile->counterparty_id,
                'customer_user_id'  => $user->id,
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
                note: 'Order placed by customer.',
                actorId: $user->id,
            );

            return $order->load('items');
        });
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
