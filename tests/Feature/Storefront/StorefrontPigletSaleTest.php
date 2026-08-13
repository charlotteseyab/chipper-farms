<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Setting;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\Storefront\StorefrontOrderItemPigAllocation;
use App\Models\User;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\OrderService;
use App\Storefront\Services\StorefrontPigAllocationService as Allocations;
use App\Storefront\Services\StorefrontPigPricingService as Pricing;
use App\Storefront\Support\CartOwner;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Selling piglets, which do not exist as animals until somebody buys them.
 *
 * A farm records piglets as COUNTS — a litter of eleven, nine weaned — and the
 * first individual record a pig gets is when it starts mattering individually.
 * `RecordPigSaleService` sells a PIG, so a piglet sale had two possible shapes:
 * give it its own ledger, or create the animals at the point of sale and sell
 * them normally.
 *
 * The second, because the first is the parallel sales system this whole feature
 * exists to prevent. These tests hold that: after a piglet order is approved,
 * `/sales` holds one ordinary row per piglet and nothing anywhere has learned a
 * second way to sell one.
 */
class StorefrontPigletSaleTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected StorefrontListing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();

        Setting::set('sales.pricing.live_weight_price', 30);
        Setting::set('sales.pricing.carcass_weight_price', 50);

        $this->listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_PIGLET_LOT,
            'source_category'    => Pricing::CATEGORY_PIGLETS,
            'title'              => 'Weaner piglets',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            // Priced by the head: there is nothing to put on a scale.
            'fixed_price'        => '250.00',
            'quantity_available' => '20',
            'quantity_unit'      => 'head',
            'pickup_allowed'     => true,
        ]);
    }

    protected function allocations(): Allocations
    {
        return $this->app->make(Allocations::class);
    }

    /** @return array{0: StorefrontOrder, 1: StorefrontOrderItem} */
    protected function pigletOrder(string $quantity = '5'): array
    {
        $buyer = $this->makeUser('customer');

        $this->app->make(CartService::class)->addItem(CartOwner::from($buyer), $this->listing, $quantity);

        $order = $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup']),
        );

        // Deliberately NOT set by hand. The category has to survive the whole
        // journey — listing, cart line, order line — or materialisation would
        // work in a test and never fire in production.
        $item = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(
            Pricing::CATEGORY_PIGLETS,
            $item->source_category,
            'The order line must know it is piglets without being told.',
        );

        return [$order->fresh(), $item->fresh()];
    }

    /* ================================================================== */

    public function test_a_piglet_line_needs_nothing_chosen_before_approval(): void
    {
        [, $item] = $this->pigletOrder('5');

        $this->assertSame(
            Allocations::STATE_READY,
            $this->allocations()->fulfilmentState($item),
            'There are no animals to choose between — they do not exist yet.',
        );

        $this->assertSame(0, PigManagement::withTrashed()->count(), 'Nothing is created before the sale.');
    }

    /**
     * The whole point.
     */
    public function test_approval_creates_one_animal_per_piglet_and_sells_each_one(): void
    {
        [$order, $item] = $this->pigletOrder('5');
        $staff = $this->makeUser('admin');

        $sales = $this->allocations()->approveAllocationsForOrder($order, $staff);

        $this->assertCount(5, $sales, 'Five piglets bought is five sales.');

        $this->assertSame(
            5,
            PigSale::count(),
            'Every piglet sale is an ordinary row in /sales, not a separate ledger.',
        );

        $this->assertSame(
            5,
            PigManagement::withTrashed()->count(),
            'One animal record per piglet, created so it could be sold properly.',
        );

        // RecordPigSaleService soft-deletes a sold animal, exactly as it does
        // for a farm-gate sale. These went through the same path.
        $this->assertSame(0, PigManagement::count());
    }

    public function test_the_created_records_say_where_they_came_from(): void
    {
        [$order] = $this->pigletOrder('2');

        $this->allocations()->approveAllocationsForOrder($order, $this->makeUser('admin'));

        $pig = PigManagement::withTrashed()->firstOrFail();

        $this->assertStringContainsString('PGL-', (string) $pig->ear_tag_number);
        $this->assertStringContainsString($order->reference, (string) $pig->ear_tag_number);
        // `acquisition_source` is where the herd records where an animal came
        // from, and there is no notes column to put it in instead.
        $this->assertStringContainsString('point of sale', (string) $pig->acquisition_source);
        $this->assertStringContainsString($order->reference, (string) $pig->acquisition_source);
        // Nobody sexed these animals, and the record must not claim otherwise.
        $this->assertSame('unknown', $pig->gender);
    }

    public function test_the_order_money_is_split_across_the_piglets(): void
    {
        [$order] = $this->pigletOrder('4');   // 4 × 250.00 = 1000.00

        $sales = $this->allocations()->approveAllocationsForOrder($order, $this->makeUser('admin'));

        $total = '0.00';
        foreach ($sales as $sale) {
            $total = bcadd($total, (string) $sale->sale_price, 2);
        }

        $this->assertSame('1000.00', $total, 'The parts must sum to the line exactly.');
    }

    /**
     * A double-clicked Confirm must not put a second litter of animals that
     * never existed into the herd.
     */
    public function test_approving_twice_creates_nothing_the_second_time(): void
    {
        [$order] = $this->pigletOrder('3');
        $staff = $this->makeUser('admin');

        $this->allocations()->approveAllocationsForOrder($order, $staff);

        $pigsAfterFirst  = PigManagement::withTrashed()->count();
        $salesAfterFirst = PigSale::count();

        $this->allocations()->approveAllocationsForOrder($order->fresh(), $staff);

        $this->assertSame($pigsAfterFirst, PigManagement::withTrashed()->count());
        $this->assertSame($salesAfterFirst, PigSale::count());
        $this->assertSame(3, $salesAfterFirst);
    }

    public function test_every_piglet_is_traceable_to_the_order_that_bought_it(): void
    {
        [$order, $item] = $this->pigletOrder('2');

        $this->allocations()->approveAllocationsForOrder($order, $this->makeUser('admin'));

        $allocations = StorefrontOrderItemPigAllocation::where('storefront_order_item_id', $item->id)->get();

        $this->assertCount(2, $allocations);

        foreach ($allocations as $allocation) {
            $this->assertSame(StorefrontOrderItemPigAllocation::STATUS_SOLD, $allocation->status);
            $this->assertNotNull($allocation->pig_sale_id);
            $this->assertTrue((bool) ($allocation->allocation_snapshot['materialised'] ?? false));
        }
    }

    /**
     * Confirming the order is the path a manager actually takes, and it has to
     * produce the same result as calling approval directly.
     */
    public function test_confirming_the_order_sells_the_piglets(): void
    {
        [$order] = $this->pigletOrder('3');

        $this->app->make(OrderService::class)->confirm($order, $this->makeUser('admin'));

        $this->assertSame(3, PigSale::count());
        $this->assertSame(
            StorefrontOrder::STATUS_CONFIRMED,
            $order->fresh()->status,
        );
    }

    /**
     * A listing whose piglets ARE individually recorded — a farm that tags them
     * — is sold the ordinary way, with a manager choosing the animals. It must
     * not have phantom records created alongside the real ones.
     */
    public function test_a_piglet_listing_with_real_animals_is_not_materialised(): void
    {
        $pig = PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number'  => 'TAGGED-PIGLET-1',
            'breed'           => 'Landrace',
            'gender'          => 'female',
            'stock_type'      => 'standard',
            'date_of_birth'   => now()->subWeeks(8)->toDateString(),
            'code'            => 'PIG-' . uniqid(),
        ]);

        $this->app->make(\App\Storefront\Services\StorefrontPigPoolService::class)
            ->attachPigs($this->listing, [$pig->id], $this->makeUser('admin'));

        [, $item] = $this->pigletOrder('1');

        $this->assertFalse(
            $this->app->make(\App\Storefront\Services\StorefrontPigletMaterialisationService::class)
                ->appliesTo($item),
            'This farm records its piglets individually; there is nothing to materialise.',
        );
    }
}
