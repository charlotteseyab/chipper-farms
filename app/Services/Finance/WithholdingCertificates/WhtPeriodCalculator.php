<?php

namespace App\Services\Finance\WithholdingCertificates;

use App\Models\Finance\WhtCertificate;
use Carbon\CarbonImmutable;

/**
 * Pure helper computing the date boundaries for a WHT period.
 *
 * Quarter boundaries (Ghana GRA convention):
 *   Q1: Jan 1 – Mar 31
 *   Q2: Apr 1 – Jun 30
 *   Q3: Jul 1 – Sep 30
 *   Q4: Oct 1 – Dec 31
 *
 * Month boundaries: 1st to last-of-month.
 * Year boundaries:  Jan 1 to Dec 31.
 *
 * Stateless. No DB access.
 */
class WhtPeriodCalculator
{
    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, label: string}
     */
    public function boundaries(string $kind, int $year, int $index): array
    {
        return match ($kind) {
            WhtCertificate::PERIOD_KIND_QUARTER => $this->quarter($year, $index),
            WhtCertificate::PERIOD_KIND_MONTH   => $this->month($year, $index),
            WhtCertificate::PERIOD_KIND_YEAR    => $this->year($year),
            default => throw new \InvalidArgumentException("Unknown period kind: {$kind}"),
        };
    }

    public function quarter(int $year, int $quarter): array
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new \InvalidArgumentException("Quarter must be 1..4, got {$quarter}");
        }
        $startMonth = (($quarter - 1) * 3) + 1;
        $from = CarbonImmutable::createFromDate($year, $startMonth, 1)->startOfDay();
        $to   = $from->addMonths(3)->subDay()->endOfDay();
        return [
            'from'  => $from->startOfDay(),
            'to'    => $to->startOfDay(),
            'label' => "Q{$quarter} {$year}",
        ];
    }

    public function month(int $year, int $month): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException("Month must be 1..12, got {$month}");
        }
        $from = CarbonImmutable::createFromDate($year, $month, 1)->startOfDay();
        $to   = $from->endOfMonth()->startOfDay();
        return [
            'from'  => $from,
            'to'    => $to,
            'label' => $from->format('F Y'),
        ];
    }

    public function year(int $year): array
    {
        $from = CarbonImmutable::createFromDate($year, 1, 1)->startOfDay();
        $to   = CarbonImmutable::createFromDate($year, 12, 31)->startOfDay();
        return [
            'from'  => $from,
            'to'    => $to,
            'label' => (string) $year,
        ];
    }

    /**
     * Convenience: get the (kind, year, index) tuple for a given date,
     * defaulting to quarter granularity.
     *
     * @return array{kind: string, year: int, index: int}
     */
    public function periodForDate(\DateTimeInterface $date, string $kind = 'quarter'): array
    {
        $d = CarbonImmutable::instance($date);
        return match ($kind) {
            WhtCertificate::PERIOD_KIND_QUARTER => [
                'kind'  => $kind,
                'year'  => (int) $d->format('Y'),
                'index' => (int) ceil((int) $d->format('n') / 3),
            ],
            WhtCertificate::PERIOD_KIND_MONTH => [
                'kind'  => $kind,
                'year'  => (int) $d->format('Y'),
                'index' => (int) $d->format('n'),
            ],
            WhtCertificate::PERIOD_KIND_YEAR => [
                'kind'  => $kind,
                'year'  => (int) $d->format('Y'),
                'index' => 1,
            ],
            default => throw new \InvalidArgumentException("Unknown period kind: {$kind}"),
        };
    }
}
