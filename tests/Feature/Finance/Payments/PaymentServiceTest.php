<?php

namespace Tests\Feature\Finance\Payments;

use App\Finance\Exceptions\DuplicatePostingException;
use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\JournalLine;
use App\Models\Finance\Payment;
use App\Models\Finance\SupplierCategory;
use App\Models\PaymentMethod;
use App\Services\Finance\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected PaymentService $service;
    protected Counterparty $supplier;
    protected PaymentMethod $cashMethod;
    protected PaymentMethod $bankMethod;
    protected PaymentMethod $creditMethod;
    protected Account $apControl;
    protected Account $cashAccount;
    protected Account $bankAccount;
    protected Account $suspenseAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // Correct COA codes per remediation, plus suspense for D31.
        $this->apControl       = $this->makeAccount('2010', 'Trade Payables — Suppliers', Account::TYPE_LIABILITY);
        $this->cashAccount     = $this->makeAccount('1010', 'Cash on Hand',                Account::TYPE_ASSET);
        $this->bankAccount     = $this->makeAccount('1030', 'Bank — Main',                  Account::TYPE_ASSET);
        $this->suspenseAccount = $this->makeAccount('2910', 'Suspense Payment',             Account::TYPE_LIABILITY);

        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'TEST', 'name' => 'Test',
            'payment_terms_days' => 30,
            'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Vendor', 'code' => 'V-T',
            'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id,
        ]);

        $this->cashMethod = PaymentMethod::create([
            'code' => 'CASH', 'name' => 'Cash',
            'is_cash' => true, 'is_bank' => false, 'is_mobile_money' => false, 'is_credit' => false,
            'requires_reference' => false,
            'gl_account_hint' => 'CASH_ON_HAND',
            'display_order' => 10, 'is_active' => true,
        ]);
        $this->bankMethod = PaymentMethod::create([
            'code' => 'BANK_TRANSFER', 'name' => 'Bank Transfer',
            'is_cash' => false, 'is_bank' => true, 'is_mobile_money' => false, 'is_credit' => false,
            'requires_reference' => true,
            'gl_account_hint' => 'BANK_MAIN',
            'display_order' => 30, 'is_active' => true,
        ]);
        $this->creditMethod = PaymentMethod::create([
            'code' => 'CREDIT', 'name' => 'On Credit',
            'is_cash' => false, 'is_bank' => false, 'is_mobile_money' => false, 'is_credit' => true,
            'requires_reference' => false,
            'gl_account_hint' => null,
            'display_order' => 90, 'is_active' => true,
        ]);

        $this->service = $this->app->make(PaymentService::class);
    }

    public function test_create_posted_balanced_journal(): void
    {
        $user = $this->makeUser('treasurer');

        $payment = $this->service->createPosted(
            supplier:    $this->supplier,
            method:      $this->cashMethod,
            amount:      '1000.00',
            paymentDate: now(),
            createdBy:   $user,
        );

        $this->assertSame(Payment::STATUS_POSTED, $payment->status);
        $this->assertNotNull($payment->journal_batch_id);
        $this->assertMatchesRegularExpression('/^PMT-\d{4}-\d{6}$/', $payment->payment_number);

        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $payment->journal_batch_id))->get();
        $debits = $lines->sum(fn ($l) => (float) $l->debit);
        $credits = $lines->sum(fn ($l) => (float) $l->credit);
        $this->assertEqualsWithDelta(1000.0, $debits, 0.01);
        $this->assertEqualsWithDelta(1000.0, $credits, 0.01);

        // Cash journal under D31 is Dr Suspense / Cr Cash (NOT Dr AP).
        $suspenseLine = $lines->firstWhere('account_id', $this->suspenseAccount->id);
        $cashLine = $lines->firstWhere('account_id', $this->cashAccount->id);
        $this->assertNotNull($suspenseLine, 'Suspense Payment debit posted');
        $this->assertNotNull($cashLine, 'Cash credit posted');
        $this->assertEqualsWithDelta(1000.0, (float) $suspenseLine->debit, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $cashLine->credit, 0.01);

        $apLine = $lines->firstWhere('account_id', $this->apControl->id);
        $this->assertNull($apLine, 'AP is NOT debited at payment creation — that happens at allocation');
    }

    public function test_rejects_non_supplier(): void
    {
        $customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'C', 'code' => 'C-X',
            'type' => Counterparty::TYPE_CUSTOMER,
            'currency_code' => 'GHS',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/not a supplier/');
        $this->service->createPosted(
            supplier: $customer, method: $this->cashMethod,
            amount: '100', paymentDate: now(), createdBy: $this->makeUser('treasurer'),
        );
    }

    public function test_rejects_zero_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createPosted(
            supplier: $this->supplier, method: $this->cashMethod,
            amount: '0', paymentDate: now(), createdBy: $this->makeUser('treasurer'),
        );
    }

    public function test_rejects_credit_terms_method(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/credit terms/');
        $this->service->createPosted(
            supplier: $this->supplier, method: $this->creditMethod,
            amount: '100', paymentDate: now(), createdBy: $this->makeUser('treasurer'),
        );
    }

    public function test_payment_number_uses_payment_date_year(): void
    {
        $user = $this->makeUser('treasurer');

        $payment = $this->service->createPosted(
            supplier: $this->supplier, method: $this->cashMethod,
            amount: '100', paymentDate: \Carbon\Carbon::parse('2025-12-31'),
            createdBy: $user,
        );

        $this->assertStringStartsWith('PMT-2025-', $payment->payment_number);
    }

    public function test_ap_account_is_resolved_at_allocation_time_not_payment_time(): void
    {
        // PaymentService creates a Dr Suspense / Cr Cash journal — AP
        // is untouched here. Verify the journal contains no AP lines
        // even when the supplier has a category-level AP override.
        $altAp = $this->makeAccount('2020', 'AP — Feed Suppliers', Account::TYPE_LIABILITY);
        $this->supplier->supplierCategory->update(['ap_account_id' => $altAp->id]);
        $this->supplier->refresh();

        $payment = $this->service->createPosted(
            supplier: $this->supplier, method: $this->cashMethod,
            amount: '500', paymentDate: now(), createdBy: $this->makeUser('treasurer'),
        );

        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $payment->journal_batch_id))->get();
        $this->assertNull($lines->firstWhere('account_id', $altAp->id),
            'Category AP override is NOT used at payment time');
        $this->assertNull($lines->firstWhere('account_id', $this->apControl->id),
            'Default AP is NOT used at payment time');
    }

    public function test_cash_routes_through_source_account_override(): void
    {
        $payment = $this->service->createPosted(
            supplier: $this->supplier, method: $this->cashMethod,
            amount: '500', paymentDate: now(),
            createdBy: $this->makeUser('treasurer'),
            extra: ['source_account_id' => $this->bankAccount->id],
        );

        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $payment->journal_batch_id))->get();
        $bankLine = $lines->firstWhere('account_id', $this->bankAccount->id);
        $this->assertNotNull($bankLine, 'source_account_id override took precedence over method hint');
    }

    public function test_cash_routes_through_method_hint_when_no_override(): void
    {
        // Cash method's hint is CASH_ON_HAND → 1010
        $payment = $this->service->createPosted(
            supplier: $this->supplier, method: $this->cashMethod,
            amount: '100', paymentDate: now(),
            createdBy: $this->makeUser('treasurer'),
        );
        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $payment->journal_batch_id))->get();
        $cashLine = $lines->firstWhere('account_id', $this->cashAccount->id);
        $this->assertNotNull($cashLine, 'Used CASH_ON_HAND hint');

        // Bank method's hint is BANK_MAIN → 1030
        $payment2 = $this->service->createPosted(
            supplier: $this->supplier, method: $this->bankMethod,
            amount: '200', paymentDate: now(),
            createdBy: $this->makeUser('treasurer'),
            extra: ['external_reference' => 'TEST-REF-001'],
        );
        $lines2 = JournalLine::whereHas('journal', fn ($q) => $q->where('journal_batch_id', $payment2->journal_batch_id))->get();
        $bankLine = $lines2->firstWhere('account_id', $this->bankAccount->id);
        $this->assertNotNull($bankLine, 'Used BANK_MAIN hint');
    }

    public function test_create_backfilled_no_journal(): void
    {
        $payment = $this->service->createBackfilled(
            supplier: $this->supplier, method: $this->cashMethod,
            amount: '500', paymentDate: now(),
            createdBy: $this->makeUser('treasurer'),
        );

        $this->assertSame(Payment::STATUS_POSTED, $payment->status);
        $this->assertTrue($payment->is_backfilled);
        $this->assertNull($payment->journal_batch_id);
        $this->assertFalse($payment->canReverse(), 'Backfilled payments cannot be reversed');
    }

    public function test_cancel_only_from_draft(): void
    {
        $payment = $this->service->createPosted(
            supplier: $this->supplier, method: $this->cashMethod,
            amount: '100', paymentDate: now(),
            createdBy: $this->makeUser('treasurer'),
        );

        $this->expectException(\DomainException::class);
        $this->service->cancel($payment, $this->makeUser('treasurer'), 'try cancel posted');
    }

    public function test_idempotency_via_journal_postings(): void
    {
        $payment = $this->service->createPosted(
            supplier: $this->supplier, method: $this->cashMethod,
            amount: '100', paymentDate: now(),
            createdBy: $this->makeUser('treasurer'),
        );

        // The journal_postings entry for this payment+purpose should exist.
        $this->assertDatabaseHas('journal_postings', [
            'idempotency_key' => \App\Models\Finance\JournalPosting::buildIdempotencyKey(
                'payment_posted',
                Payment::class,
                $payment->id,
                \App\Models\Finance\JournalPosting::PURPOSE_CASH_PAYMENT,
            ),
            'posting_purpose' => \App\Models\Finance\JournalPosting::PURPOSE_CASH_PAYMENT,
        ]);
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
}
