<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\Storefront\StorefrontListingMedia;
use App\Storefront\Services\StorefrontPigPricingService as Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The push page after the source-category rework.
 *
 * WHAT CHANGED AND WHY
 * --------------------
 * The page used to ask whoever was publishing a listing to choose a sale mode,
 * a pricing basis, a price per kilo, a unit price and a currency. Those are
 * database concepts, not farm ones. A person putting five market-ready pigs on
 * the shop knows they are Standard Stock; they should not have to know that
 * means kind `live_pig`, mode `fixed` and basis `live`, nor retype a rate the
 * farm already configured at /settings/setup.
 *
 * So the page now asks one question — WHICH STOCK — and derives the rest. These
 * tests hold that line from both directions: the derived values must be right,
 * and the removed questions must stay off the page.
 */
class StorefrontPushCategoryTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();

        Setting::set('sales.pricing.live_weight_price', 30);
        Setting::set('sales.pricing.carcass_weight_price', 50);
    }

    protected function pig(string $stockType, ?string $tag = null, ?float $weight = null): PigManagement
    {
        $pig = PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number'  => $tag ?: (strtoupper(substr($stockType, 0, 3)) . '-' . strtoupper(uniqid())),
            'breed'           => 'Landrace',
            'gender'          => 'female',
            'stock_type'      => $stockType,
            'date_of_birth'   => now()->subMonths(8)->toDateString(),
            'code'            => 'PIG-' . uniqid(),
        ]);

        if ($weight !== null) {
            PigWeight::create([
                'pig_id'     => $pig->id,
                'weight'     => $weight,
                'weigh_date' => now()->toDateString(),
            ]);
        }

        return $pig;
    }

    protected function pushPage(array $query = [])
    {
        return $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.push.create', $query));
    }

    /* ================================================================== */
    /* The four categories                                                */
    /* ================================================================== */

    public function test_the_page_offers_all_four_source_categories(): void
    {
        $this->pushPage()
            ->assertOk()
            ->assertSee('Breeding Stock')
            ->assertSee('Standard Stock')
            ->assertSee('Piglets')
            ->assertSee('Manual Entry');
    }

    public function test_the_create_page_offers_media_fields_to_media_managers(): void
    {
        $this->seedStorefrontQuotePermissions();

        $this->pushPage(['source_category' => Pricing::CATEGORY_MANUAL])
            ->assertOk()
            ->assertSee('3. Media')
            ->assertSee('Recommended image size: 1200 x 900 px')
            ->assertSee('Upload image')
            ->assertSee('...or image URL')
            ->assertSee('Alt text');
    }

    /**
     * Choosing Standard Stock must not show breeding animals, and the reverse.
     *
     * Selling a productive sow because she appeared in a market-pig picker is
     * not a display bug; it is the farm losing its breeding herd.
     */
    public function test_standard_stock_shows_only_standard_animals(): void
    {
        $standard = $this->pig('standard', 'MARKET-1', 92);
        $breeding = $this->pig('breeding', 'SOW-1', 210);

        $this->pushPage(['source_category' => Pricing::CATEGORY_STANDARD])
            ->assertOk()
            ->assertSee($standard->ear_tag_number)
            ->assertDontSee($breeding->ear_tag_number);
    }

    public function test_breeding_stock_shows_only_breeding_animals(): void
    {
        $standard = $this->pig('standard', 'MARKET-2', 92);
        $breeding = $this->pig('breeding', 'SOW-2', 210);

        $this->pushPage(['source_category' => Pricing::CATEGORY_BREEDING])
            ->assertOk()
            ->assertSee($breeding->ear_tag_number)
            ->assertDontSee($standard->ear_tag_number);
    }

    /**
     * Piglets have no individual records to tick — farrowing and weaning record
     * litter COUNTS. The page has to say so rather than show an empty list that
     * looks like "no piglets on this farm".
     */
    public function test_piglets_explain_that_animals_are_created_on_approval(): void
    {
        $this->pushPage(['source_category' => Pricing::CATEGORY_PIGLETS])
            ->assertOk()
            ->assertSee('created when a manager approves')
            ->assertDontSee('name="pool_pig_ids[]"', escape: false);
    }

    public function test_manual_entry_offers_a_price_and_no_stock_list(): void
    {
        $this->pig('standard', 'MARKET-3', 92);

        $this->pushPage(['source_category' => Pricing::CATEGORY_MANUAL])
            ->assertOk()
            ->assertSee('name="fixed_price"', escape: false)
            ->assertDontSee('name="pool_pig_ids[]"', escape: false)
            // A bag of feed cannot be derived from the herd, so this is the one
            // category that still asks for a kind.
            ->assertSee('name="kind"', escape: false);
    }

    /* ================================================================== */
    /* The questions that were removed                                    */
    /* ================================================================== */

    /**
     * @param  string  $field  a form field that must no longer be asked for
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('removedFields')]
    public function test_the_page_no_longer_asks_for_generic_pricing_fields(string $field): void
    {
        $this->pig('standard', 'MARKET-4', 92);

        $this->pushPage(['source_category' => Pricing::CATEGORY_STANDARD])
            ->assertOk()
            ->assertDontSee('name="' . $field . '"', escape: false);
    }

    public static function removedFields(): array
    {
        return [
            'sale mode'     => ['sale_mode'],
            'pricing basis' => ['pricing_basis'],
            'price per kg'  => ['price_per_kg'],
            'currency'      => ['currency_code'],
            'unit price'    => ['fixed_price'],
        ];
    }

    /**
     * Removing the questions must not remove the answers — every column the
     * rest of the shop reads still has to be populated.
     */
    public function test_the_derived_columns_are_still_written(): void
    {
        $pig = $this->pig('standard', 'MARKET-5', 100);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_STANDARD,
                'title'              => 'Derived columns',
                'quantity_available' => 1,
                'pool_pig_ids'       => [$pig->id],
            ])
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Derived columns')->firstOrFail();

        $this->assertSame(StorefrontListing::KIND_LIVE_PIG, $listing->kind);
        $this->assertSame(Pricing::CATEGORY_STANDARD, $listing->source_category);
        $this->assertNotNull($listing->sale_mode);
        $this->assertNotNull($listing->pricing_basis);
        $this->assertSame('GHS', $listing->currency_code);
    }

    public function test_initial_media_can_be_added_while_creating_a_listing(): void
    {
        $this->seedStorefrontQuotePermissions();

        $pig = $this->pig('standard', 'MEDIA-1', 100);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_STANDARD,
                'title'              => 'Listing with media',
                'quantity_available' => 1,
                'pool_pig_ids'       => [$pig->id],
                'media_url'          => 'https://example.com/listing-photo.jpg',
                'alt_text'           => 'Market-ready pig photo',
            ])
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Listing with media')->firstOrFail();

        $this->assertDatabaseHas('storefront_listing_media', [
            'organization_id' => $this->org->id,
            'listing_id'      => $listing->id,
            'type'            => StorefrontListingMedia::TYPE_IMAGE,
            'path'            => 'https://example.com/listing-photo.jpg',
            'alt_text'        => 'Market-ready pig photo',
            'is_primary'      => true,
        ]);
    }

    public function test_breeding_stock_creates_a_breeding_stock_listing(): void
    {
        $sow = $this->pig('breeding', 'SOW-3', 200);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_BREEDING,
                'title'              => 'Proven sow',
                'quantity_available' => 1,
                'pool_pig_ids'       => [$sow->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            StorefrontListing::KIND_BREEDING_STOCK,
            StorefrontListing::where('title', 'Proven sow')->firstOrFail()->kind,
        );

        $listing = StorefrontListing::where('title', 'Proven sow')->firstOrFail();

        $this->assertSame(1, StorefrontListingPig::where('storefront_listing_id', $listing->id)->count());
        $this->assertSame(1, app(\App\Storefront\Services\StorefrontPigPoolService::class)->availableCount($listing));
        $this->assertSame('1.0000', (string) $listing->fresh()->quantity_available);
    }

    public function test_hidden_listing_fields_are_derived_not_trusted_for_stock_backed_categories(): void
    {
        $pig = $this->pig('standard', 'FORGE-1', 100);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_STANDARD,
                'kind'               => StorefrontListing::KIND_BREEDING_STOCK,
                'sale_mode'          => StorefrontListing::MODE_QUOTE,
                'pricing_basis'      => StorefrontListing::BASIS_CARCASS,
                'currency_code'      => 'USD',
                'fixed_price'        => 1,
                'title'              => 'Forged fields',
                'quantity_available' => 999,
                'pool_pig_ids'       => [$pig->id],
            ])
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Forged fields')->firstOrFail();

        $this->assertSame(StorefrontListing::KIND_LIVE_PIG, $listing->kind);
        $this->assertSame(StorefrontListing::MODE_FIXED, $listing->sale_mode);
        $this->assertSame(StorefrontListing::BASIS_FIXED, $listing->pricing_basis);
        $this->assertSame('GHS', $listing->currency_code);
        $this->assertSame('3000.00', (string) $listing->fixed_price);
        $this->assertSame('1.0000', (string) $listing->quantity_available);
    }

    public function test_stock_backed_listing_without_selected_pigs_is_refused_without_shell_listing(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_STANDARD,
                'title'              => 'Empty stock-backed listing',
                'quantity_available' => 1,
                'pool_pig_ids'       => [],
            ])
            ->assertSessionHasErrors('pool_pig_ids');

        $this->assertSame(0, StorefrontListing::where('title', 'Empty stock-backed listing')->count());
    }

    /* ================================================================== */
    /* Money and weight come from the farm, not the form                  */
    /* ================================================================== */

    public function test_the_page_shows_the_farms_configured_rates(): void
    {
        $this->pig('standard', 'MARKET-6', 92);

        $this->pushPage(['source_category' => Pricing::CATEGORY_STANDARD])
            ->assertOk()
            ->assertSee('Priced from your farm setup')
            ->assertSee('30.00')
            ->assertSee('50.00');
    }

    /**
     * A farm that has not configured a live price would publish listings worth
     * nothing. The page has to say that before it happens, not after.
     */
    public function test_an_unconfigured_live_price_is_warned_about(): void
    {
        Setting::set('sales.pricing.live_weight_price', 0);
        $this->pig('standard', 'MARKET-7', 92);

        $this->pushPage(['source_category' => Pricing::CATEGORY_STANDARD])
            ->assertOk()
            ->assertSee('No live weight price is configured');
    }

    /**
     * The snapshot is built from the LATEST weights of the animals attached —
     * the finding that started this work, held at the level of the form.
     */
    public function test_the_listing_snapshot_totals_the_latest_pig_weights(): void
    {
        $a = $this->pig('standard', 'MARKET-8', 90);
        $b = $this->pig('standard', 'MARKET-9', 110);

        // `a` has been weighed again since; the newer figure is the one that counts.
        PigWeight::create(['pig_id' => $a->id, 'weight' => 96, 'weigh_date' => now()->toDateString()]);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_STANDARD,
                'title'              => 'Weighed pool',
                'quantity_available' => 2,
                'pool_pig_ids'       => [$a->id, $b->id],
            ])
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Weighed pool')->firstOrFail();

        // 96 + 110, not 90 + 110.
        $this->assertSame('206.0000', (string) $listing->total_live_weight_kg_snapshot);
        $this->assertSame('6180.00', (string) $listing->estimated_total_amount);
        $this->assertSame('30.0000', (string) $listing->live_price_per_kg_snapshot);
        $this->assertSame('206.0000', (string) $listing->carcass_capacity_kg);
    }

    /**
     * With no price field on the page, the listing's legacy single-price column
     * is derived — and derived UPWARDS.
     *
     * A pooled listing can deliver any animal in it. Charging the dearest is
     * refundable; charging the cheapest sells the farm's heaviest pig at the
     * lightest one's price and nobody notices until the margin is gone.
     */
    public function test_the_legacy_fixed_price_is_derived_from_the_dearest_animal(): void
    {
        $light = $this->pig('standard', 'MARKET-10', 80);   // 2400.00
        $heavy = $this->pig('standard', 'MARKET-11', 120);  // 3600.00

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_STANDARD,
                'title'              => 'Derived price',
                'quantity_available' => 2,
                'pool_pig_ids'       => [$light->id, $heavy->id],
            ])
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Derived price')->firstOrFail();

        $this->assertSame('3600.00', (string) $listing->fixed_price);
    }

    /**
     * The number of animals decides the quantity, whatever the form says.
     */
    public function test_a_tampered_quantity_is_ignored_for_stock_backed_listings(): void
    {
        $a = $this->pig('standard', 'MARKET-12', 90);
        $b = $this->pig('standard', 'MARKET-13', 95);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_STANDARD,
                'title'              => 'Tampered quantity',
                'quantity_available' => 9999,
                'pool_pig_ids'       => [$a->id, $b->id],
            ])
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Tampered quantity')->firstOrFail();

        $this->assertSame('2.0000', (string) $listing->quantity_available);
        $this->assertSame(2, StorefrontListingPig::where('storefront_listing_id', $listing->id)->count());
    }

    /**
     * A manual listing keeps the quantity that was typed — there is no herd to
     * derive it from, and a bag count is not an animal count.
     */
    public function test_a_manual_listing_keeps_the_typed_quantity(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_MANUAL,
                'kind'               => StorefrontListing::KIND_FEED,
                'title'              => 'Feed bags',
                'fixed_price'        => 85,
                'quantity_available' => 40,
                'quantity_unit'      => 'bag',
            ])
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Feed bags')->firstOrFail();

        $this->assertSame('40.0000', (string) $listing->quantity_available);
        $this->assertSame('85.00', (string) $listing->fixed_price);
        $this->assertSame(Pricing::CATEGORY_MANUAL, $listing->source_category);
    }

    /**
     * A pig sold at the farm gate has changed what the shop can offer, and the
     * listing's headline figures have to move with it.
     *
     * This is the case the push controller never sees. It refreshes the
     * snapshot itself when a listing is created; nothing about creation would
     * notice if the snapshot went stale afterwards. So the refresh lives in the
     * pool service, on every path that changes the pool, and this is the test
     * that holds it there.
     */
    public function test_selling_a_pooled_pig_at_the_gate_shrinks_the_listing_snapshot(): void
    {
        $a = $this->pig('standard', 'GATE-1', 100);
        $b = $this->pig('standard', 'GATE-2', 120);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => Pricing::CATEGORY_STANDARD,
                'title'              => 'Gate sale pool',
                'quantity_available' => 2,
                'pool_pig_ids'       => [$a->id, $b->id],
            ])
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Gate sale pool')->firstOrFail();

        $this->assertSame('220.0000', (string) $listing->total_live_weight_kg_snapshot);
        $this->assertSame('6600.00', (string) $listing->estimated_total_amount);

        // The heavier animal goes out of the gate, nothing to do with the shop.
        // Driven through the real sale spine, because that is what a farm does
        // and it is the path that has to keep the shop honest.
        $this->app->make(\App\Services\Sales\RecordPigSaleService::class)->execute(
            $b,
            $this->makeUser('admin'),
            [
                'sale_date'   => now()->toDateString(),
                'sale_price'  => 3600,
                'weight_type' => 'live',
                'sale_weight' => 120,
                'buyer_name'  => 'Walk-in buyer',
            ],
        );

        $listing->refresh();

        $this->assertSame(
            '100.0000',
            (string) $listing->total_live_weight_kg_snapshot,
            'A pig sold at the gate must leave the listing weight it was carrying.',
        );
        $this->assertSame('3000.00', (string) $listing->estimated_total_amount);
        $this->assertSame('100.0000', (string) $listing->carcass_capacity_kg);
    }

    /* ================================================================== */
    /* Finding an animal among hundreds                                   */
    /* ================================================================== */

    public function test_the_stock_search_narrows_the_list_on_the_server(): void
    {
        $wanted = $this->pig('standard', 'FINDME-1', 92);
        $other  = $this->pig('standard', 'OTHER-1', 88);

        $this->pushPage([
            'source_category' => Pricing::CATEGORY_STANDARD,
            'stock_search'    => 'FINDME',
        ])
            ->assertOk()
            ->assertSee($wanted->ear_tag_number)
            ->assertDontSee($other->ear_tag_number);
    }

    public function test_the_stock_search_can_find_local_ids_and_latest_weights(): void
    {
        $wanted = $this->pig('standard', 'SEARCH-1', 123.5);
        $wanted->forceFill(['local_id' => 'LOCAL-777'])->save();

        $other = $this->pig('standard', 'SEARCH-2', 88);

        $this->pushPage([
            'source_category' => Pricing::CATEGORY_STANDARD,
            'stock_search'    => 'LOCAL-777',
        ])
            ->assertOk()
            ->assertSee($wanted->ear_tag_number)
            ->assertDontSee($other->ear_tag_number);

        $matches = app(\App\Storefront\Services\StorefrontPigPoolService::class)
            ->candidatesForCategory($this->org->id, Pricing::CATEGORY_STANDARD, null, '123');

        $this->assertTrue($matches->contains(fn (PigManagement $pig) => (int) $pig->id === (int) $wanted->id));
        $this->assertFalse($matches->contains(fn (PigManagement $pig) => (int) $pig->id === (int) $other->id));
    }

    /**
     * "Select all visible" ticks what the filter has left on screen. The button
     * exists on the page and each row carries the weight the summary is built
     * from — the browser test drives the behaviour itself.
     */
    public function test_the_selector_carries_what_the_bulk_controls_need(): void
    {
        $this->pig('standard', 'MARKET-14', 92);

        $response = $this->pushPage(['source_category' => Pricing::CATEGORY_STANDARD])->assertOk();

        $response->assertSee('data-pig-select-visible', escape: false)
            ->assertSee('data-pig-clear', escape: false)
            ->assertSee('data-pig-weight="92.0000"', escape: false)
            ->assertSee('data-live-rate="30.0000"', escape: false);
    }

    /**
     * An animal with no weight cannot be priced, and staff have to be told
     * which one rather than have it silently counted as zero.
     */
    public function test_unweighed_animals_are_named_not_priced_at_zero(): void
    {
        $unweighed = $this->pig('standard', 'NOWEIGHT-1');

        $this->pushPage(['source_category' => Pricing::CATEGORY_STANDARD])
            ->assertOk()
            ->assertSee('not weighed')
            ->assertSee($unweighed->ear_tag_number);
    }

    /**
     * The category is validated, not trusted — it decides which herd a form can
     * reach into.
     */
    public function test_an_unknown_source_category_is_refused(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                'source_category'    => 'whatever_i_typed',
                'title'              => 'Bad category',
                'quantity_available' => 1,
            ])
            ->assertSessionHasErrors('source_category');

        $this->assertSame(0, StorefrontListing::where('title', 'Bad category')->count());
    }
}
