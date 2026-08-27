<?php

namespace Tests\Feature\Storefront;

use App\Models\Counterparty;
use App\Models\Storefront\StorefrontBuyer;
use App\Storefront\Payments\PaystackClient;
use App\Storefront\Services\PaystackService;
use Tests\Feature\Storefront\Support\FakePaystackClient;
use App\Models\Storefront\StorefrontCart;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\User;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\CheckoutException;
use App\Storefront\Support\CartOwner;
use App\Storefront\Support\CartOwnerResolver;
use App\Storefront\Support\CheckoutData;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 9 — guest cart and checkout (blueprint §38).
 *
 * Two things are being proven here. The first is that a buyer with no account
 * can fill a basket and order, which §17 calls essential scope. The second, and
 * the one the tests are shaped around, is that the token which replaces the
 * login does not become a way into other people's orders: it is the only
 * credential a guest has, so every way of misusing it is tested.
 */
class GuestCheckoutTest extends StorefrontTestCase
{
    use RefreshDatabase;

    /**
     * The cart cookie is encrypted by the application, as every cookie here is.
     * These tests therefore read it decrypted (`getCookie()` decrypts by
     * default) and send it back through `withCookie()`, which re-encrypts —
     * the round trip a browser actually makes. Disabling cookie encryption for
     * the test would have been easier and would have stopped exercising the
     * middleware the real request goes through.
     */
    protected function sellableListing(array $overrides = []): StorefrontListing
    {
        return $this->makePublishedListing(array_merge([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '250.00',
            'quantity_available' => '10',
        ], $overrides));
    }

    /* ---------------------------------------------------------------- */
    /* The journey                                                      */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_can_fill_a_basket_and_order_without_an_account(): void
    {
        $listing = $this->sellableListing();

        $add = $this->post(route('storefront.cart.add', $listing), ['quantity' => '2']);
        $add->assertRedirect(route('storefront.cart.index'));

        $token = $add->getCookie(CartOwnerResolver::COOKIE)?->getValue();
        $this->assertNotNull($token, 'Adding to a cart must mint a guest cart token.');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);

        // The basket survives the next request, which is the whole point of the
        // token — a cart kept in the session would not.
        $this->withCookie(CartOwnerResolver::COOKIE, $token)
            ->get(route('storefront.cart.index'))
            ->assertOk()
            ->assertSee($listing->title);

        $placed = $this->withCookie(CartOwnerResolver::COOKIE, $token)
            ->post(route('storefront.checkout.place'), [
                'payment_method'    => 'cod',
                'fulfilment_method' => 'pickup',
                'guest_name'        => 'Ama Mensah',
                'guest_phone'       => '0244000000',
            ]);

        $order = StorefrontOrder::first();

        $this->assertNotNull($order);
        $this->assertNull($order->customer_user_id, 'A guest order has no user.');
        $this->assertSame('Ama Mensah', $order->guest_name);
        $this->assertSame('0244000000', $order->guest_phone);
        $this->assertSame('500.00', (string) $order->total);

        // A guest order still reaches finance, which is built on counterparties.
        $this->assertNotNull($order->counterparty_id);

        // And they land on a page they can come back to.
        $placed->assertRedirect(route('storefront.order.track', [
            'reference' => $order->reference,
            'token'     => $order->guest_claim_token,
        ]));
    }

    public function test_the_cart_cookie_is_cleared_once_the_order_is_placed(): void
    {
        $listing = $this->sellableListing();

        $token = $this->post(route('storefront.cart.add', $listing), ['quantity' => '1'])
            ->getCookie(CartOwnerResolver::COOKIE)?->getValue();

        $placed = $this->withCookie(CartOwnerResolver::COOKIE, $token)
            ->post(route('storefront.checkout.place'), [
                'payment_method'    => 'cod',
                'fulfilment_method' => 'pickup',
                'guest_name'        => 'Ama Mensah',
                'guest_phone'       => '0244000000',
            ]);

        // Forgotten, or the buyer's next visit re-opens a converted cart.
        // Asserted as "expired" rather than "empty value": Cookie::forget()
        // works by back-dating the expiry, and the value it carries is an
        // implementation detail of the framework.
        $cookie = $placed->getCookie(CartOwnerResolver::COOKIE, false);
        $this->assertNotNull($cookie);
        $this->assertTrue(
            $cookie->getExpiresTime() > 0 && $cookie->getExpiresTime() < time(),
            'The cart cookie must be expired once the order is placed.',
        );
    }

    public function test_a_guest_must_leave_a_name_and_a_way_to_be_reached(): void
    {
        $listing = $this->sellableListing();

        $token = $this->post(route('storefront.cart.add', $listing), ['quantity' => '1'])
            ->getCookie(CartOwnerResolver::COOKIE)?->getValue();

        $this->withCookie(CartOwnerResolver::COOKIE, $token)
            ->post(route('storefront.checkout.place'), [
                'payment_method'    => 'cod',
                'fulfilment_method' => 'pickup',
                'guest_name'        => 'Ama Mensah',
            ])
            ->assertSessionHasErrors(['guest_email', 'guest_phone']);

        $this->assertSame(0, StorefrontOrder::count());
    }

    /**
     * A farm that requires accounts must say so, not 404 a full basket.
     */
    public function test_a_farm_can_switch_guest_checkout_off(): void
    {
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_GUEST_CHECKOUT);

        $listing = $this->sellableListing();

        $token = $this->post(route('storefront.cart.add', $listing), ['quantity' => '1'])
            ->getCookie(CartOwnerResolver::COOKIE)?->getValue();

        $this->withCookie(CartOwnerResolver::COOKIE, $token)
            ->get(route('storefront.checkout.show'))
            ->assertOk()
            ->assertSee('asks buyers to sign in before ordering');

        // And the endpoint refuses too — hiding the form is not enforcing it.
        $this->withCookie(CartOwnerResolver::COOKIE, $token)
            ->post(route('storefront.checkout.place'), [
                'payment_method'    => 'cod',
                'fulfilment_method' => 'pickup',
                'guest_name'        => 'Ama Mensah',
                'guest_phone'       => '0244000000',
            ])
            ->assertRedirect(route('storefront.cart.index'));

        $this->assertSame(0, StorefrontOrder::count());
    }

    /* ---------------------------------------------------------------- */
    /* The token is the credential                                      */
    /* ---------------------------------------------------------------- */

    public function test_an_order_cannot_be_read_without_its_token(): void
    {
        $order = $this->placeGuestOrder();

        $this->get(route('storefront.order.track', [
            'reference' => $order->reference,
            'token'     => str_repeat('0', 64),
        ]))->assertNotFound();
    }

    public function test_an_order_can_be_read_with_its_token(): void
    {
        $order = $this->placeGuestOrder();

        $this->get(route('storefront.order.track', [
            'reference' => $order->reference,
            'token'     => $order->guest_claim_token,
        ]))
            ->assertOk()
            ->assertSee($order->reference);
    }

    /**
     * An order already placed stays visible to whoever placed it, whatever the
     * farm has since done with its shop — the same rule as the Paystack webhook.
     */
    public function test_tracking_survives_the_shop_closing(): void
    {
        $order = $this->placeGuestOrder();

        \App\Models\Storefront\StorefrontSetting::create([
            'organization_id'  => $this->org->id,
            'public_shop_open' => false,
        ]);

        $this->get(route('storefront.order.track', [
            'reference' => $order->reference,
            'token'     => $order->guest_claim_token,
        ]))->assertOk();
    }

    /* ---------------------------------------------------------------- */
    /* Claiming                                                         */
    /* ---------------------------------------------------------------- */

    public function test_a_signed_in_buyer_can_claim_their_guest_order(): void
    {
        $order = $this->placeGuestOrder();
        $token = $order->guest_claim_token;

        $user = $this->makeUser('customer');

        $this->actingAs($user)
            ->post(route('storefront.order.claim', ['reference' => $order->reference, 'token' => $token]))
            ->assertRedirect(route('storefront.account.orders.show', $order));

        $order->refresh();

        $this->assertSame($user->id, $order->customer_user_id);
        $this->assertNotNull($order->claimed_at);
        // Single-use: the token is spent.
        $this->assertNull($order->guest_claim_token);
    }

    /**
     * The reason the token is cleared. A confirmation link forwarded to a
     * colleague must not attach the order to their account too.
     */
    public function test_a_claim_token_cannot_be_replayed(): void
    {
        $order = $this->placeGuestOrder();
        $token = $order->guest_claim_token;

        $first = $this->makeUser('customer');
        $this->actingAs($first)
            ->post(route('storefront.order.claim', ['reference' => $order->reference, 'token' => $token]))
            ->assertRedirect();

        $this->flushSession();

        $second = $this->makeUser('customer');
        $this->actingAs($second)
            ->post(route('storefront.order.claim', ['reference' => $order->reference, 'token' => $token]))
            ->assertNotFound();

        $this->assertSame($first->id, $order->fresh()->customer_user_id);
    }

    public function test_claiming_requires_being_signed_in(): void
    {
        $order = $this->placeGuestOrder();

        $this->post(route('storefront.order.claim', [
            'reference' => $order->reference,
            'token'     => $order->guest_claim_token,
        ]))->assertRedirect(route('login'));

        $this->assertNull($order->fresh()->customer_user_id);
    }

    /* ---------------------------------------------------------------- */
    /* Merging on login                                                 */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cart_merges_into_the_account_on_sign_in(): void
    {
        $listing = $this->sellableListing();

        $token = $this->post(route('storefront.cart.add', $listing), ['quantity' => '2'])
            ->getCookie(CartOwnerResolver::COOKIE)?->getValue();

        $user = $this->makeUser('customer');

        $this->actingAs($user)
            ->withCookie(CartOwnerResolver::COOKIE, $token)
            ->get(route('storefront.cart.index'))
            ->assertOk()
            ->assertSee($listing->title);

        $userCart = StorefrontCart::where('user_id', $user->id)
            ->where('status', StorefrontCart::STATUS_ACTIVE)->first();

        $this->assertNotNull($userCart);
        $this->assertSame(1, $userCart->items()->count());
        $this->assertSame('2.0000', (string) $userCart->items()->first()->quantity);

        // The guest cart is retired rather than left active alongside it.
        $this->assertSame(
            StorefrontCart::STATUS_ABANDONED,
            StorefrontCart::where('guest_token', $token)->first()?->status,
        );
    }

    public function test_merging_adds_quantities_and_caps_at_what_is_available(): void
    {
        $listing = $this->sellableListing(['quantity_available' => '3']);

        $user = $this->makeUser('customer');
        $this->app->make(CartService::class)->addItem($user, $listing, '2');

        $guestToken = bin2hex(random_bytes(32));
        $this->app->make(CartService::class)->addItem(
            CartOwner::forGuest($guestToken, $this->org->id),
            $listing,
            '2',
        );

        $merged = $this->app->make(CartService::class)->mergeGuestCartInto($guestToken, $user);

        $this->assertSame(1, $merged);

        $line = StorefrontCart::where('user_id', $user->id)
            ->where('status', StorefrontCart::STATUS_ACTIVE)
            ->first()->items()->first();

        // 2 + 2 = 4 wanted, 3 available. Capped rather than dropped, so the
        // buyer keeps what they can still have.
        $this->assertSame('3.0000', (string) $line->quantity);
    }

    /* ---------------------------------------------------------------- */
    /* Cart ownership                                                   */
    /* ---------------------------------------------------------------- */

    /**
     * The guest equivalent of "another customer's cart". Without an owner check
     * that handles tokens, one guest could edit another's basket by item id.
     */
    public function test_one_guest_cannot_change_another_guests_cart(): void
    {
        $listing = $this->sellableListing();

        $mine = $this->post(route('storefront.cart.add', $listing), ['quantity' => '1'])
            ->getCookie(CartOwnerResolver::COOKIE)?->getValue();

        $item = StorefrontCart::where('guest_token', $mine)->first()->items()->first();

        $theirToken = bin2hex(random_bytes(32));

        $this->withCookie(CartOwnerResolver::COOKIE, $theirToken)
            ->patch(route('storefront.cart.update', $item), ['quantity' => '9'])
            ->assertSessionHas('error');

        $this->assertSame('1.0000', (string) $item->fresh()->quantity);
    }

    public function test_a_visitor_with_no_token_owns_no_cart(): void
    {
        $listing = $this->sellableListing();

        $mine = $this->post(route('storefront.cart.add', $listing), ['quantity' => '1'])
            ->getCookie(CartOwnerResolver::COOKIE)?->getValue();

        $item = StorefrontCart::where('guest_token', $mine)->first()->items()->first();

        $this->delete(route('storefront.cart.remove', $item))->assertSessionHas('error');

        $this->assertNotNull($item->fresh());
    }

    /**
     * A tampered or truncated cookie must not be used as a lookup key.
     */
    public function test_a_malformed_cart_token_is_treated_as_absent(): void
    {
        $this->withCookie(CartOwnerResolver::COOKIE, 'not-a-token')
            ->get(route('storefront.cart.index'))
            ->assertOk();

        $this->assertSame(0, StorefrontCart::where('guest_token', 'not-a-token')->count());
    }

    /**
     * Browsing must not mint tokens — otherwise every anonymous page view sets
     * a cookie and leaves an empty cart row behind it.
     */
    public function test_browsing_does_not_create_a_cart(): void
    {
        $this->sellableListing();

        $this->get(route('storefront.shop'))->assertOk();
        $this->get(route('storefront.cart.index'))->assertOk();

        $this->assertSame(0, StorefrontCart::count());
    }

    /* ---------------------------------------------------------------- */
    /* The service, directly                                            */
    /* ---------------------------------------------------------------- */

    public function test_a_signed_in_buyers_order_ignores_posted_guest_fields(): void
    {
        $listing = $this->sellableListing();
        $user    = $this->makeUser('customer');

        $this->app->make(CartService::class)->addItem($user, $listing, '1');

        $order = $this->app->make(CheckoutService::class)->checkout($user, CheckoutData::fromArray([
            'payment_method'    => 'cod',
            'fulfilment_method' => 'pickup',
            // Someone else's identity, posted by a signed-in buyer.
            'guest_name'  => 'Not Me',
            'guest_email' => 'someone.else@example.com',
        ]));

        $this->assertSame($user->id, $order->customer_user_id);
        $this->assertNull($order->guest_name);
        $this->assertNull($order->guest_email);
        $this->assertNull($order->guest_claim_token, 'An account order needs no claim token.');

        $this->assertSame(
            0,
            Counterparty::where('email', 'someone.else@example.com')->count(),
            'A posted guest email must not re-address a signed-in order.',
        );
    }

    public function test_the_service_refuses_a_guest_order_when_guest_checkout_is_off(): void
    {
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_GUEST_CHECKOUT);

        $listing = $this->sellableListing();
        $token   = bin2hex(random_bytes(32));
        $owner   = CartOwner::forGuest($token, $this->org->id);

        $this->app->make(CartService::class)->addItem($owner, $listing, '1');

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('create an account');

        $this->app->make(CheckoutService::class)->checkout($owner, CheckoutData::fromArray([
            'payment_method'    => 'cod',
            'fulfilment_method' => 'pickup',
            'guest_name'        => 'Ama Mensah',
            'guest_phone'       => '0244000000',
        ]));
    }

    /**
     * A guest who gives the same email as an existing customer lands on that
     * customer's counterparty rather than a duplicate — the same rule
     * registered buyers already follow.
     */
    public function test_a_guest_email_matches_an_existing_customer(): void
    {
        $existing = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Ama Mensah', 'code' => 'CUST-AMA',
            'type' => Counterparty::TYPE_CUSTOMER,
            'email' => 'ama@example.com', 'currency_code' => 'GHS',
        ]);

        $order = $this->placeGuestOrder(['guest_email' => 'ama@example.com']);

        $this->assertSame($existing->id, $order->counterparty_id);
    }

    public function test_a_guest_creates_a_buyer_account_from_the_order_not_a_staff_user(): void
    {
        $order = $this->placeGuestOrder(['guest_email' => 'ama@example.com']);

        $this->get(route('storefront.order.track', [
            'reference' => $order->reference,
            'token'     => $order->guest_claim_token,
        ]))
            ->assertOk()
            ->assertSee('Create buyer account')
            ->assertDontSee('Only a superuser');

        $this->post(route('storefront.buyer.register.from-order.store', [
            'reference' => $order->reference,
            'token'     => $order->guest_claim_token,
        ]), [
            'name'                  => 'Ama Mensah',
            'email'                 => 'ama@example.com',
            'phone'                 => '0244000000',
            'password'              => 'buyer-secret',
            'password_confirmation' => 'buyer-secret',
        ])->assertRedirect();

        $buyer = StorefrontBuyer::first();

        $this->assertNotNull($buyer);
        $this->assertSame('ama@example.com', $buyer->email);
        $this->assertSame($order->counterparty_id, $buyer->counterparty_id);
        $this->assertSame(0, User::where('email', 'ama@example.com')->count());

        $order->refresh();
        $this->assertSame($buyer->id, $order->storefront_buyer_id);
        $this->assertNull($order->guest_claim_token);
    }

    public function test_creating_a_buyer_account_claims_other_guest_orders_for_the_verified_email(): void
    {
        $first = $this->placeGuestOrder(['guest_email' => 'ama@example.com']);
        $second = $this->placeGuestOrder(['guest_email' => 'ama@example.com']);

        $this->post(route('storefront.buyer.register.from-order.store', [
            'reference' => $first->reference,
            'token' => $first->guest_claim_token,
        ]), [
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'password' => 'buyer-secret',
            'password_confirmation' => 'buyer-secret',
        ])->assertRedirect();

        $buyer = StorefrontBuyer::firstOrFail();
        $this->assertSame($buyer->id, $first->fresh()->storefront_buyer_id);
        $this->assertSame($buyer->id, $second->fresh()->storefront_buyer_id);
        $this->assertNull($second->fresh()->guest_claim_token);
    }

    public function test_order_account_creation_cannot_change_the_verified_order_email(): void
    {
        $order = $this->placeGuestOrder(['guest_email' => 'ama@example.com']);

        $this->post(route('storefront.buyer.register.from-order.store', [
            'reference' => $order->reference,
            'token' => $order->guest_claim_token,
        ]), [
            'name' => 'Another Buyer',
            'email' => 'other@example.com',
            'password' => 'buyer-secret',
            'password_confirmation' => 'buyer-secret',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('storefront_buyers', 0);
        $this->assertNull($order->fresh()->storefront_buyer_id);
    }

    public function test_an_existing_buyer_can_sign_in_and_claim_a_guest_order(): void
    {
        $order = $this->placeGuestOrder(['guest_email' => 'ama@example.com']);
        $buyer = StorefrontBuyer::create([
            'organization_id' => $this->org->id,
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'password' => bcrypt('buyer-secret'),
        ]);

        $this->post(route('storefront.buyer.login.store'), [
            'email' => 'ama@example.com',
            'password' => 'buyer-secret',
            'claim_reference' => $order->reference,
            'claim_token' => $order->guest_claim_token,
        ])->assertRedirect(route('storefront.buyer.orders.show', $order));

        $this->assertSame($buyer->id, $order->fresh()->storefront_buyer_id);
        $this->assertNull($order->fresh()->guest_claim_token);
    }

    public function test_guest_returns_from_successful_paystack_payment_to_secure_order_details(): void
    {
        $fake = new FakePaystackClient();
        $this->app->instance(PaystackClient::class, $fake);
        $order = $this->placeGuestOrder([
            'guest_email' => 'ama@example.com',
            'payment_method' => 'paystack',
        ]);
        $initialised = app(PaystackService::class)->initialise($order, route('storefront.account.paystack.callback'));

        $this->get(route('storefront.account.paystack.callback', ['reference' => $initialised['reference']]))
            ->assertRedirect(route('storefront.order.track', [
                'reference' => $order->reference,
                'token' => $order->guest_claim_token,
            ]));
    }

    public function test_guest_order_page_offers_sign_in_when_a_buyer_account_exists(): void
    {
        $order = $this->placeGuestOrder(['guest_email' => 'ama@example.com']);
        StorefrontBuyer::create([
            'organization_id' => $this->org->id,
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'password' => bcrypt('buyer-secret'),
        ]);

        $this->get(route('storefront.order.track', [
            'reference' => $order->reference,
            'token' => $order->guest_claim_token,
        ]))
            ->assertOk()
            ->assertSee('Sign in to save this order')
            ->assertDontSee('Create buyer account');
    }

    /* ---------------------------------------------------------------- */

    protected function placeGuestOrder(array $overrides = []): StorefrontOrder
    {
        $listing = $this->sellableListing();
        $token   = bin2hex(random_bytes(32));
        $owner   = CartOwner::forGuest($token, $this->org->id);

        $this->app->make(CartService::class)->addItem($owner, $listing, '1');

        return $this->app->make(CheckoutService::class)->checkout($owner, CheckoutData::fromArray(array_merge([
            'payment_method'    => 'cod',
            'fulfilment_method' => 'pickup',
            'guest_name'        => 'Ama Mensah',
            'guest_phone'       => '0244000000',
        ], $overrides)));
    }
}
