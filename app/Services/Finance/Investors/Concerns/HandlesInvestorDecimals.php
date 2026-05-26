<?php

namespace App\Services\Finance\Investors\Concerns;

/**
 * Fixed-point decimal helpers for investor capital math. Money at scale 2;
 * ownership-percentage math at scale 6.
 */
trait HandlesInvestorDecimals
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

    protected function cmp(string $a, string $b, int $scale = 2): int
    {
        return bccomp($this->n($a), $this->n($b), $scale);
    }

    /** Multiply a money amount by an ownership fraction, rounding to 2dp. */
    protected function share(string $pool, string $fraction): string
    {
        return bcadd(bcmul($this->n($pool), $this->n($fraction), 8), '0', 2);
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
