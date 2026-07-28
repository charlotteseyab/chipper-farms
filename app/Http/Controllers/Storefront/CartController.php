<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontCartItem;
use App\Models\Storefront\StorefrontListing;
use App\Storefront\Services\CartService;
use App\Storefront\Services\Exceptions\CartException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Authenticated customer cart. (v1 requires sign-in to buy, which keeps every
 * order tied to a real AR counterparty for clean finance integration.)
 */
class CartController extends Controller
{
    public function __construct(protected CartService $cart)
    {
    }

    public function index(Request $request): View
    {
        $cart = $this->cart->activeFor($request->user());
        $cart->load('items.listing.media');

        return view('storefront.cart.index', ['cart' => $cart]);
    }

    public function add(Request $request, StorefrontListing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['nullable', 'numeric', 'min:0.0001', 'max:99999999'],
        ]);

        try {
            $this->cart->addItem($request->user(), $listing, (string) ($validated['quantity'] ?? '1'));
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('storefront.account.cart.index')->with('success', 'Added to cart.');
    }

    public function update(Request $request, StorefrontCartItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        try {
            $this->cart->updateQuantity($request->user(), $item, (string) $validated['quantity']);
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, StorefrontCartItem $item): RedirectResponse
    {
        try {
            $this->cart->removeItem($request->user(), $item);
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Item removed.');
    }
}
