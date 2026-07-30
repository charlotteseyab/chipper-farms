<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FinancePermissionsSeeder;
use Database\Seeders\Phase3aSupplierPermissionsSeeder;
use Database\Seeders\Phase3bVendorInvoicePermissionsSeeder;
use Database\Seeders\Phase3dPermissionsSeeder;
use Database\Seeders\Phase3ePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Direct-request authorisation regression tests for the finance module
 * (CF-P0-003, CF-P0-004).
 *
 * Before remediation, 69 of 207 routes under the /finance prefix carried no
 * permission middleware whatsoever, and the route group itself had no outer
 * boundary. A same-organisation authenticated user holding zero finance
 * entitlement could read customer ledgers, statements, refunds, suppliers,
 * payments, payment runs and receipts, and could additionally perform
 * state-changing operations: creating and deactivating bank accounts,
 * reconciling and reopening bank reconciliations, voiding bank statements,
 * altering customer credit holds, and issuing or voiding WHT certificates.
 *
 * These tests assert the negative case by direct request, which is the only
 * thing that matters — hidden UI controls never protected any of this.
 */
class FinanceRouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    /** Holds no finance-module permission at all. */
    protected User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->seed(FinancePermissionsSeeder::class);
        $this->seed(Phase3aSupplierPermissionsSeeder::class);
        $this->seed(Phase3bVendorInvoicePermissionsSeeder::class);
        $this->seed(Phase3dPermissionsSeeder::class);
        $this->seed(Phase3ePermissionsSeeder::class);

        // A role deliberately granted one non-finance permission, so it is a
        // real role with real entitlements — just nothing in finance. This
        // models the actual threat: an ordinary farm user, not an anonymous
        // visitor.
        $role = Role::firstOrCreate(['name' => 'storekeeper']);
        $harmless = Permission::firstOrCreate(
            ['name' => 'view_storefront_listings'],
            ['description' => 'Test-only non-finance permission', 'module' => 'storefront'],
        );
        $role->permissions()->syncWithoutDetaching([$harmless->id]);

        $this->outsider = User::forceCreate([
            'name'            => 'Storekeeper',
            'email'           => 'storekeeper_' . uniqid() . '@example.com',
            'password'        => bcrypt('x'),
            'role'            => 'storekeeper',
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
            'active_status'   => true,
        ]);
    }

    /**
     * Read routes that disclose financial position. Every one of these was
     * reachable by this user before remediation.
     *
     * @return array<string, array{0:string}>
     */
    public static function financeReadRouteProvider(): array
    {
        return [
            'refunds index'            => ['finance.refunds.index'],
            'suppliers index'          => ['finance.suppliers.index'],
            'payments index'           => ['finance.payments.index'],
            'payment runs index'       => ['finance.payment-runs.index'],
            'receipts index'           => ['finance.receipts.index'],
            'vendor invoices index'    => ['finance.vendor-invoices.index'],
            'supplier statements'      => ['finance.supplier-statements.index'],
            'wht certificates index'   => ['finance.wht-certificates.index'],
            'bank accounts index'      => ['finance.bank-accounts.index'],
            'bank statements index'    => ['finance.bank-statements.index'],
            'bank reconciliation'      => ['finance.bank-reconciliation.index'],
            'ar ageing index'          => ['finance.ageing.index'],
            'ar ageing'                => ['finance.ageing.ar'],
        ];
    }

    #[DataProvider('financeReadRouteProvider')]
    public function test_finance_reads_are_denied_without_finance_permission(string $routeName): void
    {
        if (! \Illuminate\Support\Facades\Route::has($routeName)) {
            $this->fail("Route {$routeName} is not registered; the gate cannot be asserted.");
        }

        $this->actingAs($this->outsider)
            ->get(route($routeName))
            ->assertForbidden();
    }

    /**
     * State-changing routes that had no gate at all. A GET on the create form
     * is included where one exists, because reaching the form is the first step
     * and was equally unprotected.
     *
     * @return array<string, array{0:string}>
     */
    public static function financeWriteRouteProvider(): array
    {
        return [
            'bank account create form'   => ['finance.bank-accounts.create'],
            'bank statement create form' => ['finance.bank-statements.create'],
            'bank recon create form'     => ['finance.bank-reconciliation.create'],
            'wht certificate create'     => ['finance.wht-certificates.create'],
            'supplier statement create'  => ['finance.supplier-statements.create'],
        ];
    }

    #[DataProvider('financeWriteRouteProvider')]
    public function test_finance_write_forms_are_denied_without_permission(string $routeName): void
    {
        if (! \Illuminate\Support\Facades\Route::has($routeName)) {
            $this->fail("Route {$routeName} is not registered; the gate cannot be asserted.");
        }

        $this->actingAs($this->outsider)
            ->get(route($routeName))
            ->assertForbidden();
    }

    public function test_bank_account_creation_is_denied_without_permission(): void
    {
        $this->actingAs($this->outsider)
            ->post(route('finance.bank-accounts.store'), [
                'name'          => 'Attacker Account',
                'currency_code' => 'GHS',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('bank_accounts', ['name' => 'Attacker Account']);
    }

    public function test_outer_boundary_denies_finance_dashboard(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('finance.dashboard'))
            ->assertForbidden();
    }

    /**
     * The outer boundary must admit a role holding a narrow finance permission
     * without view_finance. farmmanager is the real case: it holds
     * create_purchase_request_vendor_invoice, view_financial_reports,
     * view_treasury and others, but not view_finance. Gating the group on
     * view_finance alone would have locked it out — this test exists to stop
     * anyone "simplifying" the boundary back to that.
     */
    public function test_outer_boundary_admits_narrowly_permissioned_role(): void
    {
        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        // Seeded by Phase3bVendorInvoicePermissionsSeeder in setUp(). Asserted
        // rather than skipped: this test guards the central design decision of
        // the finance boundary, so it must never silently stop running.
        $perm = Permission::where('name', 'create_purchase_request_vendor_invoice')->first();

        $this->assertNotNull(
            $perm,
            'create_purchase_request_vendor_invoice must be seeded for this test to be meaningful.',
        );

        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $farmManager = User::forceCreate([
            'name'            => 'Farm Manager',
            'email'           => 'fm_' . uniqid() . '@example.com',
            'password'        => bcrypt('x'),
            'role'            => 'farmmanager',
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
            'active_status'   => true,
        ]);

        // Admitted through the outer boundary, and through the vendor-invoice
        // read gate, which accepts this permission alongside view_finance.
        $this->actingAs($farmManager)
            ->get(route('finance.vendor-invoices.index'))
            ->assertOk();

        // But still denied where it holds no permission.
        $this->actingAs($farmManager)
            ->get(route('finance.bank-accounts.index'))
            ->assertForbidden();
    }
}
