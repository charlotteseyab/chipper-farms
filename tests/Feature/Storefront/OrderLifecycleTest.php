<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderPayment;
use App\Storefront\Payments\PaystackClient;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\InvalidOrderTransitionException;
use App\Storefront\Services\Exceptions\PaymentException;
use App\Storefront\Services\OrderService;
use App\Storefront\Services\PaystackService;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Storefront\Support\FakePaystackClient;

class OrderLifecycleTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function placeCodOrder(array $listingOverrides = [], string $qty = '1'): array
    {
        $user    = $this->makeUser('customer');
        $listing = $this->makePublishedListing(array_merge([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '100.00',
            'quantity_available' => '10',
        ], $listingOverrides));

        $this->app->make(CartService::class)->addItem($user, $listing, $qty);
        $order = $this->app->make(CheckoutService::class)->checkout(
            $user,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup'])
        );

        return [$user, $listing, $order];
    }

    public function test_staff_confirm_reserves_a_pig_backed_listing(): void
    {
        $user = $this->makeUser('customer');
        $pig  = $this->makePig();
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '800.00',
            'quantity_available' => '1',
            'source_type'        => PigManagement::class,
            'source_id'          => $pig->id,
        ]);

        $this->app->make(CartService::class)->addItem($user, $listing, '1');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $user, CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup'])
        );

        $staff = $this->makeUser('salesperson');
        $confirmed = $this->app->make(OrderService::class)->confirm($order, $staff);

        $this->assertSame(StorefrontOrder::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame(StorefrontListing::STATUS_RESERVED, $listing->fresh()->status);
    }

    public function test_confirm_decrements_product_quantity(): void
    {
        [$user, $listing, $order] = $this->placeCodOrder(['quantity_available' => '10'], '3');

        $staff = $this->makeUser('salesperson');
        $this->app->make(OrderService::class)->confirm($order, $staff);

        $this->assertSame('7.0000', $listing->fresh()->quantity_available);
    }

    public function test_cancel_after_confirm_releases_reservation(): void
    {
        $user = $this->makeUser('customer');
        $pig  = $this->makePig();
        $listing = $this->makePublishedListing([
            'kind' => StorefrontListing::KIND_LIVE_PIG, 'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price' => '800.00', 'quantity_available' => '1',
            'source_type' => PigManagement::class, 'source_id' => $pig->id,
        ]);
        $this->app->make(CartService::class)->addItem($user, $listing, '1');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $user, CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup'])
        );

        $svc   = $this->app->make(OrderService::class);
        $staff = $this->makeUser('salesperson');
        $svc->confirm($order, $staff);
        $this->assertSame(StorefrontListing::STATUS_RESERVED, $listing->fresh()->status);

        $svc->cancel($order->fresh(), $staff, 'changed mind');
        $this->assertSame(StorefrontOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $listing->fresh()->status);
    }

    public function test_illegal_transition_throws(): void
    {
        [$user, $listing, $order] = $this->placeCodOrder();

        // Cannot mark ready before confirming.
        $this->expectException(InvalidOrderTransitionException::class);
        $this->app->make(OrderService::class)->markReadyOrDispatched($order, $this->makeUser('salesperson'));
    }

    // --- Paystack ---

    protected function bindFake(FakePaystackClient $fake): void
    {
        $this->app->instance(PaystackClient::class, $fake);
    }

    protected function placePaystackOrder(): array
    {
        $user    = $this->makeUser('customer');
        $listing = $this->makePublishedListing([
            'kind' => StorefrontListing::KIND_FEED, 'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price' => '250.00', 'quantity_available' => '5',
        ]);
        $this->app->make(CartService::class)->addItem($user, $listing, '1');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $user, CheckoutData::fromArray(['payment_method' => 'paystack', 'fulfilment_method' => 'pickup'])
        );
        return [$user, $order];
    }

    public function test_paystack_verify_advances_to_payment_confirmed_not_confirmed(): void
    {
        $fake = new FakePaystackClient();
        $this->bindFake($fake);

        [$user, $order] = $this->placePaystackOrder();
        $paystack = $this->app->make(PaystackService::class);

        $init = $paystack->initialise($order, 'https://app.test/callback');
        $reference = $init['reference'];

        $payment = $paystack->verify($reference);

        $this->assertSame(StorefrontOrderPayment::STATUS_VERIFIED, $payment->status);
        $order->refresh();
        // CRITICAL: payment success must NOT auto-confirm the order.
        $this->assertSame(StorefrontOrder::STATUS_PAYMENT_CONFIRMED, $order->status);
        $this->assertNotSame(StorefrontOrder::STATUS_CONFIRMED, $order->status);
        $this->assertSame(StorefrontOrder::PAY_PAYSTACK_VERIF, $order->payment_status);
    }

    public function test_paystack_amount_mismatch_is_rejected(): void
    {
        $fake = new FakePaystackClient();
        $this->bindFake($fake);

        [$user, $order] = $this->placePaystackOrder();
        $paystack = $this->app->make(PaystackService::class);
        $init = $paystack->initialise($order, 'https://app.test/callback');

        // Gateway reports a different (tampered) amount.
        $fake->verifyResult = ['status' => 'success', 'amount' => 100, 'currency' => 'GHS'];

        try {
            $paystack->verify($init['reference']);
            $this->fail('Expected PaymentException for amount mismatch.');
        } catch (PaymentException $e) {
            $this->assertStringContainsString('amount', strtolower($e->getMessage()));
        }

        $order->refresh();
        $this->assertSame(StorefrontOrder::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertSame(StorefrontOrderPayment::STATUS_FAILED, $order->payments()->first()->status);
    }

    public function test_paystack_verify_is_idempotent(): void
    {
        $fake = new FakePaystackClient();
        $this->bindFake($fake);

        [$user, $order] = $this->placePaystackOrder();
        $paystack = $this->app->make(PaystackService::class);
        $init = $paystack->initialise($order, 'https://app.test/callback');

        $paystack->verify($init['reference']);
        $paystack->verify($init['reference']); // second call: no-op

        $order->refresh();
        $this->assertSame(StorefrontOrder::STATUS_PAYMENT_CONFIRMED, $order->status);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_staff_confirm_after_paystack_payment(): void
    {
        $fake = new FakePaystackClient();
        $this->bindFake($fake);

        [$user, $order] = $this->placePaystackOrder();
        $paystack = $this->app->make(PaystackService::class);
        $init = $paystack->initialise($order, 'https://app.test/callback');
        $paystack->verify($init['reference']);

        $staff = $this->makeUser('salesperson');
        $confirmed = $this->app->make(OrderService::class)->confirm($order->fresh(), $staff);

        $this->assertSame(StorefrontOrder::STATUS_CONFIRMED, $confirmed->status);
    }
}
