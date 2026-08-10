<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontInventoryHold;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontSetting;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\CartException;
use App\Storefront\Services\Exceptions\InventoryConflictException;
use App\Storefront\Services\HoldService;
use App\Storefront\Services\OrderService;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 10 — checkout holds (blueprint §36, §37).
 *
 * The guarantee: two buyers must not both be able to pay for the last animal.
 * Before this phase nothing happened to inventory between checkout and staff
 * confirmation, so both checkouts succeeded, both buyers were sent to Paystack,
 * and the second found out after their money had left.
 *
 * Real contention between two database connections is proven separately in
 * `HoldConcurrencyTest`, which only runs on MariaDB — SQLite cannot demonstrate
 * `SELECT ... FOR UPDATE` blocking, and the plan's constraint 5 says money- and
 * stock-critical lock behaviour is not certified on SQLite.
 */
class HoldServiceTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function sellable(array $overrides = []): StorefrontListing
    {
        return $this->makePublishedListing(array_merge([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '250.00',
            'quantity_available' => '1',
        ], $overrides));
    }

    protected function placeOrder(StorefrontListing $listing, string $quantity = '1', string $method = 'cod'): StorefrontOrder
    {
        $buyer = $this->makeUser('customer');
        $this->app->make(CartService::class)->addItem($buyer, $listing, $quantity);

        return $this->app->make(CheckoutService::class)->checkout($buyer, CheckoutData::fromArray([
            'payment_method' => $method, 'fulfilment_method' => 'pickup',
        ]));
    }

    /* ---------------------------------------------------------------- */
    /* The guarantee                                                    */
    /* ---------------------------------------------------------------- */

    public function test_checkout_places_a_hold_for_every_line(): void
    {
        $listing = $this->sellable(['quantity_available' => '5']);

        $order = $this->placeOrder($listing, '2');

        $hold = StorefrontInventoryHold::where('order_id', $order->id)->first();

        $this->assertNotNull($hold);
        $this->assertSame(StorefrontInventoryHold::STATUS_ACTIVE, $hold->status);
        $this->assertSame('2.0000', $hold->quantityString());
        $this->assertSame($listing->id, $hold->listing_id);
    }

    /**
     * A hold does NOT move inventory. Confirmation decrements the column, once.
     * If a hold decremented too, the same stock would be taken twice.
     */
    public function test_a_hold_does_not_decrement_stock(): void
    {
        $listing = $this->sellable(['quantity_available' => '5']);

        $this->placeOrder($listing, '2');

        $this->assertSame('5.0000', (string) $listing->fresh()->quantity_available);
    }

    public function test_a_held_unit_is_not_offered_to_the_next_buyer(): void
    {
        $listing = $this->sellable(['quantity_available' => '1']);

        $this->placeOrder($listing, '1');

        $this->assertSame('0.0000', $this->app->make(HoldService::class)->availableToSell($listing->fresh()));

        // The cart refuses first, which is the friendlier moment.
        $this->expectException(CartException::class);

        $this->app->make(CartService::class)->addItem($this->makeUser('customer'), $listing, '1');
    }

    /**
     * The authoritative refusal. The cart takes no lock and is advisory; this is
     * the one that runs under `SELECT ... FOR UPDATE`.
     */
    public function test_checkout_itself_refuses_stock_that_is_already_held(): void
    {
        $listing = $this->sellable(['quantity_available' => '1']);

        $this->placeOrder($listing, '1');

        // Straight past the cart's advisory check, to the locked one: a second
        // buyer's cart built before the first hold existed.
        $second = $this->makeUser('customer');
        $cart   = $this->app->make(CartService::class)->activeFor($second);
        \App\Models\Storefront\StorefrontCartItem::create([
            'organization_id' => $this->org->id,
            'cart_id'         => $cart->id,
            'listing_id'      => $listing->id,
            'quantity'        => '1',
            'price_snapshot'  => '250.00',
            'price_basis'     => StorefrontListing::BASIS_FIXED,
        ]);

        $this->expectException(InventoryConflictException::class);
        $this->expectExceptionMessage('only 0 left');

        $this->app->make(CheckoutService::class)->checkout($second, CheckoutData::fromArray([
            'payment_method' => 'cod', 'fulfilment_method' => 'pickup',
        ]));
    }

    public function test_a_refused_checkout_leaves_no_order_behind(): void
    {
        $listing = $this->sellable(['quantity_available' => '1']);
        $this->placeOrder($listing, '1');

        $second = $this->makeUser('customer');
        $cart   = $this->app->make(CartService::class)->activeFor($second);
        \App\Models\Storefront\StorefrontCartItem::create([
            'organization_id' => $this->org->id,
            'cart_id' => $cart->id, 'listing_id' => $listing->id,
            'quantity' => '1', 'price_snapshot' => '250.00',
            'price_basis' => StorefrontListing::BASIS_FIXED,
        ]);

        try {
            $this->app->make(CheckoutService::class)->checkout($second, CheckoutData::fromArray([
                'payment_method' => 'cod', 'fulfilment_method' => 'pickup',
            ]));
            $this->fail('The second checkout should have been refused.');
        } catch (InventoryConflictException) {
            // expected
        }

        // The hold is placed INSIDE the checkout transaction, so a refusal
        // rolls the order back with it.
        $this->assertSame(1, StorefrontOrder::count());
        $this->assertSame(1, StorefrontInventoryHold::count());
    }

    /* ---------------------------------------------------------------- */
    /* Resolution                                                       */
    /* ---------------------------------------------------------------- */

    public function test_confirmation_consumes_the_hold_and_decrements_once(): void
    {
        $listing = $this->sellable(['quantity_available' => '5']);
        $order   = $this->placeOrder($listing, '2');

        $this->app->make(OrderService::class)->confirm($order->fresh(), $this->makeUser('admin'));

        $hold = StorefrontInventoryHold::where('order_id', $order->id)->first();

        $this->assertSame(StorefrontInventoryHold::STATUS_CONSUMED, $hold->status);
        $this->assertSame('3.0000', (string) $listing->fresh()->quantity_available);

        // The units must not be subtracted twice — once by the column and once
        // by a hold left live.
        $this->assertSame('3.0000', $this->app->make(HoldService::class)->availableToSell($listing->fresh()));
    }

    public function test_cancelling_releases_the_hold(): void
    {
        $listing = $this->sellable(['quantity_available' => '1']);
        $order   = $this->placeOrder($listing, '1');

        $this->app->make(OrderService::class)->cancel($order->fresh(), $this->makeUser('admin'), 'Changed mind');

        $this->assertSame(
            StorefrontInventoryHold::STATUS_RELEASED,
            StorefrontInventoryHold::where('order_id', $order->id)->first()->status,
        );
        $this->assertSame('1.0000', $this->app->make(HoldService::class)->availableToSell($listing->fresh()));
    }

    /**
     * Cancelling a confirmed order reverses the stock through
     * `applyInventoryEffect`. If cancellation ALSO turned the consumed hold back
     * into a released one, the unit would be handed back twice.
     */
    public function test_cancelling_a_confirmed_order_does_not_release_its_consumed_hold(): void
    {
        $listing = $this->sellable(['quantity_available' => '5']);
        $order   = $this->placeOrder($listing, '2');
        $staff   = $this->makeUser('admin');

        $this->app->make(OrderService::class)->confirm($order->fresh(), $staff);
        $this->app->make(OrderService::class)->cancel($order->fresh(), $staff, 'Returned');

        $this->assertSame(
            StorefrontInventoryHold::STATUS_CONSUMED,
            StorefrontInventoryHold::where('order_id', $order->id)->first()->status,
            'A consumed hold must stay consumed; releasing it would give back stock twice.',
        );
        $this->assertSame('5.0000', (string) $listing->fresh()->quantity_available);
        $this->assertSame('5.0000', $this->app->make(HoldService::class)->availableToSell($listing->fresh()));
    }

    public function test_a_failed_payment_releases_the_hold(): void
    {
        $listing = $this->sellable(['quantity_available' => '1']);
        $order   = $this->placeOrder($listing, '1', method: 'paystack');

        $this->app->make(OrderService::class)->markFailed($order->fresh(), 'Gateway declined');

        $this->assertSame(
            StorefrontInventoryHold::STATUS_RELEASED,
            StorefrontInventoryHold::where('order_id', $order->id)->first()->status,
        );
        $this->assertSame('1.0000', $this->app->make(HoldService::class)->availableToSell($listing->fresh()));
    }

    /* ---------------------------------------------------------------- */
    /* Expiry                                                           */
    /* ---------------------------------------------------------------- */

    /**
     * §37 puts the clock on payment, not on people. A cash order is waiting for
     * a member of staff and its hold "remains until staff decision" — expiring
     * those would fail routine COD orders because the farm answered the phone
     * slowly.
     */
    public function test_only_orders_awaiting_payment_are_on_a_clock(): void
    {
        $paystackOrder = $this->placeOrder($this->sellable(['quantity_available' => '3']), '1', method: 'paystack');
        $codOrder      = $this->placeOrder($this->sellable(['quantity_available' => '3']), '1', method: 'cod');

        $this->assertNotNull(
            StorefrontInventoryHold::where('order_id', $paystackOrder->id)->first()->expires_at,
        );
        $this->assertNull(
            StorefrontInventoryHold::where('order_id', $codOrder->id)->first()->expires_at,
            'A hold waiting on a person must not be on a clock.',
        );
    }

    public function test_the_hold_window_comes_from_the_storefront_settings(): void
    {
        StorefrontSetting::create([
            'organization_id'       => $this->org->id,
            'checkout_hold_minutes' => 45,
        ]);

        $order = $this->placeOrder($this->sellable(['quantity_available' => '3']), '1', method: 'paystack');

        $expiresAt = StorefrontInventoryHold::where('order_id', $order->id)->first()->expires_at;

        $this->assertEqualsWithDelta(45 * 60, now()->diffInSeconds($expiresAt), 30);
    }

    public function test_an_expired_hold_returns_the_stock_to_the_shop(): void
    {
        $listing = $this->sellable(['quantity_available' => '1']);
        $order   = $this->placeOrder($listing, '1', method: 'paystack');

        $this->assertSame('0.0000', $this->app->make(HoldService::class)->availableToSell($listing->fresh()));

        StorefrontInventoryHold::where('order_id', $order->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->artisan('storefront:expire-holds')->assertSuccessful();

        $this->assertSame(
            StorefrontInventoryHold::STATUS_EXPIRED,
            StorefrontInventoryHold::where('order_id', $order->id)->first()->status,
        );
        $this->assertSame('1.0000', $this->app->make(HoldService::class)->availableToSell($listing->fresh()));
    }

    /**
     * The sweep must not touch the order. An unpaid order whose hold lapsed is
     * still a real enquiry — the buyer may ring, or their bank may be slow.
     */
    public function test_expiry_does_not_cancel_the_order(): void
    {
        $listing = $this->sellable(['quantity_available' => '1']);
        $order   = $this->placeOrder($listing, '1', method: 'paystack');

        StorefrontInventoryHold::where('order_id', $order->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->artisan('storefront:expire-holds')->assertSuccessful();

        $this->assertSame(StorefrontOrder::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        $this->assertTrue(
            $order->fresh()->events()->where('type', 'inventory')->exists(),
            'Staff must be able to see on the timeline that the hold lapsed.',
        );
    }

    public function test_a_consumed_hold_is_never_expired_by_the_sweep(): void
    {
        // A COD order, because a Paystack order cannot go straight from
        // pending_payment to confirmed — payment is verified first, and that
        // rule is not what this test is about.
        $listing = $this->sellable(['quantity_available' => '5']);
        $order   = $this->placeOrder($listing, '1');

        $this->app->make(OrderService::class)->confirm($order->fresh(), $this->makeUser('admin'));

        // Back-date it as if the sweep had raced the confirmation.
        StorefrontInventoryHold::where('order_id', $order->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->artisan('storefront:expire-holds')->assertSuccessful();

        $this->assertSame(
            StorefrontInventoryHold::STATUS_CONSUMED,
            StorefrontInventoryHold::where('order_id', $order->id)->first()->status,
        );
    }

    /* ---------------------------------------------------------------- */
    /* Shape                                                            */
    /* ---------------------------------------------------------------- */

    /**
     * An order may legitimately carry two lines for one listing. Locking it
     * twice while applying each line separately would place two claims against
     * a single lock.
     */
    public function test_two_lines_for_one_listing_produce_one_aggregated_hold(): void
    {
        $listing = $this->sellable(['quantity_available' => '5']);

        $order = $this->placeOrder($listing, '1');

        \App\Models\Storefront\StorefrontOrderItem::create([
            'organization_id' => $this->org->id,
            'order_id'        => $order->id,
            'listing_id'      => $listing->id,
            'kind'            => $listing->kind,
            'description'     => $listing->title,
            'quantity'        => '2',
            'price_basis'     => $listing->pricing_basis,
            'unit_price'      => '250.00',
            'line_total'      => '500.00',
        ]);

        $this->app->make(HoldService::class)->placeForOrder($order->fresh());

        $holds = StorefrontInventoryHold::where('order_id', $order->id)->get();

        $this->assertCount(1, $holds, 'One hold row per listing per order.');
        $this->assertSame('3.0000', $holds->first()->quantityString());
    }

    public function test_replacing_holds_for_the_same_order_does_not_double_claim(): void
    {
        $listing = $this->sellable(['quantity_available' => '2']);
        $order   = $this->placeOrder($listing, '2');

        // A retried checkout for the same order. Without the unique key and the
        // excludingOrderId in the availability read, this would see its own hold
        // as a competitor and refuse.
        $this->app->make(HoldService::class)->placeForOrder($order->fresh());

        $this->assertSame(1, StorefrontInventoryHold::where('order_id', $order->id)->count());
        $this->assertSame('0.0000', $this->app->make(HoldService::class)->availableToSell($listing->fresh()));
    }

    public function test_holds_from_other_orders_do_not_leak_across_listings(): void
    {
        $a = $this->sellable(['quantity_available' => '1']);
        $b = $this->sellable(['quantity_available' => '1']);

        $this->placeOrder($a, '1');

        $holds = $this->app->make(HoldService::class);

        $this->assertSame('0.0000', $holds->availableToSell($a->fresh()));
        $this->assertSame('1.0000', $holds->availableToSell($b->fresh()));
    }
}
