<?php

namespace Tests\Browser;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Storefront\StorefrontCartItem;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\User;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService;
use App\Support\CurrentOrganization;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Buying a pig live or by carcass weight, driven through a real browser.
 *
 * WHAT THIS ADDS OVER THE FEATURE TESTS
 * -------------------------------------
 * The feature tests post to routes. This clicks the actual controls, which is
 * the only way to know whether a person can really do this. A checkbox that
 * never renders, a form posting the wrong field name, or a price shown in the
 * wrong column all pass an HTTP test and fail a customer.
 *
 * That is not hypothetical here: this feature has already produced a closure
 * that only broke when a page rendered and a Blade directive that only broke
 * when a template compiled, and neither showed up below the browser.
 *
 * DELIBERATELY NOT `DatabaseMigrations`
 * ------------------------------------
 * That trait runs `migrate:fresh` against the SERVED application's database.
 * This suite destroyed a development database exactly that way once already.
 * Fixtures are built fresh per test with unique identifiers instead, and
 * `DuskTestCase` refuses to run against anything but the disposable database.
 */
class CarcassAndLivePurchaseFlowTest extends DuskTestCase
{
    /**
     * A fresh browser per test — Dusk pools them, and viewport size, cookies
     * and scroll position leak from one test to the next otherwise.
     */
    protected function tearDown(): void
    {
        static::closeAll();

        parent::tearDown();
    }

    protected function suffix(): string
    {
        return strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }

    protected function organisation(): Organization
    {
        $org = Organization::first()
            ?? Organization::create(['slug' => 'dusk-farm', 'name' => 'Dusk Farm']);

        $this->app->make(CurrentOrganization::class)->bind($org);

        // The rates the shop prices from, as a farm would have configured them.
        Setting::set('sales.pricing.live_weight_price', 30);
        Setting::set('sales.pricing.carcass_weight_price', 50);

        return $org;
    }

    protected function person(Organization $org, string $email, string $name): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching(Permission::pluck('id')->all());

        $user = User::withoutGlobalScopes()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name'              => $name,
            'password'          => bcrypt('secret'),
            'role'              => 'admin',
            'role_id'           => $role->id,
            'organization_id'   => $org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * A published listing backed by weighed animals.
     *
     * @param  list<int>  $weights
     * @return array{0: StorefrontListing, 1: list<PigManagement>}
     */
    protected function weighedListing(Organization $org, User $staff, array $weights): array
    {
        $suffix = $this->suffix();

        $listing = StorefrontListing::create([
            'organization_id'    => $org->id,
            'reference'          => 'LIST-BUY-' . $suffix,
            'title'              => 'Market-ready pigs ' . $suffix,
            'slug'               => 'buy-pigs-' . strtolower($suffix),
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => StorefrontPigPricingService::CATEGORY_STANDARD,
            'sale_mode'          => StorefrontListing::MODE_FIXED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
            'quantity_unit'      => 'head',
            'pickup_allowed'     => true,
            'status'             => StorefrontListing::STATUS_PUBLISHED,
            'published_at'       => now(),
        ]);

        $pigs = [];

        foreach ($weights as $i => $kg) {
            $pig = PigManagement::create([
                'organization_id' => $org->id,
                // Explicit only to keep this suite off the shared sequence.
                'local_id'        => random_int(1_000_000, 99_999_999),
                'ear_tag_number'  => 'BUY-' . $suffix . '-' . $i,
                'breed'           => 'Large White',
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

            $pigs[] = $pig;
        }

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, array_column($pigs, 'id'), $staff);

        $this->app->make(StorefrontPigPricingService::class)
            ->refreshListingSnapshot($listing->fresh());

        return [$listing->fresh(), $pigs];
    }

    /** @return list<int> */
    protected function rowIds(StorefrontListing $listing): array
    {
        return StorefrontListingPig::where('storefront_listing_id', $listing->id)
            ->orderBy('id')->pluck('id')->map('intval')->all();
    }

    /* ================================================================== */

    /**
     * 1. The push page offers the four source categories, and choosing one
     *    loads that herd.
     */
    public function test_the_push_page_offers_the_four_source_categories(): void
    {
        $org   = $this->organisation();
        $staff = $this->person($org, 'buyflow-admin@example.com', 'Buy Flow Admin');

        $this->browse(function (Browser $browser) use ($staff) {
            $browser->resize(1400, 1800)
                ->loginAs($staff)
                ->visit('/storefront-admin/push')
                ->waitForText('What are you selling?')
                ->assertSee('Breeding Stock')
                ->assertSee('Standard Stock')
                ->assertSee('Piglets')
                ->assertSee('Manual Entry')
                // The questions that were removed must stay removed.
                ->assertMissing('select[name="sale_mode"]')
                ->assertMissing('select[name="pricing_basis"]')
                ->assertMissing('input[name="price_per_kg"]')
                ->assertMissing('input[name="currency_code"]');
        });
    }

    /**
     * 2. Choosing Manual Entry replaces the stock picker with a price box.
     */
    public function test_choosing_manual_entry_asks_for_a_price_instead_of_animals(): void
    {
        $org   = $this->organisation();
        $staff = $this->person($org, 'buyflow-admin@example.com', 'Buy Flow Admin');

        $this->browse(function (Browser $browser) use ($staff) {
            $browser->resize(1400, 1800)
                ->loginAs($staff)
                ->visit('/storefront-admin/push')
                ->waitForText('Manual Entry')
                ->clickLink('Manual Entry')
                ->waitForText('Kind')
                ->assertPresent('input[name="fixed_price"]')
                ->assertMissing('input[name="pool_pig_ids[]"]');
        });
    }

    /**
     * 3. The stock selector's bulk controls work, and the totals move with them.
     */
    public function test_selecting_all_visible_animals_updates_the_running_totals(): void
    {
        $org   = $this->organisation();
        $staff = $this->person($org, 'buyflow-admin@example.com', 'Buy Flow Admin');

        // Weighed animals, so the selector has real numbers to add up.
        $this->weighedListing($org, $staff, [90, 110]);
        $free = PigManagement::create([
            'organization_id' => $org->id,
            'local_id'        => random_int(1_000_000, 99_999_999),
            'ear_tag_number'  => 'FREE-' . $this->suffix(),
            'breed'           => 'Landrace',
            'gender'          => 'female',
            'stock_type'      => 'standard',
            'date_of_birth'   => now()->subMonths(8)->toDateString(),
            'code'            => 'PIG-' . uniqid(),
        ]);
        PigWeight::create(['pig_id' => $free->id, 'weight' => 100, 'weigh_date' => now()->toDateString()]);

        $this->browse(function (Browser $browser) use ($staff, $free) {
            $browser->resize(1400, 2000)
                ->loginAs($staff)
                ->visit('/storefront-admin/push')
                ->waitForText('Standard Stock for this listing')
                ->assertSee($free->ear_tag_number)
                // Filtered to this one animal first.
                //
                // This suite does not reset the database between tests — the
                // trait that would do that drops the served application's
                // tables — so animals from earlier tests are still on offer and
                // "select all" would add up an unpredictable herd. Filtering
                // makes the total deterministic, and it is also how a person
                // with three hundred pigs actually uses this control.
                ->type('input[data-pig-pool-filter]', $free->ear_tag_number)
                ->pause(300)
                ->press('Select all visible')
                // One animal, 100 kg × 30 = 3000.00.
                ->waitForText('3000.00')
                ->assertSee('100 kg')
                ->press('Clear selected')
                ->waitForText('0.00');
        });
    }

    /**
     * 4. A buyer sees each animal with its own weight and its own price.
     */
    public function test_the_shop_shows_every_animal_at_its_own_price(): void
    {
        $org   = $this->organisation();
        $staff = $this->person($org, 'buyflow-admin@example.com', 'Buy Flow Admin');
        $buyer = $this->person($org, 'buyflow-buyer@example.com', 'Buy Flow Buyer');

        [$listing] = $this->weighedListing($org, $staff, [80, 120]);

        $this->browse(function (Browser $browser) use ($buyer, $listing) {
            $browser->resize(1400, 2000)
                ->loginAs($buyer)
                ->visit('/shop/listing/' . $listing->slug)
                ->waitForText('Buy live animals')
                // 80 × 30 and 120 × 30 — two animals, two prices.
                ->assertSee('2,400.00')
                ->assertSee('3,600.00')
                ->assertSee('Buy by carcass weight')
                ->assertSee('Up to 200 kg available');
        });
    }

    /**
     * 5. Ticking an animal and pressing the button puts THAT animal in the
     *    basket, at the price shown next to it.
     */
    public function test_a_buyer_can_choose_an_animal_and_see_it_in_the_basket(): void
    {
        $org   = $this->organisation();
        $staff = $this->person($org, 'buyflow-admin@example.com', 'Buy Flow Admin');
        $buyer = $this->person($org, 'buyflow-buyer@example.com', 'Buy Flow Buyer');

        [$listing, $pigs] = $this->weighedListing($org, $staff, [80, 120]);
        $rows = $this->rowIds($listing);

        $this->browse(function (Browser $browser) use ($buyer, $listing, $rows, $pigs) {
            $browser->resize(1400, 2000)
                ->loginAs($buyer)
                ->visit('/shop/listing/' . $listing->slug)
                ->waitForText('Buy live animals')
                // The heavier animal.
                ->check('input[value="' . $rows[1] . '"]')
                ->press('Add chosen animals to cart')
                ->waitForText('Added to cart', 15)
                ->assertSee($pigs[1]->ear_tag_number)
                ->assertSee('120 kg')
                ->assertSee('3,600.00');
        });

        $this->assertSame(
            '3600.00',
            (string) StorefrontCartItem::where('listing_id', $listing->id)->firstOrFail()->price_snapshot,
            'The basket must hold the price the buyer was shown for the animal they picked.',
        );
    }

    /**
     * 6. Asking for kilograms puts a carcass line in the basket.
     */
    public function test_a_buyer_can_ask_for_kilograms_of_carcass_weight(): void
    {
        $org   = $this->organisation();
        $staff = $this->person($org, 'buyflow-admin@example.com', 'Buy Flow Admin');
        $buyer = $this->person($org, 'buyflow-buyer@example.com', 'Buy Flow Buyer');

        [$listing] = $this->weighedListing($org, $staff, [100, 120]);

        $this->browse(function (Browser $browser) use ($buyer, $listing) {
            $browser->resize(1400, 2000)
                ->loginAs($buyer)
                ->visit('/shop/listing/' . $listing->slug)
                ->waitForText('Buy by carcass weight')
                ->type('requested_kg', '60')
                ->press('Add to cart')
                ->waitForText('Carcass weight', 15)
                ->assertSee('60 kg')
                // 60 × 50
                ->assertSee('3,000.00');
        });
    }

    /**
     * 7. The basket does not let a buyer turn one animal into five.
     */
    public function test_the_basket_offers_no_quantity_box_for_a_named_animal(): void
    {
        $org   = $this->organisation();
        $staff = $this->person($org, 'buyflow-admin@example.com', 'Buy Flow Admin');
        $buyer = $this->person($org, 'buyflow-buyer@example.com', 'Buy Flow Buyer');

        [$listing] = $this->weighedListing($org, $staff, [100]);
        $rows = $this->rowIds($listing);

        $this->browse(function (Browser $browser) use ($buyer, $listing, $rows) {
            $browser->resize(1400, 2000)
                ->loginAs($buyer)
                ->visit('/shop/listing/' . $listing->slug)
                ->waitForText('Buy live animals')
                ->check('input[value="' . $rows[0] . '"]')
                ->press('Add chosen animals to cart')
                ->waitForText('Live animal', 15)
                // One animal is one animal: there is nothing to increment.
                ->assertMissing('input[name="quantity"]')
                ->assertSee('Remove');
        });
    }
}
