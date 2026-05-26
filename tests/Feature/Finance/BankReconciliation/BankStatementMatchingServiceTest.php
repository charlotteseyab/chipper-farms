<?php

namespace Tests\Feature\Finance\BankReconciliation;

use App\Models\Counterparty;
use App\Models\Finance\BankAccount;
use App\Models\Finance\BankStatement;
use App\Models\Finance\BankStatementLine;
use App\Models\Finance\Payment;
use App\Models\Finance\Receipt;
use App\Models\PaymentMethod;
use App\Services\Finance\BankReconciliation\BankStatementMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 3e — BankStatementMatchingService tests.
 *
 * Three-pass match engine: exact-ref, amount+date, embedded-ref.
 */
class BankStatementMatchingServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected BankAccount $bankAccount;
    protected BankStatement $statement;
    protected Counterparty $supplier;
    protected Counterparty $customer;
    protected PaymentMethod $bankMethod;
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
            'original_filename'       => 'test.csv',
            'file_sha256'             => str_repeat('a', 64),
            'imported_at'             => now(),
            'imported_by_user_id'     => $this->user->id,
            'status'                  => BankStatement::STATUS_IMPORTED,
            'line_count'              => 0,
            'total_debits'            => '0.00',
            'total_credits'           => '0.00',
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'code' => 'SUP-1', 'name' => 'Vendor X',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
        ]);

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'code' => 'CUS-1', 'name' => 'Buyer Y',
            'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
        ]);

        $this->bankMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'TRANSFER', 'name' => 'Transfer',
            'is_bank' => true, 'is_active' => true,
            'gl_account_hint' => $this->cashAccount->code,
        ]);

        $this->matcher = $this->app->make(BankStatementMatchingService::class);
    }

    protected function makeLine(array $attrs = []): BankStatementLine
    {
        return BankStatementLine::create(array_merge([
            'organization_id'   => $this->org->id,
            'bank_statement_id' => $this->statement->id,
            'line_number'       => 1,
            'value_date'        => '2026-12-15',
            'description'       => 'Test',
            'debit_amount'      => '0.00',
            'credit_amount'     => '0.00',
            'currency_code'     => 'GHS',
            'match_status'      => BankStatementLine::STATUS_UNMATCHED,
        ], $attrs));
    }

    protected function makePayment(string $amount, string $date, ?string $extRef = null, ?string $number = null): Payment
    {
        return Payment::create([
            'organization_id'    => $this->org->id,
            'payment_number'     => $number ?? 'PAY-2026-' . str_pad((string) random_int(1, 99999), 7, '0', STR_PAD_LEFT),
            'payment_date'       => $date,
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'counterparty_id'    => $this->supplier->id,
            'amount'             => $amount,
            'allocated_amount'   => '0.00',
            'withholding_amount' => '0.00',
            'net_amount'         => $amount,
            'payment_method_id'  => $this->bankMethod->id,
            'source_account_id'  => $this->cashAccount->id,
            'external_reference' => $extRef,
            'status'             => Payment::STATUS_POSTED,
            'is_backfilled'      => false,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    protected function makeReceipt(string $amount, string $date, ?string $extRef = null, ?string $number = null): Receipt
    {
        return Receipt::create([
            'organization_id'    => $this->org->id,
            'receipt_number'     => $number ?? 'REC-2026-' . str_pad((string) random_int(1, 99999), 7, '0', STR_PAD_LEFT),
            'receipt_date'       => $date,
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'counterparty_id'    => $this->customer->id,
            'amount'             => $amount,
            'allocated_amount'   => '0.00',
            'payment_method_id'  => $this->bankMethod->id,
            'external_reference' => $extRef,
            'status'             => Receipt::STATUS_POSTED,
            'is_backfilled'      => false,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    public function test_strategy1_exact_reference_matches_payment(): void
    {
        $payment = $this->makePayment('500.00', '2026-12-10', 'WIRE-XYZ-123');
        $line = $this->makeLine([
            'debit_amount'   => '500.00',
            'value_date'     => '2026-12-10',
            'bank_reference' => 'WIRE-XYZ-123',
            'description'    => 'Wire transfer to vendor',
        ]);

        $stats = $this->matcher->autoMatch($this->statement, $this->user);

        $this->assertSame(1, $stats['matched']);
        $line->refresh();
        $this->assertSame(BankStatementLine::STATUS_MATCHED, $line->match_status);
        $this->assertSame(BankStatementLine::MATCHED_TYPE_PAYMENT, $line->matched_type);
        $this->assertSame($payment->id, $line->matched_id);

        $payment->refresh();
        $this->assertSame($line->id, $payment->bank_statement_line_id);
    }

    public function test_strategy1_exact_reference_matches_receipt(): void
    {
        $receipt = $this->makeReceipt('1500.00', '2026-12-15', 'CHK-9988');
        $line = $this->makeLine([
            'credit_amount'  => '1500.00',
            'value_date'     => '2026-12-15',
            'bank_reference' => 'CHK-9988',
        ]);

        $stats = $this->matcher->autoMatch($this->statement, $this->user);

        $this->assertSame(1, $stats['matched']);
        $line->refresh();
        $this->assertSame(BankStatementLine::MATCHED_TYPE_RECEIPT, $line->matched_type);
        $this->assertSame($receipt->id, $line->matched_id);
    }

    public function test_strategy2_amount_date_match_within_window(): void
    {
        $payment = $this->makePayment('250.00', '2026-12-12');
        $line = $this->makeLine([
            'debit_amount' => '250.00',
            'value_date'   => '2026-12-13',  // 1 day later, within ±5
            'description'  => 'Some payment',
        ]);

        $stats = $this->matcher->autoMatch($this->statement, $this->user);

        $this->assertSame(1, $stats['matched']);
        $line->refresh();
        $this->assertSame($payment->id, $line->matched_id);
    }

    public function test_strategy2_amount_date_skips_outside_window(): void
    {
        $this->makePayment('250.00', '2026-12-01');
        $line = $this->makeLine([
            'debit_amount' => '250.00',
            'value_date'   => '2026-12-15',  // 14 days, outside ±5
        ]);

        $stats = $this->matcher->autoMatch($this->statement, $this->user);

        $this->assertSame(0, $stats['matched']);
        $line->refresh();
        $this->assertSame(BankStatementLine::STATUS_UNMATCHED, $line->match_status);
    }

    public function test_strategy3_embedded_reference_in_description(): void
    {
        $payment = $this->makePayment('999.99', '2026-12-15', null, 'PAY-2026-0000999');
        $line = $this->makeLine([
            'debit_amount' => '999.99',
            'value_date'   => '2026-12-30',  // outside strategy 2 window
            'description'  => 'PAYMENT REF PAY-2026-0000999 / VENDOR ACME',
        ]);

        $stats = $this->matcher->autoMatch($this->statement, $this->user);

        $this->assertSame(1, $stats['matched']);
        $line->refresh();
        $this->assertSame($payment->id, $line->matched_id);
    }

    public function test_marks_duplicate_when_multiple_candidates(): void
    {
        $this->makePayment('400.00', '2026-12-10', 'SHARED-REF');
        $this->makePayment('400.00', '2026-12-11', 'SHARED-REF');

        $line = $this->makeLine([
            'debit_amount'   => '400.00',
            'value_date'     => '2026-12-10',
            'bank_reference' => 'SHARED-REF',
        ]);

        $stats = $this->matcher->autoMatch($this->statement, $this->user);

        $this->assertSame(1, $stats['duplicates']);
        $line->refresh();
        $this->assertSame(BankStatementLine::STATUS_DUPLICATE, $line->match_status);
        $this->assertCount(2, $line->duplicate_candidate_ids ?? []);
    }

    public function test_unmatched_when_no_candidate(): void
    {
        $line = $this->makeLine([
            'credit_amount' => '777.77',
            'value_date'    => '2026-12-15',
            'description'   => 'Unrelated bank fee',
        ]);

        $stats = $this->matcher->autoMatch($this->statement, $this->user);

        $this->assertSame(0, $stats['matched']);
        $this->assertSame(1, $stats['unmatched']);
        $line->refresh();
        $this->assertSame(BankStatementLine::STATUS_UNMATCHED, $line->match_status);
    }

    public function test_manual_match_rejects_wrong_amount(): void
    {
        $payment = $this->makePayment('100.00', '2026-12-10');
        $line = $this->makeLine(['debit_amount' => '999.00', 'value_date' => '2026-12-10']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('amount');
        $this->matcher->manualMatch(
            $line,
            BankStatementLine::MATCHED_TYPE_PAYMENT,
            $payment->id,
            $this->user,
        );
    }

    public function test_manual_match_rejects_wrong_direction(): void
    {
        // Payment = money out (debit on bank). Try to match it to a credit line.
        $payment = $this->makePayment('250.00', '2026-12-10');
        $line = $this->makeLine(['credit_amount' => '250.00', 'value_date' => '2026-12-10']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('direction');
        $this->matcher->manualMatch(
            $line,
            BankStatementLine::MATCHED_TYPE_PAYMENT,
            $payment->id,
            $this->user,
        );
    }

    public function test_manual_match_rejects_wrong_source_account(): void
    {
        // Payment from a different GL account.
        $otherAcct = \App\Models\Finance\Account::create([
            'organization_id' => $this->org->id,
            'code' => '1020', 'name' => 'Petty Cash',
            'account_type' => \App\Models\Finance\Account::TYPE_ASSET,
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'organization_id'    => $this->org->id,
            'payment_number'     => 'PAY-2026-9999999',
            'payment_date'       => '2026-12-10',
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'counterparty_id'    => $this->supplier->id,
            'amount'             => '500.00',
            'allocated_amount'   => '0.00',
            'withholding_amount' => '0.00', 'net_amount' => '500.00',
            'payment_method_id'  => $this->bankMethod->id,
            'source_account_id'  => $otherAcct->id,
            'status'             => Payment::STATUS_POSTED,
            'is_backfilled'      => false,
            'created_by_user_id' => $this->user->id,
        ]);

        $line = $this->makeLine(['debit_amount' => '500.00', 'value_date' => '2026-12-10']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/different (GL )?account|wrong account/i');
        $this->matcher->manualMatch(
            $line,
            BankStatementLine::MATCHED_TYPE_PAYMENT,
            $payment->id,
            $this->user,
        );
    }

    public function test_unmatch_clears_both_sides(): void
    {
        $payment = $this->makePayment('500.00', '2026-12-10', 'XX1');
        $line = $this->makeLine([
            'debit_amount' => '500.00', 'value_date' => '2026-12-10',
            'bank_reference' => 'XX1',
        ]);

        $this->matcher->autoMatch($this->statement, $this->user);
        $line->refresh();
        $payment->refresh();
        $this->assertSame($payment->id, $line->matched_id);
        $this->assertSame($line->id, $payment->bank_statement_line_id);

        $this->matcher->unmatch($line, $this->user);

        $line->refresh();
        $payment->refresh();
        $this->assertSame(BankStatementLine::STATUS_UNMATCHED, $line->match_status);
        $this->assertNull($line->matched_type);
        $this->assertNull($line->matched_id);
        $this->assertNull($payment->bank_statement_line_id);
    }

    public function test_mark_manually_resolved(): void
    {
        $line = $this->makeLine(['credit_amount' => '50.00', 'value_date' => '2026-12-15']);
        $this->matcher->markManuallyResolved($line, $this->user, 'Bank error — to investigate');

        $line->refresh();
        $this->assertSame(BankStatementLine::STATUS_MANUALLY_RESOLVED, $line->match_status);
        $this->assertSame('Bank error — to investigate', $line->match_notes);
    }

    public function test_mark_manually_resolved_requires_min_3_chars(): void
    {
        $line = $this->makeLine(['credit_amount' => '50.00', 'value_date' => '2026-12-15']);
        $this->expectException(\InvalidArgumentException::class);
        $this->matcher->markManuallyResolved($line, $this->user, 'no');
    }

    public function test_skips_already_matched_lines_on_rerun(): void
    {
        $payment = $this->makePayment('200.00', '2026-12-10', 'REF-A');
        $line = $this->makeLine([
            'debit_amount' => '200.00', 'value_date' => '2026-12-10',
            'bank_reference' => 'REF-A',
        ]);

        $stats1 = $this->matcher->autoMatch($this->statement, $this->user);
        $this->assertSame(1, $stats1['matched']);

        $stats2 = $this->matcher->autoMatch($this->statement, $this->user);
        $this->assertSame(0, $stats2['matched']);
        $this->assertSame(1, $stats2['skipped_already_matched']);
    }
}
