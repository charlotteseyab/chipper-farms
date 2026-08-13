<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontCartItem;
use App\Models\Storefront\StorefrontListing;
use App\Storefront\Services\CartService;
use App\Storefront\Services\Exceptions\CartException;
use App\Storefront\Services\StorefrontPigPricingService;
use App\Storefront\Services\StorefrontPurchaseLineService;
use App\Storefront\Support\CartOwnerResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cart, for anyone (Phase 9, blueprint §38 "cart works before login
 * through session/cart token").
 *
 * WHAT CHANGED
 * ------------
 * This controller previously sat behind `auth:sanctum` and took
 * `$request->user()` as read. §17 lists guest checkout as *essential* scope, and
 * a shop that demands registration before you can pick something up is a shop
 * most buyers leave. Ownership is now resolved through `CartOwnerResolver`,
 * which answers with the signed-in buyer or with a guest token, so the same
 * screen serves both.
 *
 * READS DO NOT MINT A TOKEN
 * -------------------------
 * `index()` uses `current()`, which returns null for a visitor who has never
 * had a cart, and renders an empty basket. Only `add()` uses
 * `currentOrCreate()`. Otherwise every anonymous page view would set a cookie
 * and leave an empty cart row behind it.
 */
class CartController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CartOwnerResolver $owners,
        protected StorefrontPurchaseLineService $purchaseLines,
    ) {
    }

    public function index(Request $request): View
    {
        $owner = $this->owners->current($request);

        $cart = $owner === null ? null : $this->cart->existingFor($owner);
        $cart?->load('items.listing.media');

        return view('storefront.cart.index', [
            'cart'    => $cart,
            'isGuest' => $owner === null || $owner->isGuest(),
        ]);
    }

    public function add(Request $request, StorefrontListing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['nullable', 'numeric', 'min:0.0001', 'max:99999999'],
        ]);

        try {
            $this->cart->addItem(
                $this->owners->currentOrCreate($request),
                $listing,
                (string) ($validated['quantity'] ?? '1'),
            );
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('storefront.cart.index')->with('success', 'Added to cart.');
    }

    /**
     * Buy a pig: either these animals, or this many kilograms of meat.
     *
     * TWO DIFFERENT PURCHASES, ONE ENTRY POINT
     * ----------------------------------------
     * A live purchase names the animals the buyer picked. A carcass purchase
     * names a weight and leaves the choice of animal to the farm. They arrive
     * through the same form because they are the same decision — "how do you
     * want to buy this?" — and the shape of what follows depends on the answer.
     *
     * Nothing here works out a price. The service does that from the listing's
     * snapshotted rates and the animals' recorded weights, on the server, and
     * whatever the browser thinks the total is never reaches the database.
     */
    public function addPigs(Request $request, StorefrontListing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_type'    => ['required', 'in:live,carcass'],
            'listing_pig_ids'  => ['required_if:purchase_type,live', 'array'],
            'listing_pig_ids.*'=> ['integer'],
            'requested_kg'     => ['required_if:purchase_type,carcass', 'nullable', 'numeric', 'min:0.0001', 'max:99999999'],
        ]);

        $owner = $this->owners->currentOrCreate($request);

        try {
            if ($validated['purchase_type'] === StorefrontPigPricingService::PURCHASE_LIVE) {
                $lines = $this->purchaseLines->addLiveLines(
                    $owner,
                    $listing,
                    $validated['listing_pig_ids'] ?? [],
                );

                $message = $lines->count() === 1
                    ? 'Added to cart.'
                    : $lines->count() . ' animals added to cart.';
            } else {
                $this->purchaseLines->addCarcassLine(
                    $owner,
                    $listing,
                    (string) $validated['requested_kg'],
                );

                $message = 'Added to cart.';
            }
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('storefront.cart.index')->with('success', $message);
    }

    public function update(Request $request, StorefrontCartItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        try {
            // Inside the try on purpose: requireOwner() raises the same
            // CartException as the service, so both refusals reach the buyer
            // as a sentence rather than one as a sentence and one as a crash.
            $this->cart->updateQuantity($this->requireOwner($request), $item, (string) $validated['quantity']);
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, StorefrontCartItem $item): RedirectResponse
    {
        try {
            $this->cart->removeItem($this->requireOwner($request), $item);
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Item removed.');
    }

    /**
     * Changing a cart line requires an owner. A visitor with no token owns no
     * cart, so `CartService::assertOwned()` would refuse anyway — this turns
     * that into the same "not yours" answer rather than a null dereference,
     * and keeps the refusal in one place.
     */
    protected function requireOwner(Request $request): \App\Storefront\Support\CartOwner
    {
        $owner = $this->owners->current($request);

        if ($owner === null) {
            throw new CartException('That cart item does not belong to you.');
        }

        return $owner;
    }
}
