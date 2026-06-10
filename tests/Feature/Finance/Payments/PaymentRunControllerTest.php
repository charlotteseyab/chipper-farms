<?php

namespace Tests\Feature\Finance\Payments;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentRun;
use App\Models\Finance\PaymentRunLine;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\Payments\PaymentRunExecutor;
use App\Services\Finance\Payments\PaymentRunService;
use Database\Seeders\FinancePermissionsSeeder;
use Database\Seeders\Phase3aSupplierPermissionsSeeder;
use Database\Seeders\Phase3bVendorInvoicePermissionsSeeder;
use Database\Seeders\Phase3cPaymentPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PaymentRunControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected User $accountant;
    protected User $treasurer;
    protected Counterparty $supplier;
    protected PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Route::has('finance.payment-runs.index')) {
            $this->markTestSkipped('Phase 3c routes not registered. Apply WEB_PHP_PHASE3C_ROUTES_PATCH.md.');
        }

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
        Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => '2250'], [
            'name' => 'WHT', 'account_type' => Account::TYPE_LIABILITY,
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

        $this->accountant = $this->makeUser('accountant');
        $this->treasurer  = $this->makeUser('treasurer');
    }

    public function test_index_loads(): void
    {
        $this->actingAs($this->treasurer)
            ->get(route('finance.payment-runs.index'))
            ->assertOk()
            ->assertSee('Payment Runs');
    }

    public function test_create_blocked_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.payment-runs.create'))
            ->assertForbidden();
    }

    public function test_create_loads_for_treasurer(): void
    {
        $this->actingAs($this->treasurer)
            ->get(route('finance.payment-runs.create'))
            ->assertOk()
            ->assertSee('New Payment Run');
    }

    public function test_store_creates_draft(): void
    {
        $this->actingAs($this->treasurer)
            ->post(route('finance.payment-runs.store'), [
                'run_date'                  => '2026-12-15',
                'currency_code'             => 'GHS',
                'default_payment_method_id' => $this->cashMethod->id,
                'description'               => 'Test run',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_runs', [
            'currency_code' => 'GHS',
            'status'        => 'draft',
        ]);
    }

    public function test_show_loads(): void
    {
        $run = $this->makeDraftRun();

        $this->actingAs($this->treasurer)
            ->get(route('finance.payment-runs.show', $run))
            ->assertOk()
            ->assertSee($run->run_number);
    }

    public function test_add_line_via_controller(): void
    {
        $run = $this->makeDraftRun();
        $vi = $this->makePostedInvoice($this->supplier, '500.00');

        $this->actingAs($this->treasurer)
            ->post(route('finance.payment-runs.add-line', $run), [
                'vendor_invoice_id' => $vi->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_run_lines', [
            'payment_run_id'    => $run->id,
            'vendor_invoice_id' => $vi->id,
            'amount'            => '500.00',
        ]);
    }

    public function test_remove_line_via_controller(): void
    {
        $run = $this->makeDraftRun();
        $vi = $this->makePostedInvoice($this->supplier, '500.00');
        $line = $this->app->make(PaymentRunService::class)->addLine($run, $vi);

        $this->actingAs($this->treasurer)
            ->post(route('finance.payment-runs.remove-line', $line))
            ->assertRedirect();

        $this->assertDatabaseMissing('payment_run_lines', ['id' => $line->id]);
    }

    public function test_execute_materialises_payments(): void
    {
        $run = $this->makeDraftRun();
        $vi = $this->makePostedInvoice($this->supplier, '500.00');
        $this->app->make(PaymentRunService::class)->addLine($run, $vi);

        $this->actingAs($this->treasurer)
            ->post(route('finance.payment-runs.execute', $run))
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(PaymentRun::STATUS_EXECUTED, $run->status);
        $this->assertCount(1, $run->payments);

        $payment = $run->payments->first();
        $this->assertSame('500.00', $payment->amount);
        $this->assertSame(Payment::STATUS_POSTED, $payment->status);
    }

    public function test_cancel_requires_reason(): void
    {
        $run = $this->makeDraftRun();

        $this->actingAs($this->treasurer)
            ->from(route('finance.payment-runs.show', $run))
            ->post(route('finance.payment-runs.cancel', $run), [])
            ->assertSessionHasErrors(['reason']);
    }

    public function test_cancel_works(): void
    {
        $run = $this->makeDraftRun();

        $this->actingAs($this->treasurer)
            ->post(route('finance.payment-runs.cancel', $run), ['reason' => 'changed our mind'])
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(PaymentRun::STATUS_CANCELLED, $run->status);
    }

    public function test_reverse_requires_reason(): void
    {
        $run = $this->makeExecutedRun();

        $this->actingAs($this->treasurer)
            ->from(route('finance.payment-runs.show', $run))
            ->post(route('finance.payment-runs.reverse', $run), [])
            ->assertSessionHasErrors(['reason']);
    }

    public function test_reverse_works(): void
    {
        $run = $this->makeExecutedRun();

        $this->actingAs($this->treasurer)
            ->post(route('finance.payment-runs.reverse', $run), ['reason' => 'wrong week'])
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(PaymentRun::STATUS_REVERSED, $run->status);

        foreach ($run->payments as $p) {
            $this->assertSame(Payment::STATUS_REVERSED, $p->status);
        }
    }

    public function test_reverse_blocked_when_child_already_reversed(): void
    {
        $run = $this->makeExecutedRun();

        // Independently reverse one of the payments outside the run reverse flow.
        $payment = $run->payments->first();
        $payment->update(['status' => Payment::STATUS_REVERSED]);

        $this->actingAs($this->treasurer)
            ->from(route('finance.payment-runs.show', $run))
            ->post(route('finance.payment-runs.reverse', $run), ['reason' => 'attempt'])
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(PaymentRun::STATUS_EXECUTED, $run->status,
            'Run reverse blocked when a child payment was independently reversed');
    }

    /* helpers */

    protected function makeDraftRun(): PaymentRun
    {
        return $this->app->make(PaymentRunService::class)->createDraft(
            runDate: now(),
            currencyCode: 'GHS',
            defaultPaymentMethodId: $this->cashMethod->id,
            defaultSourceAccountId: null,
            createdBy: $this->treasurer,
        );
    }

    protected function makeExecutedRun(): PaymentRun
    {
        $run = $this->makeDraftRun();
        $vi = $this->makePostedInvoice($this->supplier, '500.00');
        $this->app->make(PaymentRunService::class)->addLine($run, $vi);
        $this->app->make(PaymentRunExecutor::class)->execute($run, $this->treasurer);
        return $run->fresh(['payments']);
    }

    protected function makePostedInvoice(Counterparty $supplier, string $total): VendorInvoice
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
            'withholding_total'  => '0',
            'amount_paid'        => '0',
            'balance_due'        => $total,
            'status'             => VendorInvoice::STATUS_POSTED,
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
