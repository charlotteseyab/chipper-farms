<?php

namespace Tests\Feature\Finance\Seeders;

use App\Models\Finance\Account;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1a — verifies the COA seeder produces a complete, well-formed
 * chart of accounts and that re-running it is idempotent.
 */
class ChartOfAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_account_count(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        // The production COA now includes storefront, inventory, biological
        // asset, treasury, AP, and reporting extensions.
        $count = Account::count();
        $this->assertGreaterThanOrEqual(175, $count);
        $this->assertLessThanOrEqual(220, $count);
    }

    public function test_seeder_creates_all_account_types(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        foreach (['asset', 'liability', 'equity', 'revenue', 'expense'] as $type) {
            $this->assertGreaterThan(
                0,
                Account::ofType($type)->count(),
                "No accounts found for type: {$type}"
            );
        }
    }

    public function test_critical_system_accounts_present(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $criticalCodes = [
            // Cash and Bank
            '1010', '1030',
            // AR + control accounts
            '1100', '1110', '1120',
            // Inventory
            '1200', '1210', '1220',
            // Biological assets — IAS 41 cost model
            '1300', '1340', '1350', '1360', '1370',
            // AP
            '2000', '2010',
            // Tax stack — Ghana
            '2210', '2220', '2230', '2240', '2250',
            // Customer Prepayments — Decision 5 (two-step storefront)
            '2300', '2310', '2320',
            // Investor liabilities — Decision 4 (debt + preferred)
            '2610', '2620',
            // Equity + clearing
            '3010', '3110', '3200', '3300',
            // Revenue
            '4010', '4110',
            // COGS + mortality
            '5000', '5400', '5410',
            // Finance Costs — Decision 6
            '7010',
            // Investor Returns — Decision 4
            '7110',
        ];

        foreach ($criticalCodes as $code) {
            $this->assertNotNull(
                Account::where('code', $code)->first(),
                "Critical account {$code} missing from seeder"
            );
        }
    }

    public function test_seeder_wires_parent_relationships(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $cash = Account::where('code', '1010')->first();
        $this->assertNotNull($cash);
        $this->assertNotNull($cash->parent_id);

        $cashGroup = Account::find($cash->parent_id);
        $this->assertSame('1000', $cashGroup->code);
        $this->assertSame('Cash and Bank', $cashGroup->name);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $firstCount = Account::count();

        // Re-run.
        $this->seed(ChartOfAccountsSeeder::class);
        $secondCount = Account::count();

        $this->assertSame($firstCount, $secondCount);
    }

    public function test_control_accounts_disable_manual_journal_posting(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $arControl = Account::where('code', '1110')->first();
        $this->assertTrue($arControl->is_control);
        $this->assertFalse($arControl->allow_manual_journal);

        $apControl = Account::where('code', '2010')->first();
        $this->assertTrue($apControl->is_control);
        $this->assertFalse($apControl->allow_manual_journal);
    }

    public function test_biological_asset_accounts_use_cost_model(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        // Decision 7: cost model + IAS 41 disclosure.
        // The parent should have explanatory description.
        $biologicalParent = Account::where('code', '1300')->first();
        $this->assertNotNull($biologicalParent);
        $this->assertStringContainsString('IAS 41', $biologicalParent->description);
        $this->assertStringContainsString('cost', $biologicalParent->description);
    }

    public function test_normal_balance_helpers_match_account_type(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        // Asset and Expense → debit-normal
        $cash = Account::where('code', '1010')->first();
        $this->assertTrue($cash->isDebitNormal());
        $this->assertSame(Account::NORMAL_BALANCE_DEBIT, $cash->normalBalance());

        $vetExpense = Account::where('code', '6010')->first();
        $this->assertTrue($vetExpense->isDebitNormal());

        // Liability, Equity, Revenue → credit-normal
        $apTrade = Account::where('code', '2010')->first();
        $this->assertTrue($apTrade->isCreditNormal());

        $ownerCapital = Account::where('code', '3010')->first();
        $this->assertTrue($ownerCapital->isCreditNormal());

        $pigRevenue = Account::where('code', '4010')->first();
        $this->assertTrue($pigRevenue->isCreditNormal());
    }
}
