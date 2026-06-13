<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderPayment;
use App\Storefront\Payments\PaystackClient;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\PaystackService;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Storefront\Support\FakePaystackClient;

class OrderHttpTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_phase3_tables_exist(): void
    {
        foreach ([
            'storefront_carts', 'storefront_cart_items', 'storefront_orders',
            'storefront_order_items', 'storefront_order_payments', 'storefront_order_events',
        ] as $t) {
            $this->assertTrue(Schema::hasTable($t), "Missing table: {$t}");
        }
    }

    public function test_cart_and_checkout_require_auth(): void
    {
        $listing = $this->makePublishedListing();

        $this->get(route('storefront.account.cart.index'))->assertRedirect(route('login'));
        $this->post(route('storefront.account.cart.add', $listing))->assertRedirect(route('login'));
        $this->get(route('storefront.account.checkout.show'))->assertRedirect(route('login'));
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $this->seedStorefrontPermissions();

        $owner = $this->makeUser('customer');
        $order = StorefrontOrder::create([
            'organization_id' => $this->org->id,
            'reference'       => 'SO-000999',
            'customer_user_id' => $owner->id,
            'status'          => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'  => StorefrontOrder::PAY_COD,
            'total'           => '10.00',
        ]);

        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.account.orders.show', $order))
            ->assertForbidden();
    }

    public function test_customer_cannot_view_admin_orders(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();

        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.admin.orders.index'))
            ->assertForbidden();
    }

    public function test_salesperson_can_view_admin_orders(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();

        $this->actingAs($this->makeUser('salesperson'))
            ->get(route('storefront.admin.orders.index'))
            ->assertOk();
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);

        $this->call(
            'POST',
            route('storefront.paystack.webhook'),
            [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => 'deadbeef', 'CONTENT_TYPE' => 'application/json'],
            json_encode(['event' => 'charge.success', 'data' => ['reference' => 'X']])
        )->assertStatus(401);
    }

    public function test_webhook_accepts_valid_signature_and_verifies(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);

        $fake = new FakePaystackClient();
        $this->app->instance(PaystackClient::class, $fake);

        // Place a Paystack order + initialise so a payment row exists.
        $user    = $this->makeUser('customer');
        $listing = $this->makePublishedListing([
            'kind' => StorefrontListing::KIND_FEED, 'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price' => '250.00', 'quantity_available' => '5',
        ]);
        $this->app->make(CartService::class)->addItem($user, $listing, '1');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $user, CheckoutData::fromArray(['payment_method' => 'paystack', 'fulfilment_method' => 'pickup'])
        );
        $init = $this->app->make(PaystackService::class)->initialise($order, 'https://app.test/cb');
        $reference = $init['reference'];

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]);
        $signature = hash_hmac('sha512', $body, 'sk_test_secret');

        $this->call(
            'POST',
            route('storefront.paystack.webhook'),
            [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body
        )->assertOk();

        $this->assertSame(StorefrontOrderPayment::STATUS_VERIFIED, StorefrontOrderPayment::first()->status);
        $this->assertSame(StorefrontOrder::STATUS_PAYMENT_CONFIRMED, $order->fresh()->status);
    }
}
