<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderEvent;
use App\Models\Storefront\StorefrontOrderItem;
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

    /**
     * REWRITTEN IN PHASE 9. This test previously asserted that the cart and
     * checkout redirect a guest to /login — the behaviour blueprint §17 calls
     * out as wrong ("guest cart/checkout" is *essential* storefront scope) and
     * that Phase 9 exists to reverse. It was encoding the defect, so it now
     * asserts the intended behaviour instead of being deleted.
     */
    public function test_a_guest_can_reach_the_cart_and_checkout(): void
    {
        $listing = $this->makePublishedListing([
            'kind' => StorefrontListing::KIND_FEED,
            'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price' => '250.00',
            'quantity_available' => '5',
        ]);

        $this->get(route('storefront.cart.index'))->assertOk();

        $this->post(route('storefront.cart.add', $listing), ['quantity' => '1'])
            ->assertRedirect(route('storefront.cart.index'));

        // Checkout with nothing in the basket goes back to the basket rather
        // than to /login — the guest is not blocked, they simply have nothing
        // to buy yet. The full guest journey, which needs the cart cookie
        // carried between requests, is in GuestCheckoutTest.
        $this->get(route('storefront.checkout.show'))
            ->assertRedirect(route('storefront.cart.index'));
    }

    public function test_customer_can_add_listing_to_cart(): void
    {
        $this->seedStorefrontPermissions();

        $listing = $this->makePublishedListing([
            'kind' => StorefrontListing::KIND_FEED,
            'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price' => '250.00',
            'quantity_available' => '5',
        ]);

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.cart.add', $listing), ['quantity' => '1'])
            ->assertRedirect(route('storefront.cart.index'))
            ->assertSessionHas('success', 'Added to cart.');
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

    public function test_salesperson_can_view_admin_order_detail(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();

        $order = StorefrontOrder::create([
            'organization_id' => $this->org->id,
            'reference' => 'SO-000123',
            'status' => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status' => StorefrontOrder::PAY_COD,
            'payment_method' => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '250.00',
            'total' => '250.00',
            'currency_code' => 'GHS',
            'placed_at' => now(),
        ]);

        StorefrontOrderItem::create([
            'organization_id' => $this->org->id,
            'order_id' => $order->id,
            'kind' => StorefrontListing::KIND_FEED,
            'description' => 'Feed bag',
            'quantity' => '1',
            'quantity_unit' => 'bag',
            'price_basis' => StorefrontListing::BASIS_FIXED,
            'unit_price' => '250.00',
            'line_total' => '250.00',
        ]);

        $this->actingAs($this->makeUser('salesperson'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('SO-000123')
            ->assertSee('Feed bag');
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

    public function test_refund_processed_webhook_marks_pending_paystack_refund_settled(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);

        [$order, $payment] = $this->pendingPaystackRefundOrder();

        $body = json_encode([
            'event' => 'refund.processed',
            'data' => [
                'reference' => 'rf_settled_123',
                'status' => 'processed',
                'amount' => 25000,
                'transaction' => ['reference' => $payment->provider_reference],
            ],
        ]);

        $this->call(
            'POST',
            route('storefront.paystack.webhook'),
            [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_secret'), 'CONTENT_TYPE' => 'application/json'],
            $body
        )->assertOk();

        $order = $order->fresh();
        $this->assertSame(StorefrontOrder::STATUS_REFUNDED, $order->status);
        $this->assertSame(StorefrontOrder::PAY_REFUNDED, $order->payment_status);
        $this->assertSame('processed', $order->refund_provider_status);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame(StorefrontOrderPayment::STATUS_REFUNDED, $payment->fresh()->status);

        $this->assertDatabaseHas('storefront_order_events', [
            'order_id' => $order->id,
            'type' => StorefrontOrderEvent::TYPE_PAYMENT,
            'to_status' => StorefrontOrder::STATUS_REFUNDED,
        ]);
    }

    public function test_refund_failed_webhook_keeps_order_pending_for_triage(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);

        [$order] = $this->pendingPaystackRefundOrder();

        $body = json_encode([
            'event' => 'refund.failed',
            'data' => [
                'reference' => 'rf_settled_123',
                'status' => 'failed',
            ],
        ]);

        $this->call(
            'POST',
            route('storefront.paystack.webhook'),
            [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_secret'), 'CONTENT_TYPE' => 'application/json'],
            $body
        )->assertOk();

        $order = $order->fresh();
        $this->assertSame(StorefrontOrder::STATUS_REFUND_APPROVED, $order->status);
        $this->assertSame(StorefrontOrder::PAY_REFUND_PENDING, $order->payment_status);
        $this->assertSame('failed', $order->refund_provider_status);
        $this->assertNull($order->refunded_at);
        $this->assertSame('Paystack reported refund failure.', $order->refund_failure_reason);
    }

    /**
     * @return array{0:StorefrontOrder, 1:StorefrontOrderPayment}
     */
    protected function pendingPaystackRefundOrder(): array
    {
        $order = StorefrontOrder::create([
            'organization_id' => $this->org->id,
            'reference' => 'SO-REF-' . uniqid(),
            'status' => StorefrontOrder::STATUS_REFUND_APPROVED,
            'payment_status' => StorefrontOrder::PAY_REFUND_PENDING,
            'payment_method' => StorefrontOrder::METHOD_PAYSTACK,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '250.00',
            'total' => '250.00',
            'currency_code' => 'GHS',
            'placed_at' => now(),
            'refund_provider_reference' => 'rf_settled_123',
            'refund_provider_status' => 'pending',
            'refund_submitted_at' => now(),
            'refund_amount_minor' => 25000,
        ]);

        $payment = StorefrontOrderPayment::create([
            'organization_id' => $this->org->id,
            'order_id' => $order->id,
            'provider' => StorefrontOrderPayment::PROVIDER_PAYSTACK,
            'reference' => 'PSK-' . $order->reference,
            'provider_reference' => 'PSK-' . $order->reference,
            'amount' => '250.00',
            'currency_code' => 'GHS',
            'status' => StorefrontOrderPayment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        return [$order, $payment];
    }
}
