<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Storefront\Services\StorefrontPigAllocationService;
use App\Storefront\Services\StorefrontPigPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Making the pool visible where decisions are made.
 *
 * Two screens, one purpose: nobody should be surprised. The listing page shows
 * which animals back its quantity, so "why does the shop say 3?" has an answer.
 * The direct-sale form says when the shop is advertising the animal in front of
 * you, because finding out afterwards — from a listing that quietly has one
 * fewer pig — is worse than being told.
 *
 * The sale itself is still allowed. A farm-gate sale is a real sale and the shop
 * does not get a veto. The one exception is an animal already promised to a
 * customer, which is refused rather than warned about.
 */
class PigPoolVisibilityTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();
    }

    /** @return list<PigManagement> */
    protected function makeStandardPigs(int $count): array
    {
        $pigs = [];

        for ($i = 0; $i < $count; $i++) {
            $pigs[] = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'VIS-' . strtoupper(uniqid()),
                'breed'           => 'Hampshire',
                'gender'          => 'male',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);
        }

        return $pigs;
    }

    protected function pooledListing(int $size): array
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'title'              => 'Market-ready live pigs',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '900.00',
            'quantity_available' => '0',
        ]);

        $pigs = $this->makeStandardPigs($size);

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, array_column($pigs, 'id'), $this->makeUser('admin'));

        return [$listing, $pigs];
    }

    /* ================================================================== */
    /* The listing page                                                   */
    /* ================================================================== */

    public function test_the_listing_page_shows_the_animals_behind_its_quantity(): void
    {
        [$listing, $pigs] = $this->pooledListing(3);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.listings.show', $listing))
            ->assertOk()
            ->assertSee('Pigs in this listing')
            ->assertSee($pigs[0]->ear_tag_number)
            ->assertSee($pigs[2]->ear_tag_number)
            ->assertSee('3 available');
    }

    public function test_a_listing_without_a_pool_shows_no_such_panel(): void
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'title'              => 'A bag of feed',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '50.00',
            'quantity_available' => '10',
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.listings.show', $listing))
            ->assertOk()
            ->assertDontSee('Pigs in this listing');
    }

    /**
     * A sold animal stays on the page as history, with its sale traceable.
     */
    public function test_a_sold_animal_remains_visible_as_history(): void
    {
        [$listing, $pigs] = $this->pooledListing(2);
        $staff = $this->makeUser('admin');

        $sale = $this->app->make(\App\Services\Sales\RecordPigSaleService::class)->execute(
            $pigs[0],
            $staff,
            [
                'sale_date'   => now()->toDateString(),
                'sale_price'  => 900,
                'weight_type' => 'live',
                'sale_weight' => 92,
                'buyer_name'  => 'Walk-in buyer',
            ],
        );

        $this->actingAs($staff)
            ->get(route('storefront.admin.listings.show', $listing))
            ->assertOk()
            ->assertSee($pigs[0]->ear_tag_number)
            ->assertSee('sale #' . $sale->id)
            ->assertSee('1 available');
    }

    /* ================================================================== */
    /* The direct sale form                                               */
    /* ================================================================== */

    public function test_the_sale_form_warns_when_the_shop_is_offering_this_pig(): void
    {
        [$listing, $pigs] = $this->pooledListing(2);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('pigs.sale-form', $pigs[0]->id))
            ->assertOk()
            ->assertSee('This pig is on the shop')
            ->assertSee('Market-ready live pigs')
            // The sale is still offered — the shop does not get a veto.
            ->assertSee('Record Pig Sale');
    }

    public function test_the_sale_form_is_quiet_for_a_pig_that_is_not_listed(): void
    {
        $pigs = $this->makeStandardPigs(1);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('pigs.sale-form', $pigs[0]->id))
            ->assertOk()
            ->assertDontSee('This pig is on the shop');
    }

    /**
     * The hard invariant: an animal a customer has been promised cannot be
     * sold out from under them.
     */
    public function test_a_pig_committed_to_an_order_cannot_be_sold_directly(): void
    {
        [$listing, $pigs] = $this->pooledListing(3);
        $staff = $this->makeUser('admin');

        $order = StorefrontOrder::create([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-VIS-1',
            'status'            => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'    => StorefrontOrder::PAY_COD,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '900.00', 'delivery_fee' => '0.00', 'total' => '900.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ]);

        $item = StorefrontOrderItem::create([
            'organization_id' => $this->org->id,
            'order_id'        => $order->id,
            'listing_id'      => $listing->id,
            'kind'            => StorefrontListing::KIND_LIVE_PIG,
            'description'     => 'Market-ready live pigs',
            'quantity'        => '1',
            'quantity_unit'   => 'head',
            'price_basis'     => 'fixed',
            'unit_price'      => '900.00',
            'line_total'      => '900.00',
        ]);

        $this->app->make(StorefrontPigAllocationService::class)
            ->selectForOrderItem($item, [$pigs[0]->id], $staff);

        // The controller sends them back with the reason rather than rendering
        // a form they must not use.
        $this->actingAs($staff)
            ->get(route('pigs.sale-form', $pigs[0]->id))
            ->assertRedirect()
            ->assertSessionHasErrors('sale');

        $this->assertSame(
            0,
            PigSale::where('pig_id', $pigs[0]->id)->count(),
            'A pig promised to a customer must not be sellable at the gate.',
        );
    }

    /**
     * An animal in the pool but NOT committed is still sellable — the whole
     * point of the warning rather than a block.
     */
    public function test_an_uncommitted_listed_pig_is_still_sellable(): void
    {
        [$listing, $pigs] = $this->pooledListing(2);
        $staff = $this->makeUser('admin');

        $this->actingAs($staff)
            ->get(route('pigs.sale-form', $pigs[0]->id))
            ->assertOk()
            ->assertSessionHasNoErrors();

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

        $this->assertNotNull($sale->id);

        // And the shop now offers one fewer.
        $this->assertSame(
            1,
            $this->app->make(StorefrontPigPoolService::class)->availableCount($listing->fresh()),
        );
    }
}
