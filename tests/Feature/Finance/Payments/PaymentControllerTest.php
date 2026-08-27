<?php

namespace Tests\Feature\Finance\Payments;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Payment;
use App\Models\Finance\SupplierCategory;
use App\Models\PaymentMethod;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\Payments\PaymentService;
use Database\Seeders\FinancePermissionsSeeder;
use Database\Seeders\Phase3aSupplierPermissionsSeeder;
use Database\Seeders\Phase3bVendorInvoicePermissionsSeeder;
use Database\Seeders\Phase3cPaymentPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected User $accountant;
    protected User $treasurer;
    protected User $manager;
    protected Counterparty $supplier;
    protected PaymentMethod $cashMethod;
    protected PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('finance.payments.index'),
            'Required finance.payments.index route is not registered.'
        );

        $this->setUpFinanceFixtures();

        foreach (['accountant', 'treasurer', 'manager', 'admin', 'superuser'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $this->seed(FinancePermissionsSeeder::class);
        $this->seed(Phase3aSupplierPermissionsSeeder::class);
        $this->seed(Phase3bVendorInvoicePermissionsSeeder::class);
        $this->seed(Phase3cPaymentPermissionsSeeder::class);

        Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => '2010'], [
            'name' => 'AP', 'account_type' => Account::TYPE_LIABILITY,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true]);
        Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => '2910'], [
            'name' => 'Suspense Payment', 'account_type' => Account::TYPE_LIABILITY,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true]);
        Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => '1010'], [
            'name' => 'Cash', 'account_type' => Account::TYPE_ASSET,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true]);

        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'X', 'name' => 'X',
            'payment_terms_days' => 30, 'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'V', 'code' => 'V-X',
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
            'code' => 'CHEQUE', 'name' => 'Cheque',
            'is_cash' => false, 'is_bank' => true, 'is_mobile_money' => false, 'is_credit' => false,
            'requires_reference' => true, 'gl_account_hint' => 'BANK_MAIN',
            'display_order' => 31, 'is_active' => true,
        ]);

        $this->accountant = $this->makeUser('accountant');
        $this->treasurer  = $this->makeUser('treasurer');
        $this->manager    = $this->makeUser('manager');
    }

    public function test_index_loads_for_treasurer(): void
    {
        $this->actingAs($this->treasurer)
            ->get(route('finance.payments.index'))
            ->assertOk()
            ->assertSee('Payments');
    }

    public function test_create_blocked_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.payments.create'))
            ->assertForbidden();
    }

    public function test_create_blocked_for_manager(): void
    {
        $this->actingAs($this->manager)
            ->get(route('finance.payments.create'))
            ->assertForbidden();
    }

    public function test_create_loads_for_treasurer(): void
    {
        $this->actingAs($this->treasurer)
            ->get(route('finance.payments.create'))
            ->assertOk()
            ->assertSee('New Payment');
    }

    public function test_store_creates_posted_payment(): void
    {
        $response = $this->actingAs($this->treasurer)
            ->post(route('finance.payments.store'), [
                'counterparty_id'   => $this->supplier->id,
                'payment_method_id' => $this->cashMethod->id,
                'amount'            => 500,
                'payment_date'      => '2026-12-15',
                'currency_code'     => 'GHS',
                'allocate_to'       => 'none',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'counterparty_id' => $this->supplier->id,
            'amount' => 500,
            'status' => 'posted',
        ]);
    }

    public function test_linked_posted_payment_is_the_only_path_that_marks_request_paid(): void
    {
        $purchaseRequest = $this->makeApprovedPurchaseRequest('500.00');

        $this->actingAs($this->treasurer)
            ->post(route('finance.payments.store'), [
                'counterparty_id' => $this->supplier->id,
                'payment_method_id' => $this->cashMethod->id,
                'amount' => 500,
                'payment_date' => '2026-12-15',
                'currency_code' => 'GHS',
                'allocate_to' => 'none',
                'purchase_request_id' => $purchaseRequest->id,
                'purchase_payment_stage' => 'full',
            ])
            ->assertRedirect(route('purchase-requests.show', $purchaseRequest));

        $this->assertSame('paid', $purchaseRequest->fresh()->status);
        $this->assertSame('paid', $purchaseRequest->fresh()->payment_progress);
    }

    public function test_partial_linked_payment_keeps_request_approved(): void
    {
        $purchaseRequest = $this->makeApprovedPurchaseRequest('500.00');

        $this->actingAs($this->treasurer)
            ->post(route('finance.payments.store'), [
                'counterparty_id' => $this->supplier->id,
                'payment_method_id' => $this->cashMethod->id,
                'amount' => 100,
                'payment_date' => '2026-12-15',
                'currency_code' => 'GHS',
                'allocate_to' => 'none',
                'purchase_request_id' => $purchaseRequest->id,
                'purchase_payment_stage' => 'deposit',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $purchaseRequest->fresh()->status);
        $this->assertSame('partially_paid', $purchaseRequest->fresh()->payment_progress);
    }

    public function test_store_blocks_credit_terms_method(): void
    {
        $creditMethod = PaymentMethod::create([
            'code' => 'CREDIT', 'name' => 'On Credit',
            'is_cash' => false, 'is_bank' => false, 'is_mobile_money' => false, 'is_credit' => true,
            'requires_reference' => false, 'display_order' => 90, 'is_active' => true,
        ]);

        $response = $this->actingAs($this->treasurer)
            ->from(route('finance.payments.create'))
            ->post(route('finance.payments.store'), [
                'counterparty_id'   => $this->supplier->id,
                'payment_method_id' => $creditMethod->id,
                'amount'            => 500,
                'payment_date'      => '2026-12-15',
                'currency_code'     => 'GHS',
                'allocate_to'       => 'none',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_store_requires_reference_for_cheque(): void
    {
        $response = $this->actingAs($this->treasurer)
            ->from(route('finance.payments.create'))
            ->post(route('finance.payments.store'), [
                'counterparty_id'   => $this->supplier->id,
                'payment_method_id' => $this->bankMethod->id,
                'amount'            => 500,
                'payment_date'      => '2026-12-15',
                'currency_code'     => 'GHS',
                'allocate_to'       => 'none',
                // No external_reference!
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', fn ($v) => str_contains($v ?? '', 'requires an external reference'));
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->treasurer)
            ->from(route('finance.payments.create'))
            ->post(route('finance.payments.store'), [])
            ->assertSessionHasErrors(['counterparty_id', 'payment_method_id', 'amount', 'payment_date', 'currency_code']);
    }

    public function test_show_loads(): void
    {
        $payment = $this->makePayment();

        $this->actingAs($this->treasurer)
            ->get(route('finance.payments.show', $payment))
            ->assertOk()
            ->assertSee($payment->payment_number);
    }

    public function test_reverse_requires_reason(): void
    {
        $payment = $this->makePayment();

        $this->actingAs($this->treasurer)
            ->from(route('finance.payments.show', $payment))
            ->post(route('finance.payments.reverse', $payment), [])
            ->assertSessionHasErrors(['reason']);
    }

    public function test_reverse_marks_payment_reversed(): void
    {
        $payment = $this->makePayment();

        $this->actingAs($this->treasurer)
            ->post(route('finance.payments.reverse', $payment), ['reason' => 'mistake'])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REVERSED, $payment->status);
    }

    public function test_reversing_linked_payment_reopens_purchase_request_payment_status(): void
    {
        $purchaseRequest = $this->makeApprovedPurchaseRequest('500.00');
        $payment = $this->app->make(PaymentService::class)->createPosted(
            supplier: $this->supplier,
            method: $this->cashMethod,
            amount: '500.00',
            paymentDate: now(),
            createdBy: $this->treasurer,
            extra: ['purchase_request_id' => $purchaseRequest->id],
        );
        $purchaseRequest->syncPaymentProgressFromFinance();

        $this->actingAs($this->treasurer)
            ->post(route('finance.payments.reverse', $payment), ['reason' => 'Payment cancelled'])
            ->assertRedirect();

        $this->assertSame('approved', $purchaseRequest->fresh()->status);
        $this->assertSame('not_paid', $purchaseRequest->fresh()->payment_progress);
    }

    public function test_reconcile_and_unreconcile(): void
    {
        $payment = $this->makePayment();

        $this->actingAs($this->treasurer)
            ->post(route('finance.payments.reconcile', $payment), ['reconciliation_reference' => 'STMT-LINE-42'])
            ->assertRedirect();

        $payment->refresh();
        $this->assertNotNull($payment->reconciled_at);
        $this->assertSame('STMT-LINE-42', $payment->reconciliation_reference);

        $this->actingAs($this->treasurer)
            ->post(route('finance.payments.unreconcile', $payment))
            ->assertRedirect();

        $payment->refresh();
        $this->assertNull($payment->reconciled_at);
        $this->assertNull($payment->reconciliation_reference);
    }

    public function test_reconcile_blocked_for_accountant(): void
    {
        $payment = $this->makePayment();

        $this->actingAs($this->accountant)
            ->post(route('finance.payments.reconcile', $payment), [])
            ->assertForbidden();
    }

    /* helpers */

    protected function makePayment(): Payment
    {
        return $this->app->make(PaymentService::class)->createPosted(
            supplier:    $this->supplier,
            method:      $this->cashMethod,
            amount:      '500.00',
            paymentDate: now(),
            createdBy:   $this->treasurer,
        );
    }

    protected function makeApprovedPurchaseRequest(string $total): PurchaseRequest
    {
        return PurchaseRequest::create([
            'organization_id' => $this->org->id,
            'request_no' => 'PR-' . uniqid(),
            'request_date' => now()->toDateString(),
            'requested_by' => $this->treasurer->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'payment_progress' => 'not_paid',
            'total_amount' => $total,
        ]);
    }

    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        return User::forceCreate([
            'name' => ucfirst($roleName), 'email' => $roleName . '_' . uniqid() . '@e.com',
            'password' => bcrypt('x'), 'role' => $roleName, 'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }
}
