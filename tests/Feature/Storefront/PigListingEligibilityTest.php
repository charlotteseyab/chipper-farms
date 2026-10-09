<?php

namespace Tests\Feature\Storefront;

use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\PigManagement;
use App\Models\Storefront\StorefrontListing;
use App\Storefront\Services\Exceptions\ListingNotEligibleException;
use App\Storefront\Services\ListingService;
use App\Storefront\Services\PigListingEligibilityService;
use App\Storefront\Services\PushToStorefrontService;
use App\Storefront\Support\PushToStorefrontData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 11 — may this animal be offered for sale? (Blueprint §35 step 2.)
 *
 * §35 requires the check to confirm the pig is "alive, unsold, not already
 * actively listed, not under withdrawal/health hold, has latest weight if
 * price-by-kg".
 *
 * The withdrawal tests are the ones that matter most. Selling an animal inside
 * its medicine withdrawal period puts drug residue into food; a storefront that
 * lists such an animal with one click makes it likelier, not less.
 */
class PigListingEligibilityTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected PigListingEligibilityService $eligibility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eligibility = $this->app->make(PigListingEligibilityService::class);
    }

    protected function treat(PigManagement $pig, array $overrides = []): MedicationAdministration
    {
        return MedicationAdministration::create(array_merge([
            'pig_id'              => $pig->id,
            'administration_date' => now()->subDays(2)->toDateString(),
            'medication_name'     => 'Oxytetracycline',
            'dosage'              => '10ml',
            'purpose'             => 'Respiratory infection',
        ], $overrides));
    }

    /* ---------------------------------------------------------------- */
    /* Medicine withdrawal                                              */
    /* ---------------------------------------------------------------- */

    public function test_a_pig_inside_its_withdrawal_period_cannot_be_listed(): void
    {
        $pig = $this->makePig();

        // 28-day withdrawal, given two days ago.
        $this->treat($pig, ['withdrawal_period_days' => 28]);

        $result = $this->eligibility->check($pig);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('withdrawal', implode(' ', $result['blockers']));
        // The date it clears is named, so staff know when to come back.
        $this->assertStringContainsString(
            now()->subDays(2)->addDays(28)->format('d M Y'),
            implode(' ', $result['blockers']),
        );
    }

    public function test_a_pig_whose_withdrawal_has_passed_can_be_listed(): void
    {
        $pig = $this->makePig();

        $this->treat($pig, [
            'administration_date'    => now()->subDays(40)->toDateString(),
            'withdrawal_period_days' => 28,
        ]);

        $this->assertTrue($this->eligibility->check($pig)['eligible']);
    }

    /**
     * The last day of the period still counts: a 28-day withdrawal given on the
     * 1st clears on the 29th, not the 28th. Off-by-one here is a day of residue.
     */
    public function test_the_final_day_of_the_withdrawal_period_still_blocks(): void
    {
        $pig = $this->makePig();

        $this->treat($pig, [
            'administration_date'    => now()->subDays(28)->toDateString(),
            'withdrawal_period_days' => 28,
        ]);

        $this->assertFalse(
            $this->eligibility->check($pig)['eligible'],
            'A withdrawal ending today must still block today.',
        );
    }

    public function test_a_treatment_with_no_withdrawal_period_does_not_block_but_is_flagged(): void
    {
        $pig = $this->makePig();

        $this->treat($pig, ['withdrawal_period_days' => null, 'medication_name' => 'Vitamin B complex']);

        $result = $this->eligibility->check($pig);

        // Most treatments genuinely carry no withdrawal. Blocking on a null
        // would make farms ignore the warning.
        $this->assertTrue($result['eligible']);
        // But the gap is visible rather than silently permissive.
        $this->assertNotEmpty($result['cautions']);
        $this->assertStringContainsString('Vitamin B complex', implode(' ', $result['cautions']));
    }

    public function test_an_old_treatment_with_no_withdrawal_period_is_not_flagged(): void
    {
        $pig = $this->makePig();

        $this->treat($pig, [
            'administration_date'    => now()->subDays(200)->toDateString(),
            'withdrawal_period_days' => null,
        ]);

        $this->assertSame([], $this->eligibility->check($pig)['cautions']);
    }

    public function test_the_withdrawal_end_date_is_derived_from_what_was_entered(): void
    {
        $pig = $this->makePig();

        $administration = $this->treat($pig, [
            'administration_date'    => '2026-03-01',
            'withdrawal_period_days' => 21,
        ]);

        $this->assertSame('2026-03-22', $administration->fresh()->withdrawal_ends_on->toDateString());

        // And it follows a correction rather than going stale.
        $administration->update(['withdrawal_period_days' => 7]);
        $this->assertSame('2026-03-08', $administration->fresh()->withdrawal_ends_on->toDateString());

        // Clearing the days clears the derived date too.
        $administration->update(['withdrawal_period_days' => null]);
        $this->assertNull($administration->fresh()->withdrawal_ends_on);
    }

    /* ---------------------------------------------------------------- */
    /* The recording form (IMP-090)                                     */
    /* ---------------------------------------------------------------- */

    /**
     * The input the enforcement was waiting on.
     *
     * Phase 11 built the guard and left IMP-090 open: there was no field on the
     * medication form for a withdrawal period, so every treatment recorded in
     * production read as "no withdrawal" and the guard could never fire. This
     * drives the real HTTP path to prove the value now lands.
     */
    public function test_a_withdrawal_period_recorded_on_the_form_blocks_a_sale(): void
    {
        $this->seed(\Database\Seeders\FarmOperationsPermissionsSeeder::class);

        $pig   = $this->makePig();
        $vet   = $this->makeUser('admin');

        // Doses are recorded against the medicine catalogue (health upgrade
        // Phase 3), which carries the label withdrawal period.
        $medicine = $this->stockedMedicine($vet, $pig, ['name' => 'Oxytetracycline', 'meat_withdrawal_days' => 28]);
        $this->actingAs($vet)->post(route('medications.store'), $this->dose($medicine, $pig, now()->subDay()))->assertRedirect();

        $administration = MedicationAdministration::where('pig_id', $pig->id)->firstOrFail();

        $this->assertSame(28, $administration->withdrawal_period_days);
        $this->assertNotNull(
            $administration->withdrawal_ends_on,
            'The derived end date is what the eligibility query reads.',
        );

        // And the guard now fires, which it could not before this field existed.
        $this->assertFalse($this->eligibility->check($pig)['eligible']);
    }

    public function test_a_treatment_recorded_with_no_withdrawal_period_still_saves(): void
    {
        $this->seed(\Database\Seeders\FarmOperationsPermissionsSeeder::class);

        $pig = $this->makePig();
        $admin = $this->makeUser('admin');
        $medicine = $this->stockedMedicine($admin, $pig, ['name' => 'Vitamin B complex', 'meat_withdrawal_days' => null]);

        $this->actingAs($admin)->post(route('medications.store'), $this->dose($medicine, $pig, now()))->assertRedirect();

        $this->assertNull(
            MedicationAdministration::where('pig_id', $pig->id)->firstOrFail()->withdrawal_ends_on,
        );
        // Most treatments carry none; the sale is not blocked.
        $this->assertTrue($this->eligibility->check($pig)['eligible']);
    }

    private function stockedMedicine(\App\Models\User $user, PigManagement $pig, array $attributes): \App\Models\Medicine
    {
        $medicine = \App\Models\Medicine::factory()->create($attributes + ['organization_id' => $pig->organization_id, 'allowed_routes' => []]);
        $this->actingAs($user)->post(route('medicines.batches.store', $medicine), [
            'batch_number' => 'L1', 'received_at' => now()->subDays(5)->toDateString(), 'expiry_date' => now()->addYear()->toDateString(), 'quantity_received' => '100',
        ])->assertSessionHasNoErrors();

        return $medicine;
    }

    private function dose(\App\Models\Medicine $medicine, PigManagement $pig, $at): array
    {
        return ['pig_ids' => [$pig->id], 'medicine_id' => $medicine->id, 'administered_at' => $at->format('Y-m-d H:i'),
            'amount_basis' => 'per_animal', 'dose_amount' => '5', 'dose_unit' => 'ml', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid()];
    }

    /* ---------------------------------------------------------------- */
    /* Health hold                                                      */
    /* ---------------------------------------------------------------- */

    public function test_an_unresolved_severe_condition_blocks_a_listing(): void
    {
        $pig = $this->makePig();

        HealthRecord::create([
            'pig_id'           => $pig->id,
            'record_date'      => now()->subDay()->toDateString(),
            'condition'        => 'Pneumonia',
            'severity'         => 'severe',
            'treatment_status' => 'ongoing',
        ]);

        $result = $this->eligibility->check($pig);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Pneumonia', implode(' ', $result['blockers']));
    }

    /**
     * A healing scrape is not a reason to refuse a sale, and blocking on it
     * would make the whole check something staff route around.
     */
    public function test_a_resolved_condition_does_not_block(): void
    {
        $pig = $this->makePig();

        HealthRecord::create([
            'pig_id'           => $pig->id,
            'record_date'      => now()->subDays(30)->toDateString(),
            'condition'        => 'Lameness',
            'severity'         => 'severe',
            'treatment_status' => 'completed',
            'resolved_at'      => now()->subDays(10)->toDateString(),
        ]);

        $this->assertTrue($this->eligibility->check($pig)['eligible']);
    }

    public function test_a_mild_unresolved_condition_with_no_open_treatment_does_not_block(): void
    {
        $pig = $this->makePig();

        HealthRecord::create([
            'pig_id'           => $pig->id,
            'record_date'      => now()->subDays(3)->toDateString(),
            'condition'        => 'Minor scrape',
            'severity'         => 'mild',
            'treatment_status' => 'completed',
        ]);

        $this->assertTrue($this->eligibility->check($pig)['eligible']);
    }

    /* ---------------------------------------------------------------- */
    /* Alive, unsold, not already listed                                */
    /* ---------------------------------------------------------------- */

    public function test_a_sold_or_removed_pig_cannot_be_listed(): void
    {
        $pig = $this->makePig();
        $pig->delete();

        $result = $this->eligibility->check($pig->fresh() ?? PigManagement::withTrashed()->find($pig->id));

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('already been sold', implode(' ', $result['blockers']));
    }

    public function test_a_pig_already_on_the_shop_cannot_be_listed_twice(): void
    {
        $pig = $this->makePig();

        $this->makePublishedListing([
            'kind'        => StorefrontListing::KIND_LIVE_PIG,
            'source_type' => PigManagement::class,
            'source_id'   => $pig->id,
        ]);

        $result = $this->eligibility->check($pig, null, $this->org->id);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('already on the shop', implode(' ', $result['blockers']));
    }

    /* ---------------------------------------------------------------- */
    /* Weight, when the price depends on it                             */
    /* ---------------------------------------------------------------- */

    public function test_a_per_kilo_listing_needs_a_weight(): void
    {
        $pig = $this->makePig(['current_weight_kg_cache' => null]);

        $result = $this->eligibility->check($pig, StorefrontListing::BASIS_LIVE);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('no recorded weight', implode(' ', $result['blockers']));
    }

    public function test_a_fixed_price_listing_does_not_need_a_weight(): void
    {
        $pig = $this->makePig(['current_weight_kg_cache' => null]);

        $this->assertTrue($this->eligibility->check($pig, StorefrontListing::BASIS_FIXED)['eligible']);
    }

    /* ---------------------------------------------------------------- */
    /* Every reason at once                                             */
    /* ---------------------------------------------------------------- */

    /**
     * A staff member standing in a barn should learn everything that is wrong
     * in one go. Finding out about the withdrawal period, waiting a fortnight,
     * and only then being told about the missing weight is the same information
     * delivered as badly as possible.
     */
    public function test_every_blocker_is_reported_together(): void
    {
        $pig = $this->makePig(['current_weight_kg_cache' => null]);

        $this->treat($pig, ['withdrawal_period_days' => 28]);
        HealthRecord::create([
            'pig_id' => $pig->id, 'record_date' => now()->toDateString(),
            'condition' => 'Pneumonia', 'severity' => 'severe', 'treatment_status' => 'ongoing',
        ]);

        $result = $this->eligibility->check($pig, StorefrontListing::BASIS_LIVE);

        $this->assertFalse($result['eligible']);
        $this->assertGreaterThanOrEqual(3, count($result['blockers']));
    }

    /* ---------------------------------------------------------------- */
    /* Enforcement, not just reporting                                  */
    /* ---------------------------------------------------------------- */

    public function test_pushing_an_ineligible_pig_is_refused(): void
    {
        $pig = $this->makePig();
        $this->treat($pig, ['withdrawal_period_days' => 28]);

        $this->expectException(ListingNotEligibleException::class);

        $this->app->make(PushToStorefrontService::class)->execute(
            $pig,
            $this->makeUser('admin'),
            $this->pushData(),
        );

        $this->assertSame(0, StorefrontListing::count());
    }

    /**
     * A listing drafted on Monday and published on Friday may have had a
     * treatment recorded in between. Checking only at push would let it go live
     * regardless — and withdrawal periods start after the paperwork, not before.
     */
    public function test_a_treatment_recorded_after_drafting_stops_the_listing_publishing(): void
    {
        $pig = $this->makePig();
        $staff = $this->makeUser('admin');

        $listing = $this->app->make(PushToStorefrontService::class)->execute($pig, $staff, $this->pushData());

        $this->assertSame(StorefrontListing::STATUS_DRAFT, $listing->status);

        // The vet comes on Wednesday.
        $this->treat($pig, ['withdrawal_period_days' => 28]);

        $this->expectException(ListingNotEligibleException::class);

        $this->app->make(ListingService::class)->publish($listing->fresh(), $staff);
    }

    public function test_an_eligible_pig_still_publishes_normally(): void
    {
        $pig   = $this->makePig();
        $staff = $this->makeUser('admin');

        $listing = $this->app->make(PushToStorefrontService::class)->execute($pig, $staff, $this->pushData());
        $listing = $this->app->make(ListingService::class)->publish($listing->fresh(), $staff);

        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $listing->status);
    }

    /**
     * The listing's own draft must not count as "already on the shop" when it
     * is the thing being published.
     */
    public function test_a_listing_does_not_block_its_own_publication(): void
    {
        $pig   = $this->makePig();
        $staff = $this->makeUser('admin');

        $listing = $this->app->make(PushToStorefrontService::class)->execute($pig, $staff, $this->pushData());

        $result = $this->eligibility->checkListing($listing->fresh());

        $this->assertTrue($result['eligible'], implode(' ', $result['blockers']));
    }

    /* ---------------------------------------------------------------- */

    protected function pushData(array $overrides = []): PushToStorefrontData
    {
        return PushToStorefrontData::fromArray(array_merge([
            'title'              => 'Weaner boar',
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'sale_mode'          => StorefrontListing::MODE_FIXED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '800.00',
            'quantity_available' => '1',
            'quantity_unit'      => 'head',
        ], $overrides));
    }
}
