<?php

namespace Tests\Browser;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Role;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\User;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Support\CurrentOrganization;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The pig-pool flow, driven through a real browser.
 *
 * WHAT THIS ADDS OVER THE FEATURE TESTS
 * -------------------------------------
 * The feature tests post to routes. This clicks the actual controls, which is
 * the only way to find out whether a farm can really do this: a checkbox that
 * never renders, a form that posts the wrong field name, or a button behind a
 * disabled state all pass an HTTP test and fail a person.
 *
 * It has already earned that twice in this feature — a closure that only broke
 * when a page rendered, and a Blade directive that only broke when a template
 * compiled.
 *
 * DELIBERATELY NOT `DatabaseMigrations`
 * ------------------------------------
 * That trait runs `migrate:fresh`, which drops every table on the connected
 * database. Dusk drives a real HTTP server, so that connection is the served
 * application's — and this suite destroyed a development database exactly that
 * way once already (IMP-135). `DuskTestCase` also refuses to run against
 * anything but the disposable database.
 *
 * Fixtures are therefore built fresh per test with unique identifiers rather
 * than by resetting the world.
 */
class PigPoolFlowTest extends DuskTestCase
{
    /**
     * A fresh browser per test.
     *
     * Dusk pools browsers and reuses them across tests in a class, so viewport
     * size, cookies and scroll position leak from one to the next. Every test
     * here passed alone and two failed when the class ran in order — the
     * classic symptom. Closing between tests makes each one independent, which
     * is what they claim to be.
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

        return $org;
    }

    protected function admin(Organization $org): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching(Permission::pluck('id')->all());

        $user = User::withoutGlobalScopes()->firstOrNew(['email' => 'pigpool-admin@example.com']);

        $user->forceFill([
            'name'              => 'Pig Pool Admin',
            'password'          => bcrypt('secret'),
            'role'              => 'admin',
            'role_id'           => $role->id,
            'organization_id'   => $org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /** @return list<PigManagement> */
    protected function standardPigs(Organization $org, int $count): array
    {
        $pigs = [];

        for ($i = 0; $i < $count; $i++) {
            $pigs[] = PigManagement::create([
                'organization_id' => $org->id,
                // Explicit: local_id is generated per organisation but its
                // unique index is global (recorded in the report).
                'local_id'        => random_int(1_000_000, 99_999_999),
                'ear_tag_number'  => 'DUSK-' . $this->suffix() . '-' . $i,
                'breed'           => 'Large White',
                'gender'          => $i % 2 === 0 ? 'male' : 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);
        }

        return $pigs;
    }

    /**
     * A published pooled listing with an order waiting on it.
     *
     * @return array{0: StorefrontListing, 1: StorefrontOrder, 2: StorefrontOrderItem, 3: list<PigManagement>}
     */
    protected function pooledOrder(Organization $org, User $staff, int $poolSize, int $quantity): array
    {
        $suffix = $this->suffix();

        $listing = StorefrontListing::create([
            'organization_id'    => $org->id,
            'reference'          => 'LIST-DUSK-' . $suffix,
            'title'              => 'Market-ready live pigs ' . $suffix,
            'slug'               => 'dusk-pigs-' . strtolower($suffix),
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'sale_mode'          => StorefrontListing::MODE_FIXED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '900.00',
            'quantity_available' => '0',
            'status'             => StorefrontListing::STATUS_PUBLISHED,
            'published_at'       => now(),
        ]);

        $pigs = $this->standardPigs($org, $poolSize);

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, array_column($pigs, 'id'), $staff);

        $order = StorefrontOrder::create([
            'organization_id'   => $org->id,
            'reference'         => 'SO-DUSK-' . $suffix,
            'status'            => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'    => StorefrontOrder::PAY_COD,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => (900 * $quantity) . '.00', 'delivery_fee' => '0.00',
            'total' => (900 * $quantity) . '.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ]);

        $item = StorefrontOrderItem::create([
            'organization_id' => $org->id,
            'order_id'        => $order->id,
            'listing_id'      => $listing->id,
            'kind'            => StorefrontListing::KIND_LIVE_PIG,
            'description'     => 'Market-ready live pigs ' . $suffix,
            'quantity'        => (string) $quantity,
            'quantity_unit'   => 'head',
            'price_basis'     => 'fixed',
            'unit_price'      => '900.00',
            'line_total'      => (900 * $quantity) . '.00',
        ]);

        return [$listing, $order, $item, $pigs];
    }

    /* ================================================================== */

    /**
     * 1. The push page offers Standard Stock to pool.
     */
    public function test_the_push_page_shows_a_standard_stock_selector(): void
    {
        $org   = $this->organisation();
        $staff = $this->admin($org);
        $pigs  = $this->standardPigs($org, 3);

        $this->browse(function (Browser $browser) use ($staff, $pigs) {
            $browser->loginAs($staff)
                ->visit('/storefront-admin/push')
                ->waitForText('Standard Stock for this listing')
                ->assertSee('Standard Stock for this listing')
                ->assertSee($pigs[0]->ear_tag_number)
                // The picker must actually be tickable, not just printed.
                ->assertPresent('input[name="pool_pig_ids[]"]');
        });
    }

    /**
     * 2. The approval page requires the exact number of animals.
     */
    public function test_the_approval_page_requires_the_exact_pig_count(): void
    {
        $org   = $this->organisation();
        $staff = $this->admin($org);

        [, $order, , $pigs] = $this->pooledOrder($org, $staff, poolSize: 4, quantity: 2);

        $this->browse(function (Browser $browser) use ($staff, $order, $pigs) {
            // A tall window so the whole right-hand column is on screen. The
            // controls sit below the fold at the default size, and a click on
            // an element outside the viewport gets intercepted by the sticky
            // header — a harness problem, not an application one.
            $browser->resize(1400, 1800)
                ->loginAs($staff)
                ->visit('/storefront-admin/orders/' . $order->id)
                ->waitForText('Choose the pigs for')
                ->assertSee('still to choose')
                // Tick ONE when two are needed, and save.
                //
                // Scrolled into view before clicking: the panel sits in the
                // right-hand column below the fold, and a click on an element
                // that is not in the viewport is intercepted by whatever is.
                ->check('input[value="' . $pigs[0]->id . '"]')
                ->press('Save selection')
                ->waitForText('Select exactly 2', 15)
                ->assertSee('Select exactly 2');
        });
    }

    /**
     * 3 and 4. Approving sells the animals: they appear in Sales and leave
     *          active Standard Stock.
     */
    public function test_approving_sells_the_chosen_animals_and_they_appear_in_sales(): void
    {
        $org   = $this->organisation();
        $staff = $this->admin($org);

        [, $order, , $pigs] = $this->pooledOrder($org, $staff, poolSize: 3, quantity: 2);

        $chosenTags = [$pigs[0]->ear_tag_number, $pigs[1]->ear_tag_number];

        $this->browse(function (Browser $browser) use ($staff, $order, $pigs, $chosenTags) {
            $browser->resize(1400, 1800)
                ->loginAs($staff)
                ->visit('/storefront-admin/orders/' . $order->id)
                ->waitForText('Choose the pigs for')
                ->check('input[value="' . $pigs[0]->id . '"]')
                ->check('input[value="' . $pigs[1]->id . '"]')
                ->press('Save selection')
                ->waitForText('Pigs selected', 15)
                // Now confirm — this is the irreversible step.
                ->press('Confirm order')
                ->waitForText('Pigs sold for', 20)
                ->assertDontSee('Select the exact pigs');

            // 3. /sales shows the created sales.
            $browser->visit('/sales')
                ->waitForText('Sales')
                ->assertSee($chosenTags[0])
                ->assertSee($chosenTags[1]);

            // 4. And the animals are no longer active Standard Stock.
            $browser->visit('/standard-stocks')
                ->pause(600)
                ->assertDontSee($chosenTags[0]);
        });

        // The ledger, checked directly rather than through a screen.
        $this->assertSame(2, PigSale::whereIn('pig_id', [$pigs[0]->id, $pigs[1]->id])->count());
        $this->assertSoftDeleted('pigs_management', ['id' => $pigs[0]->id]);
        $this->assertNull(PigManagement::find($pigs[2]->id)?->deleted_at, 'The unchosen animal must be untouched.');
    }

    /**
     * 5. The approval UI is usable on a phone.
     *
     * Same measurement as MobileViewportTest: the page must not scroll
     * sideways, because a checkbox list that runs off the screen cannot be used
     * with a thumb.
     */
    public function test_the_approval_screen_works_on_a_phone(): void
    {
        $org   = $this->organisation();
        $staff = $this->admin($org);

        [, $order, , ] = $this->pooledOrder($org, $staff, poolSize: 5, quantity: 3);

        $this->browse(function (Browser $browser) use ($staff, $order) {
            $browser->resize(390, 844)
                ->loginAs($staff)
                ->visit('/storefront-admin/orders/' . $order->id)
                ->waitForText('Choose the pigs for')
                ->assertPresent('input[name="pig_ids[]"]');

            $overflow = $browser->script([
                'return document.documentElement.scrollWidth - document.documentElement.clientWidth;',
            ])[0];

            $this->assertLessThanOrEqual(
                4,
                $overflow,
                "The approval screen overflows by {$overflow}px at 390px wide, which puts the "
                . 'selection controls off-screen on a phone.',
            );
        });
    }
}
