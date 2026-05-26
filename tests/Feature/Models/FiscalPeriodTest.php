<?php

namespace Tests\Feature\Models;

use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function makePeriod(string $status = FiscalPeriod::STATUS_OPEN): FiscalPeriod
    {
        $fy = FiscalYear::create([
            'name' => 'FY2026',
            'fiscal_year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYear::STATUS_OPEN,
            'is_current' => true,
        ]);

        return FiscalPeriod::create([
            'fiscal_year_id' => $fy->id,
            'period_number' => 1,
            'name' => 'Jan 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => $status,
        ]);
    }

    public function test_open_period_accepts_postings(): void
    {
        $p = $this->makePeriod(FiscalPeriod::STATUS_OPEN);

        $this->assertTrue($p->isOpen());
        $this->assertFalse($p->isClosed());
        $this->assertFalse($p->isLocked());
        $this->assertTrue($p->acceptsPostings());
    }

    public function test_closed_period_does_not_accept_postings(): void
    {
        $p = $this->makePeriod(FiscalPeriod::STATUS_CLOSED);

        $this->assertFalse($p->isOpen());
        $this->assertTrue($p->isClosed());
        $this->assertFalse($p->acceptsPostings());
    }

    public function test_locked_period_does_not_accept_postings(): void
    {
        $p = $this->makePeriod(FiscalPeriod::STATUS_LOCKED);

        $this->assertFalse($p->isOpen());
        $this->assertFalse($p->isClosed());
        $this->assertTrue($p->isLocked());
        $this->assertFalse($p->acceptsPostings());
    }

    public function test_covering_date_scope_finds_correct_period(): void
    {
        $fy = FiscalYear::create([
            'name' => 'FY2026', 'fiscal_year' => 2026,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'status' => 'open', 'is_current' => true,
        ]);

        FiscalPeriod::create([
            'fiscal_year_id' => $fy->id, 'period_number' => 1, 'name' => 'Jan 2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => 'open',
        ]);
        FiscalPeriod::create([
            'fiscal_year_id' => $fy->id, 'period_number' => 2, 'name' => 'Feb 2026',
            'start_date' => '2026-02-01', 'end_date' => '2026-02-28', 'status' => 'open',
        ]);

        $p = FiscalPeriod::coveringDate('2026-02-15')->first();

        $this->assertNotNull($p);
        $this->assertSame('Feb 2026', $p->name);
    }

    public function test_period_belongs_to_fiscal_year(): void
    {
        $p = $this->makePeriod();
        $this->assertNotNull($p->fiscalYear);
        $this->assertSame('FY2026', $p->fiscalYear->name);
    }
}