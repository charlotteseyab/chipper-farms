<?php

namespace Tests\Feature\Finance\VendorInvoicing;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Services\Finance\VendorInvoicing\VendorInvoiceLineDraft;
use App\Services\Finance\VendorInvoicing\VendorInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class VendorInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected VendorInvoiceService $service;
    protected Counterparty $supplier;
    protected Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->expenseAccount = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '6010'],
            ['name' => 'Sundry Expense', 'account_type' => Account::TYPE_EXPENSE,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );

        $category = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'TEST', 'name' => 'Test',
            'default_expense_account_id' => $this->expenseAccount->id,
            'payment_terms_days' => 21,
            'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Vendor', 'code' => 'VEND-T',
            'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS',
            'supplier_category_id' => $category->id,
            'payment_terms_days' => null, // Inherits from category
        ]);

        $this->service = $this->app->make(VendorInvoiceService::class);
    }

    public function test_create_draft_with_single_line(): void
    {
        $line = new VendorInvoiceLineDraft(
            description: 'Soybean meal — 50kg sack',
            quantity:    '10',
            unitCost:    '120',
        );

        $invoice = $this->service->createDraft($this->supplier, [
            'invoice_date' => '2026-11-01',
        ], [$line]);

        $this->assertInstanceOf(VendorInvoice::class, $invoice);
        $this->assertSame(VendorInvoice::STATUS_DRAFT, $invoice->status);
        $this->assertMatchesRegularExpression('/^VI-2026-\d{6}$/', $invoice->vendor_invoice_number);
        $this->assertSame('1200.00', $invoice->subtotal);
        $this->assertSame('1200.00', $invoice->total);
        $this->assertSame('1200.00', $invoice->balance_due);
        $this->assertCount(1, $invoice->lines);
    }

    public function test_create_draft_due_date_inherits_from_category(): void
    {
        $line = new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100');

        $invoice = $this->service->createDraft($this->supplier, [
            'invoice_date' => '2026-11-01',
        ], [$line]);

        $this->assertSame('2026-11-22', $invoice->due_date->toDateString(),
            'Inherited 21 days from category');
    }

    public function test_create_draft_due_date_explicit_overrides_category(): void
    {
        $line = new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100');

        $invoice = $this->service->createDraft($this->supplier, [
            'invoice_date' => '2026-11-01',
            'due_date'     => '2026-11-15',
        ], [$line]);

        $this->assertSame('2026-11-15', $invoice->due_date->toDateString());
    }

    public function test_create_draft_supplier_terms_override_category(): void
    {
        $this->supplier->update(['payment_terms_days' => 7]);
        $line = new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100');

        $invoice = $this->service->createDraft($this->supplier, [
            'invoice_date' => '2026-11-01',
        ], [$line]);

        $this->assertSame('2026-11-08', $invoice->due_date->toDateString(),
            'Supplier 7d wins over category 21d');
    }

    public function test_create_draft_rejects_non_supplier(): void
    {
        $customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Customer Only', 'code' => 'CUST-X',
            'type' => Counterparty::TYPE_CUSTOMER,
            'currency_code' => 'GHS',
        ]);

        $line = new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100');

        $this->expectException(\DomainException::class);
        $this->service->createDraft($customer, ['invoice_date' => '2026-11-01'], [$line]);
    }

    public function test_create_draft_rejects_no_lines(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/at least 1 line/');
        $this->service->createDraft($this->supplier, ['invoice_date' => '2026-11-01'], []);
    }

    public function test_supplier_withholding_exempt_zeroes_line_wht(): void
    {
        $this->supplier->update(['withholding_exempt' => true]);

        $line = new VendorInvoiceLineDraft(
            description:     'Service',
            quantity:        '1',
            unitCost:        '1000',
            withholdingRate: '7.5',
        );

        $invoice = $this->service->createDraft($this->supplier, [
            'invoice_date' => '2026-11-01',
        ], [$line]);

        $persistedLine = $invoice->lines->first();
        $this->assertSame('0.0000', $persistedLine->withholding_rate,
            'Supplier exemption forced WHT to zero on the persisted line');
        $this->assertSame('0.00', $persistedLine->withholding_amount);
        $this->assertSame('0.00', $invoice->withholding_total);
    }

    public function test_supplier_withholding_not_exempt_preserves_line_wht(): void
    {
        $line = new VendorInvoiceLineDraft(
            description:     'Service',
            quantity:        '1',
            unitCost:        '1000',
            withholdingRate: '7.5',
        );

        $invoice = $this->service->createDraft($this->supplier, [
            'invoice_date' => '2026-11-01',
        ], [$line]);

        $persistedLine = $invoice->lines->first();
        $this->assertSame('7.5000', $persistedLine->withholding_rate);
        $this->assertSame('75.00', $persistedLine->withholding_amount);
        $this->assertSame('75.00', $invoice->withholding_total);
    }

    public function test_recompute_header_sums_correctly(): void
    {
        $invoice = $this->service->createDraft($this->supplier, ['invoice_date' => '2026-11-01'], [
            new VendorInvoiceLineDraft(description: 'A', quantity: '2', unitCost: '50',  taxRate: '15'),
            new VendorInvoiceLineDraft(description: 'B', quantity: '1', unitCost: '200', taxRate: '15'),
        ]);

        // A: 100 + 15 = 115; B: 200 + 30 = 230; subtotal=300, tax=45, total=345
        $this->assertSame('300.00', $invoice->subtotal);
        $this->assertSame('45.00',  $invoice->tax_total);
        $this->assertSame('345.00', $invoice->total);
        $this->assertSame('345.00', $invoice->balance_due);
    }

    public function test_submit_for_approval_requires_lines(): void
    {
        $line = new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100');
        $invoice = $this->service->createDraft($this->supplier, ['invoice_date' => '2026-11-01'], [$line]);

        // Remove the line — invoice now has no lines
        $invoice->lines()->delete();
        $invoice->refresh();

        $user = $this->makeUser('accountant');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cannot be submitted/i');
        $this->service->submitForApproval($invoice, $user);
    }

    public function test_submit_for_approval_transitions_state(): void
    {
        $line = new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100');
        $invoice = $this->service->createDraft($this->supplier, ['invoice_date' => '2026-11-01'], [$line]);
        $user = $this->makeUser('accountant');

        $result = $this->service->submitForApproval($invoice, $user);

        $this->assertSame(VendorInvoice::STATUS_PENDING_APPROVAL, $result->status);
        $this->assertSame($user->id, $result->submitted_for_approval_by_user_id);
        $this->assertNotNull($result->submitted_for_approval_at);
    }

    public function test_cancel_only_from_draft_or_pending(): void
    {
        $line = new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100');
        $invoice = $this->service->createDraft($this->supplier, ['invoice_date' => '2026-11-01'], [$line]);

        // Force into posted (bypass the posting service)
        $invoice->update(['status' => VendorInvoice::STATUS_POSTED]);

        $user = $this->makeUser('accountant');

        $this->expectException(\DomainException::class);
        $this->service->cancel($invoice, $user, 'try cancel posted');
    }

    public function test_cancel_records_audit(): void
    {
        $line = new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100');
        $invoice = $this->service->createDraft($this->supplier, ['invoice_date' => '2026-11-01'], [$line]);
        $user = $this->makeUser('accountant');

        $result = $this->service->cancel($invoice, $user, 'duplicate of VI-2025-9999');

        $this->assertSame(VendorInvoice::STATUS_CANCELLED, $result->status);
        $this->assertSame($user->id, $result->cancelled_by_user_id);
        $this->assertSame('duplicate of VI-2025-9999', $result->cancellation_reason);
    }

    public function test_add_line_recomputes_header(): void
    {
        $line = new VendorInvoiceLineDraft(description: 'A', quantity: '1', unitCost: '100');
        $invoice = $this->service->createDraft($this->supplier, ['invoice_date' => '2026-11-01'], [$line]);
        $this->assertSame('100.00', $invoice->total);

        $this->service->addLine($invoice, new VendorInvoiceLineDraft(description: 'B', quantity: '1', unitCost: '50'));

        $invoice->refresh();
        $this->assertSame('150.00', $invoice->total);
        $this->assertCount(2, $invoice->lines);
    }

    public function test_add_line_blocked_on_non_draft(): void
    {
        $line = new VendorInvoiceLineDraft(description: 'A', quantity: '1', unitCost: '100');
        $invoice = $this->service->createDraft($this->supplier, ['invoice_date' => '2026-11-01'], [$line]);
        $invoice->update(['status' => VendorInvoice::STATUS_POSTED]);

        $this->expectException(\DomainException::class);
        $this->service->addLine($invoice, new VendorInvoiceLineDraft(description: 'B', quantity: '1', unitCost: '50'));
    }

    public function test_remove_line_recomputes(): void
    {
        $invoice = $this->service->createDraft($this->supplier, ['invoice_date' => '2026-11-01'], [
            new VendorInvoiceLineDraft(description: 'A', quantity: '1', unitCost: '100'),
            new VendorInvoiceLineDraft(description: 'B', quantity: '1', unitCost: '50'),
        ]);
        $this->assertSame('150.00', $invoice->total);

        $this->service->removeLine($invoice->lines->first());

        $invoice->refresh();
        $this->assertSame('50.00', $invoice->total);
        $this->assertCount(1, $invoice->lines);
    }

    public function test_line_draft_validation_rejects_zero_qty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/quantity must be > 0/');
        new VendorInvoiceLineDraft(description: 'X', quantity: '0', unitCost: '100');
    }

    public function test_line_draft_validation_rejects_empty_description(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/description must not be empty/');
        new VendorInvoiceLineDraft(description: '   ', quantity: '1', unitCost: '100');
    }

    public function test_line_draft_validation_rejects_negative_unit_cost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '-1');
    }

    public function test_vi_number_uses_invoice_year_not_current(): void
    {
        $line = new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100');
        $invoice = $this->service->createDraft($this->supplier, [
            'invoice_date' => '2025-12-31',
        ], [$line]);

        $this->assertStringStartsWith('VI-2025-', $invoice->vendor_invoice_number,
            'Sequence year derives from invoice_date, not now()');
    }

    /* helper */
    protected function makeUser(string $roleName): \App\Models\User
    {
        $role = \App\Models\Role::firstOrCreate(['name' => $roleName]);
        return \App\Models\User::forceCreate([
            'name' => ucfirst($roleName), 'email' => $roleName . '_' . uniqid() . '@e.com',
            'password' => bcrypt('x'), 'role' => $roleName, 'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }
}
