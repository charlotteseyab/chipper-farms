<?php

namespace Tests\Feature\Storefront;

use App\Finance\Subscribers\Sales\StorefrontOrderConfirmedSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\Finance\Invoice;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Storefront\Payments\PaystackClient;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\OrderService;
use App\Storefront\Services\PaystackService;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Storefront\Support\FakePaystackClient;

/**
 * Phase 4 — verifies the storefront→finance integration layer: confirmation
 * dispatches the outbox event, and the orchestrator subscriber routes lines
 * into the existing sale spine (feed/pig) with the right channel, agreed price,
 * and settlement state. It does NOT re-test the GL postings themselves (the
 * feed/pig subscribers own that and are covered elsewhere).
 */
class FinanceIntegrationTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->seed(\Database\Seeders\PaymentMethodSeeder::class);

        $year = \App\Models\FiscalYear::create([
            'organization_id' => $this->org->id,
            'fiscal_year'     => 2026,
            'name'            => 'FY2026',
            'start_date'      => '2026-01-01',
            'end_date'        => '2026-12-31',
            'status'          => 'open',
        ]);

        \App\Models\FiscalPeriod::create([
            'organization_id' => $this->org->id,
            'fiscal_year_id'  => $year->id,
            'period_number'   => 8,
            'name'            => 'August 2026',
            'start_date'      => '2026-08-01',
            'end_date'        => '2026-08-31',
            'status'          => \App\Models\FiscalPeriod::STATUS_OPEN,
            'posting_count'   => 0,
        ]);

        \App\Models\FiscalPeriod::create([
            'organization_id' => $this->org->id,
            'fiscal_year_id'  => $year->id,
            'period_number'   => 9,
            'name'            => 'September 2026',
            'start_date'      => '2026-09-01',
            'end_date'        => '2026-09-30',
            'status'          => \App\Models\FiscalPeriod::STATUS_OPEN,
            'posting_count'   => 0,
        ]);
    }

    protected function feedType(): FeedType
    {
        return FeedType::create(['name' => 'Grower Feed']);
    }

    protected function confirmEventFor(StorefrontOrder $order): DomainEvent
    {
        return DomainEvent::where('event_type', 'storefront_order.confirmed')
            ->where('source_id', $order->id)
            ->firstOrFail();
    }

    protected function runFinance(StorefrontOrder $order): void
    {
        $event = $this->confirmEventFor($order);
        $this->app->make(StorefrontOrderConfirmedSubscriber::class)->handle($event->id);
    }

    protected function placeAndConfirmFeedOrder(string $paymentMethod = 'cod'): StorefrontOrder
    {
        $customer = $this->makeUser('customer');
        $feedType = $this->feedType();

        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '12.00',
            'quantity_available' => '100',
            'quantity_unit'      => 'kg',
            'source_type'        => FeedType::class,
            'source_id'          => $feedType->id,
        ]);

        $this->app->make(CartService::class)->addItem($customer, $listing, '10');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $customer, CheckoutData::fromArray(['payment_method' => $paymentMethod, 'fulfilment_method' => 'pickup'])
        );

        if ($paymentMethod === 'paystack') {
            $this->app->instance(PaystackClient::class, new FakePaystackClient());
            $paystack = $this->app->make(PaystackService::class);
            $init = $paystack->initialise($order, 'https://app.test/cb');
            $paystack->verify($init['reference']);
            $order->refresh();
        }

        $this->app->make(OrderService::class)->confirm($order->fresh(), $this->makeUser('salesperson'));

        return $order->fresh();
    }

    public function test_confirm_dispatches_storefront_order_confirmed_event(): void
    {
        $order = $this->placeAndConfirmFeedOrder();

        $event = DomainEvent::where('event_type', 'storefront_order.confirmed')
            ->where('source_id', $order->id)->first();

        $this->assertNotNull($event);
        $this->assertSame(DomainEvent::STATUS_PENDING, $event->status);
    }

    public function test_feed_line_routes_to_feed_sale_with_storefront_channel(): void
    {
        $order = $this->placeAndConfirmFeedOrder('cod');
        $this->runFinance($order);

        $sale = FeedSale::first();
        $this->assertNotNull($sale, 'A FeedSale should have been created.');
        $this->assertSame(FeedSale::CHANNEL_STOREFRONT, $sale->channel);
        $this->assertEquals(10.0, (float) $sale->quantity_kg);
        $this->assertEquals(12.0, (float) $sale->unit_price);
        $this->assertEquals(120.0, (float) $sale->sale_total);
        $this->assertSame('pending', $sale->payment_status); // COD → AR
        $this->assertSame('posted', $order->fresh()->finance_posting_status);
    }

    public function test_paystack_feed_order_books_as_paid(): void
    {
        $order = $this->placeAndConfirmFeedOrder('paystack');
        $this->runFinance($order);

        $sale = FeedSale::first();
        $this->assertNotNull($sale);
        $this->assertSame('paid', $sale->payment_status); // Paystack verified → cash sale
    }

    public function test_per_kg_pig_line_routes_with_explicit_agreed_price(): void
    {
        $customer = $this->makeUser('customer');
        $pig = $this->makePig();

        // Agreed price 45/kg differs from the settings live default (30) — proves
        // the storefront's price is used, not a settings reprice.
        $listing = $this->makePublishedListing([
            'kind'                => StorefrontListing::KIND_LIVE_PIG,
            'pricing_basis'       => StorefrontListing::BASIS_LIVE,
            'price_per_kg'        => '45.00',
            'estimated_weight_kg' => '72.5',
            'quantity_available'  => '1',
            'source_type'         => PigManagement::class,
            'source_id'           => $pig->id,
        ]);

        $this->app->make(CartService::class)->addItem($customer, $listing, '1');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $customer, CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup'])
        );
        $this->app->make(OrderService::class)->confirm($order->fresh(), $this->makeUser('salesperson'));

        $this->runFinance($order->fresh());

        $sale = PigSale::first();
        $this->assertNotNull($sale, 'A PigSale should have been created.');
        $this->assertEquals(45.0, (float) $sale->price_per_kg, 'Agreed price must not be repriced from settings.');
        $this->assertEquals(3262.5, (float) $sale->sale_price);
        // 'market', not 'storefront'. A storefront pig sale IS a market sale;
        // 'storefront' is the channel, which the feed path already models as a
        // separate field. The old value was permitted by no CHECK constraint,
        // so every storefront pig sale failed to post to finance — this
        // assertion was encoding that defect.
        $this->assertSame('market', $sale->sale_purpose);
        // Pig is soldsoft-deleted by the sale spine.
        $this->assertTrue(PigManagement::withTrashed()->find($pig->id)->trashed());
        $this->assertSame('posted', $order->fresh()->finance_posting_status);
    }

    public function test_manual_line_posts_a_real_finance_invoice_not_a_fake_sale(): void
    {
        $customer = $this->makeUser('customer');

        // Manure Sales defaults to off, and since Phase 8 that default is
        // enforced at the cart. A farm that sells manure switches it on.
        $this->enableStorefrontFeature(\App\Support\Features\FeatureCatalog::STOREFRONT_MANURE_SALES);

        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_MANURE,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '50.00',
            'quantity_available' => '20',
            'quantity_unit'      => 'bag',
        ]);

        $this->app->make(CartService::class)->addItem($customer, $listing, '2');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $customer, CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup'])
        );
        $this->app->make(OrderService::class)->confirm($order->fresh(), $this->makeUser('salesperson'));

        $this->runFinance($order->fresh());

        $this->assertSame(0, FeedSale::count());
        $this->assertSame(0, PigSale::count());
        $invoice = Invoice::first();
        $this->assertNotNull($invoice, 'A manual storefront line should post through the finance invoice service.');
        $this->assertSame(Invoice::STATUS_POSTED, $invoice->status);
        $this->assertSame('100.00', (string) $invoice->subtotal);
        $this->assertSame('100.00', (string) $invoice->total);

        $order->refresh();
        $item = $order->items()->firstOrFail();

        $this->assertSame('posted', $order->finance_posting_status);
        $this->assertNull($order->finance_posting_error);
        $this->assertSame(Invoice::class, $item->posted_sale_type);
        $this->assertSame($invoice->id, (int) $item->posted_sale_id);
    }

    public function test_finance_routing_is_idempotent(): void
    {
        $order = $this->placeAndConfirmFeedOrder('cod');

        $this->runFinance($order);
        $this->runFinance($order); // second pass must not double-create

        $this->assertSame(1, FeedSale::count());
    }
}
