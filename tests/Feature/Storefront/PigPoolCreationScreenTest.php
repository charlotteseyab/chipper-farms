<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Storefront\Services\StorefrontPigPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Building a pig pool through the push-to-storefront page.
 *
 * The services could already build a pool; until this screen existed there was
 * no way for a farm to do it, which made the whole flow unreachable in practice.
 *
 * The page keeps both shapes a pig listing can take. Arriving from one animal's
 * record (`?pig=`) still creates a listing for that animal. Arriving without one
 * offers Standard Stock to pool, which is the new shape.
 */
class PigPoolCreationScreenTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
    }

    /** @return list<PigManagement> */
    protected function makeStandardPigs(int $count): array
    {
        $pigs = [];

        for ($i = 0; $i < $count; $i++) {
            $pigs[] = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'PUSH-' . strtoupper(uniqid()),
                'breed'           => 'Landrace',
                'gender'          => 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);
        }

        return $pigs;
    }

    /** @return array<string, mixed> */
    protected function listingPayload(array $pigIds): array
    {
        return [
            // Staff pick a CATEGORY; kind, sale mode and pricing basis are
            // derived from it, so the form no longer asks for them.
            'source_category'    => \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_STANDARD,
            'title'              => 'Market-ready live pigs',
            'fixed_price'        => 900,
            'currency_code'      => 'GHS',
            'quantity_available' => count($pigIds) ?: 1,
            'quantity_unit'      => 'head',
            'pool_pig_ids'       => $pigIds,
        ];
    }

    /* ================================================================== */

    public function test_the_push_page_offers_standard_stock_to_pool(): void
    {
        $pigs = $this->makeStandardPigs(3);

        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.push.create'))
            ->assertOk()
            ->assertSee('Standard Stock for this listing')
            ->assertSee($pigs[0]->ear_tag_number)
            ->assertSee($pigs[2]->ear_tag_number);
    }

    public function test_a_listing_is_created_from_the_selected_pigs(): void
    {
        $pigs   = $this->makeStandardPigs(5);
        $chosen = [$pigs[0]->id, $pigs[1]->id, $pigs[2]->id];

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), $this->listingPayload($chosen))
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Market-ready live pigs')->firstOrFail();

        $this->assertSame(
            3,
            StorefrontListingPig::where('storefront_listing_id', $listing->id)->count(),
        );

        // Quantity follows the animals rather than the number in the form.
        $this->assertSame(
            3,
            $this->app->make(StorefrontPigPoolService::class)->availableCount($listing),
        );
    }

    /**
     * The quantity is derived, not trusted.
     */
    public function test_the_listing_quantity_comes_from_the_pigs_not_the_form(): void
    {
        $pigs   = $this->makeStandardPigs(4);
        $chosen = [$pigs[0]->id, $pigs[1]->id];

        $payload = $this->listingPayload($chosen);
        // Staff typed a number that disagrees with what they ticked.
        $payload['quantity_available'] = 99;

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), $payload)
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'Market-ready live pigs')->firstOrFail();

        $this->assertSame(
            '2.0000',
            (string) $listing->fresh()->quantity_available,
            'The animals decide the quantity; a mistyped number must not oversell the farm.',
        );
    }

    /**
     * A pig already on the shop cannot be pooled again, and the listing that
     * was created says so rather than pretending it holds animals.
     */
    public function test_pooling_a_pig_that_is_already_listed_is_refused_clearly(): void
    {
        $pigs = $this->makeStandardPigs(2);

        $first = $this->makePublishedListing([
            'kind'          => StorefrontListing::KIND_LIVE_PIG,
            'title'         => 'Existing listing',
            'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price'   => '900.00',
        ]);

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($first, [$pigs[0]->id], $this->makeUser('admin'));

        $this->flushSession();

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), $this->listingPayload([$pigs[0]->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        // The animal stayed with the listing that already had it.
        $this->assertSame(
            1,
            StorefrontListingPig::where('pig_id', $pigs[0]->id)
                ->whereIn('status', StorefrontListingPig::ACTIVE_STATUSES)
                ->count(),
        );
    }

    /**
     * Creating a listing with no pigs at all is still allowed — that is every
     * feed, manure and service listing in the shop.
     */
    public function test_a_listing_with_no_pigs_is_still_allowed(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.push.store'), [
                // No animal backs this one — that is what 'manual' means, and
                // it is every feed, manure and service listing in the shop.
                'source_category'    => \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_MANUAL,
                'kind'               => StorefrontListing::KIND_FEED,
                'title'              => 'A bag of feed',
                'fixed_price'        => 50,
                'currency_code'      => 'GHS',
                'quantity_available' => 20,
                'quantity_unit'      => 'bag',
            ])
            ->assertRedirect();

        $listing = StorefrontListing::where('title', 'A bag of feed')->firstOrFail();

        $this->assertSame('20.0000', (string) $listing->quantity_available);
        $this->assertSame(0, StorefrontListingPig::where('storefront_listing_id', $listing->id)->count());
    }

    /**
     * Creating listings needs the permission, whatever the page shows.
     */
    public function test_building_a_pool_requires_permission_to_manage_listings(): void
    {
        $pigs = $this->makeStandardPigs(2);

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.admin.push.store'), $this->listingPayload([$pigs[0]->id]))
            ->assertForbidden();

        $this->assertSame(0, StorefrontListingPig::count());
    }
}
