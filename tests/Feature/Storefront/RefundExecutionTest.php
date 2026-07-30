<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderPayment;
use App\Storefront\Payments\PaystackClient;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\PaymentException;
use App\Storefront\Services\OrderService;
use App\Storefront\Services\StorefrontOrderRefundService;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Storefront\Support\FakePaystackClient;

/**
 * Refund execution (CF-P0-007) and customer-safe gateway errors (CF-P1-020).
 *
 * The defect: StorefrontOrderRefundService set status = 'refunded' and
 * payment_status = 'refunded' unconditionally. It never called Paystack — the
 * abstraction had no refund method to call. A customer could be shown
 * "Refunded" while the money never left the merchant, and nothing in the data
 * distinguished a completed refund from one that had never been attempted.
 *
 * The property under test is therefore not "a refund happens" but "the
 * customer-visible state never runs ahead of the money".
 */
class RefundExecutionTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected FakePaystackClient $paystack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paystack = new FakePaystackClient();
        $this->app->instance(PaystackClient::class, $this->paystack);
    }

    /**
     * Build a confirmed order that was genuinely paid through the gateway, so
     * there is money to reclaim.
     *
     * @return array{0:StorefrontOrder, 1:StorefrontListing}
     */
    protected function paidPaystackOrder(): array
    {
        $buyer   = $this->makeUser('customer');
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '100.00',
            'quantity_available' => '10',
        ]);

        $this->app->make(CartService::class)->addItem($buyer, $listing, '1');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup']),
        );

        // Attach a verified Paystack payment. Done directly so the test targets
        // the refund path rather than re-testing checkout.
        StorefrontOrderPayment::create([
            'organization_id'    => $order->organization_id,
            'order_id'           => $order->id,
            'provider'           => StorefrontOrderPayment::PROVIDER_PAYSTACK,
            'reference'          => 'PAY-' . $order->reference,
            'provider_reference' => 'ps_ref_' . $order->id,
            'amount'             => '100.00',
            'currency_code'      => 'GHS',
            'status'             => StorefrontOrderPayment::STATUS_VERIFIED,
            'verified_at'        => now(),
        ]);

        $this->app->make(OrderService::class)->confirm($order, $this->makeUser('admin'));

        return [$order->fresh(), $listing];
    }

    public function test_refund_actually_calls_the_payment_gateway(): void
    {
        [$order] = $this->paidPaystackOrder();

        $this->app->make(StorefrontOrderRefundService::class)
            ->refund($order, $this->makeUser('admin'), 'customer returned goods');

        $this->assertCount(
            1,
            $this->paystack->refunds,
            'No refund was submitted to the gateway. The order was marked refunded '
            . 'without any money being returned — the CF-P0-007 defect.',
        );

        $this->assertSame(
            'ps_ref_' . $order->id,
            $this->paystack->refunds[0]['transactionReference'],
            'The refund must target the original transaction.',
        );
    }

    /**
     * The central property. Paystack settles refunds asynchronously and returns
     * 'pending'; the order must NOT claim to be refunded yet.
     */
    public function test_pending_provider_refund_does_not_mark_the_order_refunded(): void
    {
        [$order] = $this->paidPaystackOrder();

        $this->paystack->refundResult = ['status' => 'pending'];

        $result = $this->app->make(StorefrontOrderRefundService::class)
            ->refund($order, $this->makeUser('admin'), 'customer changed mind');

        $this->assertNotSame(
            StorefrontOrder::STATUS_REFUNDED,
            $result->status,
            'The order claims to be refunded while the provider has only accepted the '
            . 'request. The customer does not have their money yet.',
        );

        $this->assertSame(StorefrontOrder::PAY_REFUND_PENDING, $result->payment_status);
        $this->assertSame('pending', $result->refund_provider_status);
        $this->assertNotNull($result->refund_submitted_at, 'Submission time must be recorded.');
        $this->assertNull(
            $result->refunded_at,
            'refunded_at must stay null until the money has actually settled.',
        );
    }

    public function test_settled_provider_refund_marks_the_order_refunded(): void
    {
        [$order] = $this->paidPaystackOrder();

        $this->paystack->refundResult = ['status' => 'processed'];

        $result = $this->app->make(StorefrontOrderRefundService::class)
            ->refund($order, $this->makeUser('admin'), 'goods returned');

        $this->assertSame(StorefrontOrder::STATUS_REFUNDED, $result->status);
        $this->assertSame(StorefrontOrder::PAY_REFUNDED, $result->payment_status);
        $this->assertNotNull($result->refunded_at);
    }

    /**
     * If the gateway rejects the submission, nothing may move.
     */
    public function test_failed_provider_submission_leaves_the_order_unrefunded(): void
    {
        [$order] = $this->paidPaystackOrder();

        $statusBefore = $order->status;

        $this->paystack->refundException = new PaymentException(
            'The refund could not be submitted to our payment provider.',
            ['http_status' => 503],
        );

        $result = $this->app->make(StorefrontOrderRefundService::class)
            ->refund($order, $this->makeUser('admin'), 'attempted refund');

        $this->assertSame(
            $statusBefore,
            $result->status,
            'A failed gateway submission must leave the order exactly where it was.',
        );

        $this->assertNotSame(StorefrontOrder::PAY_REFUNDED, $result->payment_status);
        $this->assertNull($result->refunded_at);
        $this->assertSame('submission_failed', $result->refund_provider_status);
        $this->assertNotNull(
            $result->refund_failure_reason,
            'The failure must be recorded so staff can see it and retry.',
        );
    }

    /**
     * A retry must reuse the same idempotency key, or the provider treats it as
     * a second refund and the business pays twice.
     */
    public function test_retried_refund_reuses_the_same_idempotency_key(): void
    {
        [$order] = $this->paidPaystackOrder();
        $staff   = $this->makeUser('admin');

        $this->paystack->refundException = new PaymentException('temporary failure');
        $this->app->make(StorefrontOrderRefundService::class)->refund($order, $staff, 'first attempt');

        $firstKey = $this->paystack->refunds[0]['idempotencyKey'];
        $this->assertNotNull($firstKey, 'A refund submission must carry an idempotency key.');

        // Retry after the transient failure clears.
        $this->paystack->refundException = null;
        $this->paystack->refundResult    = ['status' => 'pending'];
        $this->app->make(StorefrontOrderRefundService::class)->refund($order->fresh(), $staff, 'retry');

        $this->assertSame(
            $firstKey,
            $this->paystack->refunds[1]['idempotencyKey'],
            'The retry used a different idempotency key, so the provider would treat it '
            . 'as a second refund. That is real money leaving the business twice.',
        );
    }

    /**
     * Cash on delivery has no gateway leg, so there is nothing to submit and the
     * refund settles immediately. This guards against the fix over-correcting
     * and leaving COD refunds stuck pending forever.
     */
    public function test_cash_on_delivery_refund_needs_no_gateway_call(): void
    {
        $buyer   = $this->makeUser('customer');
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '50.00',
            'quantity_available' => '5',
        ]);

        $this->app->make(CartService::class)->addItem($buyer, $listing, '1');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup']),
        );

        $staff = $this->makeUser('admin');
        $this->app->make(OrderService::class)->confirm($order, $staff);

        $result = $this->app->make(StorefrontOrderRefundService::class)
            ->refund($order->fresh(), $staff, 'cod refund');

        $this->assertCount(0, $this->paystack->refunds, 'COD has no gateway payment to reclaim.');
        $this->assertSame(StorefrontOrder::STATUS_REFUNDED, $result->status);
        $this->assertNotNull($result->refunded_at);
    }

    /**
     * CF-P1-020: gateway internals must not reach the customer.
     */
    public function test_gateway_error_message_is_customer_safe(): void
    {
        $exception = new PaymentException(
            'We could not verify your payment.',
            ['body' => '{"status":false,"message":"Invalid key sk_test_abc123"}'],
        );

        $this->assertStringNotContainsString('sk_test_abc123', $exception->customerMessage());
        $this->assertStringNotContainsString('{"status"', $exception->customerMessage());
        $this->assertStringContainsString(
            $exception->correlationId(),
            $exception->customerMessage(),
            'The customer message must carry a correlation id so support can find the logged detail.',
        );
    }
}
