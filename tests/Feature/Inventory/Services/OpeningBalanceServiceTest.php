<?php

namespace Tests\Feature\Inventory\Services;

use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryOpeningBalanceImport;
use App\Models\RawMaterial;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Finance\Inventory\OpeningBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 4 — OpeningBalanceService: idempotency, atomicity, status machine.
 */
class OpeningBalanceServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected OpeningBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->service = $this->app->make(OpeningBalanceService::class);
    }

    public function test_successful_ingest_writes_movements_and_completes_audit_row(): void
    {
        $rm1 = $this->rm('Maize');
        $rm2 = $this->rm('Soya');

        $import = $this->service->ingestRawMaterials(
            rows: [
                ['raw_material_id' => $rm1->id, 'quantity' => 100.0, 'unit_cost' => 5.0],
                ['raw_material_id' => $rm2->id, 'quantity' => 50.0,  'unit_cost' => 8.0],
            ],
            cutoverDate: CarbonImmutable::parse('2027-04-30'),
            actor:       $this->user,
        );

        $this->assertEquals(InventoryOpeningBalanceImport::STATUS_COMPLETED, $import->status);
        $this->assertEquals(2, $import->row_count);
        $this->assertEquals(2, $import->persistedRowCount());

        $balance1 = InventoryBalance::query()->where('item_id', $rm1->id)->first();
        $balance2 = InventoryBalance::query()->where('item_id', $rm2->id)->first();
        $this->assertEquals('100.0000', $balance1->on_hand_qty);
        $this->assertEquals('500.0000', $balance1->on_hand_value);
        $this->assertEquals('5.000000', $balance1->weighted_avg_cost);
        $this->assertEquals('50.0000',  $balance2->on_hand_qty);
        $this->assertEquals('400.0000', $balance2->on_hand_value);
        $this->assertEquals('8.000000', $balance2->weighted_avg_cost);
    }

    public function test_resubmitting_identical_payload_returns_existing_completed_import(): void
    {
        $rm = $this->rm('Maize');
        $payload = [['raw_material_id' => $rm->id, 'quantity' => 100.0, 'unit_cost' => 5.0]];
        $cutover = CarbonImmutable::parse('2027-04-30');

        $first  = $this->service->ingestRawMaterials($payload, $cutover, $this->user);
        $second = $this->service->ingestRawMaterials($payload, $cutover, $this->user);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(InventoryOpeningBalanceImport::STATUS_COMPLETED, $second->status);

        // Only one movement persisted (the second was a no-op).
        $this->assertEquals(1, $first->fresh()->persistedRowCount());
    }

    public function test_writer_failure_flips_status_to_failed_and_rolls_back(): void
    {
        // Submit a payload where the second row references a non-existent
        // raw material. The first row's write must roll back too — atomicity.
        $rm = $this->rm('Maize');

        try {
            $this->service->ingestRawMaterials(
                rows: [
                    ['raw_material_id' => $rm->id, 'quantity' => 100.0, 'unit_cost' => 5.0],
                    ['raw_material_id' => 9999999, 'quantity' => 50.0, 'unit_cost' => 8.0],
                ],
                cutoverDate: CarbonImmutable::parse('2027-04-30'),
                actor:       $this->user,
            );
            $this->fail('Expected DomainException for missing raw material');
        } catch (\Throwable) {
            // expected
        }

        $import = InventoryOpeningBalanceImport::first();
        $this->assertNotNull($import);
        $this->assertEquals(InventoryOpeningBalanceImport::STATUS_FAILED, $import->status);
        $this->assertNotNull($import->error_message);

        // No movements should persist (atomicity).
        $this->assertEquals(0, $import->persistedRowCount());
        $this->assertEquals(0, InventoryBalance::count());
    }

    public function test_pending_or_failed_import_blocks_retry_under_same_hash(): void
    {
        // Manually plant a failed import with a known hash, then attempt
        // an ingest that produces the same hash; expect refusal.
        $rm = $this->rm('Maize');
        $payload = [['raw_material_id' => $rm->id, 'quantity' => 100.0, 'unit_cost' => 5.0]];

        try {
            $this->service->ingestRawMaterials(
                rows: [
                    ['raw_material_id' => $rm->id, 'quantity' => 100.0, 'unit_cost' => 5.0],
                    ['raw_material_id' => 9999999, 'quantity' => 50.0, 'unit_cost' => 8.0],
                ],
                cutoverDate: CarbonImmutable::parse('2027-04-30'),
                actor:       $this->user,
            );
        } catch (\Throwable) {
            // expected; flagged failed.
        }

        // The above failed payload has a different hash than this single-row
        // payload, so try a different single-row payload that's identical
        // to a hypothetical "stuck failed". We force a failure first with
        // single-row payload.

        // Replace the FAILED import with a single-row hash by re-failing
        // a fresh run with bad data of identical hash.

        // Easier: just call again with the SAME failed payload — same hash,
        // already failed → refusal.
        $this->expectException(\DomainException::class);
        $this->service->ingestRawMaterials(
            rows: [
                ['raw_material_id' => $rm->id, 'quantity' => 100.0, 'unit_cost' => 5.0],
                ['raw_material_id' => 9999999, 'quantity' => 50.0, 'unit_cost' => 8.0],
            ],
            cutoverDate: CarbonImmutable::parse('2027-04-30'),
            actor:       $this->user,
        );
    }

    public function test_validation_rejects_duplicate_ids_in_one_import(): void
    {
        $rm = $this->rm('Maize');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->ingestRawMaterials(
            rows: [
                ['raw_material_id' => $rm->id, 'quantity' => 100.0, 'unit_cost' => 5.0],
                ['raw_material_id' => $rm->id, 'quantity' => 50.0,  'unit_cost' => 6.0],
            ],
            cutoverDate: CarbonImmutable::parse('2027-04-30'),
            actor:       $this->user,
        );
    }

    public function test_archived_import_does_not_block_retry(): void
    {
        $rm = $this->rm('Maize');

        // Plant an archived import by direct insert.
        InventoryOpeningBalanceImport::create([
            'organization_id'    => $this->org->id,
            'item_kind'          => 'raw_material',
            'import_mode'        => 'manual',
            'import_hash'        => hash('sha256', json_encode([
                ['quantity' => 100.0, 'raw_material_id' => $rm->id, 'unit_cost' => 5.0],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'cutover_date'       => '2027-04-30',
            'row_count'          => 1,
            'status'             => InventoryOpeningBalanceImport::STATUS_ARCHIVED,
            'performed_by_user_id' => $this->user->id,
        ]);

        // A fresh ingest with the same canonical payload should now proceed.
        $import = $this->service->ingestRawMaterials(
            rows: [['raw_material_id' => $rm->id, 'quantity' => 100.0, 'unit_cost' => 5.0]],
            cutoverDate: CarbonImmutable::parse('2027-04-30'),
            actor:       $this->user,
        );

        $this->assertEquals(InventoryOpeningBalanceImport::STATUS_COMPLETED, $import->status);
    }

    public function test_finished_feed_ingest_works_independently(): void
    {
        $ft = FeedType::create(['name' => 'Grower Feed']);

        $import = $this->service->ingestFinishedFeed(
            rows: [['feed_type_id' => $ft->id, 'quantity' => 200.0, 'unit_cost' => 7.5]],
            cutoverDate: CarbonImmutable::parse('2027-04-30'),
            actor:       $this->user,
        );

        $this->assertEquals(InventoryOpeningBalanceImport::STATUS_COMPLETED, $import->status);

        $balance = InventoryBalance::query()
            ->where('item_kind', 'finished_feed')
            ->where('item_id', $ft->id)
            ->first();
        $this->assertEquals('200.0000',  $balance->on_hand_qty);
        $this->assertEquals('1500.0000', $balance->on_hand_value);
        $this->assertEquals('7.500000',  $balance->weighted_avg_cost);
    }

    protected function rm(string $name): RawMaterial
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
