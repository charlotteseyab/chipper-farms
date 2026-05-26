<?php

namespace Tests\Feature\Finance\Loans;

use App\Models\Finance\Account;
use App\Services\Finance\Loans\LoanReclassificationService;
use App\Services\Finance\Loans\LoanService;
use App\Services\Finance\Treasury\TreasuryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Covers the current/long-term reclassification report and the treasury
 * cash-position view. Both are read-only aggregations over loans + GL.
 */
class LoanReclassificationAndTreasuryTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        foreach ([
            ['2110', 'Bank Loans Current', Account::TYPE_LIABILITY, 'current_liability'],
            ['2510', 'Bank Loans LT', Account::TYPE_LIABILITY, 'non_current_liability'],
            ['2130', 'Accrued Loan Interest', Account::TYPE_LIABILITY, 'current_liability'],
            ['7010', 'Loan Interest Expense', Account::TYPE_EXPENSE, 'finance_cost'],
            ['7020', 'Loan Fees', Account::TYPE_EXPENSE, 'finance_cost'],
            ['7030', 'Loan Penalties', Account::TYPE_EXPENSE, 'finance_cost'],
            ['1030', 'Bank Main', Account::TYPE_ASSET, 'current_asset'],
        ] as [$code, $name, $type, $sub]) {
            Account::create([
                'organization_id' => $this->org->id, 'code' => $code, 'name' => $name,
                'account_type' => $type, 'account_subtype' => $sub, 'currency_code' => 'GHS',
                'is_active' => true, 'allow_manual_journal' => true,
            ]);
        }

        config()->set('loans.types.bank', [
            'label' => 'Bank Loan', 'principal_current' => '2110', 'principal_longterm' => '2510',
            'accrued_interest' => '2130', 'interest_expense' => '7010', 'fees_expense' => '7020', 'penalty_expense' => '7030',
        ]);
        config()->set('loans.default_cash_account_code', '1030');
        config()->set('loans.amortisation.rounding_scale', 2);
    }

    public function test_reclassification_splits_current_and_non_current(): void
    {
        // 24-month loan; ~first 12 instalments' principal is current, rest non-current.
        $loan = app(LoanService::class)->createDraft([
            'reference' => 'LN-RC', 'loan_type' => 'bank', 'lender_name' => 'Bank',
            'principal_amount' => '24000.00', 'annual_interest_rate' => '0.120000', 'term_months' => 24,
            'amortisation_method' => 'straight_line', 'first_payment_date' => '2026-06-30', 'origination_fee' => '0',
        ], $this->user);
        $loan = app(LoanService::class)->disburse($loan, $this->user, CarbonImmutable::parse('2026-05-10'));

        $report = app(LoanReclassificationService::class)->generate(CarbonImmutable::parse('2026-05-31'));

        $line = collect($report['lines'])->firstWhere('reference', 'LN-RC');
        $this->assertNotNull($line);
        // Straight line 24000/24 = 1000/mo. Next 12 months -> ~12000 current.
        $this->assertEqualsWithDelta(12000.00, (float) $line['current_portion'], 1000.00);
        // current + non_current = outstanding.
        $this->assertSame(
            bcadd($line['current_portion'], $line['non_current_portion'], 2),
            $line['outstanding'],
        );
    }

    public function test_treasury_reports_cash_and_upcoming(): void
    {
        // Parent 1000 + a child cash account 1030 already created above; reparent it.
        $parent = Account::create([
            'organization_id' => $this->org->id, 'code' => '1000', 'name' => 'Cash and Bank',
            'account_type' => Account::TYPE_ASSET, 'account_subtype' => 'current_asset', 'currency_code' => 'GHS',
            'is_active' => true, 'allow_manual_journal' => false,
        ]);
        Account::where('organization_id', $this->org->id)->where('code', '1030')->update(['parent_id' => $parent->id]);

        // Disburse a loan -> Dr 1030 (cash up) / Cr 2110, and creates a schedule.
        $loan = app(LoanService::class)->createDraft([
            'reference' => 'LN-TR', 'loan_type' => 'bank', 'lender_name' => 'Bank',
            'principal_amount' => '12000.00', 'annual_interest_rate' => '0.240000', 'term_months' => 12,
            'amortisation_method' => 'reducing_balance', 'first_payment_date' => '2026-06-30', 'origination_fee' => '0',
        ], $this->user);
        app(LoanService::class)->disburse($loan, $this->user, CarbonImmutable::parse('2026-05-10'));

        $pos = app(TreasuryService::class)->position(CarbonImmutable::parse('2026-05-31'), 12);

        // Cash on 1030 should reflect the 12000 disbursement debit.
        $cash1030 = collect($pos['cash_accounts'])->firstWhere('code', '1030');
        $this->assertNotNull($cash1030);
        $this->assertSame('12000.00', $cash1030['balance']);

        // Upcoming obligations: schedule lines from 2026-06-30 onward.
        $this->assertNotEmpty($pos['upcoming']);
        $this->assertGreaterThan(0, (float) $pos['upcoming_total']);
    }
}
