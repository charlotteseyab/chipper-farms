<?php

namespace Tests\Feature\Finance\Tax;

use App\Models\Finance\TaxProfile;
use App\Models\Finance\TaxProfileComponent;
use App\Models\Organization;
use App\Services\Finance\Tax\TaxCalculation;
use App\Services\Finance\Tax\TaxCalculator;
use App\Services\Finance\Tax\TaxProfileResolver;
use App\Support\CurrentOrganization;
use Database\Seeders\GhanaTaxProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ghana tax profiles and calculation — Phase 2 (blueprint §13, §30).
 *
 * The arithmetic is the point. Ghana's 2026 reform changed VAT/NHIL/GETFund from
 * a cascade to a shared base, which moves the effective rate on GHS 100 from
 * 21.90 to 20.00. Getting that wrong overstates every invoice by 1.9% and
 * produces a VAT return that does not reconcile.
 *
 * Amounts are asserted as decimal strings throughout. Comparing floats would
 * defeat the purpose of the bcmath implementation being tested.
 */
class GhanaTaxProfileTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'cf-' . uniqid(), 'name' => 'Chipper Farms', 'is_active' => true,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->seed(GhanaTaxProfileSeeder::class);
    }

    protected function calculator(): TaxCalculator
    {
        return app(TaxCalculator::class);
    }

    protected function resolver(): TaxProfileResolver
    {
        return app(TaxProfileResolver::class);
    }

    protected function profile(string $code, string $on = '2026-06-01'): TaxProfile
    {
        $p = $this->resolver()->byCode($code, $on);
        $this->assertNotNull($p, "Expected profile [{$code}] effective on {$on}.");

        return $p;
    }

    protected function levy(TaxCalculation $calc, string $code): ?array
    {
        foreach ($calc->components as $c) {
            if ($c['code'] === $code) {
                return $c;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ */
    /* The 2026 same-base rule                                            */
    /* ------------------------------------------------------------------ */

    public function test_ghana_2026_charges_all_three_levies_on_the_same_base(): void
    {
        $calc = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '100.00');

        $this->assertSame('15.00', $this->levy($calc, 'VAT_15')['amount']);
        $this->assertSame('2.50', $this->levy($calc, 'NHIL')['amount']);
        $this->assertSame('2.50', $this->levy($calc, 'GETFUND')['amount']);

        $this->assertSame('20.00', $calc->totalTax, 'Same-base Ghana 2026 on 100.00 is 20.00 tax.');
        $this->assertSame('120.00', $calc->grossTotal);
    }

    public function test_every_2026_levy_uses_the_net_amount_as_its_base(): void
    {
        $calc = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '100.00');

        foreach ($calc->components as $c) {
            $this->assertSame(
                '100.00',
                $c['base'],
                "Levy [{$c['code']}] was charged on {$c['base']} rather than the net 100.00. "
                . 'Any levy compounding on another reproduces the abolished cascade.',
            );
        }
    }

    public function test_the_covid_levy_is_absent_from_the_2026_default(): void
    {
        $calc = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '100.00');

        $this->assertNull(
            $this->levy($calc, 'COVID_LEVY'),
            'GRA abolished the COVID-19 Health Recovery Levy. It must not appear in the 2026 default.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* The historical cascade                                             */
    /* ------------------------------------------------------------------ */

    public function test_the_pre_2026_profile_still_cascades_vat_onto_the_levies(): void
    {
        $calc = $this->calculator()->apply(
            $this->profile('GH_VAT_PRE_2026', '2024-06-01'),
            '100.00',
        );

        $this->assertSame('2.50', $this->levy($calc, 'NHIL')['amount']);
        $this->assertSame('2.50', $this->levy($calc, 'GETFUND')['amount']);
        $this->assertSame('1.00', $this->levy($calc, 'COVID_LEVY')['amount']);

        $vat = $this->levy($calc, 'VAT_15');
        $this->assertSame('106.00', $vat['base'], 'VAT cascaded onto net + NHIL + GETFund + COVID.');
        $this->assertSame('15.90', $vat['amount']);

        $this->assertSame(
            '21.90',
            $calc->totalTax,
            'The historical treatment is 21.90 on 100.00. A 2024 invoice must keep saying 21.90, '
            . 'not be silently restated at the 2026 rate of 20.00.',
        );
    }

    public function test_the_two_regimes_genuinely_differ(): void
    {
        $new = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '100.00');
        $old = $this->calculator()->apply($this->profile('GH_VAT_PRE_2026', '2024-06-01'), '100.00');

        $this->assertNotSame(
            $new->totalTax,
            $old->totalTax,
            'If both regimes produce the same figure, the same-base reform has not been implemented.',
        );
        $this->assertSame('1.90', bcsub($old->totalTax, $new->totalTax, 2));
    }

    /* ------------------------------------------------------------------ */
    /* Effective dating                                                   */
    /* ------------------------------------------------------------------ */

    public function test_a_back_dated_transaction_resolves_to_the_regime_that_applied_then(): void
    {
        $this->assertNull(
            $this->resolver()->byCode('GH_VAT_2026_STANDARD', '2024-06-01'),
            'The 2026 profile must not be selectable for a 2024 transaction.',
        );

        $this->assertNotNull($this->resolver()->byCode('GH_VAT_PRE_2026', '2024-06-01'));
    }

    public function test_the_historical_profile_is_closed_for_new_transactions(): void
    {
        $this->assertNull(
            $this->resolver()->byCode('GH_VAT_PRE_2026', '2026-06-01'),
            'The pre-2026 profile ended 2025-12-31 and must not be selectable today.',
        );
    }

    public function test_the_default_sales_profile_today_is_the_2026_standard(): void
    {
        $this->assertSame(
            'GH_VAT_2026_STANDARD',
            $this->resolver()->defaultForSales('2026-06-01')?->code,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Inclusive pricing                                                  */
    /* ------------------------------------------------------------------ */

    public function test_a_tax_inclusive_price_recovers_the_correct_net(): void
    {
        $profile = $this->profile('GH_VAT_2026_STANDARD');
        $profile->update(['price_mode' => TaxProfile::PRICE_INCLUSIVE]);
        $profile->refresh()->load('components');

        $calc = $this->calculator()->apply($profile, '120.00');

        $this->assertSame('100.00', $calc->taxableBase);
        $this->assertSame('20.00', $calc->totalTax);
        $this->assertSame('120.00', $calc->grossTotal, 'An inclusive price must round-trip exactly.');
    }

    public function test_inclusive_pricing_is_not_the_naive_subtraction(): void
    {
        $profile = $this->profile('GH_VAT_2026_STANDARD');
        $profile->update(['price_mode' => TaxProfile::PRICE_INCLUSIVE]);
        $profile->refresh()->load('components');

        $calc = $this->calculator()->apply($profile, '120.00');

        // The naive approach computes 20% OF the gross (24.00) and calls the
        // remainder net (96.00). That takes the rate off the wrong base.
        $this->assertNotSame('96.00', $calc->taxableBase);
        $this->assertNotSame('24.00', $calc->totalTax);
    }

    public function test_inclusive_pricing_round_trips_through_the_cascading_profile_too(): void
    {
        $profile = $this->profile('GH_VAT_PRE_2026', '2024-06-01');
        $profile->update(['price_mode' => TaxProfile::PRICE_INCLUSIVE]);
        $profile->refresh()->load('components');

        $calc = $this->calculator()->apply($profile, '121.90');

        $this->assertSame('100.00', $calc->taxableBase);
        $this->assertSame('21.90', $calc->totalTax);
    }

    /* ------------------------------------------------------------------ */
    /* Zero, exempt and no-tax                                            */
    /* ------------------------------------------------------------------ */

    public function test_zero_rated_charges_nothing_but_keeps_input_recovery(): void
    {
        $profile = $this->profile('GH_ZERO_RATED');
        $calc    = $this->calculator()->apply($profile, '100.00');

        $this->assertSame('0.00', $calc->totalTax);
        $this->assertSame('100.00', $calc->grossTotal);
        $this->assertTrue($profile->allowsInputRecovery());
    }

    public function test_exempt_charges_nothing_and_blocks_input_recovery(): void
    {
        $profile = $this->profile('GH_EXEMPT');
        $calc    = $this->calculator()->apply($profile, '100.00');

        $this->assertSame('0.00', $calc->totalTax);
        $this->assertFalse(
            $profile->allowsInputRecovery(),
            'Exempt and zero-rated both charge nothing, but only zero-rated allows input tax '
            . 'recovery. Collapsing them misstates the recoverable position.',
        );
    }

    public function test_no_tax_is_available_for_an_unregistered_farm(): void
    {
        $calc = $this->calculator()->apply($this->profile('NO_TAX'), '100.00');

        $this->assertSame('0.00', $calc->totalTax);
        $this->assertSame('100.00', $calc->grossTotal);
    }

    /* ------------------------------------------------------------------ */
    /* Snapshot stability                                                 */
    /* ------------------------------------------------------------------ */

    public function test_changing_a_profile_later_does_not_alter_a_stored_snapshot(): void
    {
        $calc     = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '100.00');
        $snapshot = $calc->toSnapshot();

        // The rate changes in a later year.
        TaxProfileComponent::where('code', 'VAT_15')
            ->whereIn('tax_profile_id', TaxProfile::where('code', 'GH_VAT_2026_STANDARD')->pluck('id'))
            ->update(['rate' => '20.0000']);

        $restored = TaxCalculation::fromSnapshot($snapshot);

        $this->assertSame(
            '20.00',
            $restored->totalTax,
            'A posted document must keep the tax it was charged. Recomputing from live '
            . 'configuration would silently restate history.',
        );
    }

    public function test_a_snapshot_round_trips_completely(): void
    {
        $calc     = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '250.00');
        $restored = TaxCalculation::fromSnapshot($calc->toSnapshot());

        $this->assertSame($calc->taxableBase, $restored->taxableBase);
        $this->assertSame($calc->totalTax, $restored->totalTax);
        $this->assertSame($calc->grossTotal, $restored->grossTotal);
        $this->assertSame($calc->profileCode, $restored->profileCode);
        $this->assertCount(count($calc->components), $restored->components);
    }

    /* ------------------------------------------------------------------ */
    /* Partial reversal — credit notes                                    */
    /* ------------------------------------------------------------------ */

    public function test_a_partial_return_reverses_a_proportional_share_of_each_levy(): void
    {
        $calc = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '100.00');

        $quarter = $calc->proportion('0.25');

        $this->assertSame('25.00', $quarter->taxableBase);
        $this->assertSame('3.75', $this->levy($quarter, 'VAT_15')['amount']);
        $this->assertSame('0.63', $this->levy($quarter, 'NHIL')['amount']);
        $this->assertSame('0.63', $this->levy($quarter, 'GETFUND')['amount']);
        $this->assertSame('5.01', $quarter->totalTax);
    }

    public function test_a_full_return_reverses_exactly_what_was_charged(): void
    {
        $calc = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '100.00');
        $full = $calc->proportion('1');

        $this->assertSame($calc->totalTax, $full->totalTax);
        $this->assertSame($calc->grossTotal, $full->grossTotal);
    }

    public function test_a_credit_note_reverses_the_original_regime_not_todays(): void
    {
        // A 2024 sale under the cascading regime.
        $original = $this->calculator()->apply(
            $this->profile('GH_VAT_PRE_2026', '2024-06-01'),
            '100.00',
        );
        $snapshot = $original->toSnapshot();

        // Returned in 2026, when the same-base regime applies.
        $reversal = TaxCalculation::fromSnapshot($snapshot)->proportion('1');

        $this->assertSame(
            '21.90',
            $reversal->totalTax,
            'Crediting 20.00 against a sale that charged 21.90 leaves 1.90 of tax stranded '
            . 'in the VAT account forever.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Arithmetic integrity                                               */
    /* ------------------------------------------------------------------ */

    public function test_component_amounts_sum_exactly_to_the_total(): void
    {
        // 33.33 is chosen because it forces rounding on every levy.
        $calc = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '33.33');

        $sum = '0';
        foreach ($calc->components as $c) {
            $sum = bcadd($sum, $c['amount'], 2);
        }

        $this->assertSame(
            $calc->totalTax,
            $sum,
            'An invoice showing each levy separately must add up to the total charged.',
        );
        $this->assertSame($calc->grossTotal, bcadd($calc->taxableBase, $calc->totalTax, 2));
    }

    public function test_every_returned_amount_is_a_decimal_string(): void
    {
        $calc = $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), '100.00');

        $this->assertIsString($calc->taxableBase);
        $this->assertIsString($calc->totalTax);
        $this->assertIsString($calc->grossTotal);

        foreach ($calc->components as $c) {
            $this->assertIsString($c['amount'], 'Tax amounts must never become floats.');
            $this->assertIsString($c['base']);
        }
    }

    public function test_a_large_amount_does_not_lose_precision(): void
    {
        $calc = $this->calculator()->apply(
            $this->profile('GH_VAT_2026_STANDARD'),
            '9999999.99',
        );

        // Each levy is rounded to the cent before summing, so the total is
        // 1500000.00 + 250000.00 + 250000.00 rather than a single 20% of the
        // base. That is deliberate: an invoice showing three levies must add up
        // to what the customer is charged.
        $this->assertSame('2000000.00', $calc->totalTax);
        $this->assertSame('11999999.99', $calc->grossTotal);
    }

    public function test_a_non_numeric_amount_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator()->apply($this->profile('GH_VAT_2026_STANDARD'), 'not-a-number');
    }

    /* ------------------------------------------------------------------ */
    /* Organisation isolation                                             */
    /* ------------------------------------------------------------------ */

    public function test_profiles_do_not_leak_across_organisations(): void
    {
        $other = Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true,
        ]);

        $this->assertNotNull($this->resolver()->byCode('GH_VAT_2026_STANDARD', '2026-06-01', $this->org));
        $this->assertNull(
            $this->resolver()->byCode('GH_VAT_2026_STANDARD', '2026-06-01', $other),
            'An organisation with no seeded profiles must resolve nothing, not another tenant\'s.',
        );
    }

    public function test_missing_configuration_is_reported_rather_than_defaulting_to_zero(): void
    {
        $other = Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true,
        ]);

        $this->assertTrue(
            $this->resolver()->salesConfigurationMissing('2026-06-01', $other),
            'A farm with no tax setup must be flagged, not silently treated as exempt — those '
            . 'two states look identical on a posted invoice but mean opposite things.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Seeder integrity                                                   */
    /* ------------------------------------------------------------------ */

    public function test_the_seeder_is_idempotent(): void
    {
        $before = TaxProfile::where('organization_id', $this->org->id)->count();
        $componentsBefore = TaxProfileComponent::count();

        $this->seed(GhanaTaxProfileSeeder::class);

        $this->assertSame($before, TaxProfile::where('organization_id', $this->org->id)->count());
        $this->assertSame($componentsBefore, TaxProfileComponent::count());
    }

    public function test_effective_periods_of_the_standard_treatment_do_not_overlap(): void
    {
        $standard = TaxProfile::where('organization_id', $this->org->id)
            ->where('treatment', TaxProfile::TREATMENT_STANDARD)
            ->orderBy('effective_from')
            ->get();

        $this->assertGreaterThanOrEqual(2, $standard->count());

        for ($i = 0; $i < $standard->count() - 1; $i++) {
            $this->assertNotNull(
                $standard[$i]->effective_to,
                'An open-ended historical profile would overlap its successor, making resolution '
                . 'depend on row order.',
            );
            $this->assertTrue(
                $standard[$i]->effective_to->lt($standard[$i + 1]->effective_from),
                'Standard-rate profiles overlap in time.',
            );
        }
    }
}
