<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\Storefront\StorefrontOrderItemPigAllocation;
use App\Models\User;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\StorefrontPigAllocationService as Allocations;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService as Pricing;
use App\Storefront\Services\StorefrontPurchaseLineService;
use App\Storefront\Support\CartOwner;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * What a manager sees against each line of an order, and what each line needs.
 *
 * THREE LINES, THREE DIFFERENT JOBS
 * ---------------------------------
 * A live line needs nothing chosen — the buyer already named the animals and
 * paid for their weights. A carcass line needs animals chosen, but by WEIGHT:
 * "150 kg" is covered by however many pigs it takes. A manual line needs
 * nothing at all.
 *
 * Counting head on a carcass line is the mistake worth guarding: it would
 * refuse a correct selection of two heavy animals and accept a wrong one of a
 * single light animal, which is a short delivery the system called complete.
 */
class StorefrontFulfilmentStateTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected StorefrontListing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
        // Viewing and confirming orders is its own permission set.
        $this->seedStorefrontOrderPermissions();

        Setting::set('sales.pricing.live_weight_price', 30);
        Setting::set('sales.pricing.carcass_weight_price', 50);
    }

    protected function allocations(): Allocations
    {
        return $this->app->make(Allocations::class);
    }

    /** @param array<int, float> $weights */
    protected function poolListing(array $weights): StorefrontListing
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => Pricing::CATEGORY_STANDARD,
            'title'              => 'Market-ready pigs',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
            'pickup_allowed'     => true,
        ]);

        $ids = [];

        foreach ($weights as $i => $kg) {
            $pig = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'FS-' . $i . '-' . strtoupper(uniqid()),
                'breed'           => 'Landrace',
                'gender'          => 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);

            PigWeight::create(['pig_id' => $pig->id, 'weight' => $kg, 'weigh_date' => now()->toDateString()]);

            $ids[] = $pig->id;
        }

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, $ids, $this->makeUser('admin'));

        return $this->listing = $listing->fresh();
    }

    /** @return array<int, int> */
    protected function poolRowIds(): array
    {
        return StorefrontListingPig::where('storefront_listing_id', $this->listing->id)
            ->orderBy('id')->pluck('id')->all();
    }

    /** @return array<int, int> */
    protected function poolPigIds(): array
    {
        return StorefrontListingPig::where('storefront_listing_id', $this->listing->id)
            ->orderBy('id')->pluck('pig_id')->map('intval')->all();
    }

    protected function checkout(User $buyer): StorefrontOrder
    {
        return $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup']),
        );
    }

    protected function lines(): StorefrontPurchaseLineService
    {
        return $this->app->make(StorefrontPurchaseLineService::class);
    }

    /* ================================================================== */
    /* Live: the customer already chose                                   */
    /* ================================================================== */

    public function test_a_live_line_arrives_already_allocated(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$this->poolRowIds()[0]]);

        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(
            Allocations::STATE_READY,
            $this->allocations()->fulfilmentState($item),
            'The buyer named the animal; there is nothing for a manager to choose.',
        );

        $allocation = StorefrontOrderItemPigAllocation::where('storefront_order_item_id', $item->id)->firstOrFail();

        $this->assertTrue((bool) $allocation->customer_selected);
        $this->assertSame($this->poolPigIds()[0], (int) $allocation->pig_id);
    }

    /**
     * The animal a buyer has paid for must leave the shop at once, not when a
     * manager gets round to the order.
     */
    public function test_a_bought_animal_stops_being_on_offer_immediately(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$this->poolRowIds()[0]]);
        $this->checkout($buyer);

        $row = StorefrontListingPig::whereKey($this->poolRowIds()[0])->firstOrFail();

        $this->assertSame(StorefrontListingPig::STATUS_ALLOCATED, $row->status);
        $this->assertSame(
            1,
            $this->app->make(StorefrontPigPoolService::class)->availableCount($this->listing->fresh()),
            'One of the two animals has been sold; only one is left to offer.',
        );
    }

    /* ================================================================== */
    /* Carcass: chosen by weight, not by head                             */
    /* ================================================================== */

    public function test_a_carcass_line_starts_needing_a_selection(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '150');

        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(Allocations::STATE_NEEDS_SELECTION, $this->allocations()->fulfilmentState($item));
        $this->assertSame('150.0000', $this->allocations()->requestedKgFor($item));
    }

    /**
     * The rule this class exists for.
     *
     * 150 kg needs both animals. One 120 kg pig is a head count of one and a
     * short delivery; counting head would have accepted it.
     */
    public function test_one_animal_short_of_the_weight_is_not_a_complete_selection(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');
        $staff = $this->makeUser('admin');

        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '150');
        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        // The heavier animal alone — 120 kg against a 150 kg order.
        $this->allocations()->selectForOrderItem($item, [$this->poolPigIds()[1]], $staff);

        $this->assertSame(
            Allocations::STATE_NEEDS_SELECTION,
            $this->allocations()->fulfilmentState($item->fresh()),
            '120 kg does not cover a 150 kg order, however few animals it is.',
        );
        $this->assertSame('120.0000', $this->allocations()->selectedKgFor($item->fresh()));
    }

    public function test_enough_weight_makes_the_line_ready_whatever_the_head_count(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');
        $staff = $this->makeUser('admin');

        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '150');
        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->allocations()->selectForOrderItem($item, $this->poolPigIds(), $staff);

        $this->assertSame(Allocations::STATE_READY, $this->allocations()->fulfilmentState($item->fresh()));
        $this->assertSame('220.0000', $this->allocations()->selectedKgFor($item->fresh()));
    }

    /**
     * A carcass line has a quantity of 1. Under the old head-count rule
     * "select exactly 1 pig" would have REFUSED the two animals that actually
     * cover the order.
     */
    public function test_covering_the_weight_with_two_animals_is_not_refused_as_a_count_error(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');
        $staff = $this->makeUser('admin');

        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '150');
        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(
            [],
            $this->allocations()->validateSelection($item, $this->poolPigIds()),
            'Two animals covering the weight is a correct selection, not a count error.',
        );
    }

    /* ================================================================== */
    /* Conflict is visible before approval, not during it                 */
    /* ================================================================== */

    public function test_an_animal_lost_after_selection_shows_as_a_conflict(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$this->poolRowIds()[0]]);
        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(Allocations::STATE_READY, $this->allocations()->fulfilmentState($item));

        // The animal dies, or is removed from the farm, after the order.
        PigManagement::whereKey($this->poolPigIds()[0])->delete();

        $this->assertSame(
            Allocations::STATE_CONFLICT,
            $this->allocations()->fulfilmentState($item->fresh()),
            'A manager must see this before pressing approve, not during it.',
        );

        $this->assertNotEmpty($this->allocations()->fulfilmentProblems($item->fresh()));
    }

    /* ================================================================== */
    /* Manual lines ask nothing of anyone                                 */
    /* ================================================================== */

    public function test_a_manual_line_needs_no_selection(): void
    {
        $feed = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'source_category'    => Pricing::CATEGORY_MANUAL,
            'title'              => 'Feed bags',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '85.00',
            'quantity_available' => '40',
            'pickup_allowed'     => true,
        ]);

        $buyer = $this->makeUser('customer');
        $this->app->make(CartService::class)->addItem(CartOwner::from($buyer), $feed, '3');

        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertFalse($this->allocations()->requiresPigSelection($item));
        $this->assertSame(Allocations::STATE_NOT_APPLICABLE, $this->allocations()->fulfilmentState($item));
    }

    /* ================================================================== */
    /* The manager's screen says which of these it is                     */
    /* ================================================================== */

    public function test_the_order_page_shows_a_carcass_line_in_kilograms(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '150');
        $order = $this->checkout($buyer);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('150 kg')
            ->assertSee('of carcass weight')
            // Head-count progress would read "1 of 1 chosen" on a delivery
            // that is 150 kg short.
            ->assertDontSee('asks for <strong>1</strong> head', escape: false);
    }

    public function test_the_order_page_says_a_live_line_was_the_customers_choice(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$this->poolRowIds()[0]]);
        $order = $this->checkout($buyer);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('The customer chose these animals themselves');
    }

    public function test_the_order_page_shows_a_conflict_before_approval(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$this->poolRowIds()[0]]);
        $order = $this->checkout($buyer);

        PigManagement::whereKey($this->poolPigIds()[0])->delete();

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.orders.show', $order))
            ->assertOk()
            ->assertSee('This selection can no longer be sold')
            ->assertSee('has already been sold or removed');
    }

    /* ================================================================== */
    /* Approval still goes through the one sale spine                     */
    /* ================================================================== */

    public function test_approving_a_carcass_line_records_a_real_sale_for_every_animal(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');
        $staff = $this->makeUser('admin');

        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '150');
        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->allocations()->selectForOrderItem($item, $this->poolPigIds(), $staff);

        $sales = $this->allocations()->approveAllocationsForOrder($order->fresh(), $staff);

        $this->assertCount(2, $sales, 'Both animals were slaughtered for this order; both are sales.');

        foreach ($this->poolPigIds() as $pigId) {
            $this->assertDatabaseHas('pig_sales', ['pig_id' => $pigId]);
        }

        $this->assertSame(Allocations::STATE_SOLD, $this->allocations()->fulfilmentState($item->fresh()));
    }

    /**
     * The money on a carcass order is the ORDER's money, split across the
     * animals — not the animals' live value, which is a different number
     * entirely.
     */
    public function test_the_carcass_orders_money_is_what_is_split_across_the_animals(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');
        $staff = $this->makeUser('admin');

        // 150 kg × 50 = 7500.00
        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '150');
        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame('7500.00', (string) $item->line_total);

        $this->allocations()->selectForOrderItem($item, $this->poolPigIds(), $staff);
        $sales = $this->allocations()->approveAllocationsForOrder($order->fresh(), $staff);

        $total = '0.00';
        foreach ($sales as $sale) {
            $total = bcadd($total, (string) $sale->sale_price, 2);
        }

        $this->assertSame(
            '7500.00',
            $total,
            'The parts must sum to the line exactly — no cent may be created or lost in the split.',
        );
    }
}
