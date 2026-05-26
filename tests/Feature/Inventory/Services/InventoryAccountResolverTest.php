<?php

namespace Tests\Feature\Inventory\Services;

use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\RawMaterial;
use App\Services\Finance\Inventory\InventoryAccountResolutionException;
use App\Services\Finance\Inventory\InventoryAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 4 — InventoryAccountResolver fallback chain.
 *
 *   1. Per-item override (raw_material.inventory_account_id)
 *   2. Category mapping
 *   3. Default mapping
 *   4. COA code from config
 */
class InventoryAccountResolverTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected InventoryAccountResolver $resolver;
    protected Account $inv1210;
    protected Account $inv1220;
    protected Account $grIr2050;
    protected Account $susp2055;
    protected Account $adj5300;
    protected Account $ppv5310;
    protected Account $cogs5100;
    protected Account $catOverride;
    protected Account $itemOverride;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->inv1210 = $this->makeAcct('1210', 'Raw Materials Inventory', Account::TYPE_ASSET);
        $this->inv1220 = $this->makeAcct('1220', 'Finished Feed Inventory', Account::TYPE_ASSET);
        $this->grIr2050 = $this->makeAcct('2050', 'GR/IR', Account::TYPE_LIABILITY);
        $this->susp2055 = $this->makeAcct('2055', 'Inventory Suspense', Account::TYPE_LIABILITY);
        $this->adj5300 = $this->makeAcct('5300', 'Inventory Adjustments', Account::TYPE_EXPENSE);
        $this->ppv5310 = $this->makeAcct('5310', 'PPV', Account::TYPE_EXPENSE);
        $this->cogs5100 = $this->makeAcct('5100', 'Cost of Feed Sold', Account::TYPE_EXPENSE);
        $this->catOverride = $this->makeAcct('1211', 'RM Grain Inventory', Account::TYPE_ASSET);
        $this->itemOverride = $this->makeAcct('1212', 'RM Special Inventory', Account::TYPE_ASSET);

        $this->resolver = $this->app->make(InventoryAccountResolver::class);
    }

    public function test_item_override_wins(): void
    {
        $rm = RawMaterial::create([
            'name' => 'Special', 'unit' => 'kg',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
            'inventory_account_id' => $this->itemOverride->id,
        ]);

        $this->assertEquals(
            $this->itemOverride->id,
            $this->resolver->inventoryAccountForRawMaterial($rm),
        );
    }

    public function test_category_mapping_used_when_no_item_override(): void
    {
        InventoryAccountMapping::create([
            'organization_id'         => $this->org->id,
            'item_kind'               => 'raw_material',
            'category'                => 'grain',
            'is_default'              => false,
            'inventory_account_id'    => $this->catOverride->id,
        ]);

        $rm = RawMaterial::create([
            'name' => 'Maize', 'unit' => 'kg', 'category' => 'grain',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);

        $this->resolver->flushCaches();
        $this->assertEquals(
            $this->catOverride->id,
            $this->resolver->inventoryAccountForRawMaterial($rm),
        );
    }

    public function test_default_mapping_used_when_category_unmapped(): void
    {
        // Default mapping for raw_material kind.
        InventoryAccountMapping::create([
            'organization_id'         => $this->org->id,
            'item_kind'               => 'raw_material',
            'category'                => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'              => true,
            'inventory_account_id'    => $this->inv1210->id,
        ]);

        $rm = RawMaterial::create([
            'name' => 'Soya', 'unit' => 'kg', 'category' => 'protein',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);

        $this->resolver->flushCaches();
        $this->assertEquals(
            $this->inv1210->id,
            $this->resolver->inventoryAccountForRawMaterial($rm),
        );
    }

    public function test_coa_fallback_used_when_no_mappings_exist(): void
    {
        $rm = RawMaterial::create([
            'name' => 'Unmapped', 'unit' => 'kg',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);

        // Resolver should fall through to config('finance.inventory.fallback_account_codes.raw_material.inventory') = 1210.
        $this->resolver->flushCaches();
        $this->assertEquals(
            $this->inv1210->id,
            $this->resolver->inventoryAccountForRawMaterial($rm),
        );
    }

    public function test_config_override_swings_fallback_code(): void
    {
        // Create an alternative inventory account and point the config at it.
        $alt = $this->makeAcct('1213', 'Alt Inventory', Account::TYPE_ASSET);

        config()->set(
            'finance.inventory.fallback_account_codes.raw_material.inventory',
            '1213',
        );

        $rm = RawMaterial::create([
            'name' => 'NoMapping', 'unit' => 'kg',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);

        $this->resolver->flushCaches();
        $this->assertEquals($alt->id, $this->resolver->inventoryAccountForRawMaterial($rm));
    }

    public function test_missing_coa_code_throws_resolution_exception(): void
    {
        config()->set('finance.inventory.fallback_account_codes.raw_material.inventory', '9999');

        $rm = RawMaterial::create([
            'name' => 'X', 'unit' => 'kg',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);

        $this->resolver->flushCaches();
        $this->expectException(InventoryAccountResolutionException::class);
        $this->resolver->inventoryAccountForRawMaterial($rm);
    }

    public function test_gr_ir_account_resolves_through_default(): void
    {
        InventoryAccountMapping::create([
            'organization_id'  => $this->org->id,
            'item_kind'        => 'raw_material',
            'category'         => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'       => true,
            'gr_ir_account_id' => $this->grIr2050->id,
        ]);

        $rm = RawMaterial::create([
            'name' => 'X', 'unit' => 'kg',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);

        $this->resolver->flushCaches();
        $this->assertEquals($this->grIr2050->id, $this->resolver->grIrAccountForRawMaterial($rm));
    }

    public function test_feed_type_uses_finished_feed_default_mapping(): void
    {
        InventoryAccountMapping::create([
            'organization_id'       => $this->org->id,
            'item_kind'             => 'finished_feed',
            'category'              => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'            => true,
            'inventory_account_id'  => $this->inv1220->id,
            'cogs_account_id'       => $this->cogs5100->id,
        ]);

        $ft = FeedType::create([
            'name' => 'Grower Feed',
        ]);

        $this->resolver->flushCaches();
        $this->assertEquals($this->inv1220->id, $this->resolver->inventoryAccountForFeedType($ft));
        $this->assertEquals($this->cogs5100->id, $this->resolver->cogsAccountForFeedType($ft));
    }

    protected function makeAcct(string $code, string $name, string $type): Account
    {
        return Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => $code],
            [
                'name'                 => $name,
                'account_type'         => $type,
                'currency_code'        => 'GHS',
                'is_active'            => true,
                'allow_manual_journal' => true,
            ],
        );
    }
}
