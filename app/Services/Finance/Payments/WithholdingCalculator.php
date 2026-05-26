<?php

namespace App\Services\Finance\Payments;

use App\Models\Finance\VendorInvoice;

/**
 * Computes the withholding tax slice for a payment allocation.
 *
 * Phase 3b records `withholding_total` on the vendor invoice as the FULL
 * WHT we'd apply if we paid the full bill in one go. When we pay an
 * invoice in instalments, each instalment carries its proportional WHT
 * slice.
 *
 * Proportional formula:
 *   slice = round(amount * vi.withholding_total / vi.total, 2)
 *
 * Final-payment exact-match:
 *   When this allocation finishes the bill (cumulative paid == vi.total),
 *   the slice is computed as `vi.withholding_total - alreadyWithheld` to
 *   avoid rounding error on the last instalment. Without this, four
 *   25%-instalments could each round to 18.75 -> 19, producing 76 total
 *   when the actual WHT was 75.
 *
 * Edge cases:
 *   - vi.withholding_total <= 0: returns '0.00' (no WHT on this bill)
 *   - vi.total <= 0: returns '0.00' (defensive — shouldn't happen on a
 *     posted bill)
 *   - amount > balance_due: caller's job to validate; calculator just
 *     computes the slice for the amount given
 *   - Final payment where cumulative already equals or exceeds
 *     vi.total via prior allocations and rounding: clamps to non-
 *     negative (returns '0.00' rather than negative slice)
 */
class WithholdingCalculator
{
    /**
     * Compute the WHT slice for an allocation against this VI.
     *
     * @param  VendorInvoice  $vi
     * @param  string         $amount         The allocation amount
     * @param  string         $alreadyWithheld  Sum of active prior allocations' WHT
     * @return string  Slice in 2dp string form
     */
    public function slice(VendorInvoice $vi, string $amount, string $alreadyWithheld = '0'): string
    {
        $whtTotal = (string) $vi->withholding_total;
        $viTotal  = (string) $vi->total;

        // No withholding on this bill at all → zero slice.
        if (bccomp($whtTotal, '0', 2) <= 0) {
            return '0.00';
        }

        // Defensive: vi.total should never be <= 0 on a posted bill, but
        // if it somehow is, return 0 instead of dividing by zero.
        if (bccomp($viTotal, '0', 2) <= 0) {
            return '0.00';
        }

        // Final-payment detection.
        // amountAfter = vi.amount_paid + amount (what the running total
        // becomes if this allocation succeeds).
        $amountAfter = bcadd((string) $vi->amount_paid, $amount, 2);

        if (bccomp($amountAfter, $viTotal, 2) === 0) {
            // This allocation finishes the bill. Compute slice as
            // remaining WHT capacity to avoid rounding drift.
            $remaining = bcsub($whtTotal, $alreadyWithheld, 2);
            // Clamp to non-negative (rounding could theoretically push
            // alreadyWithheld over total).
            if (bccomp($remaining, '0', 2) < 0) {
                return '0.00';
            }
            return $this->roundToCents($remaining);
        }

        // Proportional slice for non-final allocation.
        // ratio = amount / vi.total
        // slice = ratio * vi.withholding_total
        $slice = bcdiv(
            bcmul($amount, $whtTotal, 8),
            $viTotal,
            8,
        );

        return $this->roundToCents($slice);
    }

    /**
     * Helper: round a decimal-as-string to 2dp.
     */
    protected function roundToCents(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
