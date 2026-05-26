<?php

namespace Tests\Feature\Costing\Services;

use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Group;
use App\Services\Finance\Costing\OpeningPigPoolService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class OpeningPigPoolServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected OpeningPigPoolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->service = $this->app->make(OpeningPigPoolService::class);
    }

    public function test_successful_ingest_completes_and_writes_movements(): void
    {
        $g1 = $this->mkGroup('Nursery A');
        $g2 = $this->mkGroup('Grower B');

        $import = $this->service->ingest(
            rows: [
                ['group_id' => $g1->id, 'pig_count' => 30, 'total_weight_kg' => 600,  'total_value' => 1500],
                ['group_id' => $g2->id, 'pig_count' => 50, 'total_weight_kg' => 3000, 'total_value' => 8000],
            ],
            cutoverDate: CarbonImmutable::parse('2027-04-30'),
            actor:       $this->user,
        );

        $this->assertEquals(GroupOpeningPoolImport::STATUS_COMPLETED, $import->status);
        $this->assertEquals(2, $import->row_count);

        $p1 = GroupCostPool::query()->where('group_id', $g1->id)->first();
        $p2 = GroupCostPool::query()->where('group_id', $g2->id)->first();
        $this->assertEquals(30,        $p1->on_hand_pig_count);
        $this->assertEquals('600.0000', $p1->total_weight_kg);
        $this->assertEquals('1500.0000', $p1->total_carrying_value);
        $this->assertEquals(50,        $p2->on_hand_pig_count);
        $this->assertEquals('3000.0000', $p2->total_weight_kg);
        $this->assertEquals('8000.0000', $p2->total_carrying_value);
    }

    public function test_resubmit_identical_payload_returns_existing_completed_import(): void
    {
        $g = $this->mkGroup();
        $rows = [['group_id' => $g->id, 'pig_count' => 10, 'total_weight_kg' => 200, 'total_value' => 500]];
        $cutover = CarbonImmutable::parse('2027-04-30');

        $first  = $this->service->ingest($rows, $cutover, $this->user);
        $second = $this->service->ingest($rows, $cutover, $this->user);

        $this->assertEquals($first->id, $second->id);
        // Only one set of movements persisted.
        $this->assertEquals(1, GroupCostMovement::query()->where('group_id', $g->id)->count());
    }

    public function test_failed_import_blocks_retry_under_same_hash(): void
    {
        $g = $this->mkGroup();
        $rows = [
            ['group_id' => $g->id,    'pig_count' => 10, 'total_weight_kg' => 100, 'total_value' => 500],
            ['group_id' => 999999999, 'pig_count' => 5,  'total_weight_kg' => 50,  'total_value' => 200], // missing
        ];
        $cutover = CarbonImmutable::parse('2027-04-30');

        try {
            $this->service->ingest($rows, $cutover, $this->user);
            $this->fail('Expected DomainException');
        } catch (\DomainException) {
            // expected
        }

        // Same payload again — should refuse.
        $this->expectException(\DomainException::class);
        $this->service->ingest($rows, $cutover, $this->user);
    }

    public function test_atomicity_rolls_back_first_row_on_second_row_failure(): void
    {
        $g = $this->mkGroup();

        try {
            $this->service->ingest(
                rows: [
                    ['group_id' => $g->id,    'pig_count' => 10, 'total_weight_kg' => 100, 'total_value' => 500],
                    ['group_id' => 999999999, 'pig_count' => 5,  'total_weight_kg' => 50,  'total_value' => 200],
                ],
                cutoverDate: CarbonImmutable::parse('2027-04-30'),
                actor:       $this->user,
            );
        } catch (\Throwable) {}

        // Atomicity: first row's movement should NOT have persisted.
        $this->assertEquals(0, GroupCostMovement::query()->where('group_id', $g->id)->count());
        // But the audit row IS persisted (outside the writer transaction).
        $import = GroupOpeningPoolImport::first();
        $this->assertNotNull($import);
        $this->assertEquals(GroupOpeningPoolImport::STATUS_FAILED, $import->status);
    }

    public function test_validation_rejects_duplicate_group_ids_in_one_import(): void
    {
        $g = $this->mkGroup();
        $this->expectException(\InvalidArgumentException::class);
        $this->service->ingest(
            rows: [
                ['group_id' => $g->id, 'pig_count' => 10, 'total_weight_kg' => 100, 'total_value' => 500],
                ['group_id' => $g->id, 'pig_count' => 20, 'total_weight_kg' => 300, 'total_value' => 800],
            ],
            cutoverDate: CarbonImmutable::parse('2027-04-30'),
            actor:       $this->user,
        );
    }

    public function test_validation_rejects_negative_pig_count(): void
    {
        $g = $this->mkGroup();
        $this->expectException(\InvalidArgumentException::class);
        $this->service->ingest(
            rows: [['group_id' => $g->id, 'pig_count' => -1, 'total_weight_kg' => 100, 'total_value' => 500]],
            cutoverDate: CarbonImmutable::parse('2027-04-30'),
            actor:       $this->user,
        );
    }

    // -------- helpers --------

    protected function mkGroup(string $name = 'Group'): Group
    {
        return Group::create([
            'name'        => $name . ' ' . uniqid(),
            'type'        => 'pen',
            'description' => null,
        ]);
    }
}
