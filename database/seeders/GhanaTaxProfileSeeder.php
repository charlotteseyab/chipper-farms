<?php

namespace Database\Seeders;

use App\Models\Finance\TaxProfile;
use App\Models\Finance\TaxProfileComponent;
use App\Models\Organization;
use App\Models\TaxCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ghana tax profiles (Phase 2, blueprint §13/§30).
 *
 * WHAT CHANGED IN 2026, AND WHY THE OLD PROFILE SURVIVES
 * ------------------------------------------------------
 * Per GRA's 2026 VAT reforms as recorded in the blueprint (§13):
 *
 *   - the COVID-19 Health Recovery Levy is ABOLISHED;
 *   - VAT remains 15%, NHIL 2.5%, GETFund 2.5%;
 *   - the levies are computed on the SAME BASE rather than cascading.
 *
 * On GHS 100 the 2026 treatment is 15.00 + 2.50 + 2.50 = 20.00 tax, gross
 * 120.00 — an effective 20%.
 *
 * The pre-2026 treatment cascaded: NHIL, GETFund and COVID on the net, then VAT
 * on net + those three. On the same GHS 100 that is 2.50 + 2.50 + 1.00 = 6.00,
 * then VAT on 106.00 = 15.90, total 21.90 — the ~21.9% the existing
 * TaxCodeSeeder docblock describes.
 *
 * Both are seeded. The historical profile is NOT a curiosity: an invoice raised
 * in 2024 must still be explainable, and a credit note against it must reverse
 * 21.90 rather than recompute 20.00. Deleting the old rates would silently
 * rewrite history. Blueprint §13 requires effective dates precisely so older
 * transactions remain explainable, and §19 of the prompt forbids deleting
 * historical tax codes.
 *
 * THIS SEEDER DOES NOT CERTIFY THE RATES
 * --------------------------------------
 * Blueprint §61 requires accountant/GRA confirmation before production. The
 * rates here follow the blueprint's reading of GRA guidance and are structurally
 * correct, but "structurally correct" is not "confirmed by someone accountable".
 * That remains an external certification item.
 *
 * Idempotent: keyed on (organization_id, code, effective_from).
 */
class GhanaTaxProfileSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::query()->pluck('id');

        if ($organizations->isEmpty()) {
            $this->command?->warn('GhanaTaxProfileSeeder: no organisations; nothing to seed.');

            return;
        }

        foreach ($organizations as $organizationId) {
            DB::transaction(function () use ($organizationId): void {
                $this->seedStandard2026($organizationId);
                $this->seedHistoricalPre2026($organizationId);
                $this->seedZeroRated($organizationId);
                $this->seedExempt($organizationId);
                $this->seedNoTax($organizationId);
            });
        }

        $this->command?->info(
            'Ghana tax profiles seeded. COVID levy is present ONLY in the pre-2026 historical '
            . 'profile, never in the 2026 default. Rates require accountant/GRA confirmation '
            . 'before production (blueprint §61).'
        );
    }

    /* ------------------------------------------------------------------ */

    protected function seedStandard2026(int $organizationId): void
    {
        $profile = $this->profile($organizationId, [
            'code'                => 'GH_VAT_2026_STANDARD',
            'name'                => 'Ghana VAT 2026 Standard',
            'description'         => 'VAT 15%, NHIL 2.5% and GETFund 2.5%, all charged on the same '
                . 'VAT-exclusive base. Effective 1 January 2026.',
            'treatment'           => TaxProfile::TREATMENT_STANDARD,
            'applies_to'          => TaxProfile::APPLIES_BOTH,
            'price_mode'          => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from'      => '2026-01-01',
            'effective_to'        => null,
            'is_default_sales'    => true,
            'is_default_purchase' => true,
        ]);

        // All three on `net` — this is the same-base rule. Using
        // compound_on_previous for any of them would reproduce the abolished
        // cascade and overstate the charge.
        $this->component($profile, $organizationId, [
            'code' => 'VAT_15', 'label' => 'VAT (15%)', 'rate' => '15.0000',
            'base_mode' => TaxProfileComponent::BASE_NET, 'calculation_order' => 10,
            'is_recoverable' => true, 'gl_account_hint' => 'VAT_PAYABLE',
        ]);
        $this->component($profile, $organizationId, [
            'code' => 'NHIL', 'label' => 'NHIL (2.5%)', 'rate' => '2.5000',
            'base_mode' => TaxProfileComponent::BASE_NET, 'calculation_order' => 20,
            'is_recoverable' => false, 'gl_account_hint' => 'NHIL_PAYABLE',
        ]);
        $this->component($profile, $organizationId, [
            'code' => 'GETFUND', 'label' => 'GETFund Levy (2.5%)', 'rate' => '2.5000',
            'base_mode' => TaxProfileComponent::BASE_NET, 'calculation_order' => 30,
            'is_recoverable' => false, 'gl_account_hint' => 'GETFUND_PAYABLE',
        ]);
    }

    protected function seedHistoricalPre2026(int $organizationId): void
    {
        $profile = $this->profile($organizationId, [
            'code'                => 'GH_VAT_PRE_2026',
            'name'                => 'Ghana VAT (pre-2026, historical)',
            'description'         => 'Historical cascading treatment: NHIL, GETFund and the COVID-19 '
                . 'Health Recovery Levy on the net amount, then VAT 15% on the levied base. '
                . 'Retained so transactions raised before 2026 remain explainable. Not for new use.',
            'treatment'           => TaxProfile::TREATMENT_STANDARD,
            'applies_to'          => TaxProfile::APPLIES_BOTH,
            'price_mode'          => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from'      => '2021-05-01',
            'effective_to'        => '2025-12-31',
            'is_default_sales'    => false,
            'is_default_purchase' => false,
        ]);

        $this->component($profile, $organizationId, [
            'code' => 'NHIL', 'label' => 'NHIL (2.5%)', 'rate' => '2.5000',
            'base_mode' => TaxProfileComponent::BASE_NET, 'calculation_order' => 10,
            'is_recoverable' => false, 'gl_account_hint' => 'NHIL_PAYABLE',
        ]);
        $this->component($profile, $organizationId, [
            'code' => 'GETFUND', 'label' => 'GETFund Levy (2.5%)', 'rate' => '2.5000',
            'base_mode' => TaxProfileComponent::BASE_NET, 'calculation_order' => 20,
            'is_recoverable' => false, 'gl_account_hint' => 'GETFUND_PAYABLE',
        ]);
        $this->component($profile, $organizationId, [
            'code' => 'COVID_LEVY', 'label' => 'COVID-19 Health Recovery Levy (1%)', 'rate' => '1.0000',
            'base_mode' => TaxProfileComponent::BASE_NET, 'calculation_order' => 30,
            'is_recoverable' => false, 'gl_account_hint' => 'COVID_LEVY_PAYABLE',
        ]);
        // VAT compounds on the levied base — the abolished behaviour, preserved
        // here and ONLY here.
        $this->component($profile, $organizationId, [
            'code' => 'VAT_15', 'label' => 'VAT (15%)', 'rate' => '15.0000',
            'base_mode' => TaxProfileComponent::BASE_COMPOUND, 'calculation_order' => 40,
            'is_recoverable' => true, 'gl_account_hint' => 'VAT_PAYABLE',
        ]);
    }

    protected function seedZeroRated(int $organizationId): void
    {
        $this->profile($organizationId, [
            'code'                => 'GH_ZERO_RATED',
            'name'                => 'Ghana Zero-Rated',
            'description'         => 'Charged at 0%. Input tax on related purchases REMAINS '
                . 'recoverable, which is what separates zero-rated from exempt.',
            'treatment'           => TaxProfile::TREATMENT_ZERO_RATED,
            'applies_to'          => TaxProfile::APPLIES_BOTH,
            'price_mode'          => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from'      => '2026-01-01',
            'effective_to'        => null,
            'is_default_sales'    => false,
            'is_default_purchase' => false,
        ]);
    }

    protected function seedExempt(int $organizationId): void
    {
        $this->profile($organizationId, [
            'code'                => 'GH_EXEMPT',
            'name'                => 'Ghana Exempt',
            'description'         => 'No VAT charged and input tax on related purchases is NOT '
                . 'recoverable. Many unprocessed agricultural products fall here — confirm each '
                . 'product with your accountant.',
            'treatment'           => TaxProfile::TREATMENT_EXEMPT,
            'applies_to'          => TaxProfile::APPLIES_BOTH,
            'price_mode'          => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from'      => '2026-01-01',
            'effective_to'        => null,
            'is_default_sales'    => false,
            'is_default_purchase' => false,
        ]);
    }

    protected function seedNoTax(int $organizationId): void
    {
        $this->profile($organizationId, [
            'code'                => 'NO_TAX',
            'name'                => 'No Tax',
            'description'         => 'For farms that are not VAT registered. Ghana\'s goods VAT '
                . 'registration threshold is GHS 750,000 turnover.',
            'treatment'           => TaxProfile::TREATMENT_NO_TAX,
            'applies_to'          => TaxProfile::APPLIES_BOTH,
            'price_mode'          => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from'      => '2026-01-01',
            'effective_to'        => null,
            'is_default_sales'    => false,
            'is_default_purchase' => false,
        ]);
    }

    /* ------------------------------------------------------------------ */

    protected function profile(int $organizationId, array $attributes): TaxProfile
    {
        // Matched with whereDate rather than updateOrCreate on the raw value.
        // `effective_from` is cast to a date, and the stored representation
        // carries a time component on some drivers, so an equality match on
        // '2026-01-01' misses '2026-01-01 00:00:00' and the seeder inserts a
        // duplicate instead of updating — which the unique index then rejects
        // on the second run. Found by test_the_seeder_is_idempotent.
        $existing = TaxProfile::query()
            ->where('organization_id', $organizationId)
            ->where('code', $attributes['code'])
            ->whereDate('effective_from', $attributes['effective_from'])
            ->first();

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing;
        }

        return TaxProfile::create(
            $attributes + ['organization_id' => $organizationId, 'is_active' => true],
        );
    }

    protected function component(TaxProfile $profile, int $organizationId, array $attributes): void
    {
        // Link to the existing tax_code where one exists, so the GL mapping and
        // lineage are shared rather than duplicated.
        $taxCodeId = TaxCode::query()
            ->where('code', $attributes['code'])
            ->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->value('id');

        TaxProfileComponent::updateOrCreate(
            ['tax_profile_id' => $profile->id, 'code' => $attributes['code']],
            $attributes + [
                'tax_profile_id' => $profile->id,
                'tax_code_id'    => $taxCodeId,
                'rate_type'      => TaxProfileComponent::RATE_PERCENT,
            ],
        );
    }
}
