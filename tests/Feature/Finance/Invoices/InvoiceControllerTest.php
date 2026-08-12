<?php

namespace Tests\Feature\Finance\Invoices;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FinancePermissionsSeeder;
use Database\Seeders\Phase2bInvoicePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * HTTP-level smoke tests. Route registration is required; missing routes are
 * production regressions.
 */
class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected User $accountant;
    protected User $manager;
    protected Counterparty $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('finance.invoices.index'),
            'Required finance.invoices.index route is not registered.'
        );

        $this->setUpFinanceFixtures();

        $this->seed(FinancePermissionsSeeder::class);
        $this->seed(Phase2bInvoicePermissionsSeeder::class);

        $this->accountant = $this->makeUser('accountant');
        $this->manager = $this->makeUser('manager');

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme Butchery',
            'code' => 'CUST-ACME',
            'type' => 'customer',
            'currency_code' => 'GHS',
        ]);
    }

    public function test_index_loads_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.invoices.index'))
            ->assertOk()
            ->assertSee('Invoices');
    }

    public function test_create_blocked_for_manager_view_only(): void
    {
        $this->actingAs($this->manager)
            ->get(route('finance.invoices.create'))
            ->assertForbidden();
    }

    public function test_create_allowed_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.invoices.create'))
            ->assertOk();
    }

    public function test_store_creates_draft_invoice(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('finance.invoices.store'), [
            'counterparty_id' => $this->customer->id,
            'invoice_date'    => '2026-05-15',
            'currency_code'   => 'GHS',
            'lines' => [
                [
                    'description' => 'Test pig',
                    'quantity'    => '50',
                    'unit_price'  => '12.50',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'counterparty_id' => $this->customer->id,
            'status'          => Invoice::STATUS_DRAFT,
            'subtotal'        => '625.00',
        ]);
    }

    public function test_store_validates_at_least_one_line(): void
    {
        $response = $this->actingAs($this->accountant)
            ->from(route('finance.invoices.create'))
            ->post(route('finance.invoices.store'), [
                'counterparty_id' => $this->customer->id,
                'invoice_date'    => '2026-05-15',
                'currency_code'   => 'GHS',
                'lines'           => [],
            ]);

        $response->assertSessionHasErrors('lines');
    }

    public function test_post_requires_post_invoice_permission(): void
    {
        // Accountant has post_invoice; manager doesn't.
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'invoice_number'  => 'INV-2026-000001',
            'invoice_date'    => '2026-05-15',
            'counterparty_id' => $this->customer->id,
            'currency_code'   => 'GHS',
            'status'          => Invoice::STATUS_DRAFT,
            'subtotal' => '100', 'discount_total' => '0',
            'tax_total' => '0', 'total' => '100',
        ]);

        $this->actingAs($this->manager)
            ->post(route('finance.invoices.post', $invoice))
            ->assertForbidden();
    }

    public function test_cancel_requires_create_invoice_permission(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'invoice_number'  => 'INV-2026-000002',
            'invoice_date'    => '2026-05-15',
            'counterparty_id' => $this->customer->id,
            'currency_code'   => 'GHS',
            'status'          => Invoice::STATUS_DRAFT,
            'subtotal' => '100', 'discount_total' => '0',
            'tax_total' => '0', 'total' => '100',
        ]);

        $this->actingAs($this->manager)
            ->post(route('finance.invoices.cancel', $invoice), ['reason' => 'oops'])
            ->assertForbidden();
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
