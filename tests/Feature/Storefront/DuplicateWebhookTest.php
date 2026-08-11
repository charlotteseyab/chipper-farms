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
use Tests\Feature\Storefront\Support\FakePaystackClient;

/**
 * Phase 17 (§60) — duplicate Paystack webhook delivery.
 *
 * §60 names this under Concurrency and nothing tested it. The signature check,
 * the refund events and the happy path were all covered; re-delivery was not.
 *
 * This is not a hypothetical. Paystack retries a webhook until it gets a 2xx,
 * and it will re-deliver on its own schedule besides — so the same
 * `charge.success` for the same reference arrives more than once as a matter of
 * routine, not as an edge case.
 *
 * WHAT MUST NOT HAPPEN
 * --------------------
 * A second delivery must not verify the payment twice, advance the order twice,
 * or write a second timeline entry that makes the audit trail claim the customer
 * paid on two occasions.
 *
 * This file covers sequential re-delivery, which is the common case. True
 * simultaneous delivery is `Tests\Concurrency\DuplicateWebhookConcurrencyTest`,
 * because in-process tests cannot show the interleaving that matters there.
 */
class DuplicateWebhookTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();

        config(['services.paystack.secret_key' => 'sk_test_duplicate']);

        $this->app->instance(PaystackClient::class, new FakePaystackClient());
    }

    /**
     * A real Paystack order, placed the way the application places one.
     *
     * Built through checkout and `initialise()` rather than by hand, so the
     * reference, the amount and the payment row are the ones the application
     * actually produces — a hand-built fixture could satisfy the anti-tamper
     * amount check by construction and prove nothing.
     *
     * @return array{0: StorefrontOrder, 1: string}
     */
    protected function payingOrder(): array
    {
        $buyer   = $this->makeUser('customer');
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '150.00',
            'quantity_available' => '5',
        ]);

        $this->app->make(CartService::class)->addItem($buyer, $listing, '1');

        $order = $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'paystack', 'fulfilment_method' => 'pickup']),
        );

        $init = $this->app->make(PaystackService::class)->initialise($order, 'https://app.test/cb');

        return [$order, $init['reference']];
    }

    protected function deliver(string $reference): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]);

        return $this->call(
            'POST',
            route('storefront.paystack.webhook'),
            [], [], [],
            [
                'HTTP_X-PAYSTACK-SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_duplicate'),
                'CONTENT_TYPE'              => 'application/json',
            ],
            $body,
        );
    }

    public function test_a_re_delivered_webhook_leaves_one_verified_payment(): void
    {
        [$order, $reference] = $this->payingOrder();

        $this->deliver($reference)->assertOk();
        $this->deliver($reference)->assertOk();
        $this->deliver($reference)->assertOk();

        $this->assertSame(
            1,
            StorefrontOrderPayment::where('order_id', $order->id)
                ->where('status', StorefrontOrderPayment::STATUS_VERIFIED)
                ->count(),
            'Re-delivery produced more than one verified payment for the same charge.',
        );

        $this->assertSame(
            StorefrontOrder::PAY_PAYSTACK_VERIF,
            $order->fresh()->payment_status,
        );
    }

    /**
     * The audit trail must not claim the customer paid three times.
     */
    public function test_re_delivery_writes_one_payment_event_not_three(): void
    {
        [$order, $reference] = $this->payingOrder();

        $this->deliver($reference)->assertOk();
        $this->deliver($reference)->assertOk();
        $this->deliver($reference)->assertOk();

        $this->assertSame(
            1,
            $order->events()->where('type', 'payment')->count(),
            'Each re-delivery wrote another payment entry, so the order history claims '
            . 'the customer paid more than once.',
        );
    }

    /**
     * The order advances once and stays where it was put.
     */
    public function test_the_order_advances_once(): void
    {
        [$order, $reference] = $this->payingOrder();

        $this->deliver($reference)->assertOk();
        $first = $order->fresh()->status;

        $this->deliver($reference)->assertOk();

        $this->assertSame(StorefrontOrder::STATUS_PAYMENT_CONFIRMED, $first);
        $this->assertSame($first, $order->fresh()->status);
    }

    /**
     * A staff member confirming the order between two deliveries must not have
     * their work undone by the second one.
     */
    public function test_a_late_delivery_does_not_reverse_a_staff_confirmation(): void
    {
        [$order, $reference] = $this->payingOrder();

        $this->deliver($reference)->assertOk();

        $order->forceFill(['status' => StorefrontOrder::STATUS_CONFIRMED])->save();

        $this->deliver($reference)->assertOk();

        $this->assertSame(
            StorefrontOrder::STATUS_CONFIRMED,
            $order->fresh()->status,
            'A re-delivered webhook pushed a confirmed order back to payment_confirmed.',
        );
    }

    /**
     * A webhook for a reference this farm has never issued is ignored quietly.
     *
     * 200 rather than an error: Paystack retries anything that is not 2xx, and
     * a reference we do not recognise will never start being recognised.
     */
    public function test_an_unknown_reference_is_answered_and_ignored(): void
    {
        $this->deliver('PSK-NEVER-ISSUED-9999')->assertOk();

        $this->assertSame(0, StorefrontOrderPayment::count());
    }
}
