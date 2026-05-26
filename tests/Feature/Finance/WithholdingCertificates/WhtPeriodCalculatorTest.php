<?php

namespace Tests\Feature\Finance\WithholdingCertificates;

use App\Services\Finance\WithholdingCertificates\WhtPeriodCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class WhtPeriodCalculatorTest extends TestCase
{
    protected WhtPeriodCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new WhtPeriodCalculator();
    }

    public function test_quarter_boundaries(): void
    {
        $q1 = $this->calc->quarter(2026, 1);
        $this->assertSame('2026-01-01', $q1['from']->toDateString());
        $this->assertSame('2026-03-31', $q1['to']->toDateString());
        $this->assertSame('Q1 2026', $q1['label']);

        $q2 = $this->calc->quarter(2026, 2);
        $this->assertSame('2026-04-01', $q2['from']->toDateString());
        $this->assertSame('2026-06-30', $q2['to']->toDateString());

        $q3 = $this->calc->quarter(2026, 3);
        $this->assertSame('2026-07-01', $q3['from']->toDateString());
        $this->assertSame('2026-09-30', $q3['to']->toDateString());

        $q4 = $this->calc->quarter(2026, 4);
        $this->assertSame('2026-10-01', $q4['from']->toDateString());
        $this->assertSame('2026-12-31', $q4['to']->toDateString());
    }

    public function test_quarter_rejects_invalid_index(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calc->quarter(2026, 5);
    }

    public function test_month_boundaries(): void
    {
        $jan = $this->calc->month(2026, 1);
        $this->assertSame('2026-01-01', $jan['from']->toDateString());
        $this->assertSame('2026-01-31', $jan['to']->toDateString());
        $this->assertSame('January 2026', $jan['label']);

        $feb = $this->calc->month(2026, 2);
        $this->assertSame('2026-02-28', $feb['to']->toDateString(), '2026 is not a leap year');

        $febLeap = $this->calc->month(2024, 2);
        $this->assertSame('2024-02-29', $febLeap['to']->toDateString(), '2024 IS a leap year');
    }

    public function test_month_rejects_invalid_index(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calc->month(2026, 13);
    }

    public function test_year_boundaries(): void
    {
        $y = $this->calc->year(2026);
        $this->assertSame('2026-01-01', $y['from']->toDateString());
        $this->assertSame('2026-12-31', $y['to']->toDateString());
        $this->assertSame('2026', $y['label']);
    }

    public function test_boundaries_dispatches_by_kind(): void
    {
        $q = $this->calc->boundaries('quarter', 2026, 3);
        $this->assertSame('Q3 2026', $q['label']);

        $m = $this->calc->boundaries('month', 2026, 6);
        $this->assertSame('June 2026', $m['label']);

        $y = $this->calc->boundaries('year', 2026, 1);
        $this->assertSame('2026', $y['label']);
    }

    public function test_boundaries_rejects_unknown_kind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calc->boundaries('weekly', 2026, 1);
    }

    public function test_period_for_date_quarter(): void
    {
        $r = $this->calc->periodForDate(CarbonImmutable::parse('2026-08-15'), 'quarter');
        $this->assertSame(['kind' => 'quarter', 'year' => 2026, 'index' => 3], $r);

        // Last day of Q3
        $r2 = $this->calc->periodForDate(CarbonImmutable::parse('2026-09-30'), 'quarter');
        $this->assertSame(3, $r2['index']);

        // First day of Q4
        $r3 = $this->calc->periodForDate(CarbonImmutable::parse('2026-10-01'), 'quarter');
        $this->assertSame(4, $r3['index']);
    }

    public function test_period_for_date_month(): void
    {
        $r = $this->calc->periodForDate(CarbonImmutable::parse('2026-08-15'), 'month');
        $this->assertSame(['kind' => 'month', 'year' => 2026, 'index' => 8], $r);
    }

    public function test_period_for_date_year(): void
    {
        $r = $this->calc->periodForDate(CarbonImmutable::parse('2026-08-15'), 'year');
        $this->assertSame(['kind' => 'year', 'year' => 2026, 'index' => 1], $r);
    }
}
