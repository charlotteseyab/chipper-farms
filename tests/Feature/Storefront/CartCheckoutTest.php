<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontCart;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\CartException;
use App\Storefront\Services\Exceptions\CheckoutException;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CartCheckoutTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function cart(): CartService
    {
        return $this->app->make(CartService::class);
    }

    protected function orderableListing(array $overrides = []): StorefrontListing
    {
        return $this->makePublishedListing(array_merge([
            'kind'               => StorefrontListing::KIND_FEED,
            'sale_mode'          => StorefrontListing::MODE_FIXED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '120.00',
            'quantity_available' => '10',
            'quantity_unit'      => 'bag',
        ], $overrides));
    }

    public function test_add_to_cart_snapshots_price_and_totals(): void
    {
        $user    = $this->makeUser('customer');
        $listing = $this->orderableListing();

        $this->cart()->addItem($user, $listing, '3');

        $cart = $this->cart()->activeFor($user);
        $cart->load('items');

        $this->assertCount(1, $cart->items);
        $this->assertSame('360.00', $cart->subtotal());
    }

    public function test_cannot_add_more_than_available(): void
    {
        $user    = $this->makeUser('customer');
        $listing = $this->orderableListing(['quantity_available' => '2']);

        $this->expectException(CartException::class);
        $this->cart()->addItem($user, $listing, '5');
    }

    public function test_cannot_add_unorderable_listing(): void
    {
        $user    = $this->makeUser('customer');
        $listing = $this->makeListing(['status' => StorefrontListing::STATUS_DRAFT]);

        $this->expectException(CartException::class);
        $this->cart()->addItem($user, $listing, '1');
    }

    public function test_cod_checkout_creates_pending_confirmation_order(): void
    {
        $user    = $this->makeUser('customer');
        $listing = $this->orderableListing();
        $this->cart()->addItem($user, $listing, '2');

        $order = $this->app->make(CheckoutService::class)->checkout(
            $user,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup'])
        );

        $this->assertSame(StorefrontOrder::STATUS_PENDING_CONFIRMATION, $order->status);
        $this->assertSame(StorefrontOrder::PAY_COD, $order->payment_status);
        $this->assertSame('240.00', $order->total);
        $this->assertCount(1, $order->items);
        // The cart used for checkout is now converted; a fresh active cart is empty.
        $this->assertTrue(StorefrontCart::where('status', StorefrontCart::STATUS_CONVERTED)->exists());
        $this->assertSame(0, $this->cart()->itemCount($user));
    }

    public function test_paystack_checkout_creates_pending_payment_order(): void
    {
        $user    = $this->makeUser('customer');
        $listing = $this->orderableListing();
        $this->cart()->addItem($user, $listing, '1');

        $order = $this->app->make(CheckoutService::class)->checkout(
            $user,
            CheckoutData::fromArray(['payment_method' => 'paystack', 'fulfilment_method' => 'pickup'])
        );

        $this->assertSame(StorefrontOrder::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertSame(StorefrontOrder::PAY_PAYSTACK_PEND, $order->payment_status);
    }

    public function test_delivery_checkout_requires_address(): void
    {
        $user    = $this->makeUser('customer');
        $this->cart()->addItem($user, $this->orderableListing(), '1');

        $this->expectException(CheckoutException::class);
        $this->app->make(CheckoutService::class)->checkout(
            $user,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'delivery'])
        );
    }

    public function test_empty_cart_cannot_checkout(): void
    {
        $user = $this->makeUser('customer');

        $this->expectException(CheckoutException::class);
        $this->app->make(CheckoutService::class)->checkout(
            $user,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup'])
        );
    }
}
