<?php

namespace App\Services\Finance\BiologicalAssets\Concerns;

/**
 * Fixed-point decimal helpers for biological-asset cost math.
 *
 * Carrying values are tracked at scale 4 (consistent with GroupCostPool and
 * the bio-asset balance columns); GL posting amounts are emitted at scale 2.
 */
trait HandlesBioAssetDecimals
{
    protected function decAdd(string $a, string $b, int $scale = 4): string
    {
        return bcadd($this->norm($a), $this->norm($b), $scale);
    }

    protected function decSub(string $a, string $b, int $scale = 4): string
    {
        return bcsub($this->norm($a), $this->norm($b), $scale);
    }

    protected function decComp(string $a, string $b, int $scale = 4): int
    {
        return bccomp($this->norm($a), $this->norm($b), $scale);
    }

    protected function decAbs(string $a, int $scale = 4): string
    {
        return $this->decComp($a, '0', $scale) < 0 ? $this->decSub('0', $a, $scale) : $this->norm($a, $scale);
    }

    /** Round/format to GL posting scale (2). */
    protected function toPostingAmount(string $a): string
    {
        return bcadd($this->norm($a), '0', 2);
    }

    protected function norm(string $v, int $scale = 8): string
    {
        $v = trim($v);
        if ($v === '' || $v === '-' || $v === '.') {
            return '0';
        }
        return $v;
    }
}
