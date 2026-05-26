<?php

namespace Tests\Feature\Finance\Receipts;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FinancePermissionsSeeder;
use Database\Seeders\Phase2bInvoicePermissionsSeeder;
use Database\Seeders\Phase2cReceiptPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class ReceiptControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected User $accountant;
    protected User $manager;
    protected Counterparty $customer;
    protected PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Route::has('finance.receipts.index')) {
            $this->markTestSkipped('Phase 2c routes not registered. Apply WEB_PHP_RECEIPTS_ROUTES_PATCH.md.');
        }

        $this->setUpFinanceFixtures();

        if (! Account::where('organization_id', $this->org->id)->where('code', '1910')->exists()) {
            Account::create([
                'organization_id' => $this->org->id, 'code' => '1910',
                'name' => 'Suspense - Receipts', 'account_type' => Account::TYPE_ASSET,
                'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
            ]);
        }

        $this->seed(FinancePermissionsSeeder::class);
        $this->seed(Phase2bInvoicePermissionsSeeder::class);
        $this->seed(Phase2cReceiptPermissionsSeeder::class);

        $this->accountant = $this->makeUser('accountant');
        $this->manager = $this->makeUser('manager');

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme Butchery', 'code' => 'CUST-ACME',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->cashMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'CASH', 'name' => 'Cash',
            'is_cash' => true, 'gl_account_hint' => 'CASH_ON_HAND', 'is_active' => true,
        ]);
    }

    public function test_index_loads_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.receipts.index'))
            ->assertOk()
            ->assertSee('Receipts');
    }

    public function test_create_blocked_for_manager(): void
    {
        $this->actingAs($this->manager)
            ->get(route('finance.receipts.create'))
            ->assertForbidden();
    }

    public function test_store_creates_and_posts_receipt(): void
    {
        $invoice = $this->makePostedInvoice('500.00');

        $response = $this->actingAs($this->accountant)->post(route('finance.receipts.store'), [
            'counterparty_id'   => $this->customer->id,
            'payment_method_id' => $this->cashMethod->id,
            'receipt_date'      => '2026-05-15',
            'amount'            => '500.00',
            'currency_code'     => 'GHS',
            'allocations' => [
                ['invoice_id' => $invoice->id, 'amount' => '500.00'],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('receipts', [
            'counterparty_id' => $this->customer->id,
            'amount'          => '500.00',
            'status'          => Receipt::STATUS_POSTED,
        ]);

        $this->assertDatabaseHas('receipt_allocations', [
            'invoice_id' => $invoice->id,
            'amount'     => '500.00',
            'status'     => 'active',
        ]);

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->balance_due);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->accountant)
            ->from(route('finance.receipts.create'))
            ->post(route('finance.receipts.store'), [])
            ->assertSessionHasErrors(['counterparty_id', 'payment_method_id', 'receipt_date', 'amount']);
    }

    public function test_show_renders_receipt_with_allocations(): void
    {
        $receipt = $this->makePostedReceipt('500.00');

        $this->actingAs($this->accountant)
            ->get(route('finance.receipts.show', $receipt))
            ->assertOk()
            ->assertSee($receipt->receipt_number);
    }

    public function test_reverse_allocation_restores_invoice_balance(): void
    {
        $invoice = $this->makePostedInvoice('500.00');

        $this->actingAs($this->accountant)->post(route('finance.receipts.store'), [
            'counterparty_id'   => $this->customer->id,
            'payment_method_id' => $this->cashMethod->id,
            'receipt_date'      => '2026-05-15',
            'amount'            => '500.00',
            'currency_code'     => 'GHS',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '500.00']],
        ]);

        $allocation = \App\Models\Finance\ReceiptAllocation::query()->latest('id')->firstOrFail();

        $this->actingAs($this->accountant)
            ->patch(route('finance.receipts.allocations.reverse', $allocation), [
                'reason' => 'wrong invoice',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('500.00', $invoice->balance_due);
        $this->assertSame('0.00', $invoice->amount_paid);

        $allocation->refresh();
        $this->assertSame('reversed', $allocation->status);
    }

    /* helpers */

    protected function makePostedInvoice(string $total): Invoice
    {
        static $counter = 0;
        return Invoice::create([
            'organization_id' => $this->org->id,
            'invoice_number'  => 'INV-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'invoice_date'    => '2026-05-15',
            'counterparty_id' => $this->customer->id,
            'currency_code'   => 'GHS', 'exchange_rate' => '1',
            'status'          => Invoice::STATUS_POSTED,
            'is_cash_invoice' => false,
            'subtotal'        => $total, 'discount_total' => '0',
            'tax_total'       => '0',    'total'         => $total,
            'amount_paid'     => '0',    'balance_due'   => $total,
            'created_by_user_id' => $this->user->id,
            'posted_by_user_id'  => $this->user->id,
            'posted_at'          => now(),
        ]);
    }

    protected function makePostedReceipt(string $amount): Receipt
    {
        static $counter = 0;
        return Receipt::create([
            'organization_id'    => $this->org->id,
            'receipt_number'     => 'RCT-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'receipt_date'       => '2026-05-15',
            'counterparty_id'    => $this->customer->id,
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'amount'             => $amount, 'allocated_amount' => '0',
            'status'             => Receipt::STATUS_POSTED,
            'is_backfilled'      => false,
            'reference_type'     => 'cash_receipt',
            'created_by_user_id' => $this->user->id,
        ]);
    }

    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        return User::forceCreate([
            'name'            => ucfirst($roleName),
            'email'           => $roleName . '_' . uniqid() . '@example.com',
            'password'        => bcrypt('x'),
            'role'            => $roleName,
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }
}
