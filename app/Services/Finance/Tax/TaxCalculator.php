<?php

namespace App\Services\Finance\Tax;

use App\Models\Finance\TaxProfile;
use App\Models\Finance\TaxProfileComponent;
use Illuminate\Support\Collection;

/**
 * Applies a tax profile to an amount (Phase 2, blueprint §13/§30).
 *
 * ALL ARITHMETIC IS DECIMAL STRINGS
 * ---------------------------------
 * Every operation here uses bcmath. `TaxCode::calculateTax()` — which this
 * replaces for profile work — used `round($base * $rate / 100, 2)` on floats.
 * It happened to be dead code, but it was a trap: 0.1 + 0.2 is not 0.3 in
 * binary floating point, and tax that is a cent out on every line is a tax
 * return that does not reconcile.
 *
 * GHANA 2026: SAME BASE, NOT CASCADING
 * ------------------------------------
 * From the 2026 VAT reforms, VAT 15%, NHIL 2.5% and GETFund 2.5% are all
 * computed on the SAME base — the VAT-exclusive amount. On GHS 100 that is
 * 15.00 + 2.50 + 2.50 = 20.00, gross 120.00.
 *
 * The previous regime cascaded: NHIL and GETFund on the net, then VAT on
 * (net + NHIL + GETFund), giving 15.75 + 2.50 + 2.50 = 20.75 on the same 100.
 * The old model is still expressible through `base_mode = compound_on_previous`
 * because the historical profile must keep explaining historical invoices.
 *
 * INCLUSIVE PRICING IS SOLVED, NOT APPROXIMATED
 * ---------------------------------------------
 * Given a tax-inclusive price the calculator must recover the net. The naive
 * approach — subtract the tax computed on the gross — is simply wrong: it takes
 * the rate off the wrong base and understates the net.
 *
 * The component algorithm is affine in the base: tax(N) = kN + F, where k comes
 * from percentage components (including any compounding between them) and F
 * from fixed components. So the calculator runs two probes to recover k and F
 * exactly, then solves N = (G - F) / (1 + k). No iteration, no approximation,
 * and it stays correct for mixed percent/fixed/compound profiles.
 *
 * ROUNDING
 * --------
 * Each component is rounded to the money scale and the total is the SUM OF
 * ROUNDED COMPONENTS. An invoice that shows VAT, NHIL and GETFund as separate
 * lines must add up to the total charged; rounding the total independently
 * would leave a visible penny discrepancy on a customer-facing document.
 */
class TaxCalculator
{
    /** Working scale for intermediate arithmetic. */
    private const WORK_SCALE = 10;

    /**
     * Apply a profile to an amount.
     *
     * @param string $amount     Decimal string. Net when the profile is
     *                           exclusive, gross when it is inclusive.
     * @param int    $moneyScale Decimal places for money (2 for GHS).
     */
    public function apply(
        TaxProfile $profile,
        string $amount,
        int $moneyScale = 2,
        ?string $effectiveOn = null,
    ): TaxCalculation {
        $this->assertNumeric($amount);

        $components = $profile->relationLoaded('components')
            ? $profile->components
            : $profile->components()->get();

        // Exempt, zero-rated and no-tax charge nothing. They are kept distinct
        // on the snapshot because they differ on input recovery, but none of
        // them produces a tax amount.
        if ($profile->isZeroCharging() || $components->isEmpty()) {
            $base = TaxCalculation::round($amount, $moneyScale);

            return new TaxCalculation(
                taxableBase: $base,
                totalTax:    TaxCalculation::round('0', $moneyScale),
                grossTotal:  $base,
                components:  [],
                profileId:   $profile->id,
                profileCode: $profile->code,
                treatment:   $profile->treatment,
                priceMode:   $profile->price_mode,
                effectiveOn: $effectiveOn,
            );
        }

        $net = $profile->isInclusive()
            ? $this->netFromGross($components, $amount, $moneyScale)
            : TaxCalculation::round($amount, $moneyScale);

        return $this->build($profile, $components, $net, $moneyScale, $effectiveOn);
    }

    /**
     * Recover the net amount from a tax-inclusive gross.
     *
     * tax(N) = kN + F, so N = (G - F) / (1 + k).
     *
     * k is measured by running the components on a base of 1 with fixed
     * components suppressed; F by running them on a base of 0 with fixed
     * components live, which captures any percentage that compounds on top of
     * a fixed levy.
     *
     * @param Collection<int, TaxProfileComponent> $components
     */
    protected function netFromGross(Collection $components, string $gross, int $moneyScale): string
    {
        $k = $this->rawTaxTotal($components, '1', suppressFixed: true);
        $f = $this->rawTaxTotal($components, '0', suppressFixed: false);

        $divisor = bcadd('1', $k, self::WORK_SCALE);

        if (bccomp($divisor, '0', self::WORK_SCALE) === 0) {
            // Would only happen with a -100% component, which is not a tax.
            throw new \DomainException('Tax profile produces a zero divisor for inclusive pricing.');
        }

        $numerator = bcsub($gross, $f, self::WORK_SCALE);

        return TaxCalculation::round(
            bcdiv($numerator, $divisor, self::WORK_SCALE),
            $moneyScale,
        );
    }

    /**
     * Unrounded total tax for a base, used only for the inclusive-price solve.
     *
     * Deliberately does NOT round per component: rounding here would make the
     * recovered multiplier slightly wrong, and the error would land on the net.
     *
     * @param Collection<int, TaxProfileComponent> $components
     */
    protected function rawTaxTotal(Collection $components, string $base, bool $suppressFixed): string
    {
        $running = '0';

        foreach ($components as $component) {
            $componentBase = $component->isCompound()
                ? bcadd($base, $running, self::WORK_SCALE)
                : $base;

            $amount = $this->componentAmount($component, $componentBase, $suppressFixed);
            $running = bcadd($running, $amount, self::WORK_SCALE);
        }

        return $running;
    }

    /**
     * @param Collection<int, TaxProfileComponent> $components
     */
    protected function build(
        TaxProfile $profile,
        Collection $components,
        string $net,
        int $moneyScale,
        ?string $effectiveOn,
    ): TaxCalculation {
        $rows        = [];
        $totalTax    = TaxCalculation::round('0', $moneyScale);
        $runningRaw  = '0';

        foreach ($components as $component) {
            // Compounding uses the UNROUNDED running total so a rounding
            // artefact on one levy cannot propagate into the base of the next.
            $componentBase = $component->isCompound()
                ? bcadd($net, $runningRaw, self::WORK_SCALE)
                : $net;

            $raw    = $this->componentAmount($component, $componentBase, suppressFixed: false);
            $amount = TaxCalculation::round($raw, $moneyScale);

            $runningRaw = bcadd($runningRaw, $raw, self::WORK_SCALE);
            $totalTax   = bcadd($totalTax, $amount, $moneyScale);

            $rows[] = [
                'code'            => $component->code,
                'label'           => $component->label,
                'rate_type'       => $component->rate_type,
                'rate'            => (string) $component->rate,
                'base_mode'       => $component->base_mode,
                'base'            => TaxCalculation::round($componentBase, $moneyScale),
                'amount'          => $amount,
                'is_recoverable'  => (bool) $component->is_recoverable,
                'gl_account_hint' => $component->gl_account_hint,
            ];
        }

        return new TaxCalculation(
            taxableBase: $net,
            totalTax:    $totalTax,
            grossTotal:  bcadd($net, $totalTax, $moneyScale),
            components:  $rows,
            profileId:   $profile->id,
            profileCode: $profile->code,
            treatment:   $profile->treatment,
            priceMode:   $profile->price_mode,
            effectiveOn: $effectiveOn,
        );
    }

    protected function componentAmount(
        TaxProfileComponent $component,
        string $base,
        bool $suppressFixed,
    ): string {
        if (! $component->isPercent()) {
            return $suppressFixed ? '0' : (string) $component->rate;
        }

        return bcdiv(
            bcmul($base, (string) $component->rate, self::WORK_SCALE),
            '100',
            self::WORK_SCALE,
        );
    }

    protected function assertNumeric(string $amount): void
    {
        if (! is_numeric($amount)) {
            throw new \InvalidArgumentException("Tax amount must be numeric, got [{$amount}].");
        }
    }
}
