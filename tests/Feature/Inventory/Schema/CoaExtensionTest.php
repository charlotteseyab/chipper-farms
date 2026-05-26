<?php

namespace Tests\Feature\Inventory\Schema;

use App\Models\Finance\Account;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — verifies the COA extension migration seeds the new accounts
 * with correct attributes (control flags, parents, types).
 *
 * RefreshDatabase runs all migrations including the COA extension
 * (2027_03_01_120000). The ChartOfAccountsSeeder is invoked to provide
 * the parent accounts (1200, 2000) so the extension can wire them.
 */
class CoaExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed the base COA. The Phase 4 extension migration depends on
        // parent accounts (1200 Inventory, 2000 AP) being present.
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_1215_raw_materials_in_transit_account_seeded_with_parent_1200(): void
    {
        $acct = $this->acctByCode('1215');
        $this->assertNotNull($acct);
        $this->assertEquals('Raw Materials In Transit', $acct->name);
        $this->assertEquals('asset', $acct->account_type);
        $this->assertEquals('current_asset', $acct->account_subtype);
        $this->assertEquals($this->acctByCode('1200')->id, $acct->parent_id);
    }

    public function test_2050_gr_ir_account_is_control_and_no_manual_journal(): void
    {
        $acct = $this->acctByCode('2050');
        $this->assertNotNull($acct);
        $this->assertEquals('liability', $acct->account_type);
        $this->assertTrue((bool) $acct->is_system);
        $this->assertTrue((bool) $acct->is_control);
        $this->assertFalse((bool) $acct->allow_manual_journal);
        $this->assertEquals($this->acctByCode('2000')->id, $acct->parent_id);
    }

    public function test_2055_inventory_suspense_account_is_control(): void
    {
        $acct = $this->acctByCode('2055');
        $this->assertNotNull($acct);
        $this->assertTrue((bool) $acct->is_control);
        $this->assertFalse((bool) $acct->allow_manual_journal);
    }

    public function test_5310_ppv_account_is_expense_cogs_subtype(): void
    {
        $acct = $this->acctByCode('5310');
        $this->assertNotNull($acct);
        $this->assertEquals('expense', $acct->account_type);
        $this->assertEquals('cogs', $acct->account_subtype);
        $this->assertTrue((bool) $acct->is_system);
        $this->assertFalse((bool) $acct->allow_manual_journal);
    }

    public function test_6990_sundry_expense_is_top_level_and_manual_postable(): void
    {
        $acct = $this->acctByCode('6990');
        $this->assertNotNull($acct);
        $this->assertEquals('expense', $acct->account_type);
        $this->assertEquals('opex', $acct->account_subtype);
        $this->assertNull($acct->parent_id, '6990 must be top-level (no parent).');
        $this->assertTrue((bool) $acct->allow_manual_journal);
        // 6990 is NOT the same as 6010 (Vet Medicine) — guards against
        // the Phase 3b misroute being reintroduced.
        $this->assertNotEquals('Veterinary Medicine and Vaccines', $acct->name);
    }

    public function test_1225_feed_wip_account_seeded(): void
    {
        $acct = $this->acctByCode('1225');
        $this->assertNotNull($acct);
        $this->assertEquals('Feed Work-in-Progress', $acct->name);
        $this->assertEquals($this->acctByCode('1200')->id, $acct->parent_id);
    }

    protected function acctByCode(string $code): ?Account
    {
        return Account::query()
            ->whereNull('organization_id')
            ->where('code', $code)
            ->first();
    }
}
