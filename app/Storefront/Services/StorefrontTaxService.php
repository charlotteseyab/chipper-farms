<?php

namespace App\Storefront\Services;

use App\Models\Finance\TaxProfile;
use App\Services\Finance\Tax\TaxCalculation;
use App\Services\Finance\Tax\TaxCalculator;
use App\Storefront\Support\StorefrontSettings;

/**
 * What tax a storefront order carries (IMP-091, blueprint §38 and §30).
 *
 * WHAT WAS WRONG
 * --------------
 * Storefront orders carried no tax at all. The buyer saw subtotal plus delivery,
 * the confirmed order created a sale with no tax, and the invoice totalled zero.
 * A VAT-registered farm selling online collected no VAT.
 *
 * THE CALCULATION IS THE REAL ONE
 * -------------------------------
 * `TaxCalculator` — the same engine the finance module posts from — not a
 * simplified copy. Ghana's 2026 regime charges VAT, NHIL and GETFund on a
 * shared base, and a single "combined rate" cannot express that, nor a fixed
 * levy, nor a component that compounds on the others. A shop that computed tax
 * its own way would disagree with its own invoices, which is worse than
 * charging none.
 *
 * INCLUSIVE OR EXCLUSIVE COMES FROM THE PROFILE
 * ---------------------------------------------
 * `TaxCalculator` already honours `TaxProfile::price_mode`, solving the net back
 * out of a tax-inclusive price. The storefront settings screen has its own
 * `tax_display_mode`, and the two would be a second source of truth for one
 * arithmetic decision — the mistake Phase 8 spent effort avoiding elsewhere. So
 * the profile decides the arithmetic and the setting is presentation only.
 *
 * WHAT IS TAXED
 * -------------
 * Subtotal plus delivery. Delivery is part of the consideration for the supply
 * in Ghana as in most VAT regimes; charging tax on the goods but not the
 * carriage would under-declare on every delivered order.
 */
class StorefrontTaxService
{
    public function __construct(
        protected TaxCalculator $calculator,
    ) {
    }

    /**
     * The profile this organisation charges storefront sales under, if any.
     *
     * Null is a legitimate answer — a farm below the VAT threshold registers
     * none, and returning a zero-rated default would be inventing a tax
     * position on its behalf.
     */
    public function profileFor(?int $organizationId): ?TaxProfile
    {
        $profile = StorefrontSettings::defaultTaxProfile($organizationId);

        if ($profile === null) {
            return null;
        }

        // A profile from another organisation must never price this farm's
        // orders. The settings screen scopes its own validation, but this is
        // read on every checkout and the check is one comparison.
        if ($organizationId !== null && (int) $profile->organization_id !== $organizationId) {
            return null;
        }

        return $profile->loadMissing('components');
    }

    /**
     * Tax on a taxable base, or null when the farm charges none.
     *
     * `$base` is subtotal plus delivery — net when the profile prices
     * exclusive, gross when it prices inclusive. `TaxCalculator` knows which
     * from the profile and solves accordingly.
     */
    public function calculate(string $base, ?int $organizationId, ?string $effectiveOn = null): ?TaxCalculation
    {
        $profile = $this->profileFor($organizationId);

        if ($profile === null) {
            return null;
        }

        return $this->calculator->apply($profile, $base, effectiveOn: $effectiveOn);
    }

    /**
     * Split an order's tax across its lines so the parts sum to the whole.
     *
     * WHY THIS IS NOT JUST A LOOP OF PROPORTIONS
     * ------------------------------------------
     * Each order line becomes its own sale and its own invoice, so the order's
     * tax has to be apportioned. Scaling the calculation per line and rounding
     * each independently does not add up: three lines of 33.33 taxed at 20%
     * round to 6.67 each, totalling 20.01 against an order tax of 20.00. The
     * buyer was charged one number and the invoices total another, which is the
     * precise failure this whole change exists to prevent.
     *
     * So the last line absorbs the rounding residual — largest-remainder in its
     * simplest honest form. The residual is at most a penny per order and it
     * lands somewhere real rather than being discarded.
     *
     * @param  array<int|string, string>  $lineTotals  key => line net amount
     * @return array<int|string, string>  key => tax for that line
     */
    public function allocate(TaxCalculation $calculation, array $lineTotals): array
    {
        $totalTax = $calculation->totalTax;

        $base = '0.00';
        foreach ($lineTotals as $amount) {
            $base = bcadd($base, $amount, 2);
        }

        // Nothing to split, or nothing to split across.
        if (bccomp($base, '0', 2) === 0 || bccomp($totalTax, '0', 2) === 0) {
            return array_map(static fn () => '0.00', $lineTotals);
        }

        $allocated = [];
        $running   = '0.00';
        $keys      = array_keys($lineTotals);
        $lastKey   = end($keys);

        foreach ($lineTotals as $key => $amount) {
            if ($key === $lastKey) {
                // Whatever is left, so the parts sum to the whole exactly.
                $allocated[$key] = bcsub($totalTax, $running, 2);

                continue;
            }

            $share = bcdiv(bcmul($totalTax, $amount, 8), $base, 8);
            $share = bcadd($share, '0', 2);

            $allocated[$key] = $share;
            $running         = bcadd($running, $share, 2);
        }

        return $allocated;
    }
}
