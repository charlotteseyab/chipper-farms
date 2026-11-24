<?php

namespace Tests\Feature\Storefront;

use App\Models\MedicationAdministration;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Storefront\Services\Exceptions\CartException;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService as Pricing;
use App\Storefront\Services\StorefrontPurchaseLineService;
use App\Storefront\Support\CartOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A pig treated AFTER it was put on the shop.
 *
 * Listing already refuses an animal under medicine withdrawal. What this covers
 * is the animal that was clean when pooled and then treated: it must drop off
 * the shop at once — not offered, not counted, not addable to a basket — and
 * come back on its own once the withdrawal has run out.
 */
class PigPoolWithdrawalTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected StorefrontListing $listing;

    /** @var list<PigManagement> */
    protected array $pigs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();

        Setting::set('sales.pricing.live_weight_price', 30);
        Setting::set('sales.pricing.carcass_weight_price', 50);

        $this->listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => Pricing::CATEGORY_STANDARD,
            'title'              => 'Market-ready pigs',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
            'pickup_allowed'     => true,
        ]);

        foreach ([100, 120] as $i => $kg) {
            $pig = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'WD-' . $i . '-' . strtoupper(uniqid()),
                'breed'           => 'Landrace',
                'gender'          => 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);

            PigWeight::create(['pig_id' => $pig->id, 'weight' => $kg, 'weigh_date' => now()->toDateString()]);

            $this->pigs[] = $pig;
        }

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($this->listing, collect($this->pigs)->pluck('id')->all(), $this->makeUser('admin'));

        $this->listing->refresh();
    }

    protected function treat(PigManagement $pig, int $withdrawalDays = 14): MedicationAdministration
    {
        return MedicationAdministration::create([
            'pig_id'                 => $pig->id,
            'administration_date'    => now()->toDateString(),
            'medication_name'        => 'Amoxicillin',
            'dosage'                 => '5 ml',
            'withdrawal_period_days' => $withdrawalDays,
        ]);
    }

    protected function rowFor(PigManagement $pig): StorefrontListingPig
    {
        return StorefrontListingPig::where('storefront_listing_id', $this->listing->id)
            ->where('pig_id', $pig->id)->firstOrFail();
    }

    public function test_a_dose_with_withdrawal_takes_the_pig_off_the_shop_at_once(): void
    {
        $this->assertSame(2, (int) $this->listing->quantity_available);

        $this->treat($this->pigs[0]);

        $listing = $this->listing->fresh();
        $this->assertSame(1, (int) $listing->quantity_available);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $listing->status);

        // Still in the pool — withdrawal is temporary, not a removal.
        $this->assertSame(StorefrontListingPig::STATUS_AVAILABLE, $this->rowFor($this->pigs[0])->status);

        $this->get(route('storefront.listing', $listing->slug))
            ->assertOk()
            ->assertDontSee($this->pigs[0]->ear_tag_number)
            ->assertSee($this->pigs[1]->ear_tag_number);
    }

    public function test_a_listing_whose_every_pig_is_withdrawn_is_sold_out(): void
    {
        $this->treat($this->pigs[0]);
        $this->treat($this->pigs[1]);

        $listing = $this->listing->fresh();
        $this->assertSame(0, (int) $listing->quantity_available);
        $this->assertSame(StorefrontListing::STATUS_SOLD_OUT, $listing->status);
    }

    public function test_a_withdrawn_pig_cannot_be_added_to_a_basket_from_a_stale_page(): void
    {
        $row = $this->rowFor($this->pigs[0]);
        $this->treat($this->pigs[0]);

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('is not available for sale right now');

        $this->app->make(StorefrontPurchaseLineService::class)
            ->addLiveLines(CartOwner::from($this->makeUser('customer')), $this->listing->fresh(), [$row->id]);
    }

    public function test_the_buyer_is_not_told_the_medical_reason(): void
    {
        $row = $this->rowFor($this->pigs[0]);
        $this->treat($this->pigs[0]);

        try {
            $this->app->make(StorefrontPurchaseLineService::class)
                ->addLiveLines(CartOwner::from($this->makeUser('customer')), $this->listing->fresh(), [$row->id]);
            $this->fail('A withdrawn pig reached the basket.');
        } catch (CartException $e) {
            $this->assertStringNotContainsString('Amoxicillin', $e->getMessage());
            $this->assertStringNotContainsString('withdrawal', strtolower($e->getMessage()));
        }
    }

    public function test_the_pig_comes_back_once_the_withdrawal_has_run_out(): void
    {
        $this->treat($this->pigs[0], withdrawalDays: 7);
        $this->treat($this->pigs[1], withdrawalDays: 7);
        $this->assertSame(StorefrontListing::STATUS_SOLD_OUT, $this->listing->fresh()->status);

        $this->travel(9)->days();
        $this->artisan('storefront:sync-availability')->assertSuccessful();

        $listing = $this->listing->fresh();
        $this->assertSame(2, (int) $listing->quantity_available);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $listing->status);
    }

    public function test_voiding_the_dose_puts_the_pig_back(): void
    {
        $dose = $this->treat($this->pigs[0]);
        $this->assertSame(1, (int) $this->listing->fresh()->quantity_available);

        $dose->forceFill(['voided_at' => now()])->save();

        $this->assertSame(2, (int) $this->listing->fresh()->quantity_available);
    }
}
