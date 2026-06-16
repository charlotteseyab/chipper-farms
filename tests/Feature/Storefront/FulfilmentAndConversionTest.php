<?php

namespace Tests\Feature\Storefront;

use App\Finance\Subscribers\Sales\StorefrontOrderConfirmedSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontQuote;
use App\Models\Storefront\StorefrontQuoteItem;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\OrderService;
use App\Storefront\Services\QuoteService;
use App\Storefront\Support\CheckoutData;
use App\Storefront\Support\QuoteRequestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class FulfilmentAndConversionTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function runOrchestrator(StorefrontOrder $order): void
    {
        $event = DomainEvent::where('event_type', 'storefront_order.confirmed')
            ->where('source_id', $order->id)->firstOrFail();
        $this->app->make(StorefrontOrderConfirmedSubscriber::class)->handle($event->id);
    }

    protected function placeConfirmRunFeed(string $method = 'cod'): StorefrontOrder
    {
        $customer = $this->makeUser('customer');
        $feedType = FeedType::create(['name' => 'Grower']);
        $listing = $this->makePublishedListing([
            'kind' => StorefrontListing::KIND_FEED, 'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price' => '12.00', 'quantity_available' => '100', 'quantity_unit' => 'kg',
            'source_type' => FeedType::class, 'source_id' => $feedType->id,
        ]);
        $this->app->make(CartService::class)->addItem($customer, $listing, '10');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $customer, CheckoutData::fromArray(['payment_method' => $method, 'fulfilment_method' => 'pickup'])
        );
        $this->app->make(OrderService::class)->confirm($order->fresh(), $this->makeUser('salesperson'));
        $this->runOrchestrator($order->fresh());
        return $order->fresh();
    }

    public function test_completion_settles_cod_feed_and_posts_receipt_event(): void
    {
        $order = $this->placeConfirmRunFeed('cod');
        $svc = $this->app->make(OrderService::class);
        $staff = $this->makeUser('salesperson');

        $svc->markReadyOrDispatched($order->fresh(), $staff);
        $svc->complete($order->fresh(), $staff);

        $order->refresh();
        $this->assertSame(StorefrontOrder::STATUS_COMPLETED, $order->status);
        $this->assertSame(StorefrontOrder::PAY_PAID, $order->payment_status);
        $this->assertSame('paid', FeedSale::first()->payment_status);
        // The feed payment spine fired (the gap Phase 5 closed).
        $this->assertTrue(
            DomainEvent::where('event_type', 'feed_sale.payment_status_changed')->exists(),
            'A feed_sale.payment_status_changed event should have been dispatched on COD settlement.'
        );
    }

    public function test_completion_marks_pig_listing_sold_out_and_settles(): void
    {
        $customer = $this->makeUser('customer');
        $pig = $this->makePig();
        $listing = $this->makePublishedListing([
            'kind' => StorefrontListing::KIND_LIVE_PIG, 'pricing_basis' => StorefrontListing::BASIS_LIVE,
            'price_per_kg' => '40.00', 'estimated_weight_kg' => '70', 'quantity_available' => '1',
            'source_type' => PigManagement::class, 'source_id' => $pig->id,
        ]);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $customer, CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup'])
        );
        $svc = $this->app->make(OrderService::class);
        $staff = $this->makeUser('salesperson');

        $svc->confirm($order->fresh(), $staff);
        $this->runOrchestrator($order->fresh());
        $svc->markReadyOrDispatched($order->fresh(), $staff);
        $svc->complete($order->fresh(), $staff);

        $this->assertSame(StorefrontListing::STATUS_SOLD_OUT, $listing->fresh()->status);
        $this->assertSame('paid', PigSale::first()->payment_status);
        $this->assertSame(StorefrontOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_paystack_order_completes_without_resettling(): void
    {
        $order = $this->placeConfirmRunFeed('cod'); // start COD then simulate already-paid
        // Force the underlying sale + order to a paid (paystack-like) state.
        FeedSale::first()->update(['payment_status' => 'paid']);
        $order->update(['payment_method' => StorefrontOrder::METHOD_PAYSTACK, 'payment_status' => StorefrontOrder::PAY_PAID]);

        $svc = $this->app->make(OrderService::class);
        $staff = $this->makeUser('salesperson');
        $svc->markReadyOrDispatched($order->fresh(), $staff);
        $svc->complete($order->fresh(), $staff);

        // No new payment-status-changed event because nothing needed settling.
        $this->assertFalse(DomainEvent::where('event_type', 'feed_sale.payment_status_changed')->exists());
        $this->assertSame(StorefrontOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    // --- Quote conversion ---

    protected function acceptedQuote(): StorefrontQuote
    {
        Notification::fake();
        $svc = $this->app->make(QuoteService::class);
        $actor = $this->makeUser('salesperson');

        $quote = $svc->request(QuoteRequestData::fromArray([
            'contact_name' => 'Bulk Buyer', 'contact_email' => 'b@example.com', 'source' => 'bulk',
            'items' => [
                ['description' => '20 weaners', 'quantity' => '20', 'quantity_unit' => 'head'],
                ['description' => '5 bags feed', 'quantity' => '5', 'quantity_unit' => 'bag'],
            ],
        ]), null);

        $quote = $svc->markReviewing($quote, $actor);
        $quote = $svc->provideQuote($quote, $actor, '5000.00', now()->addDays(7)->toDateString());
        return $svc->accept($quote, $actor);
    }

    public function test_accepted_quote_converts_to_order_linked_both_ways(): void
    {
        $quote = $this->acceptedQuote();
        $order = $this->app->make(QuoteService::class)->convertToOrder($quote, $this->makeUser('salesperson'));

        $this->assertSame(StorefrontOrder::STATUS_PENDING_CONFIRMATION, $order->status);
        $this->assertSame($quote->id, $order->quote_id);
        $this->assertEquals(5000.00, (float) $order->total);
        $this->assertCount(2, $order->items);

        $quote->refresh();
        $this->assertSame(StorefrontQuote::STATUS_CONVERTED, $quote->status);
        $this->assertSame($order->id, $quote->converted_order_id);
    }

    public function test_quote_conversion_is_idempotent(): void
    {
        $quote = $this->acceptedQuote();
        $svc = $this->app->make(QuoteService::class);

        $order1 = $svc->convertToOrder($quote, $this->makeUser('salesperson'));
        $order2 = $svc->convertToOrder($quote->fresh(), $this->makeUser('salesperson'));

        $this->assertSame($order1->id, $order2->id);
        $this->assertSame(1, StorefrontOrder::count());
    }

    public function test_unaccepted_quote_cannot_convert(): void
    {
        Notification::fake();
        $svc = $this->app->make(QuoteService::class);
        $quote = $svc->request(QuoteRequestData::fromArray([
            'contact_name' => 'X', 'contact_email' => 'x@example.com',
            'items' => [['description' => 'a', 'quantity' => '1']],
        ]), null);

        $this->expectException(\App\Storefront\Services\Exceptions\InvalidQuoteTransitionException::class);
        $svc->convertToOrder($quote, $this->makeUser('salesperson'));
    }
}
