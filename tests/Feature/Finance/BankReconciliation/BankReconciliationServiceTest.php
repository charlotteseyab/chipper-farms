<?php

namespace Tests\Feature\Finance\BankReconciliation;

use App\Models\Finance\BankAccount;
use App\Models\Finance\BankReconciliation;
use App\Models\Finance\BankStatement;
use App\Models\Finance\BankStatementLine;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\FiscalPeriod;
use App\Services\Finance\BankReconciliation\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 3e — BankReconciliationService tests.
 */
class BankReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected BankAccount $bankAccount;
    protected BankReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->bankAccount = BankAccount::create([
            'organization_id'      => $this->org->id,
            'account_id'           => $this->cashAccount->id,
            'label'                => 'Ops',
            'bank_name'            => 'Test Bank',
            'currency_code'        => 'GHS',
            'opening_balance'      => '0.00',
            'opening_balance_date' => '2026-01-01',
            'is_active'            => true,
            'created_by_user_id'   => $this->user->id,
        ]);

        $this->service = $this->app->make(BankReconciliationService::class);
    }

    /**
     * Post a journal batch hitting the bank account, on a given date and direction.
     */
    protected function postJournalToBank(string $date, string $debit, string $credit, string $contraDebit = '0.00', string $contraCredit = '0.00'): JournalBatch
    {
        // Counter-entry to the revenue account so the batch balances.
        $batch = JournalBatch::create([
            'organization_id' => $this->org->id,
            'batch_number'    => 'JB-' . random_int(100000, 999999),
            'batch_type'      => 'manual',
            'posting_date'    => $date,
            'description'     => 'Test',
            'status'          => JournalBatch::STATUS_POSTED,
            'fiscal_period_id' => FiscalPeriod::query()
                ->where('organization_id', $this->org->id)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->value('id'),
        ]);

        $journal = Journal::create([
            'organization_id'    => $this->org->id,
            'journal_batch_id'   => $batch->id,
            'journal_number'     => 'J-' . random_int(100000, 999999),
            'journal_type'       => 'manual',
            'posting_date'       => $date,
            'fiscal_period_id'   => $batch->fiscal_period_id,
            'description'        => 'Test',
            'currency_code'      => 'GHS',
            'exchange_rate'      => '1',
            'total_debit'        => bcadd($debit, $contraDebit, 2),
            'total_credit'       => bcadd($credit, $contraCredit, 2),
            'total_debit_tc'     => bcadd($debit, $contraDebit, 2),
            'total_credit_tc'    => bcadd($credit, $contraCredit, 2),
            'status'             => Journal::STATUS_POSTED,
        ]);

        JournalLine::create([
            'organization_id' => $this->org->id,
            'journal_id' => $journal->id,
            'line_number' => 1,
            'account_id' => $this->cashAccount->id,
            'debit'  => $debit,
            'credit' => $credit,
            'debit_tc' => $debit,
            'credit_tc' => $credit,
            'currency_code' => 'GHS',
            'exchange_rate' => '1',
        ]);

        // Contra to revenue.
        JournalLine::create([
            'organization_id' => $this->org->id,
            'journal_id' => $journal->id,
            'line_number' => 2,
            'account_id' => $this->revenueAccount->id,
            'debit'  => bcsub($credit, '0.00', 2),  // mirror
            'credit' => bcsub($debit, '0.00', 2),
            'debit_tc'  => bcsub($credit, '0.00', 2),
            'credit_tc' => bcsub($debit, '0.00', 2),
            'currency_code' => 'GHS',
            'exchange_rate' => '1',
        ]);

        return $batch;
    }

    public function test_create_draft_allocates_number_and_initial_state(): void
    {
        $rec = $this->service->createDraft(
            $this->bankAccount,
            '2026-12-01', '2026-12-31',
            '5000.00',
            $this->user,
        );

        $this->assertInstanceOf(BankReconciliation::class, $rec);
        $this->assertMatchesRegularExpression('/^BREC-\d{4}-\d{6}$/', $rec->reconciliation_number);
        $this->assertSame('5000.00', (string) $rec->closing_balance_per_bank);
        $this->assertSame(BankReconciliation::STATUS_DRAFT, $rec->status);
        $this->assertSame($this->user->id, $rec->created_by_user_id);
    }

    public function test_create_draft_idempotent_for_open_period(): void
    {
        $rec1 = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '1000.00', $this->user,
        );
        $rec2 = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '2000.00', $this->user,
        );

        $this->assertSame($rec1->id, $rec2->id);
    }

    public function test_create_draft_rejects_period_to_before_period_from(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createDraft(
            $this->bankAccount, '2026-12-31', '2026-12-01',
            '0.00', $this->user,
        );
    }

    public function test_compute_book_balance_via_journal_lines(): void
    {
        // 3 entries: +1000, -200, +500 = +1300
        $this->postJournalToBank('2026-12-05', '1000.00', '0.00');
        $this->postJournalToBank('2026-12-10', '0.00',    '200.00');
        $this->postJournalToBank('2026-12-20', '500.00',  '0.00');

        $rec = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '1300.00', $this->user,
        );

        // Bank account opening_balance is 0, and +1300 net within period.
        $this->assertSame('1300.00', (string) $rec->closing_balance_per_books);
    }

    public function test_refresh_arithmetic_produces_correct_difference(): void
    {
        // Books say 1000 closing. Bank says 1100. Diff = 100.
        $this->postJournalToBank('2026-12-05', '1000.00', '0.00');

        $rec = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '1100.00', $this->user,
        );

        // diff = bank_close - book_close - od + ow - bo_cr + bo_dr
        // = 1100 - 1000 - 0 + 0 - 0 + 0 = 100
        $this->assertSame('100.00', (string) $rec->reconciliation_difference);
        $this->assertFalse($rec->balancesAgree());
    }

    public function test_reconcile_refuses_when_not_balanced(): void
    {
        $this->postJournalToBank('2026-12-05', '1000.00', '0.00');
        $rec = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '1100.00', $this->user,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/balance|difference/i');
        $this->service->reconcile($rec, $this->user);
    }

    public function test_reconcile_when_balanced(): void
    {
        $this->postJournalToBank('2026-12-05', '1000.00', '0.00');
        $rec = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '1000.00', $this->user,
        );

        $this->service->reconcile($rec, $this->user);
        $rec->refresh();

        $this->assertSame(BankReconciliation::STATUS_RECONCILED, $rec->status);
        $this->assertSame($this->user->id, $rec->reconciled_by_user_id);
        $this->assertNotNull($rec->reconciled_at);
        $this->assertNotEmpty($rec->summary_snapshot);
    }

    public function test_flag_with_reason(): void
    {
        $this->postJournalToBank('2026-12-05', '1000.00', '0.00');
        $rec = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '1100.00', $this->user,
        );

        $this->service->flag($rec, $this->user, 'Bank charge dispute pending');
        $rec->refresh();

        $this->assertSame(BankReconciliation::STATUS_FLAGGED, $rec->status);
        $this->assertSame('Bank charge dispute pending', $rec->flag_reason);
        $this->assertSame($this->user->id, $rec->flagged_by_user_id);
    }

    public function test_flag_requires_min_5_char_reason(): void
    {
        $rec = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31', '0.00', $this->user,
        );
        $this->expectException(\InvalidArgumentException::class);
        $this->service->flag($rec, $this->user, 'no');
    }

    public function test_reopen_clears_locks_and_returns_to_in_progress(): void
    {
        $this->postJournalToBank('2026-12-05', '1000.00', '0.00');
        $rec = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '1000.00', $this->user,
        );
        $this->service->reconcile($rec, $this->user);

        $this->service->reopen($rec, $this->user, 'Found a missing line item');
        $rec->refresh();

        $this->assertSame(BankReconciliation::STATUS_IN_PROGRESS, $rec->status);
        $this->assertNull($rec->reconciled_at);
        $this->assertNull($rec->reconciled_by_user_id);
    }

    public function test_archive_only_from_closed_states(): void
    {
        $rec = $this->service->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31', '0.00', $this->user,
        );

        // Cannot archive a draft.
        $this->expectException(\DomainException::class);
        $this->service->archive($rec, $this->user, 'cleanup');
    }
}
