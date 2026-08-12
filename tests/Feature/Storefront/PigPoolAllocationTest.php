<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\Storefront\StorefrontOrderItemPigAllocation;
use App\Storefront\Services\Exceptions\InventoryConflictException;
use App\Storefront\Services\StorefrontPigAllocationService;
use App\Storefront\Services\StorefrontPigPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Selling Standard Stock through the shop, from a pool of animals.
 *
 * THE RULE THIS FILE EXISTS TO PROTECT
 * ------------------------------------
 * `/sales` (`pig_sales`) is the only record of a sold pig. The shop decides
 * WHICH animals a buyer gets and WHEN; it never records the sale itself. Every
 * assertion about a sale here reads `pig_sales`, never a storefront table,
 * because a test that read the storefront's own bookkeeping would pass just as
 * happily against a second, parallel sales ledger — the thing this design
 * exists to prevent.
 */
class PigPoolAllocationTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();
    }

    protected function pool(): StorefrontPigPoolService
    {
        return $this->app->make(StorefrontPigPoolService::class);
    }

    protected function allocations(): StorefrontPigAllocationService
    {
        return $this->app->make(StorefrontPigAllocationService::class);
    }

    /**
     * @return list<PigManagement>
     */
    protected function makeStandardPigs(int $count): array
    {
        $pigs = [];

        for ($i = 0; $i < $count; $i++) {
            $pigs[] = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'POOL-' . strtoupper(uniqid()),
                'breed'           => 'Large White',
                'gender'          => $i % 2 === 0 ? 'male' : 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);
        }

        return $pigs;
    }

    protected function pigListing(): StorefrontListing
    {
        return $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'title'              => 'Market-ready live pigs',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '900.00',
            'quantity_available' => '0',
        ]);
    }

    /**
     * An order for $quantity head from the listing, as checkout would leave it.
     */
    protected function orderFor(StorefrontListing $listing, int $quantity): array
    {
        $order = StorefrontOrder::create([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-POOL-' . strtoupper(uniqid()),
            'status'            => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'    => StorefrontOrder::PAY_COD,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal'          => (string) (900 * $quantity) . '.00',
            'delivery_fee'      => '0.00',
            'total'             => (string) (900 * $quantity) . '.00',
            'currency_code'     => 'GHS',
            'placed_at'         => now(),
        ]);

        $item = StorefrontOrderItem::create([
            'organization_id' => $this->org->id,
            'order_id'        => $order->id,
            'listing_id'      => $listing->id,
            'kind'            => StorefrontListing::KIND_LIVE_PIG,
            'description'     => $listing->title,
            'quantity'        => (string) $quantity,
            'quantity_unit'   => 'head',
            'price_basis'     => 'fixed',
            'unit_price'      => '900.00',
            'line_total'      => (string) (900 * $quantity) . '.00',
        ]);

        return [$order, $item];
    }

    /* ================================================================== */
    /* The pool                                                           */
    /* ================================================================== */

    public function test_a_listing_can_be_built_from_several_standard_stock_pigs(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(5);
        $staff   = $this->makeUser('admin');

        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        $this->assertSame(5, $this->pool()->availableCount($listing));

        // The public quantity follows the animals rather than being typed in.
        $this->assertSame(
            '5.0000',
            (string) $listing->fresh()->quantity_available,
            'Listing quantity must be derived from the pool, not set by hand.',
        );
    }

    public function test_a_pig_cannot_be_active_in_two_listings_at_once(): void
    {
        $first  = $this->pigListing();
        $second = $this->makePublishedListing([
            'kind'          => StorefrontListing::KIND_LIVE_PIG,
            'title'         => 'Second listing',
            'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price'   => '900.00',
        ]);

        $pigs  = $this->makeStandardPigs(1);
        $staff = $this->makeUser('admin');

        $this->pool()->attachPigs($first, [$pigs[0]->id], $staff);

        $this->expectException(InventoryConflictException::class);

        $this->pool()->attachPigs($second, [$pigs[0]->id], $staff);
    }

    /* ================================================================== */
    /* Selection                                                          */
    /* ================================================================== */

    public function test_selecting_fewer_pigs_than_ordered_is_refused(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(5);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        [, $item] = $this->orderFor($listing, 3);

        $errors = $this->allocations()->validateSelection($item, [$pigs[0]->id, $pigs[1]->id]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('exactly 3', $errors[0]);
    }

    public function test_selecting_more_pigs_than_ordered_is_refused(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(5);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        [, $item] = $this->orderFor($listing, 2);

        $errors = $this->allocations()->validateSelection(
            $item,
            [$pigs[0]->id, $pigs[1]->id, $pigs[2]->id],
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('exactly 2', $errors[0]);
    }

    public function test_a_pig_outside_the_listing_cannot_be_selected(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(3);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, [$pigs[0]->id, $pigs[1]->id], $staff);

        [, $item] = $this->orderFor($listing, 1);

        // pigs[2] exists and is eligible, but is not in this listing's pool.
        $errors = $this->allocations()->validateSelection($item, [$pigs[2]->id]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not part of this listing', $errors[0]);
    }

    /* ================================================================== */
    /* Approval — the irreversible step                                   */
    /* ================================================================== */

    public function test_approval_creates_one_real_pig_sale_per_selected_animal(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(5);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        [$order, $item] = $this->orderFor($listing, 3);

        $chosen = [$pigs[0]->id, $pigs[1]->id, $pigs[2]->id];
        $this->allocations()->selectForOrderItem($item, $chosen, $staff);

        $sales = $this->allocations()->approveAllocationsForOrder($order, $staff);

        $this->assertCount(3, $sales);

        // The assertion that matters: the sales are in pig_sales, which is what
        // /sales reads. Not in a storefront table.
        $this->assertSame(
            3,
            PigSale::whereIn('pig_id', $chosen)->count(),
            'Approval must create real pig sales, one per animal.',
        );

        // And the animals have left Standard Stock.
        foreach ($chosen as $pigId) {
            $this->assertSoftDeleted('pigs_management', ['id' => $pigId]);
        }

        // The two untouched animals are still on the farm and still on offer.
        $this->assertNull(PigManagement::find($pigs[3]->id)?->deleted_at);
        $this->assertSame(2, $this->pool()->availableCount($listing));
    }

    public function test_unselected_pigs_are_never_sold(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(5);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        [$order, $item] = $this->orderFor($listing, 2);

        $this->allocations()->selectForOrderItem($item, [$pigs[0]->id, $pigs[1]->id], $staff);
        $this->allocations()->approveAllocationsForOrder($order, $staff);

        foreach ([$pigs[2]->id, $pigs[3]->id, $pigs[4]->id] as $untouched) {
            $this->assertSame(
                0,
                PigSale::where('pig_id', $untouched)->count(),
                'An animal nobody selected must never be sold by an approval.',
            );
        }
    }

    /**
     * Double-clicked Confirm, or a retried event.
     */
    public function test_approving_twice_does_not_sell_the_animals_twice(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(3);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        [$order, $item] = $this->orderFor($listing, 2);

        $this->allocations()->selectForOrderItem($item, [$pigs[0]->id, $pigs[1]->id], $staff);

        $first  = $this->allocations()->approveAllocationsForOrder($order, $staff);
        $second = $this->allocations()->approveAllocationsForOrder($order, $staff);

        $this->assertCount(2, $first);
        $this->assertCount(0, $second, 'A second approval must find nothing left to sell.');

        $this->assertSame(2, PigSale::count(), 'Two animals, two sales, however many times Confirm is pressed.');
    }

    public function test_the_listing_sells_out_when_its_last_animal_goes(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(2);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        [$order, $item] = $this->orderFor($listing, 2);

        $this->allocations()->selectForOrderItem($item, array_column($pigs, 'id'), $staff);
        $this->allocations()->approveAllocationsForOrder($order, $staff);

        $listing->refresh();

        $this->assertSame(0, $this->pool()->availableCount($listing));
        $this->assertSame(StorefrontListing::STATUS_SOLD_OUT, $listing->status);
    }

    /**
     * The money must add up. Three head out of a line of GHS 2,700 is 900 each;
     * an odd total puts the residual on the last animal rather than losing it.
     */
    public function test_the_line_total_is_split_across_the_animals_without_losing_a_pesewa(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(3);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        [$order, $item] = $this->orderFor($listing, 3);

        // An amount that does not divide evenly.
        $item->forceFill(['line_total' => '1000.00'])->save();

        $this->allocations()->selectForOrderItem($item, array_column($pigs, 'id'), $staff);
        $this->allocations()->approveAllocationsForOrder($order, $staff);

        $total = PigSale::whereIn('pig_id', array_column($pigs, 'id'))
            ->get()
            ->reduce(fn ($carry, $sale) => bcadd($carry, (string) $sale->sale_price, 2), '0.00');

        $this->assertSame(
            '1000.00',
            $total,
            'Each animal\'s sale price must sum to the line total exactly.',
        );
    }

    /* ================================================================== */
    /* Release                                                            */
    /* ================================================================== */

    public function test_releasing_an_order_puts_the_animals_back_on_offer(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(4);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        [$order, $item] = $this->orderFor($listing, 2);

        $this->allocations()->selectForOrderItem($item, [$pigs[0]->id, $pigs[1]->id], $staff);
        $this->assertSame(2, $this->pool()->availableCount($listing), 'Selected animals leave the shelf.');

        $this->allocations()->releaseOrderAllocations($order, $staff, 'Order cancelled.');

        $this->assertSame(4, $this->pool()->availableCount($listing));
        $this->assertSame(0, PigSale::count(), 'Releasing must never sell anything.');
    }

    /* ================================================================== */
    /* Direct sale from /sales                                            */
    /* ================================================================== */

    public function test_a_direct_sale_takes_the_animal_off_the_shop(): void
    {
        $listing = $this->pigListing();
        $pigs    = $this->makeStandardPigs(3);
        $staff   = $this->makeUser('admin');
        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        $this->assertSame(3, $this->pool()->availableCount($listing));

        // Sold at the farm gate, through the ordinary sale spine.
        $sale = $this->app->make(\App\Services\Sales\RecordPigSaleService::class)->execute(
            $pigs[0],
            $staff,
            [
                'sale_date'   => now()->toDateString(),
                'sale_price'  => 900,
                'weight_type' => 'live',
                'sale_weight' => 90,
                'buyer_name'  => 'Walk-in buyer',
            ],
        );

        $this->pool()->markPigSoldDirectly($pigs[0], $sale, $staff);

        $this->assertSame(
            2,
            $this->pool()->availableCount($listing),
            'A farm-gate sale must reduce what the shop can offer.',
        );

        $poolRow = StorefrontListingPig::where('pig_id', $pigs[0]->id)->first();
        $this->assertSame(StorefrontListingPig::STATUS_SOLD, $poolRow->status);
        $this->assertSame($sale->id, $poolRow->pig_sale_id);
    }

    /* ================================================================== */
    /* Organisation isolation                                             */
    /* ================================================================== */

    public function test_one_farm_cannot_pool_another_farms_pigs(): void
    {
        $listing = $this->pigListing();
        $staff   = $this->makeUser('admin');

        $otherFarm = \App\Models\Organization::create(['slug' => 'other-pool-farm', 'name' => 'Other Farm']);

        $foreignPig = PigManagement::create([
            'organization_id' => $otherFarm->id,
            'ear_tag_number'  => 'FOREIGN-1',
            'breed'           => 'Landrace',
            'gender'          => 'female',
            'stock_type'      => 'standard',
            'date_of_birth'   => now()->subMonths(8)->toDateString(),
            'code'            => 'PIG-' . uniqid(),
        ]);

        $this->expectException(InventoryConflictException::class);

        $this->pool()->attachPigs($listing, [$foreignPig->id], $staff);
    }
}
