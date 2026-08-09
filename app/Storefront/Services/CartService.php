<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontCart;
use App\Models\Storefront\StorefrontCartItem;
use App\Models\Storefront\StorefrontListing;
use App\Models\User;
use App\Storefront\Services\Exceptions\CartException;
use App\Storefront\Support\CartOwner;
use App\Storefront\Support\StorefrontCatalog;
use Illuminate\Support\Facades\DB;

/**
 * DB-backed cart. One ACTIVE cart per owner, where an owner is either a
 * signed-in buyer or a guest holding a cart token (Phase 9, blueprint §38).
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

    public function activeFor(CartOwner|User $owner): StorefrontCart
    {
        $owner = CartOwner::from($owner);

        return StorefrontCart::firstOrCreate(
            $owner->cartAttributes() + ['status' => StorefrontCart::STATUS_ACTIVE],
            ['organization_id' => $owner->organizationId],
        );
    }

    /**
     * The owner's active cart if they have one — without creating it.
     *
     * `activeFor()` creates on demand, which is right when someone is adding an
     * item and wrong for a header badge: every anonymous page view would mint a
     * row.
     */
    public function existingFor(CartOwner|User $owner): ?StorefrontCart
    {
        $owner = CartOwner::from($owner);

        return StorefrontCart::query()
            ->where($owner->cartAttributes())
            ->where('status', StorefrontCart::STATUS_ACTIVE)
            ->first();
    }

    public function itemCount(CartOwner|User $owner): int
    {
        $cart = $this->existingFor($owner);

        return $cart ? (int) $cart->items()->count() : 0;
    }

    public function addItem(CartOwner|User $owner, StorefrontListing $listing, string $quantity = '1'): StorefrontCartItem
    {
        $owner = CartOwner::from($owner);

        $this->assertOrderable($listing);
        $this->assertQuantityAvailable($listing, $quantity);

        $unitPrice = $this->effectiveUnitPrice($listing);

        return DB::transaction(function () use ($owner, $listing, $quantity, $unitPrice) {
            $cart = $this->activeFor($owner);

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

    public function updateQuantity(CartOwner|User $owner, StorefrontCartItem $item, string $quantity): StorefrontCartItem
    {
        $this->assertOwned($owner, $item);

        if (bccomp($quantity, '0', 4) <= 0) {
            $item->delete();
            return $item;
        }

        $this->assertQuantityAvailable($item->listing, $quantity);
        $item->update(['quantity' => $quantity]);

        return $item->refresh();
    }

    public function removeItem(CartOwner|User $owner, StorefrontCartItem $item): void
    {
        $this->assertOwned($owner, $item);
        $item->delete();
    }

    public function clear(CartOwner|User $owner): void
    {
        $cart = $this->activeFor($owner);
        $cart->items()->delete();
    }

    /**
     * Move a guest cart's items into the signed-in buyer's cart.
     *
     * Called when someone who has been shopping as a guest logs in. Without it
     * the basket they just filled disappears at exactly the moment they commit
     * to buying — the dead end §46 exists to prevent.
     *
     * Quantities ADD rather than overwrite when both carts hold the same
     * listing, and each merged line is re-checked against availability: a guest
     * cart can be days old, and silently carrying 4 of something with 2 left
     * would only fail at checkout, after the buyer has entered their details.
     * A line that no longer fits is capped at what is available rather than
     * dropped, so the buyer sees what happened.
     *
     * @return int the number of lines merged
     */
    public function mergeGuestCartInto(string $guestToken, User $user): int
    {
        $guestCart = StorefrontCart::query()
            ->where('guest_token', $guestToken)
            ->where('status', StorefrontCart::STATUS_ACTIVE)
            ->with('items.listing')
            ->first();

        if ($guestCart === null || $guestCart->items->isEmpty()) {
            $guestCart?->update(['status' => StorefrontCart::STATUS_ABANDONED]);

            return 0;
        }

        return DB::transaction(function () use ($guestCart, $user): int {
            $target = $this->activeFor(CartOwner::forUser($user));
            $merged = 0;

            foreach ($guestCart->items as $item) {
                $listing = $item->listing;

                if ($listing === null) {
                    continue;
                }

                $existing = $target->items()->where('listing_id', $listing->id)->first();

                $wanted = $existing === null
                    ? (string) $item->quantity
                    : bcadd((string) $existing->quantity, (string) $item->quantity, 4);

                // Cap rather than drop: the buyer keeps what is still available.
                if (bccomp($wanted, (string) $listing->quantity_available, 4) > 0) {
                    $wanted = (string) $listing->quantity_available;
                }

                if (bccomp($wanted, '0', 4) <= 0) {
                    continue;
                }

                if ($existing !== null) {
                    $existing->update(['quantity' => $wanted]);
                } else {
                    StorefrontCartItem::create([
                        'organization_id' => $listing->organization_id,
                        'cart_id'         => $target->id,
                        'listing_id'      => $listing->id,
                        'quantity'        => $wanted,
                        'price_snapshot'  => $item->price_snapshot,
                        'price_basis'     => $item->price_basis,
                    ]);
                }

                $merged++;
            }

            // The guest cart is retired, not deleted: its items are the record
            // of what the buyer had before logging in.
            $guestCart->items()->delete();
            $guestCart->update(['status' => StorefrontCart::STATUS_ABANDONED]);

            return $merged;
        });
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

    protected function assertOwned(CartOwner|User $owner, StorefrontCartItem $item): void
    {
        if (! CartOwner::from($owner)->owns($item->cart)) {
            throw new CartException('That cart item does not belong to you.');
        }
    }
}
