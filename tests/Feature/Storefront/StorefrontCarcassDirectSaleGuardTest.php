<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\User;
use App\Services\Sales\RecordPigSaleService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\InventoryConflictException;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService as Pricing;
use App\Storefront\Services\StorefrontPurchaseLineService;
use App\Storefront\Support\CartOwner;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Selling at the farm gate, when the shop has already promised the meat.
 *
 * THE HOLE THIS CLOSES
 * --------------------
 * Every existing guard counts ANIMALS. A carcass hold names none — a buyer
 * asked for 150 kg and which pigs cover it has not been decided by anybody yet
 * — so it was invisible to all of them.
 *
 * The result was a promise that could be broken in silence: two 100 kg animals
 * back a 150 kg order, one is sold in the yard, and the listing can now deliver
 * 100 of the 150 kilograms a customer has already paid for. Nothing complained
 * until the order came to be fulfilled, by which point both the pig and the
 * money were gone.
 */
class StorefrontCarcassDirectSaleGuardTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected StorefrontListing $listing;

    /** @var array<int, PigManagement> */
    protected array $pigs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();

        Setting::set('sales.pricing.live_weight_price', 30);
        Setting::set('sales.pricing.carcass_weight_price', 50);
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
                'ear_tag_number'  => 'GATE-' . $i . '-' . strtoupper(uniqid()),
                'breed'           => 'Landrace',
                'gender'          => 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);

            PigWeight::create(['pig_id' => $pig->id, 'weight' => $kg, 'weigh_date' => now()->toDateString()]);

            $this->pigs[] = $pig;
            $ids[]        = $pig->id;
        }

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, $ids, $this->makeUser('admin'));

        return $this->listing = $listing->fresh();
    }

    protected function placeCarcassOrder(string $kg): void
    {
        $buyer = $this->makeUser('customer');

        $this->app->make(StorefrontPurchaseLineService::class)
            ->addCarcassLine(CartOwner::from($buyer), $this->listing->fresh(), $kg);

        $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup']),
        );
    }

    protected function sellAtTheGate(PigManagement $pig, User $staff): PigSale
    {
        return $this->app->make(RecordPigSaleService::class)->execute($pig, $staff, [
            'sale_date'   => now()->toDateString(),
            'sale_price'  => 3000,
            'weight_type' => 'live',
            'sale_weight' => 100,
            'buyer_name'  => 'Walk-in buyer',
        ]);
    }

    /* ================================================================== */

    /**
     * The case that was silent.
     */
    public function test_a_gate_sale_that_would_break_a_carcass_promise_is_refused(): void
    {
        $this->poolListing([100, 100]);      // 200 kg of capacity
        $this->placeCarcassOrder('150');     // 150 kg already paid for

        $this->expectException(InventoryConflictException::class);
        $this->expectExceptionMessage('already paid for 150 kg');

        $this->app->make(StorefrontPigPoolService::class)
            ->assertPigMayBeSoldDirectly($this->pigs[0]->fresh());
    }

    /**
     * The guard has to hold on the real path, not only when asked directly.
     */
    public function test_the_refusal_reaches_the_ordinary_sale_service(): void
    {
        $this->poolListing([100, 100]);
        $this->placeCarcassOrder('150');

        try {
            $this->sellAtTheGate($this->pigs[0]->fresh(), $this->makeUser('admin'));
            $this->fail('The sale should have been refused.');
        } catch (InventoryConflictException $e) {
            $this->assertStringContainsString('already paid for', $e->getMessage());
        }

        $this->assertSame(0, PigSale::count(), 'No sale may be written when the promise cannot be kept.');
        $this->assertNotNull(
            PigManagement::find($this->pigs[0]->id),
            'The animal must still be on the farm.',
        );
    }

    /**
     * A sale that leaves enough behind is nobody's business but the farm's.
     */
    public function test_a_gate_sale_that_still_leaves_enough_is_allowed(): void
    {
        $this->poolListing([100, 100]);
        $this->placeCarcassOrder('80');       // 120 kg would remain

        $sale = $this->sellAtTheGate($this->pigs[0]->fresh(), $this->makeUser('admin'));

        $this->assertNotNull($sale->id);
        $this->assertSame(1, PigSale::count());
    }

    public function test_a_listing_with_no_carcass_promises_is_unaffected(): void
    {
        $this->poolListing([100, 100]);

        $sale = $this->sellAtTheGate($this->pigs[0]->fresh(), $this->makeUser('admin'));

        $this->assertNotNull($sale->id);
    }

    /**
     * Cancelling the order gives the kilograms back, and the sale that was
     * refused a moment ago becomes possible.
     */
    public function test_releasing_the_order_frees_the_animal_again(): void
    {
        $this->poolListing([100, 100]);
        $this->placeCarcassOrder('150');

        $pool = $this->app->make(StorefrontPigPoolService::class);

        try {
            $pool->assertPigMayBeSoldDirectly($this->pigs[0]->fresh());
            $this->fail('Expected the promise to block this sale.');
        } catch (InventoryConflictException) {
            // Expected.
        }

        // The buyer changes their mind; the hold stops being live.
        \App\Models\Storefront\StorefrontInventoryHold::query()->update([
            'status'      => \App\Models\Storefront\StorefrontInventoryHold::STATUS_RELEASED,
            'resolved_at' => now(),
        ]);

        $pool->assertPigMayBeSoldDirectly($this->pigs[0]->fresh());

        $this->assertTrue(true, 'With the promise withdrawn, the farm may sell its own animal.');
    }

    /**
     * The message has to tell staff what to do about it, not just say no.
     */
    public function test_the_refusal_says_how_much_was_promised_and_how_much_is_left(): void
    {
        $this->poolListing([100, 100]);
        $this->placeCarcassOrder('150');

        try {
            $this->app->make(StorefrontPigPoolService::class)
                ->assertPigMayBeSoldDirectly($this->pigs[0]->fresh());
            $this->fail('Expected a refusal.');
        } catch (InventoryConflictException $e) {
            $this->assertStringContainsString('150 kg', $e->getMessage());
            $this->assertStringContainsString('100 kg would be left', $e->getMessage());
            $this->assertStringContainsString('Cancel or reduce that order', $e->getMessage());
        }
    }

    /**
     * An animal that has left the pool has already stopped counting towards
     * capacity, so it must not be blamed for the shortfall a second time.
     */
    public function test_an_animal_already_removed_from_the_pool_is_not_double_counted(): void
    {
        $this->poolListing([100, 100, 100]);   // 300 kg
        $this->placeCarcassOrder('150');

        StorefrontListingPig::where('pig_id', $this->pigs[2]->id)
            ->update(['status' => StorefrontListingPig::STATUS_REMOVED]);

        $this->app->make(\App\Storefront\Services\StorefrontPigPricingService::class)
            ->refreshListingSnapshot($this->listing->fresh());

        // 200 kg of capacity left, 150 promised, one 100 kg animal being sold →
        // 100 kg would remain, which is short.
        $this->expectException(InventoryConflictException::class);

        $this->app->make(StorefrontPigPoolService::class)
            ->assertPigMayBeSoldDirectly($this->pigs[0]->fresh());
    }
}
