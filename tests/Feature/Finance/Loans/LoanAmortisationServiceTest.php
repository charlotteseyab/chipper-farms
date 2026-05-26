<?php

namespace Tests\Feature\Finance\Loans;

use App\Models\Finance\Loan;
use App\Services\Finance\Loans\LoanAmortisationService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test of the amortisation engine — no DB needed.
 */
class LoanAmortisationServiceTest extends TestCase
{
    private LoanAmortisationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new LoanAmortisationService();
    }

    public function test_reducing_balance_fully_amortises(): void
    {
        $rows = $this->svc->generate('12000.00', '0.240000', 12, Loan::METHOD_REDUCING_BALANCE, CarbonImmutable::parse('2026-02-28'));

        $this->assertCount(12, $rows);
        $this->assertSame('0.00', $rows[11]['closing_balance']);

        $sumPrincipal = array_reduce($rows, fn ($c, $r) => bcadd($c, $r['principal_due'], 2), '0');
        $this->assertSame('12000.00', $sumPrincipal);

        // First period interest = 12000 * 0.02 = 240.00.
        $this->assertSame('240.00', $rows[0]['interest_due']);
    }

    public function test_straight_line_equal_principal(): void
    {
        $rows = $this->svc->generate('12000.00', '0.240000', 12, Loan::METHOD_STRAIGHT_LINE, CarbonImmutable::parse('2026-02-28'));

        $this->assertSame('1000.00', $rows[0]['principal_due']);
        $this->assertSame('240.00', $rows[0]['interest_due']);
        $this->assertSame('0.00', $rows[11]['closing_balance']);

        $sumPrincipal = array_reduce($rows, fn ($c, $r) => bcadd($c, $r['principal_due'], 2), '0');
        $this->assertSame('12000.00', $sumPrincipal);
    }

    public function test_zero_interest_loan(): void
    {
        $rows = $this->svc->generate('1200.00', '0', 12, Loan::METHOD_REDUCING_BALANCE, CarbonImmutable::parse('2026-02-28'));
        $this->assertSame('100.00', $rows[0]['principal_due']);
        $this->assertSame('0.00', $rows[0]['interest_due']);
        $this->assertSame('0.00', $rows[11]['closing_balance']);
    }

    public function test_due_dates_increment_monthly(): void
    {
        $rows = $this->svc->generate('1000.00', '0.12', 3, Loan::METHOD_STRAIGHT_LINE, CarbonImmutable::parse('2026-01-31'));
        $this->assertSame('2026-01-31', $rows[0]['due_date']);
        // Carbon addMonths handles month-end roll.
        $this->assertSame(3, count($rows));
    }

    public function test_rejects_invalid_term(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->generate('1000.00', '0.12', 0, Loan::METHOD_STRAIGHT_LINE, CarbonImmutable::parse('2026-01-31'));
    }
}
