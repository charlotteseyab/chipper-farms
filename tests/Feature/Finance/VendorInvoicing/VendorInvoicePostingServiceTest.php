<?php

namespace Tests\Feature\Finance\VendorInvoicing;

use App\Finance\Exceptions\DuplicatePostingException;
use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\JournalLine;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\TaxCode;
use App\Services\Finance\VendorInvoicing\VendorInvoiceLineDraft;
use App\Services\Finance\VendorInvoicing\VendorInvoicePostingService;
use App\Services\Finance\VendorInvoicing\VendorInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class VendorInvoicePostingServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected VendorInvoiceService $service;
    protected VendorInvoicePostingService $postingService;
    protected Counterparty $supplier;
    protected Account $expenseAccount;
    protected Account $apControl;
    protected Account $inputVat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->expenseAccount = $this->makeAccount('6020', 'Vet Expense',  Account::TYPE_EXPENSE);
        $this->apControl      = $this->makeAccount('2110', 'AP Control',   Account::TYPE_LIABILITY);
        $this->inputVat       = $this->makeAccount('1410', 'Input VAT',    Account::TYPE_ASSET);
        $this->makeAccount('6010', 'Sundry Expense', Account::TYPE_EXPENSE);

        $category = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'VET', 'name' => 'Vet',
            'default_expense_account_id' => $this->expenseAccount->id,
            'payment_terms_days' => 30, 'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Vet Inc', 'code' => 'V-VET',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $category->id,
        ]);

        $this->service = $this->app->make(VendorInvoiceService::class);
        $this->postingService = $this->app->make(VendorInvoicePostingService::class);
    }

    public function test_posts_balanced_journal(): void
    {
        $invoice = $this->createPendingInvoice([
            new VendorInvoiceLineDraft(description: 'Vaccine', quantity: '10', unitCost: '50'),
        ]);

        $approver = $this->makeUser('treasurer');
        $posted = $this->postingService->post($invoice, $approver);

        $this->assertSame(VendorInvoice::STATUS_POSTED, $posted->status);
        $this->assertNotNull($posted->journal_batch_id);
        $this->assertSame($approver->id, $posted->approved_by_user_id);

        // Verify journal balance: Dr Expense 500, Cr AP 500
        $batch = $posted->journalBatch;
        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $batch->id))->get();
        $totalDebits = $lines->sum(fn ($l) => (float) $l->debit);
        $totalCredits = $lines->sum(fn ($l) => (float) $l->credit);
        $this->assertEqualsWithDelta(500.0, $totalDebits, 0.01);
        $this->assertEqualsWithDelta(500.0, $totalCredits, 0.01);
    }

    public function test_recoverable_vat_posts_to_input_vat_account(): void
    {
        $vatCode = TaxCode::create([
            'organization_id' => $this->org->id,
            'code' => 'VAT_15', 'name' => 'VAT 15%',
            'rate_type' => 'percent', 'rate' => 15, 'applies_to' => 'purchase',
            'is_recoverable' => true, 'is_withholding' => false, 'is_compound' => false,
            'gl_account_hint' => '1410', 'display_order' => 10, 'is_active' => true,
        ]);

        $invoice = $this->createPendingInvoice([
            new VendorInvoiceLineDraft(
                description: 'Service',
                quantity: '1', unitCost: '1000',
                taxRate: '15', taxCodeId: $vatCode->id,
            ),
        ]);

        $approver = $this->makeUser('treasurer');
        $posted = $this->postingService->post($invoice, $approver);

        $batch = $posted->journalBatch;
        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $batch->id))->get();

        $vatLine = $lines->firstWhere('account_id', $this->inputVat->id);
        $this->assertNotNull($vatLine, 'Input VAT line present');
        $this->assertEqualsWithDelta(150.0, (float) $vatLine->debit, 0.01);

        // Expense line should be the SUBTOTAL only, not subtotal + tax
        $expenseLine = $lines->firstWhere('account_id', $this->expenseAccount->id);
        $this->assertEqualsWithDelta(1000.0, (float) $expenseLine->debit, 0.01,
            'Expense line is subtotal only when VAT recoverable');

        // AP control should be the FULL total (subtotal + tax)
        $apLine = $lines->firstWhere('account_id', $this->apControl->id);
        $this->assertEqualsWithDelta(1150.0, (float) $apLine->credit, 0.01);
    }

    public function test_non_recoverable_levy_folds_into_expense(): void
    {
        $nhilCode = TaxCode::create([
            'organization_id' => $this->org->id,
            'code' => 'NHIL', 'name' => 'NHIL 2.5%',
            'rate_type' => 'percent', 'rate' => 2.5, 'applies_to' => 'purchase',
            'is_recoverable' => false, 'is_withholding' => false, 'is_compound' => false,
            'gl_account_hint' => null, 'display_order' => 20, 'is_active' => true,
        ]);

        $invoice = $this->createPendingInvoice([
            new VendorInvoiceLineDraft(
                description: 'Service',
                quantity: '1', unitCost: '1000',
                taxRate: '2.5', taxCodeId: $nhilCode->id,
            ),
        ]);

        $approver = $this->makeUser('treasurer');
        $posted = $this->postingService->post($invoice, $approver);

        $batch = $posted->journalBatch;
        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $batch->id))->get();

        $vatLine = $lines->firstWhere('account_id', $this->inputVat->id);
        $this->assertNull($vatLine, 'No Input VAT line for non-recoverable tax');

        // Expense line should be subtotal + tax_amount (1000 + 25 = 1025)
        $expenseLine = $lines->firstWhere('account_id', $this->expenseAccount->id);
        $this->assertEqualsWithDelta(1025.0, (float) $expenseLine->debit, 0.01,
            'Non-recoverable levy folded into expense base');
    }

    public function test_idempotency_blocks_double_posting(): void
    {
        $invoice = $this->createPendingInvoice([
            new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100'),
        ]);

        $approver = $this->makeUser('treasurer');
        $this->postingService->post($invoice, $approver);

        // Force back to pending and try again — should be idempotency-blocked
        $invoice->update([
            'status' => VendorInvoice::STATUS_PENDING_APPROVAL,
            'journal_batch_id' => null,
        ]);
        $invoice->refresh();

        $this->expectException(DuplicatePostingException::class);
        $this->postingService->post($invoice, $approver);
    }

    public function test_uses_category_ap_account_when_set(): void
    {
        $altApAccount = $this->makeAccount('2120', 'AP Capex Suppliers', Account::TYPE_LIABILITY);
        $this->supplier->supplierCategory->update(['ap_account_id' => $altApAccount->id]);
        $this->supplier->refresh();

        $invoice = $this->createPendingInvoice([
            new VendorInvoiceLineDraft(description: 'Equipment', quantity: '1', unitCost: '500'),
        ]);

        $approver = $this->makeUser('treasurer');
        $posted = $this->postingService->post($invoice, $approver);

        $batch = $posted->journalBatch;
        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $batch->id))->get();

        $altApLine = $lines->firstWhere('account_id', $altApAccount->id);
        $this->assertNotNull($altApLine, 'Used category-specific AP account');
        $defaultApLine = $lines->firstWhere('account_id', $this->apControl->id);
        $this->assertNull($defaultApLine, 'Default 2110 AP not used');
    }

    public function test_withholding_does_not_post_at_invoice_time(): void
    {
        $invoice = $this->createPendingInvoice([
            new VendorInvoiceLineDraft(
                description: 'Service',
                quantity: '1', unitCost: '1000',
                withholdingRate: '7.5',
            ),
        ]);

        $approver = $this->makeUser('treasurer');
        $posted = $this->postingService->post($invoice, $approver);

        $batch = $posted->journalBatch;
        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $batch->id))->get();

        $totalDebits = $lines->sum(fn ($l) => (float) $l->debit);
        $totalCredits = $lines->sum(fn ($l) => (float) $l->credit);

        $this->assertEqualsWithDelta(1000.0, $totalDebits, 0.01,
            'AP posts the FULL bill — withholding not deducted at invoice time');
        $this->assertEqualsWithDelta(1000.0, $totalCredits, 0.01);

        // The invoice records withholding informationally
        $this->assertEqualsWithDelta(75.0, (float) $posted->withholding_total, 0.01);
    }

    /* helpers */

    protected function makeAccount(string $code, string $name, string $type): Account
    {
        return Account::create([
            'organization_id' => $this->org->id,
            'code' => $code, 'name' => $name, 'account_type' => $type,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
    }

    protected function makeUser(string $roleName): \App\Models\User
    {
        $role = \App\Models\Role::firstOrCreate(['name' => $roleName]);
        return \App\Models\User::forceCreate([
            'name' => ucfirst($roleName), 'email' => $roleName . '_' . uniqid() . '@e.com',
            'password' => bcrypt('x'), 'role' => $roleName, 'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }

    protected function createPendingInvoice(array $lineDrafts): VendorInvoice
    {
        $invoice = $this->service->createDraft($this->supplier, [
            'invoice_date' => '2026-11-01',
        ], $lineDrafts);
        $invoice->update([
            'status' => VendorInvoice::STATUS_PENDING_APPROVAL,
            'submitted_for_approval_at' => now(),
        ]);
        return $invoice->fresh(['lines.taxCode', 'counterparty.supplierCategory']);
    }
}
