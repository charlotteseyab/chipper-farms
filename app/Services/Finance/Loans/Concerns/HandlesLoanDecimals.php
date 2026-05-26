<?php

namespace App\Services\Finance\Loans\Concerns;

/**
 * Fixed-point decimal helpers for loan math. All money at scale 2; the
 * periodic interest rate is computed at higher precision (scale 10) before
 * the interest amount is rounded to scale 2.
 */
trait HandlesLoanDecimals
{
    protected function add(string $a, string $b, int $scale = 2): string
    {
        return bcadd($this->n($a), $this->n($b), $scale);
    }

    protected function sub(string $a, string $b, int $scale = 2): string
    {
        return bcsub($this->n($a), $this->n($b), $scale);
    }

    protected function mul(string $a, string $b, int $scale = 2): string
    {
        return bcmul($this->n($a), $this->n($b), $scale);
    }

    protected function div(string $a, string $b, int $scale = 10): string
    {
        if (bccomp($this->n($b), '0', 12) === 0) {
            return '0';
        }
        return bcdiv($this->n($a), $this->n($b), $scale);
    }

    protected function cmp(string $a, string $b, int $scale = 2): int
    {
        return bccomp($this->n($a), $this->n($b), $scale);
    }

    protected function n(string $v): string
    {
        $v = trim($v);
        if ($v === '' || $v === '-' || $v === '.') {
            return '0';
        }
        return $v;
    }
}
