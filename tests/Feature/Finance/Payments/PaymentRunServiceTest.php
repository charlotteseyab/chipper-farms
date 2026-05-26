<?php

namespace Tests\Feature\Finance\Payments;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\PaymentRun;
use App\Models\Finance\PaymentRunLine;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\PaymentMethod;
use App\Services\Finance\Payments\PaymentRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PaymentRunServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected PaymentRunService $service;
    protected Counterparty $supplierA;
    protected Counterparty $supplierB;
    protected PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        Account::create([
            'organization_id' => $this->org->id, 'code' => '2010',
            'name' => 'AP', 'account_type' => Account::TYPE_LIABILITY,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);

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

        $this->service = $this->app->make(PaymentRunService::class);
    }

    public function test_create_draft(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );

        $this->assertSame(PaymentRun::STATUS_DRAFT, $run->status);
        $this->assertMatchesRegularExpression('/^PRUN-\d{4}-\d{6}$/', $run->run_number);
        $this->assertSame(0, $run->payment_count);
    }

    public function test_add_line_default_amount_is_balance_due(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );

        $vi = $this->makePostedInvoice($this->supplierA, '500.00');
        $line = $this->service->addLine($run, $vi);

        $this->assertSame('500.00', $line->amount);
        $this->assertSame($vi->id, $line->vendor_invoice_id);
        $this->assertSame($this->supplierA->id, $line->counterparty_id);
    }

    public function test_add_line_validates_currency_match(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'USD',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );
        $vi = $this->makePostedInvoice($this->supplierA, '500.00');  // GHS

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Currency mismatch/');
        $this->service->addLine($run, $vi);
    }

    public function test_add_line_no_duplicate_vi(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );
        $vi = $this->makePostedInvoice($this->supplierA, '500.00');

        $this->service->addLine($run, $vi);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already a line/');
        $this->service->addLine($run, $vi);
    }

    public function test_add_line_blocked_on_unposted_vi(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );
        $vi = $this->makePostedInvoice($this->supplierA, '500.00');
        $vi->update(['status' => VendorInvoice::STATUS_DRAFT]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/not posted/');
        $this->service->addLine($run, $vi);
    }

    public function test_add_line_blocked_on_zero_balance(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );
        $vi = $this->makePostedInvoice($this->supplierA, '500.00');
        $vi->update(['balance_due' => '0.00']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/zero balance/');
        $this->service->addLine($run, $vi);
    }

    public function test_add_line_amount_cannot_exceed_balance(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );
        $vi = $this->makePostedInvoice($this->supplierA, '500.00');

        $this->expectException(\DomainException::class);
        $this->service->addLine($run, $vi, amount: '600.00');
    }

    public function test_refresh_aggregates(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );

        $vi1 = $this->makePostedInvoice($this->supplierA, '300.00', '0.00');
        $vi2 = $this->makePostedInvoice($this->supplierA, '500.00', '37.50');
        $vi3 = $this->makePostedInvoice($this->supplierB, '200.00', '0.00');

        $this->service->addLine($run, $vi1);
        $this->service->addLine($run, $vi2);
        $this->service->addLine($run, $vi3);

        $run->refresh();
        $this->assertSame('1000.00', $run->total_gross);
        $this->assertSame('37.50',   $run->total_withholding);
        $this->assertSame('962.50',  $run->total_net);
        $this->assertSame(2,         $run->payment_count, 'Two distinct counterparties');
    }

    public function test_remove_line_recomputes(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );

        $vi1 = $this->makePostedInvoice($this->supplierA, '300.00');
        $vi2 = $this->makePostedInvoice($this->supplierA, '500.00');

        $line1 = $this->service->addLine($run, $vi1);
        $this->service->addLine($run, $vi2);

        $run->refresh();
        $this->assertSame('800.00', $run->total_gross);

        $this->service->removeLine($line1);

        $run->refresh();
        $this->assertSame('500.00', $run->total_gross);
        $this->assertCount(1, $run->lines);
    }

    public function test_remove_line_blocked_when_executed(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );
        $vi = $this->makePostedInvoice($this->supplierA, '500.00');
        $line = $this->service->addLine($run, $vi);

        // Simulate execution.
        $line->update(['payment_id' => 999999]);
        $line->refresh();
        $run->update(['status' => PaymentRun::STATUS_EXECUTED]);

        $this->expectException(\DomainException::class);
        $this->service->removeLine($line);
    }

    public function test_cancel_only_from_draft(): void
    {
        $run = $this->service->createDraft(
            runDate: now(), currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->makeUser('treasurer'),
        );
        $run->update(['status' => PaymentRun::STATUS_EXECUTED]);

        $this->expectException(\DomainException::class);
        $this->service->cancel($run, $this->makeUser('treasurer'), 'try cancel executed');
    }

    /* helpers */

    protected function makeUser(string $roleName): \App\Models\User
    {
        $role = \App\Models\Role::firstOrCreate(['name' => $roleName]);
        return \App\Models\User::forceCreate([
            'name' => ucfirst($roleName), 'email' => $roleName . '_' . uniqid() . '@e.com',
            'password' => bcrypt('x'), 'role' => $roleName, 'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
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
