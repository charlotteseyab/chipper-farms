<?php

namespace App\Storefront\Support;

use App\Models\User;

/**
 * Who a cart belongs to — a signed-in buyer, or a guest holding a token
 * (Phase 9, blueprint §38 "cart works before login through session/cart token").
 *
 * WHY A VALUE OBJECT RATHER THAN A NULLABLE USER
 * ----------------------------------------------
 * `CartService` previously took `User $user` everywhere, and the obvious
 * change — making it `?User` — would have meant every method growing a null
 * branch, and the ownership check in `assertOwned()` silently passing for
 * *every* guest cart the moment someone forgot one. A guest is not "a user that
 * happens to be missing"; they are a different kind of owner with a different
 * proof of ownership.
 *
 * This type makes the two cases explicit and gives `matches()` one place to
 * answer "is this cart yours", so the guest branch cannot be forgotten in one
 * method while being handled in another.
 */
final class CartOwner
{
    private function __construct(
        public readonly ?User $user,
        public readonly ?string $guestToken,
        public readonly ?int $organizationId,
    ) {
    }

    public static function forUser(User $user): self
    {
        return new self($user, null, $user->organization_id === null ? null : (int) $user->organization_id);
    }

    public static function forGuest(string $token, ?int $organizationId): self
    {
        return new self(null, $token, $organizationId);
    }

    /**
     * Accept either form. Every `CartService` method takes `CartOwner|User` so
     * the dozens of existing authenticated call sites keep working unchanged —
     * a signed-in buyer is simply one kind of cart owner.
     */
    public static function from(self|User $owner): self
    {
        return $owner instanceof self ? $owner : self::forUser($owner);
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    /**
     * The columns that identify this owner's cart.
     *
     * @return array<string, mixed>
     */
    public function cartAttributes(): array
    {
        return $this->isGuest()
            ? ['guest_token' => $this->guestToken, 'user_id' => null]
            : ['user_id' => $this->user->id];
    }

    /**
     * Does the given cart belong to this owner?
     *
     * Written as an explicit pair of positive checks rather than "not someone
     * else's": a cart with neither a user nor a token — which should not exist,
     * but a bad migration or a partial write could produce one — must belong to
     * nobody rather than to everybody.
     */
    public function owns(?\App\Models\Storefront\StorefrontCart $cart): bool
    {
        if ($cart === null) {
            return false;
        }

        if ($this->isGuest()) {
            return $this->guestToken !== null
                && $cart->guest_token !== null
                && hash_equals((string) $cart->guest_token, $this->guestToken);
        }

        return $cart->user_id !== null && (int) $cart->user_id === (int) $this->user->id;
    }
}
