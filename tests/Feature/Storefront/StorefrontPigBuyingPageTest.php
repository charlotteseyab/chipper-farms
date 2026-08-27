<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\Storefront\StorefrontCartItem;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService as Pricing;
use App\Storefront\Support\CartOwnerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The shop page a buyer actually uses to buy a pig.
 *
 * The service tests prove the arithmetic. These prove the page RENDERS and that
 * the form on it reaches the service — which is a separate question, and the
 * one that has caught real defects in this work twice: an uncaptured closure
 * variable and a Blade directive that was not one. Neither showed up in a
 * service test, and both 500'd the page.
 */
class StorefrontPigBuyingPageTest extends StorefrontTestCase
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
        Setting::setAnimalBreeds(['LWDR' => 'Large White Duroc']);
    }

    /** @param array<int, float|null> $weights */
    protected function poolListing(array $weights): StorefrontListing
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => Pricing::CATEGORY_STANDARD,
            'title'              => 'Market-ready pigs',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
        ]);

        $ids = [];

        foreach ($weights as $i => $kg) {
            $pig = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'SHOP-' . $i,
                'breed'           => 'Landrace',
                'gender'          => 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);

            if ($kg !== null) {
                PigWeight::create([
                    'pig_id'     => $pig->id,
                    'weight'     => $kg,
                    'weigh_date' => now()->toDateString(),
                ]);
            }

            $this->pigs[] = $pig;
            $ids[]        = $pig->id;
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

    /* ================================================================== */

    public function test_the_listing_page_offers_both_ways_to_buy(): void
    {
        $this->poolListing([80, 120]);

        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.listing', $this->listing->slug))
            ->assertOk()
            ->assertSee('Choose your animals')
            ->assertSee('Live animal')
            ->assertSee('Carcass')
            ->assertSee('Total selected amount')
            ->assertSee('Select all visible')
            ->assertSee('Deselect all')
            ->assertSee('data-live-pig-picker', false)
            ->assertSee('data-selected-count', false)
            ->assertSee('data-select-visible', false)
            // The rate the shop is charging, from the farm's own setup.
            ->assertSee('30.00')
            ->assertSee('50.00');
    }

    public function test_zero_non_override_snapshots_recover_the_farms_configured_prices(): void
    {
        $this->poolListing([80]);
        $this->listing->forceFill([
            'pricing_override' => false,
            'live_price_per_kg_snapshot' => '0.0000',
            'carcass_price_per_kg_snapshot' => '0.0000',
        ])->save();

        $this->get(route('storefront.listing', $this->listing->slug))
            ->assertOk()
            ->assertSee('GHS 30.00 / kg')
            ->assertSee('GHS 50.00 / kg');
    }

    public function test_the_listing_page_and_shop_card_show_the_estimated_price_range(): void
    {
        $this->poolListing([25, 30]);

        $this->get(route('storefront.listing', $this->listing->slug))
            ->assertOk()
            ->assertSee('GHS 750.00 - 900.00')
            ->assertSee('from 25 kg')
            ->assertSee('to 30 kg');

        $this->get(route('storefront.shop'))
            ->assertOk()
            ->assertSee('GHS 750.00 - 900.00')
            ->assertSee('Estimated per pig');
    }

    /**
     * A buyer choosing between two animals is choosing between two prices, and
     * has to be able to see which is which.
     */
    public function test_each_animal_is_listed_with_its_own_weight_and_price(): void
    {
        $this->poolListing([80, 120]);

        $response = $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.listing', $this->listing->slug))
            ->assertOk();

        $response->assertSee('SHOP-0')->assertSee('SHOP-1')
            ->assertSee('2,400.00')     // 80 × 30
            ->assertSee('3,600.00')     // 120 × 30
            ->assertSee('4,000.00')     // 80 × 50 carcass
            ->assertSee('6,000.00')     // 120 × 50 carcass
            ->assertSee('80 kg')
            ->assertSee('120 kg');
    }

    public function test_the_animal_picker_shows_the_full_breed_name(): void
    {
        $this->poolListing([72]);
        $this->pigs[0]->update(['breed' => 'LWDR']);

        $this->get(route('storefront.listing', $this->listing->slug))
            ->assertOk()
            ->assertSee('Large White Duroc')
            ->assertDontSee('· LWDR', false);
    }

    public function test_the_live_animal_picker_is_searchable_and_paginated(): void
    {
        $this->poolListing([80, 81, 82, 83, 84, 85, 86, 87, 88]);

        $this->get(route('storefront.listing', $this->listing->slug))
            ->assertOk()
            ->assertSee('Search available pigs')
            ->assertSee('SHOP-0')
            ->assertSee('SHOP-7')
            ->assertDontSee('SHOP-8');

        $this->get(route('storefront.listing', [
            'slug' => $this->listing->slug,
            'animal_q' => 'SHOP-8',
        ]))
            ->assertOk()
            ->assertSee('SHOP-8')
            ->assertDontSee('SHOP-0');
    }

    public function test_carcass_is_chosen_after_selecting_an_animal(): void
    {
        $this->poolListing([80, 120]);

        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.listing', $this->listing->slug))
            ->assertOk()
            ->assertSee('name="purchase_type" value="carcass"', false)
            ->assertDontSee('name="requested_kg"', false);
    }

    /**
     * An unweighed animal has no price, and the page must say so rather than
     * offer it at zero.
     */
    public function test_an_unweighed_animal_cannot_be_ticked(): void
    {
        $this->poolListing([80, null]);

        $rows      = $this->poolRowIds();
        $unweighed = $rows[1];
        $weighed   = $rows[0];

        $html = $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.listing', $this->listing->slug))
            ->assertOk()
            ->assertSee('Ask us for a price')
            ->getContent();

        // The tickbox for the unweighed animal is the one that is disabled —
        // asserting that the word "disabled" appears somewhere on the page
        // would pass on any page at all.
        $this->assertMatchesRegularExpression(
            '/value="' . $unweighed . '"[^>]*disabled/',
            $html,
            'The unweighed animal must not be selectable.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/value="' . $weighed . '"[^>]*disabled/',
            $html,
            'The weighed animal must still be selectable.',
        );
    }

    /**
     * A bag of feed has no animals, no live weight and no carcass. It keeps the
     * ordinary quantity box.
     */
    public function test_a_manual_listing_keeps_the_plain_quantity_box(): void
    {
        $feed = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'source_category'    => Pricing::CATEGORY_MANUAL,
            'title'              => 'Feed bags',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '85.00',
            'quantity_available' => '40',
        ]);

        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.listing', $feed->slug))
            ->assertOk()
            ->assertDontSee('Choose your animals')
            ->assertSee('name="quantity"', escape: false);
    }

    /* ================================================================== */
    /* The form reaches the service                                       */
    /* ================================================================== */

    public function test_choosing_animals_puts_one_line_per_animal_in_the_basket(): void
    {
        $this->poolListing([80, 120]);

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.cart.add-pigs', $this->listing), [
                'purchase_type'   => 'live',
                'listing_pig_ids' => $this->poolRowIds(),
            ])
            ->assertRedirect(route('storefront.cart.index'));

        $lines = StorefrontCartItem::where('listing_id', $this->listing->id)->get();

        $this->assertCount(2, $lines);
        $this->assertEqualsCanonicalizing(
            ['2400.00', '3600.00'],
            $lines->map(fn ($l) => (string) $l->price_snapshot)->all(),
        );
    }

    public function test_a_guest_can_start_a_pig_purchase_without_signing_in(): void
    {
        $this->poolListing([80]);

        $this->get(route('storefront.listing', $this->listing->slug))
            ->assertOk()
            ->assertSee('Add chosen animals to cart')
            ->assertSee('No account needed')
            ->assertDontSee('Sign in to buy');

        $response = $this->post(route('storefront.cart.add-pigs', $this->listing), [
            'purchase_type'   => 'live',
            'listing_pig_ids' => $this->poolRowIds(),
        ])->assertRedirect(route('storefront.cart.index'));

        $token = $response->getCookie(CartOwnerResolver::COOKIE)?->getValue();
        $this->assertNotNull($token, 'A guest pig purchase should mint the same guest cart token as ordinary checkout.');
        $this->assertSame(1, StorefrontCartItem::where('listing_id', $this->listing->id)->count());
    }

    public function test_asking_for_kilograms_puts_one_carcass_line_in_the_basket(): void
    {
        $this->poolListing([80, 120]);

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.cart.add-pigs', $this->listing), [
                'purchase_type' => 'carcass',
                'requested_kg'  => '60',
            ])
            ->assertRedirect(route('storefront.cart.index'));

        $line = StorefrontCartItem::where('listing_id', $this->listing->id)->firstOrFail();

        $this->assertSame(Pricing::PURCHASE_CARCASS, $line->purchase_type);
        $this->assertSame('60.0000', (string) $line->requested_kg);
        $this->assertSame('3000.00', (string) $line->price_snapshot);
        $this->assertSame('1.0000', (string) $line->quantity, 'Kilograms must not be counted as head.');
    }

    public function test_choosing_an_animal_as_carcass_uses_its_weight_and_carcass_rate(): void
    {
        $this->poolListing([80]);

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.cart.add-pigs', $this->listing), [
                'purchase_type'   => 'carcass',
                'listing_pig_ids' => $this->poolRowIds(),
            ])
            ->assertRedirect(route('storefront.cart.index'));

        $line = StorefrontCartItem::where('listing_id', $this->listing->id)->firstOrFail();

        $this->assertSame(Pricing::PURCHASE_CARCASS, $line->purchase_type);
        $this->assertSame('80.0000', (string) $line->requested_kg);
        $this->assertSame('50.0000', (string) $line->price_per_kg_snapshot);
        $this->assertSame('4000.00', (string) $line->price_snapshot);
        $this->assertSame($this->poolRowIds(), $line->selected_listing_pig_ids);
    }

    /**
     * The browser is not trusted with the price. Whatever it posts, the server
     * charges what the animal weighs at the rate on the listing.
     */
    public function test_a_price_posted_by_the_browser_is_ignored(): void
    {
        $this->poolListing([100]);

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.cart.add-pigs', $this->listing), [
                'purchase_type'   => 'live',
                'listing_pig_ids' => $this->poolRowIds(),
                // A tampered form claiming this pig costs one unit.
                'price_snapshot'  => '1.00',
                'price'           => '1.00',
                'line_total'      => '1.00',
            ])
            ->assertRedirect();

        $this->assertSame(
            '3000.00',
            (string) StorefrontCartItem::where('listing_id', $this->listing->id)->firstOrFail()->price_snapshot,
        );
    }

    public function test_asking_for_more_kilograms_than_exist_is_refused_with_a_reason(): void
    {
        $this->poolListing([80]);

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.cart.add-pigs', $this->listing), [
                'purchase_type' => 'carcass',
                'requested_kg'  => '500',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, StorefrontCartItem::count());
    }

    public function test_a_live_purchase_with_no_animals_chosen_is_refused(): void
    {
        $this->poolListing([80]);

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.cart.add-pigs', $this->listing), [
                'purchase_type' => 'live',
            ])
            ->assertSessionHasErrors('listing_pig_ids');

        $this->assertSame(0, StorefrontCartItem::count());
    }

    /**
     * The basket must not let a buyer turn one animal into five.
     *
     * The cart page hides the quantity box for a pig line, which is where a
     * person would try it. The refusal lives in the service, which is where a
     * crafted request arrives.
     */
    public function test_the_quantity_of_a_live_line_cannot_be_changed(): void
    {
        // THREE animals, and the buyer asks for three. That quantity IS
        // available, so the ordinary availability check has nothing to say and
        // the only thing that can refuse is the pig-line rule itself. With one
        // animal this test passed whether the rule existed or not.
        $this->poolListing([100, 105, 110]);
        $buyer = $this->makeUser('customer');

        $this->actingAs($buyer)
            ->post(route('storefront.cart.add-pigs', $this->listing), [
                'purchase_type'   => 'live',
                'listing_pig_ids' => [$this->poolRowIds()[0]],
            ]);

        $line = StorefrontCartItem::where('listing_id', $this->listing->id)->firstOrFail();

        $this->actingAs($buyer)
            ->patch(route('storefront.cart.update', $line), ['quantity' => '3'])
            ->assertSessionHas('error');

        $this->assertStringContainsString(
            'Remove it and choose again',
            (string) session('error'),
            'The refusal must be the pig-line rule, not a coincidental availability error.',
        );

        $this->assertSame(
            '1.0000',
            (string) $line->fresh()->quantity,
            'One animal must stay one animal.',
        );
    }

    /**
     * The row has to say WHICH animal — a basket of four pigs from one listing
     * is otherwise four identical rows at four different prices.
     */
    public function test_the_basket_names_the_animal_and_the_weight(): void
    {
        $this->poolListing([100]);
        $buyer = $this->makeUser('customer');

        $this->actingAs($buyer)
            ->post(route('storefront.cart.add-pigs', $this->listing), [
                'purchase_type'   => 'live',
                'listing_pig_ids' => $this->poolRowIds(),
            ]);

        $this->actingAs($buyer)
            ->get(route('storefront.cart.index'))
            ->assertOk()
            ->assertSee('SHOP-0')
            ->assertSee('100 kg')
            ->assertSee('30.00/kg');
    }

    public function test_an_unknown_purchase_type_is_refused(): void
    {
        $this->poolListing([80]);

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.cart.add-pigs', $this->listing), [
                'purchase_type' => 'whatever',
                'requested_kg'  => '10',
            ])
            ->assertSessionHasErrors('purchase_type');

        $this->assertSame(0, StorefrontCartItem::count());
    }
}
