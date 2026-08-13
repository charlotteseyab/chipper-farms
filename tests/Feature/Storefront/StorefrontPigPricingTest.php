<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\Storefront\StorefrontListing;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Storefront pig pricing comes from the farm's setup, on the animal's real weight.
 *
 * TWO RULES, BOTH OF THEM MONEY
 * -----------------------------
 * 1. The rate is the farm's own `sales.pricing.*` configuration — the same
 *    numbers `RecordPigSaleService` uses at the gate. A shop that quotes from
 *    somewhere else would contradict the farm's own price list.
 *
 * 2. The weight is the LATEST recorded weight, not
 *    `current_weight_kg_cache`. That cache is written at intake and never
 *    updated when a pig is weighed, so on any animal weighed since arrival it is
 *    stale. The push page was reading it, which priced listings off an intake
 *    figure that could be months out of date.
 */
class StorefrontPigPricingTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();

        Setting::set('sales.pricing.live_weight_price', 30);
        Setting::set('sales.pricing.carcass_weight_price', 50);
    }

    protected function pricing(): StorefrontPigPricingService
    {
        return $this->app->make(StorefrontPigPricingService::class);
    }

    protected function pricedPig(?float $cacheWeight = null): PigManagement
    {
        return PigManagement::create([
            'organization_id'         => $this->org->id,
            'ear_tag_number'          => 'PRC-' . strtoupper(uniqid()),
            'breed'                   => 'Large White',
            'gender'                  => 'male',
            'stock_type'              => 'standard',
            'date_of_birth'           => now()->subMonths(8)->toDateString(),
            'code'                    => 'PIG-' . uniqid(),
            'current_weight_kg_cache' => $cacheWeight,
        ]);
    }

    protected function weigh(PigManagement $pig, float $kg, int $daysAgo = 0): PigWeight
    {
        return PigWeight::create([
            'pig_id'     => $pig->id,
            'weight'     => $kg,
            'weigh_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    /* ================================================================== */

    public function test_the_rates_come_from_the_farms_setup_configuration(): void
    {
        $this->assertSame('30.0000', $this->pricing()->livePricePerKg());
        $this->assertSame('50.0000', $this->pricing()->carcassPricePerKg());
    }

    /**
     * The finding this service exists for.
     */
    public function test_the_latest_recorded_weight_wins_over_the_intake_cache(): void
    {
        // Arrived at 60kg and was never re-cached...
        $pig = $this->pricedPig(cacheWeight: 60);

        // ...but has been weighed twice since.
        $this->weigh($pig, 88.0, daysAgo: 30);
        $this->weigh($pig, 96.5, daysAgo: 2);

        $this->assertSame(
            '96.5000',
            $this->pricing()->latestWeightKg($pig->fresh()),
            'Pricing must use the most recent weight record, not the stale intake cache.',
        );

        // 96.5 × 30 = 2895.00, not 60 × 30 = 1800.00
        $this->assertSame('2895.00', $this->pricing()->liveValueOf($pig->fresh()));
    }

    /**
     * Two weights on the same day: the later record is the correction.
     *
     * Ordering by `weigh_date` alone left this undefined, and the two engines
     * disagreed — SQLite returned the newer row, MariaDB the older one, so the
     * same animal was priced differently depending on where the code ran. A pig
     * re-weighed after a misread scale was being priced off the misreading.
     */
    public function test_a_second_weight_on_the_same_day_is_the_one_that_counts(): void
    {
        // TWO animals, and the correction written LAST.
        //
        // The shape matters. Eager loading fetches every weight for the whole
        // set in one query, and with the dates tied the engine returns them in
        // primary-key order — so the correction, written after the second
        // animal's first weighing, sits behind the reading it corrects. A
        // single pig with two adjacent rows did NOT reproduce this: the test
        // passed with the tie-break removed, which is how I found out it was
        // proving nothing.
        $corrected = $this->pricedPig();
        $other     = $this->pricedPig();

        $this->weigh($corrected, 90.0, daysAgo: 0);
        $this->weigh($other, 110.0, daysAgo: 0);
        // The scale was misread; re-weighed the same day.
        $this->weigh($corrected, 96.0, daysAgo: 0);

        // Read through the EAGER-LOADED relation, which is the path the pricing
        // service actually takes — it loads `with('latestWeight')` for a whole
        // pool at once.
        $loaded = PigManagement::query()
            ->with('latestWeight')
            ->whereKey([$corrected->getKey(), $other->getKey()])
            ->get()
            ->keyBy('id');

        $this->assertSame(
            '96.0000',
            $this->pricing()->latestWeightKg($loaded[$corrected->getKey()]),
            'A correction recorded the same day must win over the reading it corrects.',
        );
    }

    public function test_the_cache_is_used_only_when_the_animal_has_never_been_weighed(): void
    {
        $pig = $this->pricedPig(cacheWeight: 72);

        $this->assertSame('72.0000', $this->pricing()->latestWeightKg($pig));
    }

    public function test_an_unweighed_animal_is_reported_rather_than_priced_at_zero(): void
    {
        $pig = $this->pricedPig();

        $this->assertNull($this->pricing()->latestWeightKg($pig));
        $this->assertNull($this->pricing()->liveValueOf($pig));

        $summary = $this->pricing()->summarise([$pig]);

        $this->assertSame(1, $summary['count']);
        $this->assertSame(0, $summary['weighed_count']);
        $this->assertContains($pig->ear_tag_number, $summary['unweighed']);
    }

    public function test_a_selection_is_summarised_for_the_picker(): void
    {
        $a = $this->pricedPig(); $this->weigh($a, 80);
        $b = $this->pricedPig(); $this->weigh($b, 100);
        $c = $this->pricedPig(); $this->weigh($c, 120);

        $summary = $this->pricing()->summarise([$a->fresh(), $b->fresh(), $c->fresh()]);

        $this->assertSame(3, $summary['count']);
        $this->assertSame('300.0000', $summary['total_weight_kg']);
        $this->assertSame('80.0000', $summary['min_weight_kg']);
        $this->assertSame('120.0000', $summary['max_weight_kg']);
        $this->assertSame('100.0000', $summary['average_weight_kg']);

        // 300 × 30
        $this->assertSame('9000.00', $summary['estimated_total']);
        // The per-animal range the shop shows instead of one misleading price.
        $this->assertSame('2400.00', $summary['min_estimated_price']);
        $this->assertSame('3600.00', $summary['max_estimated_price']);
    }

    /**
     * The listing carries the picture, so the shop never has to recompute it
     * per page view and a price change tomorrow does not silently reprice it.
     */
    public function test_the_listing_snapshot_follows_the_animals(): void
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => StorefrontPigPricingService::CATEGORY_STANDARD,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
        ]);

        $a = $this->pricedPig(); $this->weigh($a, 90);
        $b = $this->pricedPig(); $this->weigh($b, 110);

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, [$a->id, $b->id], $this->makeUser('admin'));

        $this->pricing()->refreshListingSnapshot($listing);

        $listing->refresh();

        $this->assertSame('30.0000', (string) $listing->live_price_per_kg_snapshot);
        $this->assertSame('50.0000', (string) $listing->carcass_price_per_kg_snapshot);
        $this->assertSame('200.0000', (string) $listing->total_live_weight_kg_snapshot);
        $this->assertSame('6000.00', (string) $listing->estimated_total_amount);
        $this->assertSame('2700.00', (string) $listing->min_estimated_pig_price);
        $this->assertSame('3300.00', (string) $listing->max_estimated_pig_price);

        // Carcass capacity is kilograms and is NOT quantity_available.
        $this->assertSame('200.0000', (string) $listing->carcass_capacity_kg);
        $this->assertSame('2.0000', (string) $listing->quantity_available);
    }

    /**
     * Two farms, two price lists.
     */
    public function test_each_farm_prices_from_its_own_setup(): void
    {
        $this->assertSame('30.0000', $this->pricing()->livePricePerKg());

        $otherFarm = \App\Models\Organization::create(['slug' => 'other-price-farm', 'name' => 'Other Farm']);
        $this->app->make(\App\Support\CurrentOrganization::class)->bind($otherFarm);

        Setting::set('sales.pricing.live_weight_price', 42);

        $this->assertSame('42.0000', $this->pricing()->livePricePerKg());

        $this->app->make(\App\Support\CurrentOrganization::class)->bind($this->org);

        $this->assertSame(
            '30.0000',
            $this->pricing()->livePricePerKg(),
            "One farm's price change must not move another farm's.",
        );
    }
}
