<?php

namespace Tests\Feature\Finance\Payments;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\JournalLine;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\PaymentMethod;
use App\Services\Finance\Payments\PaymentAllocationService;
use App\Services\Finance\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PaymentAllocationServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected PaymentService $paymentService;
    protected PaymentAllocationService $allocService;
    protected Counterparty $supplier;
    protected PaymentMethod $cashMethod;
    protected Account $apControl;
    protected Account $whtAccount;
    protected Account $suspenseAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->apControl       = $this->makeAccount('2010', 'Trade Payables — Suppliers', Account::TYPE_LIABILITY);
        $this->whtAccount      = $this->makeAccount('2250', 'Withholding Tax Payable',     Account::TYPE_LIABILITY);
        $this->suspenseAccount = $this->makeAccount('2910', 'Suspense Payment',            Account::TYPE_LIABILITY);
        $this->makeAccount('1010', 'Cash on Hand', Account::TYPE_ASSET);
        $this->makeAccount('6999', 'Other Operating Expenses', Account::TYPE_EXPENSE);

        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'TEST', 'name' => 'Test',
            'payment_terms_days' => 30,
            'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Vendor', 'code' => 'V-T',
            'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id,
        ]);

        $this->cashMethod = PaymentMethod::create([
            'code' => 'CASH', 'name' => 'Cash',
            'is_cash' => true, 'is_bank' => false, 'is_mobile_money' => false, 'is_credit' => false,
            'requires_reference' => false, 'gl_account_hint' => 'CASH_ON_HAND',
            'display_order' => 10, 'is_active' => true,
        ]);

        $this->paymentService = $this->app->make(PaymentService::class);
        $this->allocService = $this->app->make(PaymentAllocationService::class);
    }

    public function test_allocate_with_wht_posts_combined_journal(): void
    {
        $vi = $this->makePostedInvoice(total: '1000.00', whtTotal: '75.00');
        // payment.amount is NET cash leaving bank: 1000 gross - 75 WHT = 925
        $payment = $this->makePosted(amount: '925.00');

        $allocation = $this->allocService->allocate(
            payment: $payment, invoice: $vi,
            amount: '1000.00', createdBy: $this->makeUser('treasurer'),
        );

        $this->assertSame('1000.00', $allocation->amount, 'Allocation amount is GROSS AP relief');
        $this->assertSame('75.00',   $allocation->withholding_amount);
        $this->assertNotNull($allocation->withholding_journal_batch_id);

        // Verify the combined 3-line journal:
        //   Dr AP                1000
        //       Cr Suspense          925
        //       Cr WHT Payable        75
        $lines = JournalLine::whereHas('journal',
            fn ($q) => $q->where('journal_batch_id', $allocation->withholding_journal_batch_id))->get();

        $apDr = $lines->firstWhere('account_id', $this->apControl->id);
        $suspenseCr = $lines->firstWhere('account_id', $this->suspenseAccount->id);
        $whtCr = $lines->firstWhere('account_id', $this->whtAccount->id);

        $this->assertNotNull($apDr, 'AP debit line');
        $this->assertNotNull($suspenseCr, 'Suspense credit line');
        $this->assertNotNull($whtCr, 'WHT credit line');
        $this->assertEqualsWithDelta(1000.0, (float) $apDr->debit, 0.01);
        $this->assertEqualsWithDelta(925.0,  (float) $suspenseCr->credit, 0.01);
        $this->assertEqualsWithDelta(75.0,   (float) $whtCr->credit, 0.01);

        // Journal must balance.
        $debits = $lines->sum(fn ($l) => (float) $l->debit);
        $credits = $lines->sum(fn ($l) => (float) $l->credit);
        $this->assertEqualsWithDelta(1000.0, $debits, 0.01);
        $this->assertEqualsWithDelta(1000.0, $credits, 0.01);
    }

    public function test_allocate_zero_wht_two_line_journal(): void
    {
        $vi = $this->makePostedInvoice(total: '500.00', whtTotal: '0.00');
        $payment = $this->makePosted(amount: '500.00');

        $allocation = $this->allocService->allocate(
            payment: $payment, invoice: $vi,
            amount: '500.00', createdBy: $this->makeUser('treasurer'),
        );

        $this->assertSame('0.00', $allocation->withholding_amount);
        $this->assertNotNull($allocation->withholding_journal_batch_id, 'Allocation journal still posted (just no WHT line)');

        $lines = JournalLine::whereHas('journal',
            fn ($q) => $q->where('journal_batch_id', $allocation->withholding_journal_batch_id))->get();

        // No WHT line.
        $whtLine = $lines->firstWhere('account_id', $this->whtAccount->id);
        $this->assertNull($whtLine, 'No WHT line when slice = 0');

        // Just Dr AP / Cr Suspense.
        $this->assertCount(2, $lines);
        $apDr = $lines->firstWhere('account_id', $this->apControl->id);
        $suspenseCr = $lines->firstWhere('account_id', $this->suspenseAccount->id);
        $this->assertEqualsWithDelta(500.0, (float) $apDr->debit, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $suspenseCr->credit, 0.01);
    }

    public function test_refresh_payment_aggregates(): void
    {
        $vi = $this->makePostedInvoice(total: '1000.00', whtTotal: '75.00');
        // payment.amount = NET cash leaving = 925 (1000 gross - 75 wht)
        $payment = $this->makePosted(amount: '925.00');

        $this->allocService->allocate(
            payment: $payment, invoice: $vi,
            amount: '1000.00', createdBy: $this->makeUser('treasurer'),
        );

        $payment->refresh();
        // allocated = GROSS sum, withholding = wht sum, net = allocated - withheld
        $this->assertSame('1000.00', $payment->allocated_amount, 'GROSS AP relief');
        $this->assertSame('75.00',   $payment->withholding_amount);
        $this->assertSame('925.00',  $payment->net_amount, 'Cash drawn from suspense');
        // Fully allocated when net == amount (suspense closed).
        $this->assertTrue($payment->isFullyAllocated());
    }

    public function test_refresh_vi_aggregates(): void
    {
        $vi = $this->makePostedInvoice(total: '1000.00', whtTotal: '0.00');
        $payment = $this->makePosted(amount: '600.00');

        $this->allocService->allocate(
            payment: $payment, invoice: $vi,
            amount: '600.00', createdBy: $this->makeUser('treasurer'),
        );

        $vi->refresh();
        $this->assertSame('600.00', $vi->amount_paid);
        $this->assertSame('400.00', $vi->balance_due);
        $this->assertNull($vi->fully_paid_at, 'Not yet fully paid');

        // Pay the rest.
        $payment2 = $this->makePosted(amount: '400.00');
        $this->allocService->allocate(
            payment: $payment2, invoice: $vi,
            amount: '400.00', createdBy: $this->makeUser('treasurer'),
        );

        $vi->refresh();
        $this->assertSame('1000.00', $vi->amount_paid);
        $this->assertSame('0.00',    $vi->balance_due);
        $this->assertNotNull($vi->fully_paid_at, 'fully_paid_at set when balance hits zero');
    }

    public function test_reverse_allocation_clears_aggregates_and_fully_paid(): void
    {
        $vi = $this->makePostedInvoice(total: '500.00', whtTotal: '0.00');
        $payment = $this->makePosted(amount: '500.00');
        $treasurer = $this->makeUser('treasurer');

        $alloc = $this->allocService->allocate(
            payment: $payment, invoice: $vi,
            amount: '500.00', createdBy: $treasurer,
        );

        $vi->refresh();
        $this->assertNotNull($vi->fully_paid_at);

        $this->allocService->reverse($alloc, $treasurer, 'oops');

        $vi->refresh();
        $this->assertSame('0.00', $vi->amount_paid);
        $this->assertSame('500.00', $vi->balance_due);
        $this->assertNull($vi->fully_paid_at, 'fully_paid_at cleared on reversal');

        $payment->refresh();
        $this->assertSame('0.00', $payment->allocated_amount);
    }

    public function test_reverse_allocation_reverses_combined_journal(): void
    {
        $vi = $this->makePostedInvoice(total: '1000.00', whtTotal: '75.00');
        $payment = $this->makePosted(amount: '925.00');
        $treasurer = $this->makeUser('treasurer');

        $alloc = $this->allocService->allocate(
            payment: $payment, invoice: $vi,
            amount: '1000.00', createdBy: $treasurer,
        );
        $allocBatchId = $alloc->withholding_journal_batch_id;

        $reversed = $this->allocService->reverse($alloc, $treasurer, 'undo');

        $this->assertSame(PaymentAllocation::STATUS_REVERSED, $reversed->status);
        $this->assertNotNull($reversed->reversed_by_journal_batch_id);
        $this->assertNotEquals($allocBatchId, $reversed->reversed_by_journal_batch_id);
    }

    public function test_validates_same_counterparty(): void
    {
        $vi = $this->makePostedInvoice(total: '500.00', whtTotal: '0.00');
        $payment = $this->makePosted(amount: '500.00');

        // Change payment supplier to a different one.
        $other = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Other', 'code' => 'V-O',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
        ]);
        $payment->update(['counterparty_id' => $other->id]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/counterparty/');
        $this->allocService->allocate(
            payment: $payment, invoice: $vi,
            amount: '500.00', createdBy: $this->makeUser('treasurer'),
        );
    }

    public function test_validates_amount_le_balance_due(): void
    {
        $vi = $this->makePostedInvoice(total: '500.00', whtTotal: '0.00');
        $payment = $this->makePosted(amount: '1000.00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/balance due/');
        $this->allocService->allocate(
            payment: $payment, invoice: $vi,
            amount: '600.00', createdBy: $this->makeUser('treasurer'),
        );
    }

    public function test_validates_amount_le_unallocated(): void
    {
        $vi1 = $this->makePostedInvoice(total: '500.00', whtTotal: '0.00');
        $vi2 = $this->makePostedInvoice(total: '500.00', whtTotal: '0.00');
        $payment = $this->makePosted(amount: '500.00');
        $treasurer = $this->makeUser('treasurer');

        $this->allocService->allocate($payment, $vi1, '500.00', $treasurer);

        // payment fully allocated; try to push more.
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/unallocated/');
        $this->allocService->allocate(
            payment: $payment, invoice: $vi2,
            amount: '100.00', createdBy: $treasurer,
        );
    }

    public function test_auto_allocate_fifo(): void
    {
        // Three open VIs in different invoice_dates.
        $oldest = $this->makePostedInvoice(total: '300.00', whtTotal: '0.00', date: '2026-01-01');
        $middle = $this->makePostedInvoice(total: '300.00', whtTotal: '0.00', date: '2026-02-01');
        $newest = $this->makePostedInvoice(total: '300.00', whtTotal: '0.00', date: '2026-03-01');

        $payment = $this->makePosted(amount: '500.00');
        $treasurer = $this->makeUser('treasurer');

        $allocations = $this->allocService->autoAllocateFifo($payment, $treasurer);

        $this->assertCount(2, $allocations, 'Two allocations: oldest fully paid, middle partially');

        $oldest->refresh();
        $middle->refresh();
        $newest->refresh();

        $this->assertSame('300.00', $oldest->amount_paid, 'Oldest fully allocated');
        $this->assertSame('200.00', $middle->amount_paid, 'Middle partially allocated');
        $this->assertSame('0.00', $newest->amount_paid, 'Newest untouched');

        $payment->refresh();
        $this->assertTrue($payment->isFullyAllocated());
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

    protected function makePostedInvoice(string $total, string $whtTotal, string $date = '2026-11-01'): VendorInvoice
    {
        return VendorInvoice::create([
            'organization_id'    => $this->org->id,
            'vendor_invoice_number' => 'VI-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'invoice_date'       => $date,
            'due_date'           => $date,
            'currency_code'      => 'GHS',
            'counterparty_id'    => $this->supplier->id,
            'subtotal'           => $total,
            'tax_total'          => '0',
            'total'              => $total,
            'withholding_total'  => $whtTotal,
            'amount_paid'        => '0',
            'balance_due'        => $total,
            'status'             => VendorInvoice::STATUS_POSTED,
        ]);
    }

    protected function makePosted(string $amount): Payment
    {
        return $this->paymentService->createPosted(
            supplier:    $this->supplier,
            method:      $this->cashMethod,
            amount:      $amount,
            paymentDate: now(),
            createdBy:   $this->makeUser('treasurer'),
        );
    }
}
