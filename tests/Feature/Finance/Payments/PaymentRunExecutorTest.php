<?php

namespace Tests\Feature\Finance\Payments;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentRun;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\PaymentMethod;
use App\Services\Finance\Payments\PaymentRunExecutor;
use App\Services\Finance\Payments\PaymentRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PaymentRunExecutorTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected PaymentRunService $runService;
    protected PaymentRunExecutor $executor;
    protected Counterparty $supplierA;
    protected Counterparty $supplierB;
    protected PaymentMethod $cashMethod;
    protected PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->makeAccount('2010', 'AP', Account::TYPE_LIABILITY);
        $this->makeAccount('2250', 'WHT Payable', Account::TYPE_LIABILITY);
        $this->makeAccount('2910', 'Suspense Payment', Account::TYPE_LIABILITY);
        $this->makeAccount('1010', 'Cash on Hand', Account::TYPE_ASSET);
        $this->makeAccount('1030', 'Bank — Main', Account::TYPE_ASSET);
        $this->makeAccount('6999', 'Other Operating Expenses', Account::TYPE_EXPENSE);

        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'X', 'name' => 'X',
            'payment_terms_days' => 30, 'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplierA = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'A', 'code' => 'V-A',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id,
        ]);
        $this->supplierB = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'B', 'code' => 'V-B',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id,
        ]);

        $this->cashMethod = PaymentMethod::create([
            'code' => 'CASH', 'name' => 'Cash',
            'is_cash' => true, 'is_bank' => false, 'is_mobile_money' => false, 'is_credit' => false,
            'requires_reference' => false, 'gl_account_hint' => 'CASH_ON_HAND',
            'display_order' => 10, 'is_active' => true,
        ]);
        $this->bankMethod = PaymentMethod::create([
            'code' => 'BANK_TRANSFER', 'name' => 'Bank',
            'is_cash' => false, 'is_bank' => true, 'is_mobile_money' => false, 'is_credit' => false,
            'requires_reference' => true, 'gl_account_hint' => 'BANK_MAIN',
            'display_order' => 30, 'is_active' => true,
        ]);

        $this->runService = $this->app->make(PaymentRunService::class);
        $this->executor = $this->app->make(PaymentRunExecutor::class);
    }

    public function test_execute_groups_by_counterparty_method(): void
    {
        $run = $this->makeDraftRun();

        // Two VIs for supplier A, both with default method (cash) → ONE payment
        $vi1 = $this->makePostedInvoice($this->supplierA, '300.00');
        $vi2 = $this->makePostedInvoice($this->supplierA, '200.00');
        // One VI for supplier B → ONE payment (different supplier)
        $vi3 = $this->makePostedInvoice($this->supplierB, '500.00');

        $this->runService->addLine($run, $vi1);
        $this->runService->addLine($run, $vi2);
        $this->runService->addLine($run, $vi3);

        $this->executor->execute($run, $this->makeUser('treasurer'));

        $run->refresh();
        $this->assertSame(PaymentRun::STATUS_EXECUTED, $run->status);
        $this->assertCount(2, $run->payments, 'Two payments: one per supplier');

        // Supplier A payment = 500
        $payA = $run->payments->firstWhere('counterparty_id', $this->supplierA->id);
        $this->assertNotNull($payA);
        $this->assertSame('500.00', $payA->amount);

        // Supplier B payment = 500
        $payB = $run->payments->firstWhere('counterparty_id', $this->supplierB->id);
        $this->assertNotNull($payB);
        $this->assertSame('500.00', $payB->amount);
    }

    public function test_execute_different_methods_produce_separate_payments(): void
    {
        $run = $this->makeDraftRun();

        $vi1 = $this->makePostedInvoice($this->supplierA, '300.00');
        $vi2 = $this->makePostedInvoice($this->supplierA, '700.00');

        $this->runService->addLine($run, $vi1);  // default cash
        $this->runService->addLine($run, $vi2, paymentMethodOverrideId: $this->bankMethod->id);

        $this->executor->execute($run, $this->makeUser('treasurer'));

        $run->refresh();
        $this->assertCount(2, $run->payments, 'Same supplier, different methods → 2 payments');
    }

    public function test_execute_throws_when_no_method_available(): void
    {
        // Run with NO default method, line with NO override.
        $run = $this->runService->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: null,  // <-- no default
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );

        $vi = $this->makePostedInvoice($this->supplierA, '500.00');
        $this->runService->addLine($run, $vi);  // no method override

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/no payment method/');
        $this->executor->execute($run, $this->makeUser('treasurer'));
    }

    public function test_execute_marks_lines_with_payment_and_allocation(): void
    {
        $run = $this->makeDraftRun();
        $vi = $this->makePostedInvoice($this->supplierA, '500.00');
        $line = $this->runService->addLine($run, $vi);

        $this->executor->execute($run, $this->makeUser('treasurer'));

        $line->refresh();
        $this->assertNotNull($line->payment_id);
        $this->assertNotNull($line->payment_allocation_id);
    }

    public function test_execute_creates_wht_journal_when_vi_has_withholding(): void
    {
        $run = $this->makeDraftRun();
        $vi = $this->makePostedInvoice($this->supplierA, '1000.00', '75.00');
        $this->runService->addLine($run, $vi);

        $this->executor->execute($run, $this->makeUser('treasurer'));

        $run->refresh();
        $payment = $run->payments->first();
        // payment.amount under D31 is the NET cash leaving (1000 - 75 = 925).
        $this->assertSame('925.00', $payment->amount);
        $this->assertNotNull($payment->journal_batch_id, 'Cash journal posted');

        $allocation = $payment->allocations->first();
        $this->assertSame('1000.00', $allocation->amount, 'Allocation amount = GROSS AP relief');
        $this->assertSame('75.00', $allocation->withholding_amount);
        $this->assertNotNull($allocation->withholding_journal_batch_id, 'Allocation journal posted');
    }

    public function test_reverse_run_reverses_all_child_payments(): void
    {
        $run = $this->makeDraftRun();
        $vi1 = $this->makePostedInvoice($this->supplierA, '500.00');
        $vi2 = $this->makePostedInvoice($this->supplierB, '300.00');
        $this->runService->addLine($run, $vi1);
        $this->runService->addLine($run, $vi2);

        $treasurer = $this->makeUser('treasurer');
        $this->executor->execute($run, $treasurer);
        $this->executor->reverse($run->fresh(), $treasurer, 'mistaken run');

        $run->refresh();
        $this->assertSame(PaymentRun::STATUS_REVERSED, $run->status);

        foreach ($run->payments as $payment) {
            $this->assertSame(Payment::STATUS_REVERSED, $payment->status);
            $this->assertNotNull($payment->reversed_by_journal_batch_id);
        }

        // VIs should now have balance_due restored.
        $vi1->refresh();
        $this->assertSame('500.00', $vi1->balance_due);
    }

    public function test_reverse_run_blocked_if_child_already_reversed(): void
    {
        $run = $this->makeDraftRun();
        $vi = $this->makePostedInvoice($this->supplierA, '500.00');
        $this->runService->addLine($run, $vi);

        $treasurer = $this->makeUser('treasurer');
        $this->executor->execute($run, $treasurer);

        // Independently reverse the child payment.
        $payment = $run->fresh()->payments->first();
        $payment->update(['status' => Payment::STATUS_REVERSED]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cannot be reversed/');
        $this->executor->reverse($run->fresh(), $treasurer, 'attempt');
    }

    /* helpers */

    protected function makeAccount(string $code, string $name, string $type): Account
    {
        return Account::firstOrCreate([
            'organization_id' => $this->org->id,
            'code' => $code,
        ], [
            'name' => $name, 'account_type' => $type,
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

    protected function makeDraftRun(): PaymentRun
    {
        return $this->runService->createDraft(
            runDate: now(),
            currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );
    }

    protected function makePostedInvoice(Counterparty $supplier, string $total, string $whtTotal = '0.00'): VendorInvoice
    {
        return VendorInvoice::create([
            'organization_id'    => $this->org->id,
            'vendor_invoice_number' => 'VI-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'invoice_date'       => '2026-11-01',
            'due_date'           => '2026-11-30',
            'currency_code'      => 'GHS',
            'counterparty_id'    => $supplier->id,
            'subtotal'           => $total,
            'tax_total'          => '0',
            'total'              => $total,
            'withholding_total'  => $whtTotal,
            'amount_paid'        => '0',
            'balance_due'        => $total,
            'status'             => VendorInvoice::STATUS_POSTED,
        ]);
    }
}
