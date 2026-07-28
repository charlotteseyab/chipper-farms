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
use App\Storefront\Support\CheckoutData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CheckoutService $checkout,
        protected CustomerAccountService $accounts,
        protected PaystackService $paystack,
    ) {
    }

    public function show(Request $request): View|RedirectResponse
    {
        $cart = $this->cart->activeFor($request->user());
        $cart->load('items.listing');

        if ($cart->items->isEmpty()) {
            return redirect()->route('storefront.account.cart.index')->with('error', 'Your cart is empty.');
        }

        $profile = $this->accounts->ensureProfile($request->user());
        $addresses = CounterpartyAddress::query()
            ->where('counterparty_id', $profile->counterparty_id)
            ->orderByDesc('is_default')->get();

        return view('storefront.checkout.show', [
            'cart'      => $cart,
            'subtotal'  => $cart->subtotal(),
            'addresses' => $addresses,
        ]);
    }

    public function place(CheckoutRequest $request): RedirectResponse
    {
        $data = CheckoutData::fromArray($request->validated());

        try {
            $order = $this->checkout->checkout($request->user(), $data);
        } catch (CheckoutException $e) {
            return redirect()->route('storefront.account.cart.index')->with('error', $e->getMessage());
        }

        // Paystack orders go straight to the gateway; COD lands on the order page.
        if ($order->payment_method === StorefrontOrder::METHOD_PAYSTACK) {
            try {
                $result = $this->paystack->initialise(
                    $order,
                    callbackUrl: route('storefront.account.paystack.callback'),
                );
            } catch (PaymentException $e) {
                return redirect()
                    ->route('storefront.account.orders.show', $order)
                    ->with('error', 'Could not start payment: ' . $e->getMessage());
            }

            return redirect()->away($result['authorization_url']);
        }

        return redirect()
            ->route('storefront.account.orders.show', $order)
            ->with('success', "Order {$order->reference} placed. We'll confirm availability shortly.");
    }
}
