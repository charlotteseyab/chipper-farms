<?php

namespace Tests\Feature\Finance\Phase7;

use App\Models\Counterparty;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceLine;
use App\Models\Finance\Receipt;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 7 — customer-facing documents (blueprint §28, §29).
 *
 * A document endpoint is the kind of URL that gets forwarded, so the
 * cross-organisation tests here matter more than they would on an internal
 * screen. The header test is the other one worth having: these documents read
 * the organisation's own details, not the installation-wide environment
 * configuration the statement renderer uses.
 */
class CustomerDocumentTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->seed(\Database\Seeders\FinancePermissionsSeeder::class);
        $this->seed(\Database\Seeders\Phase2bInvoicePermissionsSeeder::class);
        $this->seed(\Database\Seeders\Phase2cReceiptPermissionsSeeder::class);

        $this->org->update([
            'name'           => 'Chipper Farms',
            'legal_name'     => 'Chipper Farms Limited',
            'phone'          => '0302000000',
            'tax_identifier' => 'C0001234567',
        ]);

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Ama Mensah', 'code' => 'CUST-AMA',
            'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
        ]);
    }

    public function test_an_invoice_renders_as_a_pdf(): void
    {
        $invoice = $this->makeInvoice('500.00');

        InvoiceLine::create([
            'organization_id' => $this->org->id,
            'invoice_id'      => $invoice->id,
            'line_number'     => 1,
            'description'     => 'Weaner pigs',
            'quantity'        => '2',
            'unit_price'      => '250.00',
            'line_subtotal'   => '500.00',
            'line_tax_amount' => '0',
            'line_total'      => '500.00',
        ]);

        $response = $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.invoices.document', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        // Actually a PDF, not an HTML error page served with a PDF header.
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * The statement renderer reads its company block from
     * `config('finance.statements.company_*')` — one company name for the whole
     * installation. On a second tenant that prints another farm's name and TIN
     * on this farm's invoices. These documents read the organisation row.
     */
    public function test_the_header_comes_from_the_organisation_not_the_environment(): void
    {
        config([
            'finance.statements.company_name' => 'Some Other Installation Ltd',
            'finance.statements.company_tin'  => 'WRONG-TIN',
        ]);

        $renderer = $this->app->make(\App\Services\Finance\Documents\CustomerDocumentRenderer::class);

        $issuer = $renderer->issuer($this->org->id);

        $this->assertSame('Chipper Farms Limited', $issuer['name']);
        $this->assertSame('C0001234567', $issuer['tin']);
    }

    public function test_the_environment_still_fills_in_what_the_organisation_has_not_set(): void
    {
        config(['finance.statements.company_address' => '12 Farm Road, Accra']);

        $renderer = $this->app->make(\App\Services\Finance\Documents\CustomerDocumentRenderer::class);

        // The organisation has no address; the env value is the fallback, so a
        // single-tenant installation configured the old way still prints.
        $this->assertSame('12 Farm Road, Accra', $renderer->issuer($this->org->id)['address']);
    }

    public function test_a_receipt_renders_as_a_pdf(): void
    {
        $receipt = Receipt::create([
            'organization_id'    => $this->org->id,
            'receipt_number'     => 'RCT-2026-000001',
            'receipt_date'       => '2026-05-15',
            'counterparty_id'    => $this->customer->id,
            'currency_code'      => 'GHS',
            'exchange_rate'      => '1',
            'amount'             => '500.00',
            'allocated_amount'   => '0',
            'payment_method_id'  => PaymentMethod::create([
                'organization_id' => $this->org->id,
                'code' => 'CASH', 'name' => 'Cash',
                'is_cash' => true, 'is_bank' => false, 'is_mobile_money' => false,
                'is_active' => true,
            ])->id,
            'status'             => Receipt::STATUS_POSTED,
            'is_backfilled'      => false,
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.receipts.document', $receipt));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_credit_note_renders_as_a_pdf(): void
    {
        $creditNote = $this->makeCreditNote();

        $response = $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.credit-notes.document', $creditNote));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /* ---------------------------------------------------------------- */
    /* Cross-organisation                                                */
    /* ---------------------------------------------------------------- */

    public function test_another_organisations_invoice_document_is_not_reachable(): void
    {
        $other = Organization::create(['slug' => 'other', 'name' => 'Other Farm']);

        $foreignCustomer = Counterparty::create([
            'organization_id' => $other->id,
            'name' => 'Their customer', 'code' => 'CUST-X',
            'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
        ]);

        $foreignInvoice = Invoice::create([
            'organization_id'  => $other->id,
            'invoice_number'   => 'INV-OTHER-1',
            'invoice_date'     => '2026-05-15',
            'counterparty_id'  => $foreignCustomer->id,
            'currency_code'    => 'GHS', 'exchange_rate' => '1',
            'status'           => Invoice::STATUS_POSTED, 'is_cash_invoice' => false,
            'subtotal' => '500.00', 'discount_total' => '0', 'tax_total' => '0',
            'total' => '500.00', 'amount_paid' => '0', 'balance_due' => '500.00',
            'created_by_user_id' => $this->user->id,
        ]);

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.invoices.document', $foreignInvoice))
            ->assertNotFound();
    }

    public function test_another_organisations_credit_note_is_not_reachable(): void
    {
        $other = Organization::create(['slug' => 'other', 'name' => 'Other Farm']);

        $foreign = $this->makeCreditNote($other->id);

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.credit-notes.show', $foreign))
            ->assertNotFound();

        $this->flushSession();

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.credit-notes.document', $foreign))
            ->assertNotFound();
    }

    /* ---------------------------------------------------------------- */

    protected function makeInvoice(string $total): Invoice
    {
        static $counter = 0;

        return Invoice::create([
            'organization_id'  => $this->org->id,
            'invoice_number'   => 'INV-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'invoice_date'     => '2026-05-15',
            'due_date'         => '2026-06-15',
            'counterparty_id'  => $this->customer->id,
            'currency_code'    => 'GHS', 'exchange_rate' => '1',
            'status'           => Invoice::STATUS_POSTED, 'is_cash_invoice' => false,
            'subtotal' => $total, 'discount_total' => '0', 'tax_total' => '0',
            'total' => $total, 'amount_paid' => '0', 'balance_due' => $total,
            'created_by_user_id' => $this->user->id,
            'posted_by_user_id'  => $this->user->id,
            'posted_at'          => now(),
        ]);
    }

    protected function makeCreditNote(?int $organizationId = null): CreditNote
    {
        static $counter = 0;
        $organizationId ??= $this->org->id;

        $customer = $organizationId === $this->org->id
            ? $this->customer
            : Counterparty::create([
                'organization_id' => $organizationId,
                'name' => 'Their customer', 'code' => 'CUST-CN-' . (++$counter),
                'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
            ]);

        return CreditNote::create([
            'organization_id'    => $organizationId,
            'credit_note_number' => 'CN-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'customer_id'        => $customer->id,
            'currency_code'      => 'GHS',
            'subtotal'           => '120.00',
            'tax_total'          => '0.00',
            'total'              => '120.00',
            'issue_date'         => '2026-05-20',
            'accounting_date'    => '2026-05-20',
            'reason'             => 'Two piglets returned',
            'status'             => CreditNote::STATUS_POSTED,
            'idempotency_key'    => 'test-cn-' . $counter,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    /** @param  list<string>  $permissions */
    protected function userWith(array $permissions): User
    {
        static $sequence = 0;
        $role = Role::firstOrCreate(['name' => 'phase7-docs-' . (++$sequence)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['name' => $name, 'description' => $name, 'module' => 'finance'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::forceCreate([
            'name'              => 'Doc tester ' . $sequence,
            'email'             => "docs-{$sequence}@example.com",
            'password'          => bcrypt('secret'),
            'role'              => $role->name,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }
}
