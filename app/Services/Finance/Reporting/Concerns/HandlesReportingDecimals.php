<?php

namespace App\Services\Finance\Reporting\Concerns;

/**
 * Shared fixed-point decimal helpers for the reporting services.
 *
 * All money math goes through bcmath at scale 2 (statements are presented
 * to the cent/pesewa). Using strings end-to-end avoids float drift in the
 * balance checks that the statements assert on.
 */
trait HandlesReportingDecimals
{
    protected function decAdd(string $a, string $b, int $scale = 2): string
    {
        if (function_exists('bcadd')) {
            return bcadd($this->norm($a), $this->norm($b), $scale);
        }
        return number_format((float) $a + (float) $b, $scale, '.', '');
    }

    protected function decSub(string $a, string $b, int $scale = 2): string
    {
        if (function_exists('bcsub')) {
            return bcsub($this->norm($a), $this->norm($b), $scale);
        }
        return number_format((float) $a - (float) $b, $scale, '.', '');
    }

    protected function decComp(string $a, string $b, int $scale = 2): int
    {
        if (function_exists('bccomp')) {
            return bccomp($this->norm($a), $this->norm($b), $scale);
        }
        $fa = round((float) $a, $scale);
        $fb = round((float) $b, $scale);
        return $fa <=> $fb;
    }

    protected function decAbs(string $a, int $scale = 2): string
    {
        return $this->decComp($a, '0', $scale) < 0
            ? $this->decSub('0', $a, $scale)
            : $this->norm($a, $scale);
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
