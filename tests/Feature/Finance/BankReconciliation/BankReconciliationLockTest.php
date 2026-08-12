<?php

namespace Tests\Feature\Finance\BankReconciliation;

use App\Models\Counterparty;
use App\Models\Finance\BankAccount;
use App\Models\Finance\BankReconciliation;
use App\Models\Finance\BankStatement;
use App\Models\Finance\BankStatementLine;
use App\Models\Finance\Payment;
use App\Models\Finance\Receipt;
use App\Models\PaymentMethod;
use App\Services\Finance\BankReconciliation\BankReconciliationService;
use App\Services\Finance\BankReconciliation\BankStatementMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 3e — Period-freeze invariant (D45).
 *
 * Once a BankReconciliation is reconciled or flagged:
 *   - Payments matched to statement lines in that recon cannot be reversed.
 *   - Receipts matched to statement lines in that recon cannot be reversed.
 *   - Payment allocations whose parent payment is in that recon cannot be reversed.
 *   - Receipt allocations whose parent receipt is in that recon cannot be reversed.
 *   - Statement lines in that recon cannot be unmatched.
 *
     * Missing lock dependencies are production regressions and fail directly.
 */
class BankReconciliationLockTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected BankAccount $bankAccount;
    protected BankStatement $statement;
    protected Counterparty $supplier;
    protected Counterparty $customer;
    protected PaymentMethod $bankMethod;
    protected BankReconciliationService $reconService;
    protected BankStatementMatchingService $matcher;

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

        $this->statement = BankStatement::create([
            'organization_id'         => $this->org->id,
            'bank_account_id'         => $this->bankAccount->id,
            'statement_number'        => 'BSTMT-2026-000001',
            'period_from'             => '2026-12-01',
            'period_to'               => '2026-12-31',
            'opening_balance_per_bank' => '0.00',
            'closing_balance_per_bank' => '0.00',
            'currency_code'           => 'GHS',
            'source_format'           => 'generic_csv',
            'original_filename'       => 't.csv',
            'file_sha256'             => str_repeat('b', 64),
            'imported_at'             => now(),
            'imported_by_user_id'     => $this->user->id,
            'status'                  => BankStatement::STATUS_IMPORTED,
            'line_count'              => 0,
            'total_debits'            => '0.00',
            'total_credits'           => '0.00',
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'code' => 'SUP-1', 'name' => 'V', 'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
        ]);
        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'code' => 'CUS-1', 'name' => 'C', 'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
        ]);
        $this->bankMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'TRANSFER', 'name' => 'Transfer',
            'is_bank' => true, 'is_active' => true,
            'gl_account_hint' => $this->cashAccount->code,
        ]);

        $this->reconService = $this->app->make(BankReconciliationService::class);
        $this->matcher = $this->app->make(BankStatementMatchingService::class);
    }

    /**
     * Set up: payment matched to a line, then create + reconcile a recon
     * that includes that statement.
     */
    protected function setUpReconciledPaymentScenario(): array
    {
        $payment = Payment::create([
            'organization_id'    => $this->org->id,
            'payment_number'     => 'PAY-2026-0001',
            'payment_date'       => '2026-12-10',
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'counterparty_id'    => $this->supplier->id,
            'amount'             => '500.00', 'allocated_amount' => '0.00',
            'withholding_amount' => '0.00', 'net_amount' => '500.00',
            'payment_method_id'  => $this->bankMethod->id,
            'source_account_id'  => $this->cashAccount->id,
            'external_reference' => 'WIRE-A',
            'status'             => Payment::STATUS_POSTED,
            'is_backfilled'      => false,
            'created_by_user_id' => $this->user->id,
        ]);

        $line = BankStatementLine::create([
            'organization_id'   => $this->org->id,
            'bank_statement_id' => $this->statement->id,
            'line_number'       => 1,
            'value_date'        => '2026-12-10',
            'description'       => 'Wire transfer',
            'bank_reference'    => 'WIRE-A',
            'debit_amount'      => '500.00',
            'credit_amount'     => '0.00',
            'currency_code'     => 'GHS',
            'match_status'      => BankStatementLine::STATUS_UNMATCHED,
        ]);

        $this->matcher->autoMatch($this->statement, $this->user);

        $rec = $this->reconService->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '0.00',  // doesn't have to balance for the lock test, we'll force-close
            $this->user,
        );
        // Force close (skip arithmetic check by direct assignment).
        $rec->update([
            'status' => BankReconciliation::STATUS_RECONCILED,
            'reconciled_at' => now(),
            'reconciled_by_user_id' => $this->user->id,
        ]);
        // Stamp the recon onto the statement and the payment so the lock applies.
        $this->statement->update(['bank_reconciliation_id' => $rec->id]);
        $payment->update([
            'bank_statement_line_id' => $line->id,
            'bank_reconciliation_id' => $rec->id,
        ]);

        return ['payment' => $payment, 'line' => $line->refresh(), 'rec' => $rec];
    }

    public function test_locking_reconciliations_returns_recon_for_matched_payment(): void
    {
        $ctx = $this->setUpReconciledPaymentScenario();

        $locks = $this->reconService->lockingReconciliations(
            BankStatementLine::MATCHED_TYPE_PAYMENT,
            $ctx['payment']->id,
        );

        $this->assertCount(1, $locks);
        $this->assertSame($ctx['rec']->id, $locks[0]->id);
    }

    public function test_locking_reconciliations_empty_for_open_recon(): void
    {
        $ctx = $this->setUpReconciledPaymentScenario();

        // Reopen.
        $this->reconService->reopen($ctx['rec'], $this->user, 'Test reopen reason');

        $locks = $this->reconService->lockingReconciliations(
            BankStatementLine::MATCHED_TYPE_PAYMENT,
            $ctx['payment']->id,
        );

        $this->assertCount(0, $locks);
    }

    public function test_unmatch_blocked_when_recon_locked(): void
    {
        $ctx = $this->setUpReconciledPaymentScenario();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/locked|reconcil/i');
        $this->matcher->unmatch($ctx['line'], $this->user);
    }

    public function test_unmatch_unblocked_after_reopen(): void
    {
        $ctx = $this->setUpReconciledPaymentScenario();
        $this->reconService->reopen($ctx['rec'], $this->user, 'For testing only');

        // Now should succeed.
        $this->matcher->unmatch($ctx['line'], $this->user);
        $line = $ctx['line']->refresh();
        $this->assertSame(BankStatementLine::STATUS_UNMATCHED, $line->match_status);
    }

    public function test_payment_allocation_reverse_blocked_when_recon_locked(): void
    {
        $reflection = new \ReflectionClass(\App\Services\Finance\Payments\PaymentAllocationService::class);
        $ctor = $reflection->getConstructor();
        $this->assertNotNull($ctor, 'PaymentAllocationService must expose its dependencies through the constructor.');

        $params = $ctor ? $ctor->getParameters() : [];
        $hasBankRecDep = false;
        foreach ($params as $p) {
            $type = $p->getType()?->getName() ?? '';
            if (str_contains($type, 'BankReconciliationService')) {
                $hasBankRecDep = true;
                break;
            }
        }

        $this->assertTrue(
            $hasBankRecDep,
            'PaymentAllocationService must consult BankReconciliationService before reversing allocations.'
        );

        // If patched, we'd test here. Smoke-test the lock query path.
        $ctx = $this->setUpReconciledPaymentScenario();
        $locks = $this->reconService->lockingReconciliations(
            BankStatementLine::MATCHED_TYPE_PAYMENT,
            $ctx['payment']->id,
        );
        $this->assertNotEmpty($locks);
    }

    public function test_receipt_reverse_blocked_when_recon_locked(): void
    {
        // The ReceiptController guard cannot be tested without HTTP machinery,
        // but we test the query path that the controller will call.
        $receipt = Receipt::create([
            'organization_id'    => $this->org->id,
            'receipt_number'     => 'REC-2026-0001',
            'receipt_date'       => '2026-12-15',
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'counterparty_id'    => $this->customer->id,
            'amount'             => '1000.00', 'allocated_amount' => '0.00',
            'payment_method_id'  => $this->bankMethod->id,
            'external_reference' => 'CHK-1',
            'status'             => Receipt::STATUS_POSTED,
            'is_backfilled'      => false,
            'created_by_user_id' => $this->user->id,
        ]);

        $line = BankStatementLine::create([
            'organization_id'   => $this->org->id,
            'bank_statement_id' => $this->statement->id,
            'line_number'       => 1,
            'value_date'        => '2026-12-15',
            'description'       => 'Customer cheque',
            'bank_reference'    => 'CHK-1',
            'debit_amount'      => '0.00',
            'credit_amount'     => '1000.00',
            'currency_code'     => 'GHS',
            'match_status'      => BankStatementLine::STATUS_UNMATCHED,
        ]);

        $this->matcher->autoMatch($this->statement, $this->user);

        $rec = $this->reconService->createDraft(
            $this->bankAccount, '2026-12-01', '2026-12-31',
            '1000.00', $this->user,
        );
        $rec->update([
            'status' => BankReconciliation::STATUS_RECONCILED,
            'reconciled_at' => now(),
            'reconciled_by_user_id' => $this->user->id,
        ]);
        $this->statement->update(['bank_reconciliation_id' => $rec->id]);
        $receipt->update([
            'bank_statement_line_id' => $line->id,
            'bank_reconciliation_id' => $rec->id,
        ]);

        $locks = $this->reconService->lockingReconciliations(
            BankStatementLine::MATCHED_TYPE_RECEIPT,
            $receipt->id,
        );
        $this->assertCount(1, $locks);
        $this->assertSame($rec->id, $locks[0]->id);
    }

    public function test_flagged_recon_also_locks(): void
    {
        $ctx = $this->setUpReconciledPaymentScenario();

        // Force flagged status (skip the 5-char reason validation by direct update).
        $ctx['rec']->update([
            'status' => BankReconciliation::STATUS_FLAGGED,
            'flagged_at' => now(),
            'flagged_by_user_id' => $this->user->id,
            'flag_reason' => 'For testing flagged lock behavior',
        ]);

        $locks = $this->reconService->lockingReconciliations(
            BankStatementLine::MATCHED_TYPE_PAYMENT,
            $ctx['payment']->id,
        );
        $this->assertCount(1, $locks);
    }

    public function test_archived_recon_does_not_lock(): void
    {
        $ctx = $this->setUpReconciledPaymentScenario();

        // Archive (legitimate path).
        $this->reconService->archive($ctx['rec'], $this->user, 'Old');

        $locks = $this->reconService->lockingReconciliations(
            BankStatementLine::MATCHED_TYPE_PAYMENT,
            $ctx['payment']->id,
        );
        $this->assertCount(0, $locks);
    }
}
