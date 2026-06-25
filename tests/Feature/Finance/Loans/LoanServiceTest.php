<?php

namespace Tests\Feature\Finance\Loans;

use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Models\Finance\Loan;
use App\Models\Finance\LoanTransaction;
use App\Services\Finance\Loans\LoanService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // Loan + interest COA accounts the service resolves by code.
        $this->acct('2110', 'Bank Loans — Current Portion', Account::TYPE_LIABILITY, 'current_liability', false);
        $this->acct('2510', 'Bank Loans — Long-term', Account::TYPE_LIABILITY, 'non_current_liability', false);
        $this->acct('2130', 'Accrued Loan Interest', Account::TYPE_LIABILITY, 'current_liability', false);
        $this->acct('7010', 'Loan Interest Expense', Account::TYPE_EXPENSE, 'finance_cost');
        $this->acct('7020', 'Loan Fees', Account::TYPE_EXPENSE, 'finance_cost');
        $this->acct('7030', 'Loan Penalties', Account::TYPE_EXPENSE, 'finance_cost');
        $this->acct('1030', 'Bank — Main', Account::TYPE_ASSET, 'current_asset');

        config()->set('loans.types.bank', [
            'label' => 'Bank Loan', 'principal_current' => '2110', 'principal_longterm' => '2510',
            'accrued_interest' => '2130', 'interest_expense' => '7010', 'fees_expense' => '7020', 'penalty_expense' => '7030',
        ]);
        config()->set('loans.default_cash_account_code', '1030');
        config()->set('loans.amortisation.default_method', 'reducing_balance');
        config()->set('loans.amortisation.rounding_scale', 2);
    }

    public function test_create_draft_generates_schedule(): void
    {
        $loan = $this->draftLoan();
        $this->assertSame(Loan::STATUS_DRAFT, $loan->status);
        $this->assertCount(12, $loan->scheduleLines);
    }

    public function test_disburse_posts_balanced_journal(): void
    {
        $loan = $this->draftLoan();
        $loan = app(LoanService::class)->disburse($loan, $this->user, CarbonImmutable::parse('2026-05-10'));

        $this->assertSame(Loan::STATUS_ACTIVE, $loan->status);
        $this->assertSame('12000.00', $loan->principal_outstanding);

        $batchId = LoanTransaction::where('loan_id', $loan->id)->where('txn_type', 'disbursement')->value('journal_batch_id');
        $lines = $this->batchLines($batchId);
        $this->assertEqualsWithDelta($lines->sum(fn ($l) => (float) $l->debit), $lines->sum(fn ($l) => (float) $l->credit), 0.001);

        // Dr 1030 bank 12000 / Cr 2110 principal 12000.
        $this->assertTrue($this->lineExists($lines, '1030', debit: '12000.00'));
        $this->assertTrue($this->lineExists($lines, '2110', credit: '12000.00'));
    }

    public function test_accrue_posts_interest_expense(): void
    {
        $loan = $this->draftLoan();
        $loan = app(LoanService::class)->disburse($loan, $this->user, CarbonImmutable::parse('2026-05-10'));

        $firstLine = $loan->scheduleLines()->orderBy('period_number')->first();
        $txn = app(LoanService::class)->accrueLine($loan->fresh(), $firstLine, $this->user, CarbonImmutable::parse('2026-05-31'));

        $this->assertNotNull($txn);
        $lines = $this->batchLines($txn->journal_batch_id);
        // Dr 7010 interest expense / Cr 2130 accrued.
        $this->assertTrue($this->lineExists($lines, '7010', debit: '240.00'));
        $this->assertTrue($this->lineExists($lines, '2130', credit: '240.00'));

        $this->assertSame('240.00', $loan->fresh()->interest_accrued_outstanding);
    }

    public function test_accrue_is_idempotent(): void
    {
        $loan = $this->draftLoan();
        $loan = app(LoanService::class)->disburse($loan, $this->user, CarbonImmutable::parse('2026-05-10'));
        $firstLine = $loan->scheduleLines()->orderBy('period_number')->first();

        app(LoanService::class)->accrueLine($loan->fresh(), $firstLine, $this->user, CarbonImmutable::parse('2026-05-31'));
        // Second call: line already flagged accrued -> returns null, no double post.
        $second = app(LoanService::class)->accrueLine($loan->fresh(), $firstLine->fresh(), $this->user, CarbonImmutable::parse('2026-05-31'));
        $this->assertNull($second);

        $this->assertSame(1, LoanTransaction::where('loan_id', $loan->id)->where('txn_type', 'accrual')->count());
    }

    public function test_repayment_splits_principal_and_interest(): void
    {
        $loan = $this->draftLoan();
        $loan = app(LoanService::class)->disburse($loan, $this->user, CarbonImmutable::parse('2026-05-10'));
        $firstLine = $loan->scheduleLines()->orderBy('period_number')->first();
        app(LoanService::class)->accrueLine($loan->fresh(), $firstLine, $this->user, CarbonImmutable::parse('2026-05-31'));

        $loan = $loan->fresh();
        $txn = app(LoanService::class)->repay($loan, [
            'principal' => '894.72', 'interest' => '240.00', 'schedule_line_id' => $firstLine->id,
        ], $this->user, CarbonImmutable::parse('2026-05-31'));

        $lines = $this->batchLines($txn->journal_batch_id);
        $this->assertEqualsWithDelta($lines->sum(fn ($l) => (float) $l->debit), $lines->sum(fn ($l) => (float) $l->credit), 0.001);

        // Dr 2110 principal 894.72 / Dr 2130 accrued interest 240 / Cr 1030 bank 1134.72.
        $this->assertTrue($this->lineExists($lines, '2110', debit: '894.72'));
        $this->assertTrue($this->lineExists($lines, '2130', debit: '240.00'));
        $this->assertTrue($this->lineExists($lines, '1030', credit: '1134.72'));

        $loan = $loan->fresh();
        $this->assertSame('11105.28', $loan->principal_outstanding); // 12000 - 894.72
        $this->assertSame('0.00', $loan->interest_accrued_outstanding); // cleared
    }

    public function test_repayment_can_post_to_protected_loan_control_accounts(): void
    {
        $this->assertFalse((bool) Account::where('code', '2110')->firstOrFail()->allow_manual_journal);

        $loan = $this->draftLoan(principal: '1000.00', rate: '0', term: 1);
        $loan = app(LoanService::class)->disburse($loan, $this->user, CarbonImmutable::parse('2026-05-10'));

        $txn = app(LoanService::class)->repay($loan->fresh(), ['principal' => '100.00'], $this->user, CarbonImmutable::parse('2026-05-15'));

        $lines = $this->batchLines($txn->journal_batch_id);
        $this->assertTrue($this->lineExists($lines, '2110', debit: '100.00'));
        $this->assertTrue($this->lineExists($lines, '1030', credit: '100.00'));
    }

    public function test_full_repayment_settles_loan(): void
    {
        $loan = $this->draftLoan(principal: '1000.00', rate: '0', term: 1);
        $loan = app(LoanService::class)->disburse($loan, $this->user, CarbonImmutable::parse('2026-05-10'));

        app(LoanService::class)->repay($loan->fresh(), ['principal' => '1000.00'], $this->user, CarbonImmutable::parse('2026-05-15'));

        $this->assertSame(Loan::STATUS_SETTLED, $loan->fresh()->status);
        $this->assertSame('0.00', $loan->fresh()->principal_outstanding);
    }

    // ---- helpers ----

    protected function draftLoan(string $principal = '12000.00', string $rate = '0.240000', int $term = 12): Loan
    {
        return app(LoanService::class)->createDraft([
            'reference' => 'LN-' . uniqid(),
            'loan_type' => 'bank',
            'lender_name' => 'Test Bank',
            'principal_amount' => $principal,
            'annual_interest_rate' => $rate,
            'term_months' => $term,
            'amortisation_method' => 'reducing_balance',
            'first_payment_date' => '2026-05-31',
            'origination_fee' => '0',
        ], $this->user);
    }

    protected function acct(string $code, string $name, string $type, string $subtype, bool $allowManualJournal = true): Account
    {
        return Account::create([
            'organization_id' => $this->org->id, 'code' => $code, 'name' => $name,
            'account_type' => $type, 'account_subtype' => $subtype, 'currency_code' => 'GHS',
            'is_active' => true, 'allow_manual_journal' => $allowManualJournal,
        ]);
    }

    protected function batchLines(?int $batchId)
    {
        return JournalLine::whereIn('journal_id', Journal::whereIn('journal_batch_id', [$batchId])->pluck('id'))->get();
    }

    protected function lineExists($lines, string $code, ?string $debit = null, ?string $credit = null): bool
    {
        $acctId = Account::where('organization_id', $this->org->id)->where('code', $code)->value('id');
        foreach ($lines as $l) {
            if ((int) $l->account_id !== (int) $acctId) {
                continue;
            }
            if ($debit !== null && bccomp((string) $l->debit, $debit, 2) === 0) {
                return true;
            }
            if ($credit !== null && bccomp((string) $l->credit, $credit, 2) === 0) {
                return true;
            }
        }
        return false;
    }
}
