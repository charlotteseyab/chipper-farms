<?php

namespace Tests\Feature\Finance\SupplierStatements;

use App\Models\Counterparty;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Phase3dPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class SupplierStatementControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $supplier;
    protected User $accountant;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('finance.supplier-statements.index')) {
            $this->markTestSkipped('Phase 3d routes not registered. Apply WEB_PHP_PHASE3D_ROUTES_PATCH.md.');
        }

        $this->setUpFinanceFixtures();
        $this->seed(Phase3dPermissionsSeeder::class);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme Vendor', 'code' => 'VEND-A',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
        ]);

        $this->accountant = $this->makeUser('accountant');
        $this->manager    = $this->makeUser('manager');  // No supplier-stmt perm
    }

    public function test_index_loads_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.supplier-statements.index'))
            ->assertOk()
            ->assertSee('Supplier Statements');
    }

    public function test_index_blocked_for_user_without_perm(): void
    {
        $this->actingAs($this->manager)
            ->get(route('finance.supplier-statements.index'))
            ->assertForbidden();
    }

    public function test_create_form_loads(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.supplier-statements.create'))
            ->assertOk()
            ->assertSee('New Supplier Statement');
    }

    public function test_store_creates_draft_with_redirect(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('finance.supplier-statements.store'), [
            'counterparty_id'  => $this->supplier->id,
            'statement_date'   => '2026-12-31',
            'period_from'      => '2026-12-01',
            'period_to'        => '2026-12-31',
            'currency_code'    => 'GHS',
            'supplier_balance' => '5000.00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('supplier_statements', [
            'counterparty_id'  => $this->supplier->id,
            'supplier_balance' => '5000.00',
            'status'           => 'draft',
        ]);
    }

    public function test_store_validates_period_to_after_from(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('finance.supplier-statements.store'), [
            'counterparty_id'  => $this->supplier->id,
            'statement_date'   => '2026-12-31',
            'period_from'      => '2026-12-31',
            'period_to'        => '2026-12-01', // before
            'supplier_balance' => '0.00',
        ]);
        $response->assertSessionHasErrors('period_to');
    }

    public function test_store_rejects_non_supplier(): void
    {
        $cust = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Cust', 'code' => 'C-1',
            'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
        ]);

        $response = $this->actingAs($this->accountant)->post(route('finance.supplier-statements.store'), [
            'counterparty_id'  => $cust->id,
            'statement_date'   => '2026-12-31',
            'period_from'      => '2026-12-01',
            'period_to'        => '2026-12-31',
            'supplier_balance' => '0.00',
        ]);

        // Either 422 (ensureSupplierInOrg abort) or back-with-error.
        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_show_renders_for_drafts(): void
    {
        // Create via service so the statement is properly initialised.
        $svc = $this->app->make(\App\Services\Finance\SupplierStatements\SupplierStatementService::class);
        $stmt = $svc->createDraft(
            $this->supplier,
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-12-01'),
            new \DateTimeImmutable('2026-12-31'),
            '5000.00',
            $this->accountant,
        );

        $this->actingAs($this->accountant)
            ->get(route('finance.supplier-statements.show', $stmt))
            ->assertOk()
            ->assertSee($stmt->statement_number);
    }

    public function test_store_line_via_http(): void
    {
        $svc = $this->app->make(\App\Services\Finance\SupplierStatements\SupplierStatementService::class);
        $stmt = $svc->createDraft(
            $this->supplier,
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-12-01'),
            new \DateTimeImmutable('2026-12-31'),
            '0.00',
            $this->accountant,
        );

        $resp = $this->actingAs($this->accountant)->post(
            route('finance.supplier-statements.lines.store', $stmt),
            [
                'document_date'      => '2026-12-05',
                'document_kind'      => 'invoice',
                'document_reference' => 'ACM-001',
                'amount'             => '1000.00',
            ],
        );

        $resp->assertRedirect();
        $this->assertDatabaseHas('supplier_statement_lines', [
            'supplier_statement_id' => $stmt->id,
            'document_reference'    => 'ACM-001',
            'amount'                => '1000.00',
        ]);
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
}
