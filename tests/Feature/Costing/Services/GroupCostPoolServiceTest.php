<?php

namespace Tests\Feature\Costing\Services;

use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Group;
use App\Services\Finance\Costing\DuplicateGroupCostMovementException;
use App\Services\Finance\Costing\GroupCostPoolService;
use App\Services\Finance\Costing\InsufficientPoolException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 5 — pool math correctness, idempotency, concurrency.
 *
 * The reasoning the team will hit hardest in audit is the per-kg
 * allocation formula. These tests pin it down with concrete numbers.
 */
class GroupCostPoolServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected GroupCostPoolService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->service = $this->app->make(GroupCostPoolService::class);
    }

    // ============================================================
    // Opening
    // ============================================================

    public function test_opening_sets_initial_state_and_per_kg_basis(): void
    {
        $group  = $this->mkGroup();
        $import = $this->mkImport();

        $m = $this->service->recordOpening(
            groupId:        $group->id,
            pigCount:       50,
            totalWeight:    '2000',
            totalValue:     '5000',
            import:         $import,
            importRowIndex: 1,
        );

        $this->assertEquals(50,        $m->pig_count_after);
        $this->assertEquals('2000.0000', $m->weight_after);
        $this->assertEquals('5000.0000', $m->value_after);
        $this->assertEquals('2.500000', $m->event_per_kg_basis); // 5000 / 2000

        $pool = GroupCostPool::query()->where('group_id', $group->id)->first();
        $this->assertEquals(50,         $pool->on_hand_pig_count);
        $this->assertEquals('5000.0000', $pool->total_carrying_value);
        $this->assertEquals('2000.0000', $pool->total_weight_kg);
        $this->assertNotNull($pool->opened_at);
    }

    public function test_opening_refused_when_pool_has_movements(): void
    {
        $group  = $this->mkGroup();
        $import1 = $this->mkImport();

        $this->service->recordOpening($group->id, 50, '2000', '5000', $import1, 1);

        $import2 = $this->mkImport();
        $this->expectException(\DomainException::class);
        $this->service->recordOpening($group->id, 30, '1000', '3000', $import2, 1);
    }

    // ============================================================
    // Feed issuance
    // ============================================================

    public function test_feed_issue_adds_value_only(): void
    {
        [$group, $pool] = $this->seedOpenPool(50, '2000', '5000');

        $m = $this->service->recordFeedIssue(
            groupId:     $group->id,
            totalCost:   '1500',
            feedIssueId: 1,
        );

        // Feed brings value but not weight or count.
        $this->assertEquals(0,         $m->pig_count_delta);
        $this->assertEquals('1500.0000', $m->value_delta);
        $this->assertEquals('0.0000',  $m->weight_delta);
        $this->assertEquals('6500.0000', $m->value_after);   // 5000 + 1500
        $this->assertEquals('2000.0000', $m->weight_after);
        $this->assertEquals(50,         $m->pig_count_after);

        // Per-kg basis BEFORE feed issue is captured for audit (the
        // pre-issuance basis, not the post-issuance one — that's the
        // basis that would have been used if a sale had happened at
        // this instant).
        $this->assertEquals('2.500000', $m->event_per_kg_basis);
    }

    // ============================================================
    // Sale COGS allocation
    // ============================================================

    public function test_sale_cogs_allocates_proportional_to_weight(): void
    {
        [$group, $pool] = $this->seedOpenPool(50, '2000', '5000');
        // Per-kg basis = 2.5. A 100kg pig should absorb 250.

        $m = $this->service->recordSaleCogs(
            groupId:   $group->id,
            pigWeight: '100',
            pigSaleId: 1,
        );

        $this->assertEquals(-1,         $m->pig_count_delta);
        $this->assertEquals('-100.0000', $m->weight_delta);
        $this->assertEquals('-250.0000', $m->value_delta);   // 100 * 2.5
        $this->assertEquals(49,         $m->pig_count_after);
        $this->assertEquals('1900.0000', $m->weight_after);  // 2000 - 100
        $this->assertEquals('4750.0000', $m->value_after);   // 5000 - 250
        $this->assertEquals('2.500000', $m->event_per_kg_basis);
    }

    public function test_sale_cogs_with_subsequent_feed_uses_new_basis(): void
    {
        [$group, $pool] = $this->seedOpenPool(50, '2000', '5000');

        // First feed brings value 3000 → pool now 8000 value, 2000 weight,
        // new per-kg = 4.0.
        $this->service->recordFeedIssue($group->id, '3000', 1);

        // Sell a 100kg pig: should now cost 400.
        $m = $this->service->recordSaleCogs($group->id, '100', 1);
        $this->assertEquals('-400.0000', $m->value_delta);
        $this->assertEquals('4.000000',  $m->event_per_kg_basis);
    }

    public function test_sale_cogs_blocked_when_pool_value_insufficient(): void
    {
        [$group, $pool] = $this->seedOpenPool(50, '100', '50');
        // basis = 0.5; selling all 100kg at once would need value 50 — exactly equal.
        // Selling MORE than 100kg should fail on weight first.
        $this->expectException(InsufficientPoolException::class);
        $this->service->recordSaleCogs($group->id, '200', 1);
    }

    public function test_sale_cogs_from_empty_pool_returns_zero_allocation(): void
    {
        $group = $this->mkGroup();
        // No opening, no movements.

        $m = $this->service->recordSaleCogs(
            groupId:    $group->id,
            pigWeight:  '50',
            pigSaleId:  1,
            allowOverdraw: true,  // pool has nothing; allow the no-op.
        );

        // Per-kg basis = 0 → cogs = 0.
        $this->assertEquals('0.0000', $m->value_delta);
        // Weight delta still records the pig (audit).
        $this->assertEquals('-50.0000', $m->weight_delta);
    }

    // ============================================================
    // Mortality writeoff
    // ============================================================

    public function test_mortality_writeoff_uses_same_math_as_sale(): void
    {
        [$group, $pool] = $this->seedOpenPool(50, '2000', '5000');
        // basis = 2.5. A 80kg dead pig writes off 200.

        $m = $this->service->recordMortalityWriteoff(
            groupId:    $group->id,
            pigWeight:  '80',
            pigDeathId: 1,
        );

        $this->assertEquals(-1,         $m->pig_count_delta);
        $this->assertEquals('-200.0000', $m->value_delta);
        $this->assertEquals('-80.0000', $m->weight_delta);
        $this->assertEquals(GroupCostMovement::EVENT_MORTALITY_WRITEOFF, $m->event_type);
    }

    public function test_mortality_writeoff_auto_closes_pool_at_zero(): void
    {
        // 1 pig, 100kg, 200 value, basis 2.0
        [$group, $pool] = $this->seedOpenPool(1, '100', '200');

        $this->service->recordMortalityWriteoff($group->id, '100', 1);

        $pool->refresh();
        $this->assertEquals(0,        $pool->on_hand_pig_count);
        $this->assertEquals('0.0000', $pool->total_carrying_value);
        $this->assertEquals('0.0000', $pool->total_weight_kg);
        $this->assertNotNull($pool->closed_at);
    }

    // ============================================================
    // Transfers
    // ============================================================

    public function test_transfer_out_and_in_preserve_value_across_pools(): void
    {
        [$src, $srcPool] = $this->seedOpenPool(50, '2000', '5000');
        $dest = $this->mkGroup('Destination');

        // Transfer one 80kg pig: should withdraw 200 from src, add 200 to dest.
        $out = $this->service->recordTransferOut(
            groupId:    $src->id,
            pigWeight:  '80',
            transferId: 1,
        );

        $this->assertEquals('-200.0000', $out->value_delta);
        $this->assertEquals('-80.0000',  $out->weight_delta);

        $in = $this->service->recordTransferIn(
            groupId:           $dest->id,
            pigWeight:         '80',
            transferredValue:  '200',
            transferId:        1,
        );

        $this->assertEquals('200.0000', $in->value_delta);
        $this->assertEquals('80.0000',  $in->weight_delta);
        $this->assertEquals(1,          $in->pig_count_after);

        // The OUT and IN sides have different source_ids despite same
        // transferId (because of × 10 + side encoding).
        $this->assertNotEquals($out->source_id, $in->source_id);
    }

    // ============================================================
    // Idempotency
    // ============================================================

    public function test_duplicate_feed_issue_source_throws_typed_exception(): void
    {
        [$group, $pool] = $this->seedOpenPool(50, '2000', '5000');
        $first = $this->service->recordFeedIssue($group->id, '500', 999);

        try {
            $this->service->recordFeedIssue($group->id, '999', 999);
            $this->fail('Expected DuplicateGroupCostMovementException');
        } catch (DuplicateGroupCostMovementException $e) {
            $this->assertEquals(GroupCostMovement::SOURCE_FEED_ISSUE, $e->sourceType);
            $this->assertEquals(999, $e->sourceId);
            $this->assertEquals($first->id, $e->existingMovementId);
        }
    }

    // ============================================================
    // Clamp threshold (parity with Phase 4)
    // ============================================================

    public function test_clamp_threshold_drains_tiny_residual_to_zero(): void
    {
        [$group, $pool] = $this->seedOpenPool(2, '10', '20');
        // basis = 2.0

        // Mortality with a weight ALMOST equal to half: 4.99995kg.
        // value_delta = 9.9999 (truncated to scale 4).
        // Then sell the 'other' 5.00005kg pig: removes 10.0001 from a
        // pool that has ~10.0001 remaining. Tiny residuals expected.
        $this->service->recordMortalityWriteoff(
            groupId:    $group->id,
            pigWeight:  '4.99995',
            pigDeathId: 1,
        );

        $m = $this->service->recordSaleCogs(
            groupId:    $group->id,
            pigWeight:  '5.00005',
            pigSaleId:  1,
            allowOverdraw: true,  // tiny residual may force overdraw
        );

        // Pool should now be ~0 / ~0. Clamp should pull it exactly to 0.
        $pool = GroupCostPool::query()->where('group_id', $group->id)->first();
        $this->assertEquals('0.0000', $pool->total_carrying_value);
        $this->assertEquals('0.0000', $pool->total_weight_kg);
        $this->assertEquals(0, $pool->on_hand_pig_count);
    }

    // ============================================================
    // Helpers
    // ============================================================

    protected function mkGroup(string $name = 'Group A'): Group
    {
        return Group::create([
            'name'        => $name . ' ' . uniqid(),
            'type'        => 'pen',
            'description' => 'Test group',
        ]);
    }

    protected function mkImport(): GroupOpeningPoolImport
    {
        return GroupOpeningPoolImport::create([
            'organization_id'      => $this->org->id,
            'import_mode'          => 'manual',
            'import_hash'          => bin2hex(random_bytes(32)),
            'cutover_date'         => '2027-04-30',
            'row_count'            => 1,
            'status'               => 'pending',
            'performed_by_user_id' => $this->user->id,
        ]);
    }

    /**
     * @return array{0: Group, 1: GroupCostPool}
     */
    protected function seedOpenPool(int $count, string $weight, string $value): array
    {
        $group  = $this->mkGroup();
        $import = $this->mkImport();
        $this->service->recordOpening($group->id, $count, $weight, $value, $import, 1);
        $pool = GroupCostPool::query()->where('group_id', $group->id)->first();
        return [$group, $pool];
    }
}
