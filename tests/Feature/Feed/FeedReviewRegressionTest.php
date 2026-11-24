<?php

namespace Tests\Feature\Feed;

use App\Models\DomainEvent;
use App\Models\FeedingStandard;
use App\Models\FeedIssue;
use App\Models\FeedProductionPlan;
use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\Room;
use App\Models\Structure;
use App\Models\User;
use App\Services\Feed\FeedCorrectionService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedProductionPlanService;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Inventory\StockCountService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FeedReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private FeedType $feed;

    private RawMaterial $maize;

    private int $source = 810000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(10, 0));
        config(['finance.instant_posting.enabled' => false]);
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $role = Role::firstOrCreate(['name' => 'admin']);
        foreach (['manage_feed_production', 'manage_feed_issues', 'view_group_pools', 'view_feed_production', 'manage_raw_materials'] as $name) {
            $p = Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'feed']);
            $role->permissions()->syncWithoutDetaching($p->id);
        }
        $this->actor = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin', 'role_id' => $role->id, 'active_status' => true]);
        $this->actingAs($this->actor);
        $this->feed = FeedType::create(['name' => 'Review feed'])->fresh();
        $this->maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 2])->fresh();
        $this->receive('raw_material', $this->maize->id, 1000);
        $this->feed->recipes()->create(['raw_material_id' => $this->maize->id, 'quantity_per_100kg' => 100]);
    }

    private function receive($kind, $id, $qty)
    {
        DB::transaction(fn () => app(InventoryService::class)->recordReceive($kind, $id, $qty, 2, 'stock_log', ++$this->source));
    }

    private function pig()
    {
        return PigManagement::factory()->create(['date_of_birth' => now()->subDays(120)]);
    }

    private function standard()
    {
        return FeedingStandard::create(['stage' => 'grower', 'daily_kg' => 2, 'feed_type_id' => $this->feed->id, 'effective_from' => '2026-06-15', 'is_active' => true, 'stage_basis' => 'existing_age_rules']);
    }

    public function test_cancelled_plan_cannot_be_confirmed(): void
    {
        $plan = app(FeedProductionPlanService::class)->create($this->actor, ['feed_type_id' => $this->feed->id, 'production_date' => '2026-06-15', 'planned_quantity_kg' => 100, 'submission_key' => (string) Str::uuid()]);
        app(FeedCorrectionService::class)->cancelPlan($this->actor, $plan, 'No longer needed');
        $this->post(route('feed-production.plans.complete', $plan), ['batch_date' => '2026-06-15', 'finished_quantity_kg' => 100, 'actual_quantities' => [$this->maize->id => 100]])->assertSessionHas('mix_error');
        $this->assertSame('cancelled', $plan->fresh()->status);
        $this->assertNull($plan->fresh()->feed_batch_id);
        $this->assertEquals(1000, app(FeedPlanningService::class)->available($this->org->id, 'raw_material', $this->maize->id));
        $this->assertDatabaseCount('feed_batches', 0);
    }

    public function test_restock_ten_bags_prefills_ten_bags(): void
    {
        $bag = RawMaterial::create(['name' => 'Bag maize', 'unit' => 'bag', 'kg_per_unit' => 50, 'quantity_in_stock' => 0, 'reorder_level' => 10]);
        $this->get(route('purchase-requests.create', ['restock_material' => $bag->id]))->assertOk()->assertViewHas('prefillItems', fn ($items) => $items[0]['quantity'] === 10.0);
    }

    public function test_feed_retry_returns_success_after_first_save(): void
    {
        $this->receive('finished_feed', $this->feed->id, 100);
        $body = ['entries' => [['stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 80, 'submission_key' => (string) Str::uuid()]]];
        $this->post(route('feed.issues.store'), $body)->assertSessionHas('success');
        $this->post(route('feed.issues.store'), $body)->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertEquals(20, app(FeedPlanningService::class)->available($this->org->id, 'finished_feed', $this->feed->id));
        $this->assertSame(1, FeedIssue::count());
    }

    public function test_stage_filter_respects_room_filter(): void
    {
        $building = Structure::create(['name' => 'House', 'code' => 'H']);
        $empty = Room::create(['structure_id' => $building->id, 'name' => 'Empty', 'code' => 'E']);
        $pig = $this->pig();
        $this->standard();
        $this->receive('finished_feed', $this->feed->id, 100);
        app(IssueFeedToGroupService::class)->execute($this->actor, ['feeding_stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 2]);
        $rows = app(FeedPlanningService::class)->consumption($this->org->id, '2026-06-15', '2026-06-15', ['stage' => 'grower', 'room_id' => $empty->id]);
        $this->assertCount(0, $rows);
    }

    public function test_backdated_stock_count_rejects_subsequent_usage(): void
    {
        $material = RawMaterial::create(['name' => 'Count maize', 'unit' => 'kg', 'quantity_in_stock' => 0]);
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('raw_material', $material->id, 100, 2, 'stock_log', ++$this->source, occurredAt: now()->subDay()->toImmutable()));
        DB::transaction(fn () => app(InventoryService::class)->recordUsage('raw_material', $material->id, 20, 'stock_log', ++$this->source));
        $this->expectException(ValidationException::class);
        app(StockCountService::class)->record($this->actor, ['submission_key' => (string) Str::uuid(), 'counted_at' => '2026-06-14 10:00:00', 'lines' => [['item_kind' => 'raw_material', 'item_id' => $material->id, 'counted_qty' => 100]]]);
    }

    public function test_older_open_plan_remains_visible_after_thirty_newer_closed_plans(): void
    {
        $plan = app(FeedProductionPlanService::class)->create($this->actor, ['feed_type_id' => $this->feed->id, 'production_date' => '2026-06-15', 'planned_quantity_kg' => 100, 'submission_key' => (string) Str::uuid()]);
        $plan->forceFill(['created_at' => now()->subDay()])->save();
        for ($n = 0; $n < 30; $n++) {
            FeedProductionPlan::create(['feed_type_id' => $this->feed->id, 'production_date' => '2026-06-15', 'planned_quantity_kg' => 100, 'submission_key' => (string) Str::uuid(), 'recipe_snapshot' => $plan->recipe_snapshot, 'created_by' => $this->actor->id, 'status' => 'cancelled']);
        }
        $this->get(route('feed-production.index'))->assertOk()->assertViewHas('waitingPlans', fn ($plans) => $plans->contains('id', $plan->id))->assertDontSee('No plans waiting.');
        $this->assertSame('planned', $plan->fresh()->status);
    }

    public function test_backdated_count_without_later_movements_keeps_its_posting_date(): void
    {
        $material = RawMaterial::create(['name' => 'Dated maize', 'unit' => 'kg', 'quantity_in_stock' => 0]);
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('raw_material', $material->id, 100, 2, 'stock_log', ++$this->source, occurredAt: now()->subDays(2)->toImmutable()));
        $count = app(StockCountService::class)->record($this->actor, ['submission_key' => (string) Str::uuid(), 'counted_at' => '2026-06-14 10:00:00', 'lines' => [['item_kind' => 'raw_material', 'item_id' => $material->id, 'counted_qty' => 95]]]);
        $movement = InventoryMovement::findOrFail($count->lines->first()->inventory_movement_id);
        $this->assertSame('2026-06-14', $movement->occurred_at->toDateString());
        $event = DomainEvent::where('source_id', $count->lines->first()->raw_material_stock_log_id)->where('event_type', 'raw_material.adjusted')->firstOrFail();
        $this->assertSame('2026-06-14', $event->occurred_at->toDateString());
        $this->assertEquals(95, app(FeedPlanningService::class)->available($this->org->id, 'raw_material', $material->id));
    }

    public function test_stale_finished_feed_count_rolls_back_the_entire_count(): void
    {
        $material = RawMaterial::create(['name' => 'Earlier raw count', 'unit' => 'kg', 'quantity_in_stock' => 0]);
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('raw_material', $material->id, 100, 2, 'stock_log', ++$this->source, occurredAt: now()->subDays(2)->toImmutable()));
        $this->receive('finished_feed', $this->feed->id, 100);
        $response = $this->post(route('stock-counts.store'), ['submission_key' => (string) Str::uuid(), 'counted_at' => '2026-06-14 10:00:00', 'counts' => ['raw_material' => [$material->id => 95], 'finished_feed' => [$this->feed->id => 90]]]);
        $response->assertSessionHasErrors('counted_at');
        $this->assertDatabaseCount('stock_counts', 0);
        $this->assertDatabaseCount('stock_count_lines', 0);
        $this->assertEquals(100, app(FeedPlanningService::class)->available($this->org->id, 'finished_feed', $this->feed->id));
        $this->assertEquals(100, app(FeedPlanningService::class)->available($this->org->id, 'raw_material', $material->id));
    }

    public function test_restock_uses_stock_units_for_partial_bags_and_kg(): void
    {
        foreach ([['bag', 50, 2.5, 10, 8.0], ['kg', null, 2.5, 10, 7.5]] as [$unit, $factor, $stock, $reorder, $expected]) {
            $material = RawMaterial::create(['name' => 'Restock '.$unit, 'unit' => $unit, 'kg_per_unit' => $factor, 'quantity_in_stock' => $stock, 'reorder_level' => $reorder]);
            $this->get(route('purchase-requests.create', ['restock_material' => $material->id]))->assertOk()
                ->assertViewHas('prefillItems', fn ($items) => $items[0]['quantity'] === $expected)
                ->assertViewHas('prefillNotes', fn ($notes) => str_contains($notes, '10 '.$unit));
        }
    }

    public function test_retry_can_include_saved_rows_and_a_new_stage_without_rededucting(): void
    {
        $this->receive('finished_feed', $this->feed->id, 100);
        $saved = ['stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 80, 'submission_key' => (string) Str::uuid()];
        $this->post(route('feed.issues.store'), ['entries' => [$saved]])->assertSessionHas('success');
        $new = ['stage' => 'boar', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 10, 'submission_key' => (string) Str::uuid()];
        $this->post(route('feed.issues.store'), ['entries' => [$saved, $new]])->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertDatabaseCount('feed_issues', 2);
        $this->assertEquals(10, app(FeedPlanningService::class)->available($this->org->id, 'finished_feed', $this->feed->id));
    }

    public function test_conflicting_retry_is_refused_and_new_rows_roll_back(): void
    {
        $this->receive('finished_feed', $this->feed->id, 100);
        $saved = ['stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 30, 'submission_key' => (string) Str::uuid()];
        $this->post(route('feed.issues.store'), ['entries' => [$saved]])->assertSessionHas('success');
        $new = ['stage' => 'boar', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 10, 'submission_key' => (string) Str::uuid()];
        $saved['quantity_kg'] = 40;
        $this->post(route('feed.issues.store'), ['entries' => [$new, $saved]])->assertSessionHas('error');
        $this->assertDatabaseCount('feed_issues', 1);
        $this->assertEquals(70, app(FeedPlanningService::class)->available($this->org->id, 'finished_feed', $this->feed->id));
    }

    public function test_combined_report_filters_match_one_location_for_stage_and_legacy_issues(): void
    {
        $house = Structure::create(['name' => 'House A', 'code' => 'A']);
        $other = Structure::create(['name' => 'House B', 'code' => 'B']);
        $room = Room::create(['structure_id' => $house->id, 'name' => 'Room A', 'code' => 'RA']);
        $otherRoom = Room::create(['structure_id' => $other->id, 'name' => 'Room B', 'code' => 'RB']);
        $pig = $this->pig();
        $pig->update(['room_id' => $room->id]);
        $this->standard();
        $this->receive('finished_feed', $this->feed->id, 100);
        $issue = app(IssueFeedToGroupService::class)->execute($this->actor, ['feeding_stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 2]);
        $snapshot = $issue->planning_snapshot;
        $snapshot['locations'][] = ['stage' => 'grower', 'room_id' => $otherRoom->id, 'structure_id' => $other->id];
        $issue->update(['planning_snapshot' => $snapshot]);
        foreach (['grower', null] as $stage) {
            $issue->update(['feeding_stage' => $stage]);
            $planning = app(FeedPlanningService::class);
            $this->assertCount(1, $planning->consumption($this->org->id, '2026-06-15', '2026-06-15', ['stage' => 'grower', 'room_id' => $room->id, 'structure_id' => $house->id]));
            $this->assertCount(0, $planning->consumption($this->org->id, '2026-06-15', '2026-06-15', ['stage' => 'grower', 'room_id' => $room->id, 'structure_id' => $other->id]));
        }
    }

    public function test_pending_and_earlier_plans_have_independent_pagination(): void
    {
        for ($n = 0; $n < 12; $n++) {
            foreach (['planned', 'cancelled'] as $status) {
                FeedProductionPlan::create(['feed_type_id' => $this->feed->id, 'production_date' => '2026-06-15', 'planned_quantity_kg' => 100, 'submission_key' => (string) Str::uuid(), 'recipe_snapshot' => ['ingredients' => []], 'created_by' => $this->actor->id, 'status' => $status]);
            }
        }
        $this->get(route('feed-production.index', ['plans_page' => 2, 'earlier_plans_page' => 1]))->assertOk()
            ->assertViewHas('waitingPlans', fn ($plans) => $plans->currentPage() === 2 && $plans->count() === 2 && $plans->total() === 12)
            ->assertViewHas('earlierPlans', fn ($plans) => $plans->currentPage() === 1 && $plans->count() === 10 && $plans->total() === 12)
            ->assertSee('plans_page=2', false);
    }
}
