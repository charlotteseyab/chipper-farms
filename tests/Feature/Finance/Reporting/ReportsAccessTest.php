<?php

namespace Tests\Feature\Finance\Reporting;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Smoke + permission-gating for the Phase 6 routes. These assume the
 * Phase 6 routes patch has been merged into web.php.
 */
class ReportsAccessTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_income_statement_forbidden_without_permission(): void
    {
        $resp = $this->actingAs($this->user)->get('/finance/reports/income-statement');
        // Either 403 (permission middleware) — never 200 without the perm.
        $this->assertContains($resp->status(), [403, 302]);
    }

    public function test_income_statement_accessible_with_permission(): void
    {
        $this->grant('view_financial_reports');

        $resp = $this->actingAs($this->user)->get('/finance/reports/income-statement');
        $resp->assertOk();
    }

    public function test_balance_sheet_accessible_with_permission(): void
    {
        $this->grant('view_financial_reports');
        $this->actingAs($this->user)->get('/finance/reports/balance-sheet')->assertOk();
    }

    public function test_cash_flow_accessible_with_permission(): void
    {
        $this->grant('view_financial_reports');
        $this->actingAs($this->user)->get('/finance/reports/cash-flow')->assertOk();
    }

    public function test_period_close_index_requires_view_period_close(): void
    {
        $resp = $this->actingAs($this->user)->get('/finance/close');
        $this->assertContains($resp->status(), [403, 302]);

        $this->grant('view_period_close');
        $this->actingAs($this->user)->get('/finance/close')->assertOk();
    }

    protected function grant(string $code): void
    {
        $perm = Permission::firstOrCreate(['name' => $code], ['name' => $code, 'description' => $code, 'module' => 'finance']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        // refresh cached permissions on the user's role
        $this->user->refresh();
    }
}
