<?php

namespace Tests\Feature\Finance\Schema;

use App\Models\Finance\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the Phase 7 bio-asset COA finalisation migration produces the
 * intended state: 1300 parent, 1310–1370 control stage accounts, and 1230
 * repurposed as a non-control clearing account.
 */
class BiologicalAssetCoaTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_accounts_are_control_under_parent(): void
    {
        $this->seedPreState();
        $this->runMigration();

        $parent = $this->acct('1300');
        $this->assertNotNull($parent);

        foreach (['1310','1320','1330','1340','1350','1360','1370'] as $code) {
            $a = $this->acct($code);
            $this->assertNotNull($a, "{$code} should exist");
            $this->assertTrue((bool) $a->is_control, "{$code} should be control");
            $this->assertFalse((bool) $a->allow_manual_journal, "{$code} should block manual journals");
            $this->assertSame($parent->id, $a->parent_id, "{$code} should be under 1300");
        }
    }

    public function test_1230_becomes_clearing_account(): void
    {
        $this->seedPreState();
        $this->runMigration();

        $clearing = $this->acct('1230');
        $this->assertNotNull($clearing);
        $this->assertStringContainsStringIgnoringCase('clearing', $clearing->name);
        $this->assertFalse((bool) $clearing->is_control);
    }

    public function test_migration_is_idempotent(): void
    {
        $this->seedPreState();
        $this->runMigration();
        // Running again must not throw or duplicate.
        $this->runMigration();

        $this->assertSame(1, Account::whereNull('organization_id')->where('code', '1360')->count());
        $this->assertSame(1, Account::whereNull('organization_id')->where('code', '1230')->count());
    }

    protected function seedPreState(): void
    {
        Account::query()->whereNull('organization_id')->whereIn('code',
            ['1200','1230','1300','1310','1320','1330','1340','1350','1360','1370'])->delete();

        Account::create(['organization_id' => null, 'code' => '1200', 'name' => 'Inventory',
            'account_type' => 'asset', 'account_subtype' => 'current_asset', 'currency_code' => 'GHS',
            'is_active' => true, 'allow_manual_journal' => false]);
        Account::create(['organization_id' => null, 'code' => '1230', 'name' => 'Live Pig Inventory',
            'account_type' => 'asset', 'account_subtype' => 'current_asset', 'currency_code' => 'GHS',
            'is_active' => true, 'is_control' => true, 'allow_manual_journal' => false]);
    }

    protected function runMigration(): void
    {
        $m = include database_path('migrations/2027_09_01_150000_finalise_biological_asset_coa.php');
        $m->up();
    }

    protected function acct(string $code): ?Account
    {
        return Account::whereNull('organization_id')->where('code', $code)->first();
    }
}
