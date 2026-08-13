<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\Storefront\StorefrontCartItem;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Storefront\Services\Exceptions\CartException;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService as Pricing;
use App\Storefront\Services\StorefrontPurchaseLineService;
use App\Storefront\Support\CartOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Buying a pig live, or buying its meat by the kilogram.
 *
 * These are two different transactions and the shop has to keep them apart.
 *
 * LIVE is an animal: the buyer picks which ones, and pays for the weight those
 * animals actually carry. Two pigs in a listing are not worth the same, so each
 * is its own line at its own price — the alternative is a single unit price
 * that either rounds the money or states a weight no animal in the basket has.
 *
 * CARCASS is meat: the buyer asks for kilograms and the farm decides which
 * animals meet it. Nothing is picked, and the reservation is in kilograms.
 *
 * The line that matters most here is that kilograms are never counted as head.
 */
class StorefrontPurchaseLineTest extends StorefrontTestCase
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

    protected function lines(): StorefrontPurchaseLineService
    {
        return $this->app->make(StorefrontPurchaseLineService::class);
    }

    /**
     * A published listing backed by animals of the given weights.
     *
     * @param  array<int, float>  $weights
     */
    protected function poolListing(array $weights): StorefrontListing
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => Pricing::CATEGORY_STANDARD,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
        ]);

        $ids = [];

        foreach ($weights as $i => $kg) {
            $pig = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'BUY-' . $i . '-' . strtoupper(uniqid()),
                'breed'           => 'Landrace',
                'gender'          => 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);

            PigWeight::create([
                'pig_id'     => $pig->id,
                'weight'     => $kg,
                'weigh_date' => now()->toDateString(),
            ]);

            $this->pigs[] = $pig;
            $ids[]        = $pig->id;
        }

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, $ids, $this->makeUser('admin'));

        return $this->listing = $listing->fresh();
    }

    /** @return array<int, int> the pool row ids, in the order the pigs were made */
    protected function poolRowIds(): array
    {
        return StorefrontListingPig::where('storefront_listing_id', $this->listing->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    protected function buyer(): CartOwner
    {
        return CartOwner::from($this->makeUser('customer', 'Buyer'));
    }

    /* ================================================================== */
    /* Live: the buyer picks animals                                      */
    /* ================================================================== */

    public function test_each_chosen_animal_becomes_its_own_line_at_its_own_price(): void
    {
        $this->poolListing([80, 120]);
        $rows = $this->poolRowIds();

        $lines = $this->lines()->addLiveLines($this->buyer(), $this->listing, $rows);

        $this->assertCount(2, $lines);

        $totals = $lines->map(fn (StorefrontCartItem $l) => (string) $l->price_snapshot)->sort()->values()->all();

        // 80 × 30 and 120 × 30 — not two lines at the average of 3000.
        $this->assertSame(['2400.00', '3600.00'], $totals);

        foreach ($lines as $line) {
            $this->assertSame('1.0000', (string) $line->quantity);
            $this->assertSame(Pricing::PURCHASE_LIVE, $line->purchase_type);
            $this->assertSame('30.0000', (string) $line->price_per_kg_snapshot);
        }
    }

    /**
     * The whole point of a snapshot: the basket is not repriced behind the
     * buyer when the farm changes its rate.
     */
    public function test_a_later_rate_change_does_not_reprice_a_basket(): void
    {
        $this->poolListing([100]);
        $line = $this->lines()->addLiveLines($this->buyer(), $this->listing, $this->poolRowIds())->first();

        $this->assertSame('3000.00', (string) $line->price_snapshot);

        Setting::set('sales.pricing.live_weight_price', 45);

        $this->assertSame(
            '3000.00',
            (string) $line->fresh()->price_snapshot,
            'A price change must not reach into a basket that was already filled.',
        );
    }

    /**
     * The arithmetic has to be readable years later without re-deriving it.
     */
    public function test_the_line_records_how_its_price_was_reached(): void
    {
        $this->poolListing([92.5]);
        $line = $this->lines()->addLiveLines($this->buyer(), $this->listing, $this->poolRowIds())->first();

        $snapshot = $line->calculation_snapshot;

        $this->assertSame('live', $snapshot['basis']);
        $this->assertSame('92.5000', $snapshot['weight_kg']);
        $this->assertSame('30.0000', $snapshot['rate_per_kg']);
        $this->assertSame('2775.00', $snapshot['line_total']);
        $this->assertSame('2775.00', (string) $line->price_snapshot);

        // And which animal it was, by tag, so an invoice can name it.
        $weights = $line->selected_pig_weight_snapshot;
        $this->assertSame($this->pigs[0]->ear_tag_number, reset($weights)['ear_tag']);
    }

    public function test_the_same_animal_twice_is_still_one_animal(): void
    {
        $this->poolListing([100]);
        $buyer = $this->buyer();
        $rows  = $this->poolRowIds();

        $this->lines()->addLiveLines($buyer, $this->listing, $rows);
        $this->lines()->addLiveLines($buyer, $this->listing, $rows);

        $this->assertSame(
            1,
            StorefrontCartItem::where('listing_id', $this->listing->id)->count(),
            'Adding the same pig again must not put it in the basket twice.',
        );
    }

    /**
     * An animal with no weight cannot be priced, and must not be priced at zero.
     */
    public function test_an_unweighed_animal_is_refused_rather_than_sold_for_nothing(): void
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => Pricing::CATEGORY_STANDARD,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
        ]);

        $pig = PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number'  => 'NOKG-1',
            'breed'           => 'Landrace',
            'gender'          => 'female',
            'stock_type'      => 'standard',
            'date_of_birth'   => now()->subMonths(8)->toDateString(),
            'code'            => 'PIG-' . uniqid(),
        ]);

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, [$pig->id], $this->makeUser('admin'));

        $this->listing = $listing->fresh();

        // The listing has no priceable weight at all, so it cannot be bought.
        $this->expectException(CartException::class);

        $this->lines()->addLiveLines($this->buyer(), $this->listing, $this->poolRowIds());

        $this->assertSame(0, StorefrontCartItem::count());
    }

    public function test_an_animal_from_another_listing_cannot_be_bought_here(): void
    {
        $this->poolListing([100]);
        $mine = $this->poolRowIds();

        $other = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => Pricing::CATEGORY_STANDARD,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
        ]);

        $this->expectException(CartException::class);

        $this->lines()->addLiveLines($this->buyer(), $other, $mine);
    }

    public function test_an_animal_already_taken_cannot_be_bought(): void
    {
        $this->poolListing([100, 110]);
        $rows = $this->poolRowIds();

        StorefrontListingPig::whereKey($rows[0])
            ->update(['status' => StorefrontListingPig::STATUS_ALLOCATED]);

        $this->expectException(CartException::class);

        $this->lines()->addLiveLines($this->buyer(), $this->listing, [$rows[0]]);
    }

    /* ================================================================== */
    /* Carcass: the buyer asks for kilograms                              */
    /* ================================================================== */

    public function test_a_carcass_line_is_priced_at_the_carcass_rate(): void
    {
        $this->poolListing([100, 120]);

        $line = $this->lines()->addCarcassLine($this->buyer(), $this->listing, '80');

        $this->assertSame(Pricing::PURCHASE_CARCASS, $line->purchase_type);
        $this->assertSame('80.0000', (string) $line->requested_kg);
        $this->assertSame('50.0000', (string) $line->price_per_kg_snapshot);
        // 80 × 50
        $this->assertSame('4000.00', (string) $line->price_snapshot);
    }

    /**
     * The distinction the schema was changed for.
     *
     * A request for 80 kg is ONE request, not eighty animals. If kilograms were
     * written into the head count, a listing of two pigs would look like it had
     * eighty spoken for, and everything downstream that reads a quantity —
     * availability, holds, the order line — would be reading meat as livestock.
     */
    public function test_kilograms_are_never_counted_as_head(): void
    {
        $this->poolListing([100, 120]);

        $line = $this->lines()->addCarcassLine($this->buyer(), $this->listing, '80');

        $this->assertSame('1.0000', (string) $line->quantity);
        $this->assertSame('80.0000', (string) $line->requested_kg);
    }

    /**
     * A listing cannot promise more meat than its animals carry.
     */
    public function test_a_request_beyond_the_listings_capacity_is_refused(): void
    {
        $this->poolListing([100, 120]);   // 220 kg of capacity

        $this->assertSame('220.0000', $this->lines()->carcassKgAvailable($this->listing->fresh()));

        $this->expectExceptionMessage('Only 220 kg is available');

        $this->lines()->addCarcassLine($this->buyer(), $this->listing, '400');
    }

    public function test_capacity_follows_the_animals_still_in_the_pool(): void
    {
        $this->poolListing([100, 120]);

        // One is sold at the farm gate, through the ordinary sale spine.
        $this->app->make(\App\Services\Sales\RecordPigSaleService::class)->execute(
            $this->pigs[1],
            $this->makeUser('admin'),
            [
                'sale_date'   => now()->toDateString(),
                'sale_price'  => 3600,
                'weight_type' => 'live',
                'sale_weight' => 120,
                'buyer_name'  => 'Walk-in buyer',
            ],
        );

        $this->assertSame(
            '100.0000',
            $this->lines()->carcassKgAvailable($this->listing->fresh()),
            'Meat the farm no longer has must stop being on offer.',
        );
    }

    public function test_zero_or_negative_kilograms_is_refused(): void
    {
        $this->poolListing([100]);

        $this->expectExceptionMessage('Enter how many kilograms');

        $this->lines()->addCarcassLine($this->buyer(), $this->listing, '0');
    }

    /**
     * A farm that has not configured a carcass rate cannot sell meat online.
     * Refusing is right; quoting zero is not.
     */
    public function test_a_listing_with_no_carcass_rate_cannot_be_bought_by_weight(): void
    {
        Setting::set('sales.pricing.carcass_weight_price', 0);
        $this->poolListing([100]);

        $this->expectExceptionMessage('no carcass price');

        $this->lines()->addCarcassLine($this->buyer(), $this->listing->fresh(), '50');
    }

    /**
     * A manual listing — a bag of feed — has no animals and no weight, and must
     * not be reachable through either pig-buying path.
     */
    public function test_a_manual_listing_cannot_be_bought_by_live_or_carcass_weight(): void
    {
        $feed = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'source_category'    => Pricing::CATEGORY_MANUAL,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '85.00',
            'quantity_available' => '40',
        ]);

        $this->expectExceptionMessage('not sold by live or carcass weight');

        $this->lines()->addCarcassLine($this->buyer(), $feed, '10');
    }
}
