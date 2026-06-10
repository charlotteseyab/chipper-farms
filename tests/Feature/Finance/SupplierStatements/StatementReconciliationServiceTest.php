<?php

namespace Tests\Feature\Finance\SupplierStatements;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Payment;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\SupplierStatement;
use App\Models\Finance\SupplierStatementLine;
use App\Models\Finance\VendorInvoice;
use App\Models\PaymentMethod;
use App\Services\Finance\SupplierStatements\StatementReconciliationService;
use App\Services\Finance\SupplierStatements\SupplierStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * StatementReconciliationService — the matching engine.
 */
class StatementReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $supplier;
    protected SupplierStatementService $hdrService;
    protected StatementReconciliationService $matcher;
    protected PaymentMethod $bankMethod;
    protected Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $expense = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '6010'],
            ['name' => 'Sundry Expense', 'account_type' => Account::TYPE_EXPENSE,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'GEN', 'name' => 'General',
            'default_expense_account_id' => $expense->id,
            'payment_terms_days' => 30, 'default_withholding_percent' => 0,
            'is_active' => true,
        ]);
        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme Vendor', 'code' => 'VEND-A',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id, 'tax_id' => 'P0001234567',
        ]);

        $this->bankAccount = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '1020'],
            ['name' => 'Bank GCB', 'account_type' => Account::TYPE_ASSET,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
        $this->bankMethod = PaymentMethod::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => 'BANK'],
            ['name' => 'Bank Transfer', 'is_active' => true,
             'default_source_account_id' => $this->bankAccount->id],
        );

        $this->hdrService = $this->app->make(SupplierStatementService::class);
        $this->matcher    = $this->app->make(StatementReconciliationService::class);
    }

    public function test_invoice_line_matches_by_supplier_invoice_number_case_insensitive(): void
    {
        $vi = $this->makeVi('1000.00', '2026-12-05', supplierRef: 'ACM-001');

        $stmt = $this->makeStatement('1000.00');
        $this->hdrService->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => 'acm-001', // lowercase
            'amount' => '1000.00',
        ]);

        $summary = $this->matcher->reconcile($stmt->fresh(), $this->user);

        $this->assertSame(1, $summary['matched']);
        $line = $stmt->lines()->first();
        $this->assertSame(SupplierStatementLine::STATUS_MATCHED, $line->match_status);
        $this->assertSame((int) $vi->id, (int) $line->matched_id);
        $this->assertSame(SupplierStatementLine::MATCHED_TYPE_VENDOR_INVOICE, $line->matched_type);
    }

    public function test_invoice_line_amount_mismatch_when_total_differs(): void
    {
        $this->makeVi('1000.00', '2026-12-05', supplierRef: 'ACM-001');

        $stmt = $this->makeStatement('1100.00');
        $this->hdrService->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => 'ACM-001',
            'amount' => '1100.00',
        ]);

        $summary = $this->matcher->reconcile($stmt->fresh(), $this->user);

        $this->assertSame(1, $summary['amount_mismatch']);
        $line = $stmt->lines()->first();
        $this->assertSame(SupplierStatementLine::STATUS_AMOUNT_MISMATCH, $line->match_status);
        $this->assertSame('100.00', (string) $line->amount_difference);
    }

    public function test_invoice_line_not_in_our_books_when_no_match(): void
    {
        $stmt = $this->makeStatement('500.00');
        $this->hdrService->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => 'GHOST-001',
            'amount' => '500.00',
        ]);

        $summary = $this->matcher->reconcile($stmt->fresh(), $this->user);

        $this->assertSame(1, $summary['not_in_our_books']);
        $line = $stmt->lines()->first();
        $this->assertSame(SupplierStatementLine::STATUS_NOT_IN_OUR_BOOKS, $line->match_status);
    }

    public function test_duplicate_supplier_invoice_numbers_are_rejected_by_vendor_invoice_constraint(): void
    {
        $this->makeVi('500.00', '2026-12-05', supplierRef: 'DUP-001');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->makeVi('500.00', '2026-12-15', supplierRef: 'DUP-001');
    }

    public function test_payment_line_matches_by_external_reference(): void
    {
        $payment = $this->makePayment('500.00', '2026-12-10', externalReference: 'CHQ-99001');

        $stmt = $this->makeStatement('500.00');
        $this->hdrService->addLine($stmt, [
            'document_date' => '2026-12-10', 'document_kind' => 'payment',
            'document_reference' => 'CHQ-99001',
            'amount' => '500.00',
        ]);

        $summary = $this->matcher->reconcile($stmt->fresh(), $this->user);

        $this->assertSame(1, $summary['matched']);
        $line = $stmt->lines()->first();
        $this->assertSame(SupplierStatementLine::MATCHED_TYPE_PAYMENT, $line->matched_type);
        $this->assertSame((int) $payment->id, (int) $line->matched_id);
    }

    public function test_payment_line_falls_back_to_amount_date_when_ref_missing(): void
    {
        // Their statement has a different ref scheme; we have a payment
        // with no external_reference but the right amount and date.
        $payment = $this->makePayment('300.00', '2026-12-08', externalReference: null);

        $stmt = $this->makeStatement('300.00');
        $this->hdrService->addLine($stmt, [
            'document_date' => '2026-12-08', 'document_kind' => 'payment',
            'document_reference' => 'theirs/PAY-X',  // doesn't match ours
            'amount' => '300.00',
        ]);

        $summary = $this->matcher->reconcile($stmt->fresh(), $this->user);

        // Strategy 2 fallback: only 1 candidate by amount + date.
        $this->assertSame(1, $summary['matched']);
        $line = $stmt->lines()->first();
        $this->assertSame((int) $payment->id, (int) $line->matched_id);
    }

    public function test_payment_line_fuzzy_match_within_3_day_window(): void
    {
        $payment = $this->makePayment('300.00', '2026-12-08', externalReference: null);

        $stmt = $this->makeStatement('300.00');
        $this->hdrService->addLine($stmt, [
            'document_date' => '2026-12-10', // 2 days off
            'document_kind' => 'payment',
            'document_reference' => 'SUPPLIER-REF-UNMATCHED',
            'amount' => '300.00',
        ]);

        $summary = $this->matcher->reconcile($stmt->fresh(), $this->user);
        $this->assertSame(1, $summary['matched']);
    }

    public function test_payment_line_outside_3_day_window_does_not_match(): void
    {
        $this->makePayment('300.00', '2026-12-08', externalReference: null);

        $stmt = $this->makeStatement('300.00');
        $this->hdrService->addLine($stmt, [
            'document_date' => '2026-12-15', // 7 days off
            'document_kind' => 'payment',
            'document_reference' => 'SUPPLIER-REF-UNMATCHED',
            'amount' => '300.00',
        ]);

        $summary = $this->matcher->reconcile($stmt->fresh(), $this->user);
        $this->assertSame(0, $summary['matched']);
        $this->assertSame(1, $summary['not_in_our_books']);
    }

    public function test_manually_resolved_lines_are_skipped(): void
    {
        $stmt = $this->makeStatement('100.00');
        $line = $this->hdrService->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => 'GHOST-X', 'amount' => '100',
        ]);
        $this->matcher->markLineManuallyResolved($line->fresh(), $this->user, 'Accepted as forex variance.');

        $summary = $this->matcher->reconcile($stmt->fresh(), $this->user);

        $this->assertSame(1, $summary['skipped_resolved']);
        $this->assertSame(0, $summary['not_in_our_books']);
        $line = $stmt->lines()->first();
        $this->assertSame(SupplierStatementLine::STATUS_MANUALLY_RESOLVED, $line->match_status);
    }

    public function test_compute_our_balance_sums_open_vendor_invoices(): void
    {
        $this->makeVi('1000.00', '2026-12-01', supplierRef: 'A', balance: '1000.00');
        $this->makeVi('500.00',  '2026-12-15', supplierRef: 'B', balance: '500.00');
        // Outside period_to → ignored
        $this->makeVi('999.00',  '2027-01-05', supplierRef: 'C', balance: '999.00');

        $stmt = $this->makeStatement('2000.00');
        $summary = $this->matcher->reconcile($stmt->fresh(), $this->user);

        $this->assertSame('1500.00', $summary['our_balance']);
        $this->assertSame('500.00', $summary['balance_difference']);
    }

    public function test_missing_from_statement_lists_open_vis_not_cited(): void
    {
        $citedVi   = $this->makeVi('1000.00', '2026-12-01', supplierRef: 'CITED');
        $missingVi = $this->makeVi('500.00',  '2026-12-15', supplierRef: 'MISSING');

        $stmt = $this->makeStatement('1000.00');
        $this->hdrService->addLine($stmt, [
            'document_date' => '2026-12-01', 'document_kind' => 'invoice',
            'document_reference' => 'CITED', 'amount' => '1000.00',
        ]);

        $missing = $this->matcher->missingFromStatement($stmt->fresh());
        $this->assertCount(1, $missing);
        $this->assertSame((int) $missingVi->id, (int) $missing[0]->id);
    }

    /* helpers */

    protected function makeStatement(string $supplierBalance): SupplierStatement
    {
        return $this->hdrService->createDraft(
            $this->supplier,
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-12-01'),
            new \DateTimeImmutable('2026-12-31'),
            $supplierBalance,
            $this->user,
        );
    }

    protected function makeVi(
        string $total,
        string $date,
        string $supplierRef,
        ?string $balance = null,
    ): VendorInvoice {
        static $counter = 0;
        return VendorInvoice::create([
            'organization_id' => $this->org->id,
            'vendor_invoice_number' => 'VI-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'supplier_invoice_number' => $supplierRef,
            'invoice_date' => $date, 'due_date' => $date,
            'counterparty_id' => $this->supplier->id,
            'currency_code' => 'GHS', 'exchange_rate' => '1',
            'status' => VendorInvoice::STATUS_POSTED,
            'subtotal' => $total, 'tax_total' => '0', 'withholding_total' => '0',
            'total' => $total,
            'amount_paid' => '0', 'balance_due' => $balance ?? $total,
        ]);
    }

    protected function makePayment(
        string $amount,
        string $date,
        ?string $externalReference,
    ): Payment {
        static $counter = 0;
        return Payment::create([
            'organization_id' => $this->org->id,
            'payment_number' => 'P-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'payment_date' => $date,
            'currency_code' => 'GHS', 'exchange_rate' => '1',
            'counterparty_id' => $this->supplier->id,
            'amount' => $amount,
            'allocated_amount' => '0',  // not allocated; matches against payment.amount in fallback
            'withholding_amount' => '0', 'net_amount' => $amount,
            'payment_method_id' => $this->bankMethod->id,
            'source_account_id' => $this->bankAccount->id,
            'external_reference' => $externalReference,
            'status' => Payment::STATUS_POSTED,
            'is_backfilled' => false,
            'created_by_user_id' => $this->user->id,
        ]);
    }
}
