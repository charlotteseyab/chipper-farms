<?php

namespace Tests\Feature\Inventory\Services;

use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\RawMaterial;
use App\Services\Finance\Inventory\DuplicateMovementException;
use App\Services\Finance\Inventory\InsufficientInventoryException;
use App\Services\Finance\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 4 — WAC math correctness, idempotency, concurrency-safe writes.
 *
 * Each scenario builds a known sequence of movements and asserts the
 * balance row + ledger row state after each step.
 */
class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->service = $this->app->make(InventoryService::class);
    }

    // ============================================================
    // RECEIVE
    // ============================================================

    public function test_receive_into_empty_ledger_sets_wac_to_receipt_price(): void
    {
        $rm = $this->makeRawMaterial();

        $m = $this->service->recordReceive(
            itemKind:   InventoryMovement::ITEM_KIND_RAW_MATERIAL,
            itemId:     $rm->id,
            quantity:   100.0,
            unitCost:   5.0,
            sourceType: InventoryMovement::SOURCE_STOCK_LOG,
            sourceId:   1,
            occurredAt: CarbonImmutable::parse('2027-05-01 09:00:00'),
        );

        $this->assertEquals('100.0000', $m->quantity_on_hand_after);
        $this->assertEquals('500.0000', $m->value_on_hand_after);
        $this->assertEquals('5.000000', $m->weighted_avg_cost_after);
        $this->assertEquals('5.000000', $m->event_unit_cost);

        $balance = InventoryBalance::query()->where('item_id', $rm->id)->first();
        $this->assertEquals('100.0000', $balance->on_hand_qty);
        $this->assertEquals('500.0000', $balance->on_hand_value);
        $this->assertEquals('5.000000', $balance->weighted_avg_cost);
        $this->assertEquals($m->id, $balance->last_movement_id);
    }

    public function test_second_receive_shifts_wac_per_iso_2_weighted_average(): void
    {
        $rm = $this->makeRawMaterial();

        // Receipt 1: 100 @ 5.00 → WAC 5.00, value 500
        $this->service->recordReceive(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id,
            100.0, 5.0,
            InventoryMovement::SOURCE_STOCK_LOG, 1,
        );

        // Receipt 2: 100 @ 7.00 → new value = 500 + 700 = 1200, new qty 200, WAC = 6
        $m2 = $this->service->recordReceive(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id,
            100.0, 7.0,
            InventoryMovement::SOURCE_STOCK_LOG, 2,
        );

        $this->assertEquals('200.0000', $m2->quantity_on_hand_after);
        $this->assertEquals('1200.0000', $m2->value_on_hand_after);
        $this->assertEquals('6.000000', $m2->weighted_avg_cost_after);
    }

    public function test_usage_consumes_at_current_wac_and_does_not_shift_it(): void
    {
        $rm = $this->makeRawMaterial();

        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1);
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 7.0, InventoryMovement::SOURCE_STOCK_LOG, 2);
        // WAC is now 6.00, on-hand 200, value 1200.

        $usage = $this->service->recordUsage(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id,
            50.0,
            InventoryMovement::SOURCE_FEED_BATCH_ITEM, 1,
        );

        $this->assertEquals('150.0000', $usage->quantity_on_hand_after);
        $this->assertEquals('900.0000', $usage->value_on_hand_after);
        $this->assertEquals('6.000000', $usage->weighted_avg_cost_after);
        $this->assertEquals('-50.0000', $usage->quantity_delta);
        $this->assertEquals('-300.0000', $usage->value_delta);
    }

    public function test_usage_exceeding_on_hand_is_blocked_by_default(): void
    {
        $rm = $this->makeRawMaterial();
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 10.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1);

        $this->expectException(InsufficientInventoryException::class);

        $this->service->recordUsage(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0,
            InventoryMovement::SOURCE_FEED_BATCH_ITEM, 1,
        );
    }

    public function test_usage_exceeding_on_hand_allowed_with_override(): void
    {
        $rm = $this->makeRawMaterial();
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 10.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1);

        $m = $this->service->recordUsage(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0,
            InventoryMovement::SOURCE_FEED_BATCH_ITEM, 1,
            allowNegative: true,
        );

        // On-hand goes -90 (intentional override path).
        $this->assertLessThan(0, (float) $m->quantity_on_hand_after);
    }

    public function test_clamp_threshold_drains_tiny_residual_to_zero(): void
    {
        $rm = $this->makeRawMaterial();

        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 10.00000, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1);

        // Use almost-but-not-exact 10.0; legacy float math could leave 0.00001 residual.
        $m = $this->service->recordUsage(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id,
            9.99995,
            InventoryMovement::SOURCE_FEED_BATCH_ITEM, 1,
        );

        // After this, on-hand should clamp to 0 due to the residual threshold logic.
        // The clamp threshold is 0.0001; remaining 0.00005 is well below it.
        $this->assertEquals('0.0000', $m->quantity_on_hand_after);
        $this->assertEquals('0.0000', $m->value_on_hand_after);
    }

    // ============================================================
    // ADJUSTMENT
    // ============================================================

    public function test_adjustment_add_increases_at_current_wac(): void
    {
        $rm = $this->makeRawMaterial();
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1);

        $m = $this->service->recordAdjustment(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 'add', 10.0,
            InventoryMovement::SOURCE_STOCK_LOG, 2,
        );

        $this->assertEquals('110.0000', $m->quantity_on_hand_after);
        $this->assertEquals('550.0000', $m->value_on_hand_after);
        $this->assertEquals('5.000000', $m->weighted_avg_cost_after);
        $this->assertEquals('10.0000', $m->quantity_delta);
        $this->assertEquals('50.0000', $m->value_delta);
    }

    public function test_adjustment_deduct_reduces_value_at_wac(): void
    {
        $rm = $this->makeRawMaterial();
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1);

        $m = $this->service->recordAdjustment(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 'deduct', 10.0,
            InventoryMovement::SOURCE_STOCK_LOG, 2,
        );

        $this->assertEquals('90.0000', $m->quantity_on_hand_after);
        $this->assertEquals('450.0000', $m->value_on_hand_after);
        $this->assertEquals('5.000000', $m->weighted_avg_cost_after);
    }

    public function test_adjustment_set_overwrites_qty_and_recomputes_value_at_wac(): void
    {
        $rm = $this->makeRawMaterial();
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1);

        $m = $this->service->recordAdjustment(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 'set', 80.0,
            InventoryMovement::SOURCE_STOCK_LOG, 2,
        );

        $this->assertEquals('80.0000', $m->quantity_on_hand_after);
        $this->assertEquals('400.0000', $m->value_on_hand_after);
        $this->assertEquals('5.000000', $m->weighted_avg_cost_after);
        $this->assertEquals('-20.0000', $m->quantity_delta);
        $this->assertEquals('-100.0000', $m->value_delta);
    }

    public function test_adjustment_set_higher_writes_positive_delta(): void
    {
        $rm = $this->makeRawMaterial();
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1);

        $m = $this->service->recordAdjustment(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 'set', 130.0,
            InventoryMovement::SOURCE_STOCK_LOG, 2,
        );

        $this->assertEquals('130.0000', $m->quantity_on_hand_after);
        $this->assertEquals('650.0000', $m->value_on_hand_after);
        $this->assertEquals('30.0000', $m->quantity_delta);
        $this->assertEquals('150.0000', $m->value_delta);
    }

    // ============================================================
    // FEED PRODUCTION
    // ============================================================

    public function test_feed_production_adds_finished_feed_at_effective_unit_cost(): void
    {
        $m = $this->service->recordFeedProduction(
            feedTypeId:  42,
            quantity:    100.0,
            totalCost:   750.0,
            feedBatchId: 1,
        );

        $this->assertEquals('100.0000', $m->quantity_on_hand_after);
        $this->assertEquals('750.0000', $m->value_on_hand_after);
        $this->assertEquals('7.500000', $m->weighted_avg_cost_after);
        $this->assertEquals('7.500000', $m->event_unit_cost);
        $this->assertEquals(InventoryMovement::ITEM_KIND_FINISHED_FEED, $m->item_kind);
    }

    public function test_second_feed_batch_combines_wac_with_first(): void
    {
        $this->service->recordFeedProduction(42, 100.0, 750.0, 1);  // WAC 7.50
        $m = $this->service->recordFeedProduction(42, 100.0, 850.0, 2);  // 200 qty, 1600 val, WAC 8

        $this->assertEquals('200.0000', $m->quantity_on_hand_after);
        $this->assertEquals('1600.0000', $m->value_on_hand_after);
        $this->assertEquals('8.000000', $m->weighted_avg_cost_after);
    }

    // ============================================================
    // OPENING + IDEMPOTENCY
    // ============================================================

    public function test_opening_balance_writes_initial_movement(): void
    {
        $rm = $this->makeRawMaterial();
        $import = \App\Models\Finance\InventoryOpeningBalanceImport::create([
            'organization_id'    => $this->org->id,
            'item_kind'          => 'raw_material',
            'import_mode'        => 'manual',
            'import_hash'        => str_repeat('a', 64),
            'cutover_date'       => '2027-04-30',
            'row_count'          => 1,
            'status'             => 'pending',
            'performed_by_user_id' => $this->user->id,
        ]);

        $m = $this->service->recordOpening(
            'raw_material', $rm->id, 50.0, 4.0, $import, 1,
        );

        $this->assertEquals('50.0000', $m->quantity_on_hand_after);
        $this->assertEquals('200.0000', $m->value_on_hand_after);
        $this->assertEquals('4.000000', $m->weighted_avg_cost_after);
        $this->assertEquals($import->id, $m->opening_balance_import_id);
    }

    public function test_opening_balance_refuses_when_ledger_non_empty(): void
    {
        $rm = $this->makeRawMaterial();
        $this->service->recordReceive(InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 10.0, 5.0, InventoryMovement::SOURCE_STOCK_LOG, 1);

        $import = \App\Models\Finance\InventoryOpeningBalanceImport::create([
            'organization_id'    => $this->org->id,
            'item_kind'          => 'raw_material',
            'import_mode'        => 'manual',
            'import_hash'        => str_repeat('b', 64),
            'cutover_date'       => '2027-04-30',
            'row_count'          => 1,
            'status'             => 'pending',
            'performed_by_user_id' => $this->user->id,
        ]);

        $this->expectException(\DomainException::class);
        $this->service->recordOpening('raw_material', $rm->id, 50.0, 4.0, $import, 1);
    }

    public function test_duplicate_source_throws_typed_exception(): void
    {
        $rm = $this->makeRawMaterial();
        $first = $this->service->recordReceive(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 5.0,
            InventoryMovement::SOURCE_STOCK_LOG, 999,
        );

        try {
            $this->service->recordReceive(
                InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 50.0, 7.0,
                InventoryMovement::SOURCE_STOCK_LOG, 999,
            );
            $this->fail('Expected DuplicateMovementException');
        } catch (DuplicateMovementException $e) {
            $this->assertEquals(InventoryMovement::SOURCE_STOCK_LOG, $e->sourceType);
            $this->assertEquals(999, $e->sourceId);
            $this->assertEquals($first->id, $e->existingMovementId);
        }
    }

    public function test_invalid_item_kind_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->recordReceive(
            'biological_pig',  // not in ALLOWED_ITEM_KINDS
            1, 1.0, 1.0,
            InventoryMovement::SOURCE_STOCK_LOG, 1,
        );
    }

    public function test_negative_quantity_rejected(): void
    {
        $rm = $this->makeRawMaterial();
        $this->expectException(\InvalidArgumentException::class);
        $this->service->recordReceive(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id,
            -5.0, 1.0,
            InventoryMovement::SOURCE_STOCK_LOG, 1,
        );
    }

    // ============================================================
    // Helpers
    // ============================================================

    protected function makeRawMaterial(string $name = 'Maize'): RawMaterial
    {
        return RawMaterial::create([
            'name'              => $name . '-' . uniqid(),
            'unit'              => 'kg',
            'quantity_in_stock' => 0,
            'unit_cost'         => 0,
            'reorder_level'     => 0,
        ]);
    }
}
