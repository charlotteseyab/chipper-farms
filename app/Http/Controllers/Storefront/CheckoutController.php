<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\CounterpartyAddress;
use App\Models\Storefront\StorefrontOrder;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\CustomerAccountService;
use App\Storefront\Services\Exceptions\CheckoutException;
use App\Storefront\Services\Exceptions\PaymentException;
use App\Storefront\Services\PaystackService;
use App\Storefront\Support\CartOwnerResolver;
use App\Storefront\Support\CheckoutData;
use App\Storefront\Support\StorefrontCatalog;
use App\Storefront\Support\StorefrontSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Checkout, for a signed-in buyer or a guest (Phase 9, blueprint §38).
 *
 * WHERE A GUEST ORDER GOES AFTERWARDS
 * -----------------------------------
 * A guest has no account page to land on, so the order redirect carries the
 * order's single-use claim token: `/shop/order/{reference}/{token}`. That one
 * URL is both the confirmation page and the tracking page, which is what §38's
 * recovery row needs — a guest whose payment fails has somewhere to go back to.
 *
 * The cart cookie is forgotten on success. Leaving it set would re-open a
 * converted cart on the buyer's next visit.
 */
class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CheckoutService $checkout,
        protected CustomerAccountService $accounts,
        protected PaystackService $paystack,
        protected CartOwnerResolver $owners,
    ) {
    }

    public function show(Request $request): View|RedirectResponse
    {
        $owner = $this->owners->current($request);

        $cart = $owner === null ? null : $this->cart->existingFor($owner);
        $cart?->load('items.listing');

        if ($cart === null || $cart->items->isEmpty()) {
            return redirect()->route('storefront.cart.index')->with('error', 'Your cart is empty.');
        }

        $organizationId = $owner->isGuest()
            ? $owner->organizationId
            : $request->user()->organization_id;

        // A guest reaching checkout at a farm that requires accounts is not
        // sent to a 404 — that is a dead end with a full basket behind it.
        // They are shown the same page with a sign-in prompt instead.
        $guestAllowed = ! $owner->isGuest() || StorefrontSettings::guestCheckoutEnabled($organizationId);

        $addresses = collect();

        if (! $owner->isGuest()) {
            $profile = $this->accounts->ensureProfile($request->user());
            $addresses = CounterpartyAddress::query()
                ->where('counterparty_id', $profile->counterparty_id)
                ->orderByDesc('is_default')->get();
        }

        // §38 — the buyer sees the tax before committing. Computed with the
        // real TaxCalculator so the preview and the order cannot disagree; the
        // base here is the goods only, since delivery is not chosen yet.
        $subtotal   = $cart->subtotal();
        $taxation   = app(\App\Storefront\Services\StorefrontTaxService::class)
            ->calculate((string) $subtotal, $organizationId);

        $taxInclusive = $taxation !== null
            && $taxation->priceMode === \App\Models\Finance\TaxProfile::PRICE_INCLUSIVE;

        return view('storefront.checkout.show', [
            'cart'               => $cart,
            'subtotal'           => $subtotal,
            'taxPreview'         => $taxation?->totalTax,
            'taxInclusive'       => $taxInclusive,
            'taxLabel'           => $taxation === null
                ? 'Tax'
                : 'Tax (' . collect($taxation->components)->pluck('code')->join(' + ') . ')',
            'totalPreview'       => $taxation === null || $taxInclusive
                ? (string) $subtotal
                : bcadd((string) $subtotal, $taxation->totalTax, 2),
            'addresses'          => $addresses,
            'isGuest'            => $owner->isGuest(),
            'guestAllowed'       => $guestAllowed,
            'paymentMethods'     => StorefrontCatalog::enabledPaymentMethods($organizationId),
            'fulfilmentMethods'  => StorefrontCatalog::enabledFulfilmentMethods($organizationId),
            'deliveryZones'      => StorefrontSettings::deliveryZones($organizationId),
            'refundPolicy'       => StorefrontSettings::refundPolicy($organizationId),
            'supportEmail'       => StorefrontSettings::supportEmail($organizationId),
            'supportPhone'       => StorefrontSettings::supportPhone($organizationId),
        ]);
    }

    public function place(CheckoutRequest $request): RedirectResponse
    {
        $owner = $this->owners->current($request);

        if ($owner === null) {
            return redirect()->route('storefront.cart.index')->with('error', 'Your cart is empty.');
        }

        $data = CheckoutData::fromArray($request->validated());

        try {
            $order = $this->checkout->checkout($owner, $data);
        } catch (CheckoutException $e) {
            return redirect()->route('storefront.cart.index')->with('error', $e->getMessage());
        }

        // The cart is converted; the token that addressed it must not survive,
        // or the next visit re-opens a converted cart.
        if ($owner->isGuest()) {
            $this->owners->forget();
        }

        $destination = $this->orderDestination($order);

        // Paystack orders go straight to the gateway; COD lands on the order page.
        if ($order->payment_method === StorefrontOrder::METHOD_PAYSTACK) {
            try {
                $result = $this->paystack->initialise(
                    $order,
                    callbackUrl: route('storefront.account.paystack.callback'),
                );
            } catch (PaymentException $e) {
                return redirect()->to($destination)
                    ->with('error', 'Could not start payment: ' . $e->getMessage());
            }

            return redirect()->away($result['authorization_url']);
        }

        return redirect()->to($destination)
            ->with('success', "Order {$order->reference} placed. We'll confirm availability shortly.");
    }

    /**
     * Where the buyer lands after placing an order.
     *
     * A guest has no account area, so they get the tracking URL carrying their
     * claim token — the only thing that proves the order is theirs.
     */
    protected function orderDestination(StorefrontOrder $order): string
    {
        return $order->guest_claim_token !== null
            ? route('storefront.order.track', [
                'reference' => $order->reference,
                'token'     => $order->guest_claim_token,
            ])
            : route('storefront.account.orders.show', $order);
    }
}
