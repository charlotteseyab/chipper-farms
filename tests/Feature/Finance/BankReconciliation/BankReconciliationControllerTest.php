<?php

namespace Tests\Feature\Finance\BankReconciliation;

use App\Models\Finance\BankAccount;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Phase3ePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 3e — Controller perm gate tests.
 *
 * Verifies:
 *   - reconcile_bank_statement is required for all bank-reconciliation routes
 *   - import_bank_statement (clerical) is NOT enough for reconciliation
 *   - view_bank_accounts vs manage_bank_accounts split is honoured
 *
 * Uses Route::has guard so tests skip cleanly until WEB_PHP_PHASE3E_ROUTES_PATCH
 * is applied.
 */
class BankReconciliationControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected User $accountant;  // import only, NOT reconcile
    protected User $treasurer;   // full bank surface
    protected User $manager;     // none

    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('finance.bank-reconciliation.index')) {
            $this->markTestSkipped('Phase 3e routes not yet registered. Apply WEB_PHP_PHASE3E_ROUTES_PATCH.md.');
        }

        $this->setUpFinanceFixtures();
        $this->seed(Phase3ePermissionsSeeder::class);

        // Roles (the fixture user has 'admin', so we don't reuse them).
        $accountantRole = Role::firstOrCreate(
            ['name' => 'accountant'],
            ['description' => 'Accountant role for tests']
        );
        $treasurerRole = Role::firstOrCreate(
            ['name' => 'treasurer'],
            ['description' => 'Treasurer role for tests']
        );
        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            ['description' => 'Manager role for tests']
        );

        // Re-seed so the new roles get the perms.
        $this->seed(Phase3ePermissionsSeeder::class);

        $this->accountant = User::factory()->create([
            'role' => 'accountant',
            'role_id' => $accountantRole->id,
            'organization_id' => $this->org->id,
        ]);
        $this->treasurer = User::factory()->create([
            'role' => 'treasurer',
            'role_id' => $treasurerRole->id,
            'organization_id' => $this->org->id,
        ]);
        $this->manager = User::factory()->create([
            'role' => 'manager',
            'role_id' => $managerRole->id,
            'organization_id' => $this->org->id,
        ]);

        $this->bankAccount = BankAccount::create([
            'organization_id'      => $this->org->id,
            'account_id'           => $this->cashAccount->id,
            'label'                => 'Ops',
            'bank_name'            => 'Test',
            'currency_code'        => 'GHS',
            'opening_balance'      => '0.00',
            'opening_balance_date' => '2026-01-01',
            'is_active'            => true,
            'created_by_user_id'   => $this->user->id,
        ]);
    }

    public function test_reconciliation_index_blocked_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.bank-reconciliation.index'))
            ->assertForbidden();
    }

    public function test_reconciliation_index_blocked_for_manager(): void
    {
        $this->actingAs($this->manager)
            ->get(route('finance.bank-reconciliation.index'))
            ->assertForbidden();
    }

    public function test_reconciliation_index_allowed_for_treasurer(): void
    {
        $this->actingAs($this->treasurer)
            ->get(route('finance.bank-reconciliation.index'))
            ->assertOk();
    }

    public function test_bank_statements_index_allowed_for_accountant(): void
    {
        // The clerical-level perm DOES grant import surface.
        $this->actingAs($this->accountant)
            ->get(route('finance.bank-statements.index'))
            ->assertOk();
    }

    public function test_bank_statements_index_blocked_for_manager(): void
    {
        $this->actingAs($this->manager)
            ->get(route('finance.bank-statements.index'))
            ->assertForbidden();
    }

    public function test_bank_accounts_index_allowed_for_accountant_view_only(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('finance.bank-accounts.index'))
            ->assertOk();
    }

    public function test_bank_accounts_create_blocked_for_accountant(): void
    {
        // Accountant has view, NOT manage.
        $this->actingAs($this->accountant)
            ->get(route('finance.bank-accounts.create'))
            ->assertForbidden();
    }

    public function test_bank_accounts_create_allowed_for_treasurer(): void
    {
        $this->actingAs($this->treasurer)
            ->get(route('finance.bank-accounts.create'))
            ->assertOk();
    }

    public function test_reconciliation_store_blocked_for_accountant(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('finance.bank-reconciliation.store'), [
                'bank_account_id'           => $this->bankAccount->id,
                'period_from'               => '2026-12-01',
                'period_to'                 => '2026-12-31',
                'closing_balance_per_bank'  => '1000.00',
            ])
            ->assertForbidden();
    }
}
