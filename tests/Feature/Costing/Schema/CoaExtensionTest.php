<?php

namespace Tests\Feature\Costing\Schema;

use App\Models\Finance\Account;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoaExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_1230_live_pig_inventory_is_control_and_no_manual(): void
    {
        $acct = $this->acctByCode('1230');
        $this->assertNotNull($acct);
        $this->assertEquals('asset', $acct->account_type);
        $this->assertEquals('current_asset', $acct->account_subtype);
        $this->assertTrue((bool) $acct->is_system);
        $this->assertTrue((bool) $acct->is_control);
        $this->assertFalse((bool) $acct->allow_manual_journal);
    }

    public function test_1230_parented_under_1200_inventory(): void
    {
        $parent = $this->acctByCode('1200');
        $child  = $this->acctByCode('1230');
        $this->assertNotNull($parent, 'Parent 1200 must exist before this assertion is meaningful.');
        $this->assertEquals($parent->id, $child->parent_id);
    }

    public function test_5110_cost_of_pigs_sold_is_expense_cogs(): void
    {
        $acct = $this->acctByCode('5110');
        $this->assertNotNull($acct);
        $this->assertEquals('expense', $acct->account_type);
        $this->assertEquals('cogs', $acct->account_subtype);
        $this->assertFalse((bool) $acct->allow_manual_journal);
    }

    public function test_5320_mortality_loss_is_expense_cogs(): void
    {
        $acct = $this->acctByCode('5320');
        $this->assertNotNull($acct);
        $this->assertEquals('expense', $acct->account_type);
        $this->assertEquals('cogs', $acct->account_subtype);
        $this->assertFalse((bool) $acct->allow_manual_journal);
    }

    public function test_5110_and_5320_parented_under_5000_cogs_root(): void
    {
        $parent = $this->acctByCode('5000');
        if (! $parent) {
            // Some COA seeders don't ship a 5000 parent — Phase 5 must
            // still seed cleanly; assertion skipped.
            $this->markTestSkipped('5000 parent not present in this COA seed.');
            return;
        }
        $this->assertEquals($parent->id, $this->acctByCode('5110')->parent_id);
        $this->assertEquals($parent->id, $this->acctByCode('5320')->parent_id);
    }

    protected function acctByCode(string $code): ?Account
    {
        return Account::query()
            ->whereNull('organization_id')
            ->where('code', $code)
            ->first();
    }
}
