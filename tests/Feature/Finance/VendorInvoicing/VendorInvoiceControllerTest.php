<?php

namespace Tests\Feature\Finance\VendorInvoicing;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\VendorInvoicing\VendorInvoiceLineDraft;
use App\Services\Finance\VendorInvoicing\VendorInvoiceService;
use Database\Seeders\FinancePermissionsSeeder;
use Database\Seeders\Phase3aSupplierPermissionsSeeder;
use Database\Seeders\Phase3bVendorInvoicePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class VendorInvoiceControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected User $accountant;
    protected User $treasurer;
    protected User $manager;
    protected Counterparty $supplier;
    protected VendorInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Route::has('finance.vendor-invoices.index')) {
            $this->markTestSkipped('Phase 3b routes not registered. Apply WEB_PHP_PHASE3B_ROUTES_PATCH.md.');
        }

        $this->setUpFinanceFixtures();

        foreach (['accountant', 'treasurer', 'manager', 'admin', 'superuser'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $this->seed(FinancePermissionsSeeder::class);
        $this->seed(Phase3aSupplierPermissionsSeeder::class);
        $this->seed(Phase3bVendorInvoicePermissionsSeeder::class);

        Account::firstOrCreate([
            'organization_id' => $this->org->id, 'code' => '6010',
        ], [
            'name' => 'Sundry', 'account_type' => Account::TYPE_EXPENSE,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
        Account::firstOrCreate([
            'organization_id' => $this->org->id, 'code' => '2110',
        ], [
            'name' => 'AP', 'account_type' => Account::TYPE_LIABILITY,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
        Account::firstOrCreate([
            'organization_id' => $this->org->id, 'code' => '1410',
        ], [
            'name' => 'Input VAT', 'account_type' => Account::TYPE_ASSET,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);

        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'X', 'name' => 'X',
            'payment_terms_days' => 30, 'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Vendor', 'code' => 'V-X',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id,
        ]);

        $this->accountant = $this->makeUser('accountant');
        $this->treasurer  = $this->makeUser('treasurer');
        $this->manager    = $this->makeUser('manager');

        $this->service = $this->app->make(VendorInvoiceService::class);
    }

    public function test_index_loads_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.vendor-invoices.index'))
            ->assertOk()
            ->assertSee('Vendor Invoices');
    }

    public function test_create_blocked_for_manager(): void
    {
        $this->actingAs($this->manager)
            ->get(route('finance.vendor-invoices.create'))
            ->assertForbidden();
    }

    public function test_create_loads_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.vendor-invoices.create'))
            ->assertOk()
            ->assertSee('New Vendor Invoice');
    }

    public function test_store_creates_draft(): void
    {
        $response = $this->actingAs($this->accountant)
            ->post(route('finance.vendor-invoices.store'), [
                'counterparty_id' => $this->supplier->id,
                'invoice_date'    => '2026-11-01',
                'currency_code'   => 'GHS',
                'lines' => [[
                    'description' => 'Test line',
                    'quantity'    => 5,
                    'unit_cost'   => 100,
                ]],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('vendor_invoices', [
            'counterparty_id' => $this->supplier->id,
            'status' => 'draft',
        ]);
    }

    public function test_store_validates_lines_required(): void
    {
        $this->actingAs($this->accountant)
            ->from(route('finance.vendor-invoices.create'))
            ->post(route('finance.vendor-invoices.store'), [
                'counterparty_id' => $this->supplier->id,
                'invoice_date'    => '2026-11-01',
                'currency_code'   => 'GHS',
                'lines' => [],
            ])
            ->assertSessionHasErrors(['lines']);
    }

    public function test_submit_for_approval_transitions_state(): void
    {
        $invoice = $this->makeDraftInvoice();

        $this->actingAs($this->accountant)
            ->post(route('finance.vendor-invoices.submit', $invoice))
            ->assertRedirect();

        $this->assertSame(VendorInvoice::STATUS_PENDING_APPROVAL, $invoice->fresh()->status);
    }

    public function test_post_blocked_for_accountant(): void
    {
        $invoice = $this->makeDraftInvoice();
        $invoice->update(['status' => VendorInvoice::STATUS_PENDING_APPROVAL]);

        $this->actingAs($this->accountant)
            ->post(route('finance.vendor-invoices.post', $invoice))
            ->assertForbidden();
    }

    public function test_post_works_for_treasurer(): void
    {
        $invoice = $this->makeDraftInvoice($this->accountant);
        $invoice->update(['status' => VendorInvoice::STATUS_PENDING_APPROVAL]);

        $this->actingAs($this->treasurer)
            ->post(route('finance.vendor-invoices.post', $invoice))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(VendorInvoice::STATUS_POSTED, $invoice->status);
        $this->assertNotNull($invoice->journal_batch_id);
        $this->assertSame($this->treasurer->id, $invoice->approved_by_user_id);
    }

    public function test_two_person_rule_blocks_creator_approver(): void
    {
        // Treasurer drafts AND tries to approve same invoice
        $invoice = $this->makeDraftInvoice($this->treasurer);
        $invoice->update(['status' => VendorInvoice::STATUS_PENDING_APPROVAL]);

        $response = $this->actingAs($this->treasurer)
            ->from(route('finance.vendor-invoices.show', $invoice))
            ->post(route('finance.vendor-invoices.post', $invoice))
            ->assertRedirect();

        // Should redirect back with error, NOT post the invoice
        $this->assertSame(VendorInvoice::STATUS_PENDING_APPROVAL, $invoice->fresh()->status);
        $response->assertSessionHas('error', fn ($v) => str_contains(strtolower($v ?? ''), 'two-person'));
    }

    public function test_two_person_rule_disabled_via_config(): void
    {
        config()->set('finance.vendor_invoices.enforce_two_person_rule', false);

        $invoice = $this->makeDraftInvoice($this->treasurer);
        $invoice->update(['status' => VendorInvoice::STATUS_PENDING_APPROVAL]);

        $this->actingAs($this->treasurer)
            ->post(route('finance.vendor-invoices.post', $invoice))
            ->assertRedirect();

        $this->assertSame(VendorInvoice::STATUS_POSTED, $invoice->fresh()->status,
            'With two-person rule disabled, creator can also approve');
    }

    public function test_cancel_requires_reason(): void
    {
        $invoice = $this->makeDraftInvoice();

        $this->actingAs($this->accountant)
            ->from(route('finance.vendor-invoices.show', $invoice))
            ->post(route('finance.vendor-invoices.cancel', $invoice), [])
            ->assertSessionHasErrors(['reason']);
    }

    public function test_cancel_marks_invoice(): void
    {
        $invoice = $this->makeDraftInvoice();

        $this->actingAs($this->accountant)
            ->post(route('finance.vendor-invoices.cancel', $invoice), [
                'reason' => 'Duplicate of VI-2026-000001',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(VendorInvoice::STATUS_CANCELLED, $invoice->status);
        $this->assertSame('Duplicate of VI-2026-000001', $invoice->cancellation_reason);
    }

    public function test_show_re_evaluates_match_status(): void
    {
        $invoice = $this->makeDraftInvoice();

        $this->actingAs($this->accountant)
            ->get(route('finance.vendor-invoices.show', $invoice))
            ->assertOk();

        // Without PR linkage, match_status is null after evaluate
        $this->assertNull($invoice->fresh()->match_status);
    }

    public function test_filter_by_status(): void
    {
        $draft = $this->makeDraftInvoice();
        $cancelled = $this->makeDraftInvoice();
        $cancelled->update(['status' => VendorInvoice::STATUS_CANCELLED]);

        $response = $this->actingAs($this->accountant)
            ->get(route('finance.vendor-invoices.index', ['status' => 'cancelled']));

        $response->assertOk()
            ->assertSee($cancelled->vendor_invoice_number)
            ->assertDontSee($draft->vendor_invoice_number);
    }

    /* helpers */

    protected function makeDraftInvoice(?User $createdBy = null): VendorInvoice
    {
        return $this->service->createDraft(
            $this->supplier,
            ['invoice_date' => '2026-11-01'],
            [new VendorInvoiceLineDraft(description: 'X', quantity: '1', unitCost: '100')],
            $createdBy ?? $this->accountant,
        );
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
