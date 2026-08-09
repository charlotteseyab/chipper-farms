<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontCart;
use App\Models\Storefront\StorefrontCartItem;
use App\Models\Storefront\StorefrontListing;
use App\Models\User;
use App\Storefront\Services\Exceptions\CartException;
use App\Storefront\Support\StorefrontCatalog;
use Illuminate\Support\Facades\DB;

/**
 * DB-backed cart for an authenticated customer. One ACTIVE cart per user.
 *
 * Pricing is SNAPSHOTTED at add-time (price_snapshot) so a later listing price
 * change never silently re-prices an in-progress cart. The effective unit
 * price is resolved here:
 *   - fixed basis   → the listing's fixed_price
 *   - live/carcass  → price_per_kg × estimated_weight_kg (a per-head price)
 * A listing that cannot be priced (e.g. per-kg with no weight, or quote-only)
 * is not addable to the cart — those go through the quote flow instead.
 */
class CartService
{
    public function __construct(protected ListingAvailabilityService $availability)
    {
    }

    public function activeFor(User $user): StorefrontCart
    {
        return StorefrontCart::firstOrCreate(
            ['user_id' => $user->id, 'status' => StorefrontCart::STATUS_ACTIVE],
            ['organization_id' => $user->organization_id],
        );
    }

    public function itemCount(User $user): int
    {
        $cart = StorefrontCart::query()
            ->where('user_id', $user->id)
            ->where('status', StorefrontCart::STATUS_ACTIVE)
            ->first();

        return $cart ? (int) $cart->items()->count() : 0;
    }

    public function addItem(User $user, StorefrontListing $listing, string $quantity = '1'): StorefrontCartItem
    {
        $this->assertOrderable($listing);
        $this->assertQuantityAvailable($listing, $quantity);

        $unitPrice = $this->effectiveUnitPrice($listing);

        return DB::transaction(function () use ($user, $listing, $quantity, $unitPrice) {
            $cart = $this->activeFor($user);

            $existing = $cart->items()->where('listing_id', $listing->id)->first();

            if ($existing !== null) {
                $newQty = bcadd((string) $existing->quantity, $quantity, 4);
                $this->assertQuantityAvailable($listing, $newQty);
                $existing->update([
                    'quantity'       => $newQty,
                    'price_snapshot' => $unitPrice, // refresh to current effective price
                ]);
                return $existing->refresh();
            }

            return StorefrontCartItem::create([
                'organization_id' => $listing->organization_id,
                'cart_id'         => $cart->id,
                'listing_id'      => $listing->id,
                'quantity'        => $quantity,
                'price_snapshot'  => $unitPrice,
                'price_basis'     => $listing->pricing_basis,
            ]);
        });
    }

    public function updateQuantity(User $user, StorefrontCartItem $item, string $quantity): StorefrontCartItem
    {
        $this->assertOwned($user, $item);

        if (bccomp($quantity, '0', 4) <= 0) {
            $item->delete();
            return $item;
        }

        $this->assertQuantityAvailable($item->listing, $quantity);
        $item->update(['quantity' => $quantity]);

        return $item->refresh();
    }

    public function removeItem(User $user, StorefrontCartItem $item): void
    {
        $this->assertOwned($user, $item);
        $item->delete();
    }

    public function clear(User $user): void
    {
        $cart = $this->activeFor($user);
        $cart->items()->delete();
    }

    // ------------------------------------------------------------------

    public function effectiveUnitPrice(StorefrontListing $listing): string
    {
        if ($listing->pricing_basis === StorefrontListing::BASIS_FIXED) {
            if ($listing->fixed_price === null) {
                throw new CartException('This listing has no price and cannot be added to the cart.');
            }
            return (string) $listing->fixed_price;
        }

        // live/carcass per-kg basis → per-head price = price_per_kg × weight.
        if ($listing->price_per_kg === null || $listing->estimated_weight_kg === null) {
            throw new CartException('This listing is priced per kg but has no weight; please request a quote.');
        }

        return bcmul((string) $listing->price_per_kg, (string) $listing->estimated_weight_kg, 2);
    }

    protected function assertOrderable(StorefrontListing $listing): void
    {
        // Phase 8: the category this listing belongs to must still be one the
        // organisation sells. Checked here rather than only in the controller
        // because a cart built before a category was switched off would
        // otherwise carry the item all the way to checkout — and because a
        // listing left published by mistake is reachable by any caller that
        // has its model.
        if (! StorefrontCatalog::isKindEnabled($listing->kind, $listing->organization_id)) {
            throw new CartException('This item is no longer offered.');
        }

        if (! $listing->isOrderable()) {
            throw new CartException('This listing is not available for direct purchase.');
        }

        if (! $this->availability->isSourceAvailable($listing)) {
            throw new CartException('This item is no longer available.');
        }
    }

    protected function assertQuantityAvailable(StorefrontListing $listing, string $quantity): void
    {
        if (bccomp($quantity, (string) $listing->quantity_available, 4) > 0) {
            throw new CartException('Requested quantity exceeds what is available.');
        }
    }

    protected function assertOwned(User $user, StorefrontCartItem $item): void
    {
        $cart = $item->cart;
        if ($cart === null || (int) $cart->user_id !== (int) $user->id) {
            throw new CartException('That cart item does not belong to you.');
        }
    }
}
