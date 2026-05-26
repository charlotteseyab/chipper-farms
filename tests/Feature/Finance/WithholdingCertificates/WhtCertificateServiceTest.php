<?php

namespace Tests\Feature\Finance\WithholdingCertificates;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\WhtCertificate;
use App\Models\PaymentMethod;
use App\Services\Finance\WithholdingCertificates\WhtCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * WhtCertificateService — generation, refresh, issue, void, and the
 * issuedCertificatesCovering helper.
 *
 * Storage is faked so issued cert PDF render writes to a virtual disk
 * rather than the real filesystem.
 */
class WhtCertificateServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $supplier;
    protected WhtCertificateService $service;
    protected PaymentMethod $bankMethod;
    protected Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        Storage::fake('local');
        Config::set('finance.attachments.disk', 'local');
        Config::set('finance.statements.company_tin', 'C0009999999');
        Config::set('finance.statements.company_name', 'Chipper Farms Ltd');

        $expense = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '6010'],
            ['name' => 'Sundry Expense', 'account_type' => Account::TYPE_EXPENSE,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'GEN', 'name' => 'General',
            'default_expense_account_id' => $expense->id,
            'payment_terms_days' => 30, 'default_withholding_percent' => 5,
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

        $this->service = $this->app->make(WhtCertificateService::class);
    }

    public function test_generate_draft_creates_with_zero_totals_when_no_allocations(): void
    {
        $cert = $this->service->generateDraft(
            $this->supplier, 'quarter', 2026, 4, $this->user
        );

        $this->assertInstanceOf(WhtCertificate::class, $cert);
        $this->assertSame(WhtCertificate::STATUS_DRAFT, $cert->status);
        $this->assertSame('quarter', $cert->period_kind);
        $this->assertSame(4, $cert->period_index);
        $this->assertSame(2026, $cert->period_year);
        $this->assertSame('2026-10-01', $cert->period_from->toDateString());
        $this->assertSame('2026-12-31', $cert->period_to->toDateString());
        $this->assertSame('0.00', (string) $cert->total_withheld);
        $this->assertNull($cert->certificate_number, 'No number until issued');
    }

    public function test_generate_draft_aggregates_contributing_allocations(): void
    {
        // Two payments inside Q4 2026, both with withholding allocations.
        $vi = $this->makeVi('1000.00');
        $pay1 = $this->makePostedPayment('500.00', '2026-10-15');
        $pay2 = $this->makePostedPayment('500.00', '2026-11-20');
        $this->makeAllocation($pay1, $vi, '500.00', '25.00');
        $this->makeAllocation($pay2, $vi, '500.00', '25.00');

        // Outside period - excluded
        $payOut = $this->makePostedPayment('200.00', '2027-01-05');
        $this->makeAllocation($payOut, $vi, '200.00', '10.00');

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);

        $this->assertSame('1000.00', (string) $cert->total_amount_paid);
        $this->assertSame('50.00',   (string) $cert->total_withheld);
        $this->assertSame(2, $cert->allocation_count);
        $this->assertSame(2, $cert->payment_count);
    }

    public function test_generate_draft_idempotent_returns_existing_draft(): void
    {
        $first  = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $second = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);

        $this->assertSame($first->id, $second->id);
    }

    public function test_generate_draft_blocks_when_issued_cert_exists_for_same_key(): void
    {
        $vi = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $draft = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $this->service->issue($draft, $this->user);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already exists');
        $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
    }

    public function test_refresh_draft_recomputes_after_new_allocation_lands(): void
    {
        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $this->assertSame('0.00', (string) $cert->total_withheld);

        // Now an allocation lands.
        $vi  = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $refreshed = $this->service->refreshDraft($cert->fresh());
        $this->assertSame('25.00', (string) $refreshed->total_withheld);
    }

    public function test_refresh_draft_blocks_if_issued(): void
    {
        $vi  = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $issued = $this->service->issue($cert, $this->user);

        $this->expectException(\DomainException::class);
        $this->service->refreshDraft($issued->fresh());
    }

    public function test_issue_requires_supplier_tin(): void
    {
        $vi  = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $this->supplier->update(['tax_id' => null]);
        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no TIN on file');
        $this->service->issue($cert, $this->user);
    }

    public function test_issue_blocks_when_no_withholding_in_period(): void
    {
        // Draft with zero withholding (no allocations).
        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);

        $this->expectException(\DomainException::class);
        $cert->refresh();
        $this->service->issue($cert, $this->user);
    }

    public function test_issue_allocates_number_snapshots_identity_renders_pdf_and_locks(): void
    {
        $vi  = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $issued = $this->service->issue($cert, $this->user);

        $this->assertSame(WhtCertificate::STATUS_ISSUED, $issued->status);
        $this->assertNotNull($issued->certificate_number);
        $this->assertMatchesRegularExpression(
            '/^WHT-Q4-2026-\d{6}$/',
            $issued->certificate_number
        );
        $this->assertSame('Acme Vendor',        $issued->supplier_name_snapshot);
        $this->assertSame('P0001234567',        $issued->supplier_tin_snapshot);
        $this->assertSame('Chipper Farms Ltd',  $issued->issuer_name_snapshot);
        $this->assertSame('C0009999999',        $issued->issuer_tin_snapshot);
        $this->assertNotNull($issued->pdf_path);
        $this->assertNotNull($issued->issued_at);
        $this->assertSame($this->user->id, $issued->issued_by_user_id);

        // PDF actually written to fake disk.
        Storage::disk('local')->assertExists($issued->pdf_path);
    }

    public function test_void_requires_reason(): void
    {
        $vi  = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $issued = $this->service->issue($cert, $this->user);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->void($issued, $this->user, '');
    }

    public function test_void_transitions_issued_to_void(): void
    {
        $vi  = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $issued = $this->service->issue($cert, $this->user);
        $voided = $this->service->void($issued, $this->user, 'Filed in error.');

        $this->assertSame(WhtCertificate::STATUS_VOID, $voided->status);
        $this->assertSame('Filed in error.', $voided->void_reason);
        $this->assertNotNull($voided->voided_at);
    }

    public function test_issued_certificates_covering_returns_certs_for_allocations_in_snapshot(): void
    {
        $vi  = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $alloc = $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $issued = $this->service->issue($cert, $this->user);

        $covering = $this->service->issuedCertificatesCovering($alloc->fresh());
        $this->assertCount(1, $covering);
        $this->assertSame($issued->id, $covering[0]->id);
    }

    public function test_issued_certificates_covering_empty_for_non_contributing_allocations(): void
    {
        $vi  = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $contributingAlloc = $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $this->service->issue($cert, $this->user);

        // A separate allocation made AFTER cert was issued.
        $pay2 = $this->makePostedPayment('100.00', '2026-11-25');
        $newAlloc = $this->makeAllocation($pay2, $vi, '100.00', '5.00');

        $covering = $this->service->issuedCertificatesCovering($newAlloc->fresh());
        $this->assertCount(0, $covering, 'A new allocation post-issue is NOT in the snapshot');
    }

    public function test_void_certificate_does_not_block_new_allocation_reverse(): void
    {
        $vi  = $this->makeVi('1000.00');
        $pay = $this->makePostedPayment('500.00', '2026-10-15');
        $alloc = $this->makeAllocation($pay, $vi, '500.00', '25.00');

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $issued = $this->service->issue($cert, $this->user);
        $this->service->void($issued, $this->user, 'Filed in error.');

        $covering = $this->service->issuedCertificatesCovering($alloc->fresh());
        $this->assertCount(0, $covering, 'Voided certs do NOT lock allocations');
    }

    /* helpers */

    protected function makeVi(string $total): VendorInvoice
    {
        static $counter = 0;
        return VendorInvoice::create([
            'organization_id' => $this->org->id,
            'vendor_invoice_number' => 'VI-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'supplier_invoice_number' => 'SUPREF-' . $counter,
            'invoice_date' => '2026-10-01', 'due_date' => '2026-10-30',
            'counterparty_id' => $this->supplier->id,
            'currency_code' => 'GHS', 'exchange_rate' => '1',
            'status' => VendorInvoice::STATUS_POSTED,
            'subtotal' => $total, 'tax_total' => '0', 'withholding_total' => '0',
            'total' => $total,
            'amount_paid' => '0', 'balance_due' => $total,
        ]);
    }

    protected function makePostedPayment(string $amount, string $date): Payment
    {
        static $counter = 0;
        return Payment::create([
            'organization_id' => $this->org->id,
            'payment_number' => 'P-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'payment_date' => $date,
            'currency_code' => 'GHS', 'exchange_rate' => '1',
            'counterparty_id' => $this->supplier->id,
            'amount' => $amount,
            'allocated_amount' => $amount,
            'withholding_amount' => '0', 'net_amount' => $amount,
            'payment_method_id' => $this->bankMethod->id,
            'source_account_id' => $this->bankAccount->id,
            'status' => Payment::STATUS_POSTED,
            'is_backfilled' => false,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    protected function makeAllocation(
        Payment $payment, VendorInvoice $vi, string $amount, string $withholding,
    ): PaymentAllocation {
        return PaymentAllocation::create([
            'organization_id' => $this->org->id,
            'payment_id' => $payment->id,
            'vendor_invoice_id' => $vi->id,
            'amount' => $amount,
            'withholding_amount' => $withholding,
            'net_amount' => bcsub($amount, $withholding, 2),
            'status' => PaymentAllocation::STATUS_ACTIVE,
            'allocated_by_user_id' => $this->user->id,
        ]);
    }
}
