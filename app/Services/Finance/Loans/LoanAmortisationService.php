<?php

namespace App\Services\Finance\Loans;

use App\Models\Finance\Loan;
use App\Services\Finance\Loans\Concerns\HandlesLoanDecimals;
use Carbon\CarbonImmutable;

/**
 * Pure amortisation schedule generator. No DB, no side effects — given loan
 * terms it returns the full instalment schedule. The LoanService persists
 * the result into loan_schedule_lines.
 *
 * Two methods:
 *
 *   reducing_balance (annuity): equal total instalment A, where
 *       A = P · r / (1 − (1+r)^−n)
 *     with P = principal, r = periodic rate, n = number of periods.
 *     Interest each period = balance · r; principal = A − interest.
 *
 *   straight_line: equal principal each period (P / n); interest each
 *     period = balance · r; total instalment declines over time.
 *
 * Rounding: every amount is rounded to scale 2. Accumulated rounding error
 * is absorbed in the FINAL period so the schedule fully amortises the
 * principal to exactly zero (closing_balance of the last line == 0.00).
 *
 * The periodic rate is annual / 12 (monthly basis). A zero-interest loan is
 * supported (straight principal division).
 */
class LoanAmortisationService
{
    use HandlesLoanDecimals;

    /**
     * Generate the schedule.
     *
     * @return array<int, array{
     *   period_number:int, due_date:string, opening_balance:string,
     *   interest_due:string, principal_due:string, total_due:string,
     *   closing_balance:string
     * }>
     */
    public function generate(
        string $principal,
        string $annualRate,
        int $termMonths,
        string $method,
        CarbonImmutable $firstPaymentDate,
        int $scale = 2,
    ): array {
        if ($termMonths < 1) {
            throw new \InvalidArgumentException('term_months must be >= 1.');
        }
        if ($this->cmp($principal, '0') <= 0) {
            throw new \InvalidArgumentException('principal must be > 0.');
        }

        $periodicRate = $this->div($this->n($annualRate), '12', 12); // scale 12

        return match ($method) {
            Loan::METHOD_STRAIGHT_LINE    => $this->straightLine($principal, $periodicRate, $termMonths, $firstPaymentDate, $scale),
            Loan::METHOD_REDUCING_BALANCE => $this->reducingBalance($principal, $periodicRate, $termMonths, $firstPaymentDate, $scale),
            default => throw new \InvalidArgumentException("Unknown amortisation method '{$method}'."),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function reducingBalance(string $principal, string $periodicRate, int $n, CarbonImmutable $firstDue, int $scale): array
    {
        $rows = [];
        $balance = $this->n($principal);

        // Annuity instalment A = P r / (1 - (1+r)^-n).
        // Zero-rate fallback: equal principal.
        if (bccomp($periodicRate, '0', 12) === 0) {
            return $this->straightLine($principal, $periodicRate, $n, $firstDue, $scale);
        }

        $instalment = $this->annuityInstalment($principal, $periodicRate, $n, $scale);

        for ($period = 1; $period <= $n; $period++) {
            $opening = $balance;
            $interest = bcmul($opening, $periodicRate, $scale + 4);
            $interest = $this->roundScale($interest, $scale);

            if ($period < $n) {
                $principalDue = $this->sub($instalment, $interest, $scale);
                $closing = $this->sub($opening, $principalDue, $scale);
                $totalDue = $instalment;
            } else {
                // Final period: principal clears the remaining balance exactly.
                $principalDue = $opening;
                $closing = '0.00';
                $totalDue = $this->add($principalDue, $interest, $scale);
            }

            $rows[] = [
                'period_number'   => $period,
                'due_date'        => $firstDue->addMonths($period - 1)->toDateString(),
                'opening_balance' => $opening,
                'interest_due'    => $interest,
                'principal_due'   => $principalDue,
                'total_due'       => $totalDue,
                'closing_balance' => $closing,
            ];

            $balance = $closing;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function straightLine(string $principal, string $periodicRate, int $n, CarbonImmutable $firstDue, int $scale): array
    {
        $rows = [];
        $balance = $this->n($principal);

        // Equal principal per period, last absorbs rounding.
        $basePrincipal = $this->roundScale(bcdiv($principal, (string) $n, $scale + 4), $scale);

        for ($period = 1; $period <= $n; $period++) {
            $opening = $balance;
            $interest = $this->roundScale(bcmul($opening, $periodicRate, $scale + 4), $scale);

            if ($period < $n) {
                $principalDue = $basePrincipal;
                $closing = $this->sub($opening, $principalDue, $scale);
            } else {
                $principalDue = $opening;   // clear remainder exactly
                $closing = '0.00';
            }

            $totalDue = $this->add($principalDue, $interest, $scale);

            $rows[] = [
                'period_number'   => $period,
                'due_date'        => $firstDue->addMonths($period - 1)->toDateString(),
                'opening_balance' => $opening,
                'interest_due'    => $interest,
                'principal_due'   => $principalDue,
                'total_due'       => $totalDue,
                'closing_balance' => $closing,
            ];

            $balance = $closing;
        }

        return $rows;
    }

    /**
     * Annuity instalment A = P·r / (1 − (1+r)^−n), computed at high precision.
     */
    protected function annuityInstalment(string $principal, string $periodicRate, int $n, int $scale): string
    {
        $hp = 12; // high precision for the factor

        // (1+r)^n
        $onePlusR = bcadd('1', $periodicRate, $hp);
        $compound = $this->bcpow($onePlusR, $n, $hp);          // (1+r)^n
        // denominator = 1 - (1+r)^-n = 1 - 1/compound = (compound - 1)/compound
        $denom = bcdiv(bcsub($compound, '1', $hp), $compound, $hp);
        // A = P r / denom
        $numerator = bcmul($principal, $periodicRate, $hp);
        $instalment = bcdiv($numerator, $denom, $hp);

        return $this->roundScale($instalment, $scale);
    }

    /**
     * Integer power for bcmath (positive exponent).
     */
    protected function bcpow(string $base, int $exp, int $scale): string
    {
        $result = '1';
        for ($i = 0; $i < $exp; $i++) {
            $result = bcmul($result, $base, $scale);
        }
        return $result;
    }

    /**
     * Round a high-precision decimal string to the given scale (half-up).
     * bcmath truncates, so we inspect the next digit and bump if >= 5.
     */
    protected function roundScale(string $value, int $scale): string
    {
        $value = $this->n($value);

        if (! str_contains($value, '.')) {
            return bcadd($value, '0', $scale);
        }

        [$int, $frac] = explode('.', $value, 2);
        $frac = str_pad($frac, $scale + 1, '0');
        $roundDigit = (int) $frac[$scale];
        $truncated = $int . '.' . substr($frac, 0, $scale);

        if ($roundDigit < 5) {
            return bcadd($truncated, '0', $scale);
        }

        $increment = $scale > 0
            ? '0.' . str_repeat('0', $scale - 1) . '1'
            : '1';

        $negative = str_starts_with($truncated, '-');
        $abs = ltrim($truncated, '-');
        $abs = bcadd($abs, $increment, $scale);

        return ($negative ? '-' : '') . $abs;
    }
}
