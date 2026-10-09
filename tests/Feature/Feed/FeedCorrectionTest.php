<?php

namespace Tests\Feature\Feed;

use App\Finance\Subscribers\Costing\FeedIssuedSubscriber;
use App\Finance\Subscribers\Inventory\FeedBatchProducedSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedBatch;
use App\Models\FeedIssue;
use App\Models\FeedProductionPlan;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\JournalBatch;
use App\Models\Permission;
use App\Models\RawMaterial;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedRecipeService;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Feed\ProduceFeedBatchService;
use App\Services\Finance\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class FeedCorrectionTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    private int $source = 920000;

    private FeedType $feed;

    private RawMaterial $maize;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        foreach (['1210' => 'Raw Materials', '1220' => 'Finished Feed', '1320' => 'Sows', '1360' => 'Growers'] as $code => $name) {
            Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => $code], [
                'name' => $name, 'account_type' => Account::TYPE_ASSET, 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
            ]);
        }
        InventoryAccountMapping::create([
            'organization_id' => $this->org->id, 'item_kind' => 'finished_feed', 'category' => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default' => true, 'inventory_account_id' => Account::where('organization_id', $this->org->id)->where('code', '1220')->value('id'),
        ]);
        foreach (['manage_feed_issues', 'manage_feed_production', 'view_group_pools'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'feed']);
            $this->user->role()->first()->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->feed = FeedType::create(['name' => 'Sow feed'])->fresh();
        $this->maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 2])->fresh();
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('raw_material', $this->maize->id, 1000, 2, 'stock_log', ++$this->source));
        app(FeedRecipeService::class)->save($this->user, ['feed_type_id' => $this->feed->id, 'items' => [['raw_material_id' => $this->maize->id, 'quantity_per_100kg' => 100]]]);
    }

    public function test_reversing_a_posted_feeding_restores_stock_pool_ledger_and_asset_value(): void
    {
        $this->actingAs($this->user);
        DB::transaction(fn () => app(InventoryService::class)->recordFeedProduction($this->feed->id, 100, 500, ++$this->source));
        $issue = app(IssueFeedToGroupService::class)->execute($this->user, ['feeding_stage' => 'gestating_sow', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 40]);
        app(FeedIssuedSubscriber::class)->handle($this->event('feed.issued', $issue->id)->id);
        $issue->refresh();
        $this->assertSame('posted', $issue->posting_status);

        $this->post(route('feed.issues.reverse', $issue), ['reason' => 'Entered against the wrong stage'])
            ->assertSessionHas('success');

        $issue->refresh();
        $this->assertTrue($issue->isReversed());
        $this->assertSame('reversed', $issue->posting_status);
        $this->assertEquals(100, $this->stock('finished_feed', $this->feed->id));
        $this->assertEquals('0.0000', (string) GroupCostPool::where('group_id', $issue->group_id)->value('total_carrying_value'));

        $original = JournalBatch::findOrFail($issue->journal_batch_id);
        $this->assertSame(JournalBatch::STATUS_REVERSED, $original->status);
        $this->assertSame((int) $issue->reversal_journal_batch_id, (int) $original->reversed_by_batch_id);

        $sows = BiologicalAssetStageBalance::where('group_id', $issue->group_id)->where('stage_key', 'sow')->firstOrFail();
        $this->assertSame('0.0000', (string) $sows->carrying_value);
    }

    public function test_reversing_before_posting_cancels_the_pending_posting(): void
    {
        // The posting must still be waiting when the reversal happens.
        config(['finance.instant_posting.enabled' => false]);
        $this->actingAs($this->user);
        DB::transaction(fn () => app(InventoryService::class)->recordFeedProduction($this->feed->id, 100, 500, ++$this->source));
        $issue = app(IssueFeedToGroupService::class)->execute($this->user, ['feeding_stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 10]);

        $this->post(route('feed.issues.reverse', $issue), ['reason' => 'Duplicate entry'])->assertSessionHas('success');

        $event = $this->event('feed.issued', $issue->id);
        $this->assertSame(DomainEvent::STATUS_SKIPPED, $event->status);
        $this->assertNull($issue->fresh()->reversal_journal_batch_id);
        $this->assertSame(0, JournalBatch::count(), 'Nothing reached the ledger, so nothing needed reversing');
        $this->assertEquals(100, $this->stock('finished_feed', $this->feed->id));
    }

    public function test_reversed_feedings_drop_out_of_consumption_and_cannot_be_reversed_twice(): void
    {
        $this->actingAs($this->user);
        DB::transaction(fn () => app(InventoryService::class)->recordFeedProduction($this->feed->id, 100, 500, ++$this->source));
        $issue = app(IssueFeedToGroupService::class)->execute($this->user, ['feeding_stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 10]);
        $this->post(route('feed.issues.reverse', $issue), ['reason' => 'Duplicate entry']);

        $this->assertSame([], app(FeedPlanningService::class)->consumption($this->org->id, now()->toDateString(), now()->toDateString()));
        $this->post(route('feed.issues.reverse', $issue), ['reason' => 'Duplicate entry'])
            ->assertSessionHas('error', 'This feeding has already been reversed.');
        $this->assertEquals(100, $this->stock('finished_feed', $this->feed->id));
    }

    public function test_reversal_needs_a_reason(): void
    {
        $this->actingAs($this->user);
        DB::transaction(fn () => app(InventoryService::class)->recordFeedProduction($this->feed->id, 100, 500, ++$this->source));
        $issue = app(IssueFeedToGroupService::class)->execute($this->user, ['feeding_stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 10]);

        $this->post(route('feed.issues.reverse', $issue), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertFalse($issue->fresh()->isReversed());
    }

    public function test_reversing_an_untouched_batch_restores_ingredients_and_removes_the_feed(): void
    {
        $this->actingAs($this->user);
        $batch = $this->mix(200);
        app(FeedBatchProducedSubscriber::class)->handle($this->event('feed_batch.produced', $batch->id)->id);
        $batch->refresh();
        $this->assertNotNull($batch->journal_batch_id);

        $this->post(route('feed-production.batches.reverse', $batch), ['reason' => 'Mixed the wrong recipe'])->assertSessionHas('success');

        $batch->refresh();
        $this->assertTrue($batch->isReversed());
        $this->assertEquals(1000, $this->stock('raw_material', $this->maize->id));
        $this->assertEquals(1000, $this->maize->fresh()->quantity_in_stock);
        $this->assertEquals(0, $this->stock('finished_feed', $this->feed->id));
        $this->assertSame(JournalBatch::STATUS_REVERSED, JournalBatch::findOrFail($batch->journal_batch_id)->status);
        $this->assertSame(1, $this->maize->stockLogs()->where('type', 'usage_reversal')->count());
    }

    public function test_batch_cannot_be_reversed_once_its_feed_has_been_used(): void
    {
        $this->actingAs($this->user);
        $batch = $this->mix(200);
        app(IssueFeedToGroupService::class)->execute($this->user, ['feeding_stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 10]);

        $this->post(route('feed-production.batches.reverse', $batch), ['reason' => 'Mixed the wrong recipe'])
            ->assertSessionHas('error');

        $this->assertFalse($batch->fresh()->isReversed());
        $this->assertEquals(190, $this->stock('finished_feed', $this->feed->id));
    }

    public function test_reversing_a_planned_batch_reopens_its_plan_for_confirmation(): void
    {
        $this->actingAs($this->user);
        $plan = app(\App\Services\Feed\FeedProductionPlanService::class)->create($this->user, [
            'feed_type_id' => $this->feed->id, 'planned_quantity_kg' => 100, 'production_date' => now()->toDateString(), 'submission_key' => (string) Str::uuid(),
        ]);
        $done = app(\App\Services\Feed\FeedProductionPlanService::class)->complete($this->user, $plan, [
            'batch_date' => now()->toDateString(), 'finished_quantity_kg' => 100, 'actual_quantities' => [$this->maize->id => 100],
        ]);

        $this->post(route('feed-production.batches.reverse', $done->feed_batch_id), ['reason' => 'Wrong quantities entered'])->assertSessionHas('success');
        $plan->refresh();
        $this->assertSame('planned', $plan->status);
        $this->assertNull($plan->feed_batch_id);

        $again = app(\App\Services\Feed\FeedProductionPlanService::class)->complete($this->user, $plan, [
            'batch_date' => now()->toDateString(), 'finished_quantity_kg' => 98, 'actual_quantities' => [$this->maize->id => 100],
        ]);
        $this->assertSame('completed', $again->status);
        $this->assertEquals(98, $this->stock('finished_feed', $this->feed->id));
        $this->assertSame(2, FeedBatch::count());
    }

    public function test_planned_production_can_be_cancelled_but_not_after_it_is_made(): void
    {
        $this->actingAs($this->user);
        $service = app(\App\Services\Feed\FeedProductionPlanService::class);
        $plan = $service->create($this->user, ['feed_type_id' => $this->feed->id, 'planned_quantity_kg' => 50, 'production_date' => now()->toDateString(), 'submission_key' => (string) Str::uuid()]);

        $this->post(route('feed-production.plans.cancel', $plan), ['reason' => 'Bought ready-made feed instead'])->assertSessionHas('success');
        $plan->refresh();
        $this->assertSame('cancelled', $plan->status);
        $this->assertSame('Bought ready-made feed instead', $plan->cancellation_reason);

        $made = $service->create($this->user, ['feed_type_id' => $this->feed->id, 'planned_quantity_kg' => 50, 'production_date' => now()->toDateString(), 'submission_key' => (string) Str::uuid()]);
        $service->complete($this->user, $made, ['batch_date' => now()->toDateString(), 'finished_quantity_kg' => 50, 'actual_quantities' => [$this->maize->id => 50]]);
        $this->post(route('feed-production.plans.cancel', $made), ['reason' => 'Changed my mind'])
            ->assertSessionHas('error', 'This plan has been produced. Reverse its batch instead.');
    }

    private function mix(float $kg): FeedBatch
    {
        return app(ProduceFeedBatchService::class)->execute($this->user, [
            'feed_type_id' => $this->feed->id, 'batch_date' => now()->toDateString(), 'batch_size_kg' => $kg, 'submission_key' => (string) Str::uuid(),
        ])['feed_batch'];
    }

    private function event(string $type, int $sourceId): DomainEvent
    {
        return DomainEvent::where('event_type', $type)->where('source_id', $sourceId)->firstOrFail();
    }

    private function stock(string $kind, int $id): float
    {
        return app(FeedPlanningService::class)->available($this->org->id, $kind, $id);
    }
}
