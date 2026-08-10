<?php

namespace Tests\Feature\Storefront;

use App\Models\Finance\TaxProfile;
use App\Models\Finance\TaxProfileComponent;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontSetting;
use App\Services\Finance\Tax\TaxCalculation;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\StorefrontTaxService;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * IMP-091 — storefront orders carry tax (blueprint §38, §30).
 *
 * Before this, a storefront order recorded subtotal, delivery and total, and no
 * tax at all. The confirmed order created a sale with no tax and the invoice
 * totalled zero. A VAT-registered farm selling online collected no VAT.
 *
 * THE GOVERNING PROPERTY
 * ----------------------
 * What the buyer is charged and what the farm's books record must be the same
 * number. Every test here is an instance of that: across price modes, across
 * delivery, across multiple lines, and at the boundary where rounding could
 * make the parts fail to sum to the whole.
 */
class StorefrontTaxTest extends StorefrontTestCase
{
    use RefreshDatabase;

    /**
     * Ghana's 2026 shape: three levies on a SHARED base, not compounding.
     * 15% VAT + 2.5% NHIL + 2.5% GETFund = 20% of the net.
     *
     * A single "effective rate" could imitate this one case and would break on
     * the fixed levy and the compounding levy tested below — which is exactly
     * why the storefront calls the real calculator rather than multiplying.
     */
    protected function ghanaProfile(string $priceMode = TaxProfile::PRICE_EXCLUSIVE): TaxProfile
    {
        $profile = TaxProfile::create([
            'organization_id' => $this->org->id,
            'code'            => 'GH-2026-' . strtoupper(substr($priceMode, 0, 3)),
            'name'            => 'Ghana standard 2026',
            'applies_to'      => 'sales',
            'treatment'       => TaxProfile::TREATMENT_STANDARD,
            'price_mode'      => $priceMode,
            'effective_from'  => '2020-01-01',
            'is_active'       => true,
        ]);

        foreach ([['VAT', '15'], ['NHIL', '2.5'], ['GETFUND', '2.5']] as $i => [$code, $rate]) {
            TaxProfileComponent::create([
                'tax_profile_id'    => $profile->id,
                'code'              => $code,
                'label'             => $code,
                'rate_type'         => TaxProfileComponent::RATE_PERCENT,
                'rate'              => $rate,
                // BASE_NET, not compounding: Ghana's 2026 regime charges all
                // three on the SAME net, which is what makes 15 + 2.5 + 2.5
                // come to exactly 20% rather than the old cascade's 21.9%.
                'base_mode'         => TaxProfileComponent::BASE_NET,
                'calculation_order' => $i + 1,
            ]);
        }

        StorefrontSetting::updateOrCreate(
            ['organization_id' => $this->org->id],
            ['default_tax_profile_id' => $profile->id],
        );

        return $profile->load('components');
    }

    protected function sellable(array $overrides = []): StorefrontListing
    {
        return $this->makePublishedListing(array_merge([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '100.00',
            'quantity_available' => '20',
        ], $overrides));
    }

    protected function order(StorefrontListing $listing, string $quantity = '1', array $checkout = []): StorefrontOrder
    {
        $buyer = $this->makeUser('customer');
        $this->app->make(CartService::class)->addItem($buyer, $listing, $quantity);

        return $this->app->make(CheckoutService::class)->checkout($buyer, CheckoutData::fromArray(array_merge([
            'payment_method' => 'cod', 'fulfilment_method' => 'pickup',
        ], $checkout)));
    }

    /* ---------------------------------------------------------------- */
    /* The charge                                                       */
    /* ---------------------------------------------------------------- */

    public function test_an_order_carries_tax_computed_by_the_real_engine(): void
    {
        $this->ghanaProfile();

        $order = $this->order($this->sellable(), '1');

        // 100.00 net, three levies on a shared base = 20.00.
        $this->assertSame('100.00', (string) $order->subtotal);
        $this->assertSame('20.00', (string) $order->tax_total);
        $this->assertSame('120.00', (string) $order->total);
    }

    /**
     * The snapshot is what lets a credit note reverse the regime that applied
     * at the time rather than today's (IMP-014).
     */
    public function test_the_component_breakdown_is_snapshotted_on_the_order(): void
    {
        $profile = $this->ghanaProfile();

        $order = $this->order($this->sellable(), '1');

        $this->assertSame($profile->id, $order->tax_profile_id);
        $this->assertIsArray($order->tax_snapshot);

        $recovered = TaxCalculation::fromSnapshot($order->tax_snapshot);

        $this->assertSame('20.00', $recovered->totalTax);
        $this->assertCount(3, $recovered->components);
    }

    /**
     * A farm below the VAT threshold registers no profile. Charging a
     * zero-rated default would be inventing a tax position on its behalf.
     */
    public function test_a_farm_with_no_profile_charges_no_tax(): void
    {
        $order = $this->order($this->sellable(), '1');

        $this->assertSame('0.00', (string) $order->tax_total);
        $this->assertSame('100.00', (string) $order->total);
        $this->assertNull($order->tax_profile_id);
    }

    /**
     * Carriage is part of the consideration for the supply. Taxing the goods
     * but not the delivery under-declares on every delivered order.
     */
    public function test_delivery_is_part_of_the_taxable_base(): void
    {
        $this->ghanaProfile();
        $this->enableStorefrontFeature(\App\Support\Features\FeatureCatalog::STOREFRONT_DELIVERY);

        $listing = $this->sellable(['delivery_allowed' => true, 'delivery_fee' => '50.00']);

        $order = $this->order($listing, '1', [
            'fulfilment_method'   => 'delivery',
            'ship_address_line_1' => '12 Farm Road',
            'ship_city'           => 'Accra',
        ]);

        // (100 + 50) × 20% = 30.00
        $this->assertSame('50.00', (string) $order->delivery_fee);
        $this->assertSame('30.00', (string) $order->tax_total);
        $this->assertSame('180.00', (string) $order->total);
    }

    /**
     * An inclusive profile means the displayed price ALREADY contains the tax.
     * The total must not grow, or the buyer is charged twice for it.
     */
    public function test_an_inclusive_profile_recovers_tax_from_within_the_price(): void
    {
        $this->ghanaProfile(TaxProfile::PRICE_INCLUSIVE);

        $order = $this->order($this->sellable(['fixed_price' => '120.00']), '1');

        $this->assertSame('120.00', (string) $order->subtotal);
        $this->assertSame(
            '120.00',
            (string) $order->total,
            'An inclusive price already contains the tax; adding it again charges the buyer twice.',
        );
        $this->assertSame('20.00', (string) $order->tax_total);
    }

    /* ---------------------------------------------------------------- */
    /* The allocation                                                   */
    /* ---------------------------------------------------------------- */

    /**
     * Each order line becomes its own sale and its own invoice, so the order's
     * tax is apportioned. Rounding each share independently does not add up:
     * three lines of 33.33 at 20% round to 6.67 each — 20.01 against an order
     * tax of 20.00. The buyer was charged one number and the invoices would
     * total another.
     */
    public function test_the_parts_sum_to_the_whole_when_the_split_does_not_divide(): void
    {
        $this->ghanaProfile();

        $calculation = $this->app->make(StorefrontTaxService::class)
            ->calculate('100.00', $this->org->id);

        $allocated = $this->app->make(StorefrontTaxService::class)
            ->allocate($calculation, ['a' => '33.33', 'b' => '33.33', 'c' => '33.34']);

        $sum = '0.00';
        foreach ($allocated as $share) {
            $sum = bcadd($sum, $share, 2);
        }

        $this->assertSame(
            $calculation->totalTax,
            $sum,
            'The apportioned tax must sum to exactly what the buyer was charged.',
        );
    }

    public function test_allocation_of_a_zero_tax_is_zero_everywhere(): void
    {
        $this->ghanaProfile();

        $calculation = $this->app->make(StorefrontTaxService::class)->calculate('0.00', $this->org->id);
        $allocated   = $this->app->make(StorefrontTaxService::class)
            ->allocate($calculation, ['a' => '0.00', 'b' => '0.00']);

        $this->assertSame(['a' => '0.00', 'b' => '0.00'], $allocated);
    }

    /* ---------------------------------------------------------------- */
    /* The governing property: charged == posted                        */
    /* ---------------------------------------------------------------- */

    /**
     * THE test. What the buyer was charged must be what the ledger records.
     *
     * A feed sale posts revenue straight to the ledger rather than through an
     * invoice, so without an explicit tax line the buyer would be charged 20.00
     * that never reached the books — the farm holding money it had not recorded
     * as owed to the revenue authority. Worse than charging no tax at all.
     */
    public function test_the_tax_charged_reaches_the_ledger(): void
    {
        $this->ghanaProfile();

        // The ledger assertions below need a real chart of accounts (the VAT
        // control account is where output tax lands) and an open period to
        // post into.
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $year = \App\Models\FiscalYear::create([
            'organization_id' => $this->org->id,
            'fiscal_year'     => (int) now()->year,
            'name'            => 'FY' . now()->year,
            'start_date'      => now()->startOfYear()->toDateString(),
            'end_date'        => now()->endOfYear()->toDateString(),
            'status'          => 'open',
        ]);

        \App\Models\FiscalPeriod::create([
            'organization_id' => $this->org->id,
            'fiscal_year_id'  => $year->id,
            'period_number'   => (int) now()->month,
            'name'            => now()->format('F Y'),
            'start_date'      => now()->startOfMonth()->toDateString(),
            'end_date'        => now()->endOfMonth()->toDateString(),
            'status'          => \App\Models\FiscalPeriod::STATUS_OPEN,
            'posting_count'   => 0,
        ]);

        $feedType = \App\Models\FeedType::create(['name' => 'Grower Feed']);

        $listing = $this->sellable([
            'kind'          => StorefrontListing::KIND_FEED,
            'source_type'   => \App\Models\FeedType::class,
            'source_id'     => $feedType->id,
            'quantity_unit' => 'kg',
            'fixed_price'   => '100.00',
        ]);

        $order = $this->order($listing, '1');

        $this->assertSame('20.00', (string) $order->tax_total);

        $this->app->make(\App\Storefront\Services\OrderService::class)
            ->confirm($order->fresh(), $this->makeUser('admin'));

        $event = \App\Models\DomainEvent::where('event_type', 'storefront_order.confirmed')
            ->where('source_id', $order->id)
            ->firstOrFail();
        $this->app->make(\App\Finance\Subscribers\Sales\StorefrontOrderConfirmedSubscriber::class)
            ->handle($event->id);

        $sale = \App\Models\FeedSale::first();
        $this->assertNotNull($sale, 'The order should have produced a feed sale.');
        $this->assertSame(
            '20.00',
            bcadd((string) $sale->tax_amount, '0', 2),
            'The tax the buyer paid must be carried onto the sale finance posts from.',
        );

        // Drain the feed sale's own revenue event. The storefront subscriber
        // creates the sale; the feed subscriber posts it, and in production the
        // outbox runs both. Invoked directly here for the same reason the
        // storefront one is: this test is about the numbers, not the queue.
        $revenueEvent = \App\Models\DomainEvent::query()
            ->where('event_type', \App\Domain\Events\Sales\FeedSaleRecorded::TYPE)
            ->where('source_id', $sale->id)
            ->orderBy('id')
            ->first();

        $this->assertNotNull($revenueEvent, 'The feed sale should have raised a revenue event.');

        $this->app->make(\App\Finance\Subscribers\Sales\FeedSaleRevenueSubscriber::class)
            ->handle($revenueEvent->id);

        // And it landed in the ledger as a liability, not as income.
        $vatAccount = $this->app->make(\App\Finance\Resolvers\FinanceAccountResolver::class)
            ->byHint('VAT_CONTROL');

        $credited = \App\Models\Finance\JournalLine::query()
            ->where('account_id', $vatAccount->id)
            ->sum('credit');

        $this->assertSame(
            '20.00',
            bcadd((string) $credited, '0', 2),
            'Output tax must be credited to the tax control account. Without it the farm '
            . 'has taken money it has not recorded as owed.',
        );
    }

    /* ---------------------------------------------------------------- */
    /* Cross-organisation                                               */
    /* ---------------------------------------------------------------- */

    /**
     * Another farm's tax profile must never price this farm's orders. The
     * settings screen validates its own input; this is the read path, on every
     * checkout.
     */
    public function test_a_profile_from_another_organisation_is_ignored(): void
    {
        $other = \App\Models\Organization::create(['slug' => 'other-tax', 'name' => 'Other Farm']);

        $foreign = TaxProfile::create([
            'organization_id' => $other->id,
            'code'            => 'OTHER-VAT',
            'name'            => 'Their VAT',
            'applies_to'      => 'sales',
            'treatment'       => TaxProfile::TREATMENT_STANDARD,
            'price_mode'      => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from'  => '2020-01-01',
            'is_active'       => true,
        ]);

        // Pointed at deliberately, as a hand-edited row or a stale setting would.
        StorefrontSetting::updateOrCreate(
            ['organization_id' => $this->org->id],
            ['default_tax_profile_id' => $foreign->id],
        );

        $this->assertNull(
            $this->app->make(StorefrontTaxService::class)->profileFor($this->org->id),
            "Another farm's profile must not price this farm's orders.",
        );

        $order = $this->order($this->sellable(), '1');
        $this->assertSame('0.00', (string) $order->tax_total);
    }
}
