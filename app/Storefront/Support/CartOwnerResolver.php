<?php

namespace App\Storefront\Support;

use App\Models\User;
use App\Storefront\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Works out whose cart the current request is touching (Phase 9, §38).
 *
 * THE TOKEN LIVES IN A COOKIE, THE CART LIVES IN THE DATABASE
 * -----------------------------------------------------------
 * Keeping the cart itself in the session would lose it whenever the session
 * rotated — which Laravel does on login, exactly when a buyer is most likely to
 * have a full basket. A database row addressed by a cookie token survives the
 * login, survives a browser restart, and lets one merge path handle both
 * "logged in halfway through" and "came back tomorrow".
 *
 * The cookie is httpOnly (no script needs to read it) and long-lived, because
 * an abandoned basket a week later is a sale, not a stale record.
 *
 * TOKENS ARE ONLY MINTED WHEN SOMETHING IS PUT IN A CART
 * ------------------------------------------------------
 * `current()` does not create a token for a visitor who is only browsing —
 * that would set a cookie on every anonymous page view and leave a trail of
 * empty carts. `currentOrCreate()` is the write path, called when an item is
 * actually added.
 *
 * MERGING ON LOGIN
 * ----------------
 * When a signed-in request arrives still carrying a guest token, the guest
 * cart's items are merged into the buyer's own cart and the cookie is cleared.
 * Without it a buyer who signs in at checkout watches their basket vanish at
 * the moment they commit to buying, which is the dead end §46 exists to
 * prevent.
 */
class CartOwnerResolver
{
    public const COOKIE = 'cf_cart';

    /** 60 days, in minutes. An abandoned basket next week is still a sale. */
    public const COOKIE_MINUTES = 60 * 24 * 60;

    public function __construct(
        protected CartService $carts,
        protected StorefrontSettingsResolver $settings,
    ) {
    }

    /**
     * The current owner, or null for a guest who has never had a cart.
     */
    public function current(Request $request): ?CartOwner
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->mergeGuestCartIfPresent($request, $user);

            return CartOwner::forUser($user);
        }

        $token = $this->readToken($request);

        return $token === null
            ? null
            : CartOwner::forGuest($token, $this->settings->organizationId());
    }

    /**
     * The current owner, minting a guest token if there is not one yet.
     *
     * Call this only on a write — see the class docblock.
     */
    public function currentOrCreate(Request $request): CartOwner
    {
        $existing = $this->current($request);

        if ($existing !== null) {
            return $existing;
        }

        // 64 hex characters from the framework's CSPRNG. Opaque: it says
        // nothing about the buyer, so a leaked token discloses a basket and
        // nothing else.
        $token = bin2hex(random_bytes(32));

        Cookie::queue(
            Cookie::make(
                name: self::COOKIE,
                value: $token,
                minutes: self::COOKIE_MINUTES,
                httpOnly: true,
                sameSite: 'lax',
            )
        );

        // Queued cookies are not readable back from this request, so the token
        // is stashed for the rest of it. Without this, adding two items in one
        // request would create two carts.
        $request->attributes->set('storefront_guest_cart_token', $token);

        return CartOwner::forGuest($token, $this->settings->organizationId());
    }

    /**
     * Forget the guest token — after checkout, or after merging into an
     * account. Leaving it set means the next visit re-opens a converted cart.
     */
    public function forget(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    protected function readToken(Request $request): ?string
    {
        $token = $request->attributes->get('storefront_guest_cart_token')
            ?? $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        // A token is 64 hex characters. Anything else is a tampered or
        // truncated cookie; treat it as absent rather than querying with it.
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? $token : null;
    }

    protected function mergeGuestCartIfPresent(Request $request, User $user): void
    {
        $token = $this->readToken($request);

        if ($token === null) {
            return;
        }

        $this->carts->mergeGuestCartInto($token, $user);
        $this->forget();
        $request->attributes->remove('storefront_guest_cart_token');
    }
}
