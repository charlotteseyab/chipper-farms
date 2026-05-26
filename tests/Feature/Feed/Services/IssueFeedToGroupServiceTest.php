<?php

namespace Tests\Feature\Feed\Services;

use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\Group;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Finance\Inventory\InsufficientInventoryException;
use App\Services\Finance\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 5 — verify the orchestration service writes both ledgers
 * atomically.
 *
 * The contract under test:
 *   - Reduces finished feed inventory at WAC.
 *   - Increases the group pool by total_cost.
 *   - Both writes succeed or both roll back.
 *   - The FeedIssued domain event is enqueued only on success.
 */
class IssueFeedToGroupServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected IssueFeedToGroupService $service;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->service          = $this->app->make(IssueFeedToGroupService::class);
        $this->inventoryService = $this->app->make(InventoryService::class);
    }

    public function test_issuance_decrements_feed_inventory_and_increases_pool(): void
    {
        $group    = $this->mkGroup();
        $feedType = FeedType::create(['name' => 'Grower Feed']);

        // Seed: 1000 kg of finished feed at WAC 5.00.
        $this->seedFinishedFeed($feedType, 1000.0, 5.0);

        $issue = $this->service->execute(
            actor: $this->user,
            data: [
                'group_id'     => $group->id,
                'feed_type_id' => $feedType->id,
                'quantity_kg'  => 50.0,
                'issued_at'    => null,
                'reference'    => 'DN-001',
                'notes'        => null,
            ],
        );

        // Feed issue row written.
        $this->assertNotNull($issue);
        $this->assertEquals('50.0000',   (string) $issue->quantity_kg);
        $this->assertEquals('5.000000',  (string) $issue->unit_cost_at_issuance);
        $this->assertEquals('250.0000',  (string) $issue->total_cost);
        $this->assertEquals(FeedIssue::POSTING_PENDING, $issue->posting_status);

        // Finished feed inventory dropped.
        $balance = InventoryBalance::query()
            ->where('item_kind', InventoryBalance::ITEM_KIND_FINISHED_FEED)
            ->where('item_id',   $feedType->id)
            ->first();
        $this->assertEquals('950.0000',  (string) $balance->on_hand_qty);
        $this->assertEquals('4750.0000', (string) $balance->on_hand_value);
        $this->assertEquals('5.000000',  (string) $balance->weighted_avg_cost);  // unchanged

        // Group cost pool increased.
        $pool = GroupCostPool::query()->where('group_id', $group->id)->first();
        $this->assertEquals('250.0000', (string) $pool->total_carrying_value);
        $this->assertEquals('0.0000',   (string) $pool->total_weight_kg);  // feed alone, no weight
        $this->assertEquals(0,          $pool->on_hand_pig_count);

        // One movement on each ledger, both linked to feed_issue.
        $invMovement = InventoryMovement::query()
            ->where('source_type', InventoryMovement::SOURCE_FEED_ISSUE)
            ->where('source_id', $issue->id)
            ->first();
        $this->assertNotNull($invMovement);

        $poolMovement = GroupCostMovement::query()
            ->where('source_type', GroupCostMovement::SOURCE_FEED_ISSUE)
            ->where('source_id', $issue->id)
            ->first();
        $this->assertNotNull($poolMovement);
    }

    public function test_atomicity_rolls_back_pool_movement_on_inventory_overdraw(): void
    {
        $group    = $this->mkGroup();
        $feedType = FeedType::create(['name' => 'Grower Feed']);

        // Only 10 kg in stock — try to issue 50.
        $this->seedFinishedFeed($feedType, 10.0, 5.0);

        try {
            $this->service->execute(
                actor: $this->user,
                data: [
                    'group_id'     => $group->id,
                    'feed_type_id' => $feedType->id,
                    'quantity_kg'  => 50.0,
                ],
            );
            $this->fail('Expected InsufficientInventoryException');
        } catch (InsufficientInventoryException) {
            // expected
        }

        // No feed_issues row.
        $this->assertEquals(0, FeedIssue::count());
        // No pool movement.
        $this->assertEquals(0, GroupCostMovement::query()->where('group_id', $group->id)->count());
        // Inventory unchanged.
        $balance = InventoryBalance::query()->where('item_id', $feedType->id)->first();
        $this->assertEquals('10.0000', (string) $balance->on_hand_qty);
    }

    public function test_zero_wac_issuance_records_audit_with_zero_cost(): void
    {
        $group    = $this->mkGroup();
        $feedType = FeedType::create(['name' => 'Grower Feed']);

        // Seed finished feed at zero unit cost (edge case — early opening,
        // or feed produced from donated raw materials).
        $this->seedFinishedFeed($feedType, 1000.0, 0.0);

        $issue = $this->service->execute(
            actor: $this->user,
            data: [
                'group_id'     => $group->id,
                'feed_type_id' => $feedType->id,
                'quantity_kg'  => 50.0,
            ],
        );

        $this->assertEquals('0.0000', (string) $issue->total_cost);
        $this->assertEquals('0.000000', (string) $issue->unit_cost_at_issuance);

        // Pool got zero value but the audit row exists.
        $movement = GroupCostMovement::query()
            ->where('source_type', GroupCostMovement::SOURCE_FEED_ISSUE)
            ->where('source_id', $issue->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals('0.0000', (string) $movement->value_delta);
    }

    public function test_zero_quantity_rejected(): void
    {
        $group    = $this->mkGroup();
        $feedType = FeedType::create(['name' => 'X']);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->execute(
            actor: $this->user,
            data: [
                'group_id'     => $group->id,
                'feed_type_id' => $feedType->id,
                'quantity_kg'  => 0.0,
            ],
        );
    }

    // -------- helpers --------

    protected function mkGroup(): Group
    {
        return Group::create(['name' => 'G-' . uniqid(), 'type' => 'pen']);
    }

    protected function seedFinishedFeed(FeedType $ft, float $qty, float $unitCost): void
    {
        $this->inventoryService->recordFeedProduction(
            feedTypeId:  $ft->id,
            quantity:    $qty,
            totalCost:   $qty * $unitCost,
            feedBatchId: 9000 + $ft->id,
        );
    }
}
