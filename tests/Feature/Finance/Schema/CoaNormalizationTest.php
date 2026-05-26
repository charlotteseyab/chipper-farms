<?php

namespace Tests\Feature\Finance\Schema;

use App\Models\Finance\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Verifies the Phase 6 COA normalization migration produces the intended
 * final state (Option A). Because the test DB is built from all migrations,
 * we assert the post-migration invariants directly.
 *
 * NOTE: these assertions assume the seeder has NOT been run (pure migration
 * state). We seed the conflicting pre-state ourselves to prove the migration
 * converges.
 */
class CoaNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_1230_is_live_pig_inventory_control_no_manual(): void
    {
        // Simulate the seeder having created 1230 as vet supplies + 5000 leaf,
        // then run the normalization migration logic by re-asserting via the
        // migration's public effect. Since RefreshDatabase already ran all
        // migrations, 1230 should already be normalized IF a prior migration
        // created it. Create the parent + a vet-labelled 1230 to mimic the
        // conflict, then run the migration class directly.
        $this->seedConflictState();

        $this->runNormalization();

        $live = Account::whereNull('organization_id')->where('code', '1230')->first();
        $this->assertNotNull($live);
        $this->assertSame('Live Pig Inventory', $live->name);
        $this->assertTrue((bool) $live->is_control);
        $this->assertFalse((bool) $live->allow_manual_journal);
    }

    public function test_vet_supplies_relocated_to_1250(): void
    {
        $this->seedConflictState();
        $this->runNormalization();

        $vet = Account::whereNull('organization_id')->where('code', '1250')->first();
        $this->assertNotNull($vet);
        $this->assertStringContainsStringIgnoringCase('veterinary', $vet->name);
    }

    public function test_5000_is_cost_of_sales_parent_no_manual(): void
    {
        $this->seedConflictState();
        $this->runNormalization();

        $cos = Account::whereNull('organization_id')->where('code', '5000')->first();
        $this->assertNotNull($cos);
        $this->assertSame('Cost of Sales', $cos->name);
        $this->assertFalse((bool) $cos->allow_manual_journal);
    }

    public function test_5110_parented_under_5000(): void
    {
        $this->seedConflictState();
        $this->runNormalization();

        $parent = Account::whereNull('organization_id')->where('code', '5000')->first();
        $child  = Account::whereNull('organization_id')->where('code', '5110')->first();
        $this->assertNotNull($child);
        $this->assertSame($parent->id, $child->parent_id);
    }

    // ----------------------------------------------------------------

    /**
     * Build the conflicting pre-state: 1200 parent, 1230 as vet supplies,
     * 5000 as a postable "Cost of Pigs Sold" leaf, plus 5110.
     */
    protected function seedConflictState(): void
    {
        Account::query()->whereNull('organization_id')->whereIn('code', ['1200', '1230', '5000', '5110'])->delete();

        Account::create([
            'organization_id' => null, 'code' => '1200', 'name' => 'Inventory',
            'account_type' => 'asset', 'account_subtype' => 'current_asset',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
        ]);
        Account::create([
            'organization_id' => null, 'code' => '1230', 'name' => 'Veterinary Supplies Inventory',
            'account_type' => 'asset', 'account_subtype' => 'current_asset',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
        Account::create([
            'organization_id' => null, 'code' => '5000', 'name' => 'Cost of Pigs Sold',
            'account_type' => 'expense', 'account_subtype' => 'cogs',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
        ]);
        Account::create([
            'organization_id' => null, 'code' => '5110', 'name' => 'Cost of Pigs Sold',
            'account_type' => 'expense', 'account_subtype' => 'cogs',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
        ]);
    }

    protected function runNormalization(): void
    {
        $migration = include database_path('migrations/2027_07_01_140000_normalize_coa_for_phase_6_reporting.php');
        $migration->up();
    }
}
