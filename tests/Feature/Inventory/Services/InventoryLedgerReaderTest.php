<?php

namespace Tests\Feature\Inventory\Services;

use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\RawMaterial;
use App\Services\Finance\Inventory\InventoryLedgerReader;
use App\Services\Finance\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class InventoryLedgerReaderTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected InventoryService $service;
    protected InventoryLedgerReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->service = $this->app->make(InventoryService::class);
        $this->reader  = $this->app->make(InventoryLedgerReader::class);
    }

    public function test_total_value_sums_per_kind(): void
    {
        $rm = $this->mkRm();
        $ft = FeedType::create(['name' => 'Grower Feed']);

        $this->service->recordReceive(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id,
            100.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1,
        );
        $this->service->recordFeedProduction(
            feedTypeId: $ft->id, quantity: 200.0, totalCost: 1500.0, feedBatchId: 1,
        );

        $totals = $this->reader->totalValue();
        $this->assertEquals('500.0000', $totals['raw_material_value']);
        $this->assertEquals('1500.0000', $totals['finished_feed_value']);
        $this->assertEquals('2000.0000', $totals['total_value']);
    }

    public function test_summary_returns_per_item_rows(): void
    {
        $rm1 = $this->mkRm('Maize');
        $rm2 = $this->mkRm('Soya');

        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm1->id, 50.0, 4.0, InventoryMovement::SOURCE_STOCK_LOG, 1);
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm2->id, 30.0, 6.0, InventoryMovement::SOURCE_STOCK_LOG, 2);

        $rows = $this->reader->summary();
        $this->assertCount(2, $rows);

        $byName = $rows->keyBy('item_name');
        $this->assertEquals('50.0000', $byName[$rm1->name]['on_hand_qty']);
        $this->assertEquals('30.0000', $byName[$rm2->name]['on_hand_qty']);
    }

    public function test_summary_for_item_returns_null_if_no_movements(): void
    {
        $rm = $this->mkRm();
        $this->assertNull(
            $this->reader->summaryForItem('raw_material', $rm->id),
        );
    }

    public function test_item_value_at_returns_point_in_time(): void
    {
        $rm = $this->mkRm();
        $t1 = CarbonImmutable::parse('2027-05-01 09:00:00');
        $t2 = CarbonImmutable::parse('2027-05-02 09:00:00');

        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1, occurredAt: $t1);
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 7.0, InventoryMovement::SOURCE_STOCK_LOG, 2, occurredAt: $t2);

        // At t1 — only first receipt counts.
        $this->assertEquals('500.0000', $this->reader->itemValueAt('raw_material', $rm->id, $t1));
        // Just before t2 — still only first.
        $this->assertEquals('500.0000', $this->reader->itemValueAt('raw_material', $rm->id, $t2->subMinute()));
        // At/after t2 — both receipts counted.
        $this->assertEquals('1200.0000', $this->reader->itemValueAt('raw_material', $rm->id, $t2));
    }

    protected function mkRm(string $name = 'Maize'): RawMaterial
    {
        return RawMaterial::create([
            'name' => $name . '-' . uniqid(),
            'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);
    }
}
