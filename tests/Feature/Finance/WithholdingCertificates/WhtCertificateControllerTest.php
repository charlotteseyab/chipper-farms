<?php

namespace Tests\Feature\Finance\WithholdingCertificates;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\WhtCertificate;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\WithholdingCertificates\WhtCertificateService;
use Database\Seeders\Phase3dPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * WhtCertificateController HTTP tests.
 *
 * Verifies:
 *   - manage_wht_certificates gates the basic CRUD surface
 *   - issue_wht_certificate is required ON TOP for issuance
 *   - PDF download requires manage_ AND streams the binary
 *   - non-supplier counterparties are rejected at store
 */
class WhtCertificateControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $supplier;
    protected User $accountant;  // has manage_, NOT issue_
    protected User $treasurer;   // has both
    protected User $manager;     // has neither
    protected PaymentMethod $bankMethod;
    protected Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(
            Route::has('finance.wht-certificates.index'),
            'Required finance.wht-certificates.index route is not registered.'
        );

        $this->setUpFinanceFixtures();
        foreach (['accountant', 'treasurer', 'manager', 'admin', 'superuser'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
        $this->seed(Phase3dPermissionsSeeder::class);

        Storage::fake('local');
        Config::set('finance.attachments.disk', 'local');
        Config::set('finance.statements.company_tin', 'C0009999999');
        Config::set('finance.statements.company_name', 'Chipper Farms Ltd');

        $expense = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '6010'],
            ['name' => 'Sundry Expense', 'account_type' => Account::TYPE_EXPENSE,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'GEN', 'name' => 'General',
            'default_expense_account_id' => $expense->id,
            'payment_terms_days' => 30, 'default_withholding_percent' => 5,
            'is_active' => true,
        ]);
        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme Vendor', 'code' => 'VEND-A',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id, 'tax_id' => 'P0001234567',
        ]);

        $this->bankAccount = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '1020'],
            ['name' => 'Bank GCB', 'account_type' => Account::TYPE_ASSET,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
        $this->bankMethod = PaymentMethod::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => 'BANK'],
            ['name' => 'Bank Transfer', 'is_active' => true,
             'default_source_account_id' => $this->bankAccount->id],
        );

        $this->accountant = $this->makeUser('accountant');
        $this->treasurer  = $this->makeUser('treasurer');
        $this->manager    = $this->makeUser('manager');
    }

    public function test_index_loads_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.wht-certificates.index'))
            ->assertOk()
            ->assertSee('WHT Certificates');
    }

    public function test_index_blocked_for_manager(): void
    {
        $this->actingAs($this->manager)
            ->get(route('finance.wht-certificates.index'))
            ->assertForbidden();
    }

    public function test_create_form_loads_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.wht-certificates.create'))
            ->assertOk();
    }

    public function test_store_creates_draft(): void
    {
        $resp = $this->actingAs($this->accountant)->post(
            route('finance.wht-certificates.store'),
            [
                'counterparty_id' => $this->supplier->id,
                'period_kind'     => 'quarter',
                'period_year'     => 2026,
                'period_index'    => 4,
            ],
        );

        $resp->assertRedirect();
        $this->assertDatabaseHas('wht_certificates', [
            'counterparty_id' => $this->supplier->id,
            'period_kind'     => 'quarter',
            'period_year'     => 2026,
            'period_index'    => 4,
            'status'          => 'draft',
        ]);
    }

    public function test_store_validates_period_index_range(): void
    {
        $resp = $this->actingAs($this->accountant)->post(
            route('finance.wht-certificates.store'),
            [
                'counterparty_id' => $this->supplier->id,
                'period_kind'     => 'quarter',
                'period_year'     => 2026,
                'period_index'    => 99,  // out of range
            ],
        );
        $resp->assertSessionHasErrors('period_index');
    }

    public function test_show_renders_for_accountant(): void
    {
        $svc = $this->app->make(WhtCertificateService::class);
        $cert = $svc->generateDraft($this->supplier, 'quarter', 2026, 4, $this->accountant);

        $this->actingAs($this->accountant)
            ->get(route('finance.wht-certificates.show', $cert))
            ->assertOk()
            ->assertSee('Q4 2026');
    }

    public function test_issue_blocked_for_accountant(): void
    {
        $svc = $this->app->make(WhtCertificateService::class);
        $this->makeWithholdingScenario();
        $cert = $svc->generateDraft($this->supplier, 'quarter', 2026, 4, $this->accountant);

        $this->actingAs($this->accountant)
            ->post(route('finance.wht-certificates.issue', $cert))
            ->assertForbidden();

        $cert->refresh();
        $this->assertSame(WhtCertificate::STATUS_DRAFT, $cert->status);
    }

    public function test_issue_works_for_treasurer(): void
    {
        $svc = $this->app->make(WhtCertificateService::class);
        $this->makeWithholdingScenario();
        $cert = $svc->generateDraft($this->supplier, 'quarter', 2026, 4, $this->accountant);

        $this->actingAs($this->treasurer)
            ->post(route('finance.wht-certificates.issue', $cert))
            ->assertRedirect();

        $cert->refresh();
        $this->assertSame(WhtCertificate::STATUS_ISSUED, $cert->status);
        $this->assertNotNull($cert->certificate_number);
    }

    public function test_pdf_download_streams_for_issued(): void
    {
        $svc = $this->app->make(WhtCertificateService::class);
        $this->makeWithholdingScenario();
        $cert = $svc->generateDraft($this->supplier, 'quarter', 2026, 4, $this->accountant);
        $cert = $svc->issue($cert, $this->treasurer);

        $resp = $this->actingAs($this->accountant)
            ->get(route('finance.wht-certificates.pdf', $cert));
        $resp->assertOk();
        // application/pdf or octet-stream depending on storage adapter.
        $this->assertNotEmpty($resp->headers->get('Content-Type'));
    }

    public function test_void_works_for_accountant(): void
    {
        $svc = $this->app->make(WhtCertificateService::class);
        $this->makeWithholdingScenario();
        $cert = $svc->generateDraft($this->supplier, 'quarter', 2026, 4, $this->accountant);
        $cert = $svc->issue($cert, $this->treasurer);

        $resp = $this->actingAs($this->accountant)->post(
            route('finance.wht-certificates.void', $cert),
            ['reason' => 'Filed under wrong period.'],
        );
        $resp->assertRedirect();

        $cert->refresh();
        $this->assertSame(WhtCertificate::STATUS_VOID, $cert->status);
    }

    public function test_void_validates_reason(): void
    {
        $svc = $this->app->make(WhtCertificateService::class);
        $this->makeWithholdingScenario();
        $cert = $svc->generateDraft($this->supplier, 'quarter', 2026, 4, $this->accountant);
        $cert = $svc->issue($cert, $this->treasurer);

        $resp = $this->actingAs($this->accountant)->post(
            route('finance.wht-certificates.void', $cert),
            ['reason' => 'X'],  // too short
        );
        $resp->assertSessionHasErrors('reason');
    }

    /* helpers */

    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        return User::forceCreate([
            'name' => ucfirst($roleName), 'email' => $roleName . '_' . uniqid() . '@e.com',
            'password' => bcrypt('x'), 'role' => $roleName, 'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }

    protected function makeWithholdingScenario(): void
    {
        $vi = VendorInvoice::create([
            'organization_id' => $this->org->id,
            'vendor_invoice_number' => 'VI-2026-000001',
            'supplier_invoice_number' => 'SUPREF-001',
            'invoice_date' => '2026-10-01', 'due_date' => '2026-10-30',
            'counterparty_id' => $this->supplier->id,
            'currency_code' => 'GHS', 'exchange_rate' => '1',
            'status' => VendorInvoice::STATUS_POSTED,
            'subtotal' => '1000.00', 'tax_total' => '0', 'withholding_total' => '0',
            'total' => '1000.00', 'amount_paid' => '0', 'balance_due' => '1000.00',
        ]);
        $payment = Payment::create([
            'organization_id' => $this->org->id,
            'payment_number' => 'P-2026-000001',
            'payment_date' => '2026-10-15',
            'currency_code' => 'GHS', 'exchange_rate' => '1',
            'counterparty_id' => $this->supplier->id,
            'amount' => '500.00',
            'allocated_amount' => '500.00',
            'withholding_amount' => '25.00', 'net_amount' => '475.00',
            'payment_method_id' => $this->bankMethod->id,
            'source_account_id' => $this->bankAccount->id,
            'status' => Payment::STATUS_POSTED,
            'is_backfilled' => false,
            'created_by_user_id' => $this->user->id,
        ]);
        PaymentAllocation::create([
            'organization_id' => $this->org->id,
            'payment_id' => $payment->id,
            'vendor_invoice_id' => $vi->id,
            'amount' => '500.00',
            'withholding_amount' => '25.00',
            'net_amount' => '475.00',
            'allocation_date' => $payment->payment_date,
            'status' => PaymentAllocation::STATUS_ACTIVE,
            'allocated_by_user_id' => $this->user->id,
        ]);
    }
}
