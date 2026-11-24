<?php

namespace Tests\Feature\Finance;

use App\Models\DomainEvent;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\JournalBatch;
use App\Models\Group;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Services\Feed\FeedModuleReset;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Finance\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Clearing a farm's feed module takes its accounting with it, and nothing
 * else: the books still balance, and pigs and other farms are untouched.
 */
class FeedModuleResetTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    private FeedType $feed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        config(['finance.instant_posting.enabled' => true]);
        $this->mapFeedAccounts();

        $this->feed = FeedType::create(['name' => 'Grower Feed']);
        RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 2]);
        app(InventoryService::class)->recordFeedProduction(feedTypeId: $this->feed->id, quantity: 100, totalCost: 500, feedBatchId: 1);
        $group = Group::create(['name' => 'Pen 1', 'type' => 'pen']);
        DB::transaction(fn () => app(IssueFeedToGroupService::class)->execute($this->user, [
            'group_id' => $group->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => 10,
        ]));
        PigManagement::create(['ear_tag_number' => 'KEEP-1', 'code' => 'KEEP-1', 'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subDays(90)]);
    }

    public function test_the_fixture_posted_a_feeding(): void
    {
        $this->assertNotNull(FeedIssue::firstOrFail()->journal_batch_id);
        $this->assertGreaterThan(0, JournalBatch::count());
    }

    public function test_a_dry_run_lists_what_would_go_and_changes_nothing(): void
    {
        $rows = app(FeedModuleReset::class)->run($this->org->id, dryRun: true);

        $this->assertCount(1, $rows['feed_issues']);
        $this->assertNotEmpty($rows['journal_batches']);
        $this->assertSame(1, FeedIssue::count());
        $this->assertSame(1, FeedType::count());
    }

    public function test_it_removes_the_feed_records_and_what_they_posted_and_the_books_still_balance(): void
    {
        $other = Organization::create(['slug' => 'other', 'name' => 'Other farm']);
        $otherFeed = FeedType::withoutGlobalScopes()->create(['organization_id' => $other->id, 'name' => 'Their feed']);

        app(FeedModuleReset::class)->run($this->org->id, dryRun: false);

        $this->assertSame(0, FeedIssue::withoutGlobalScopes()->where('organization_id', $this->org->id)->count());
        $this->assertSame(0, FeedType::where('organization_id', $this->org->id)->count());
        $this->assertSame(0, RawMaterial::where('organization_id', $this->org->id)->count());
        $this->assertSame(0, JournalBatch::where('organization_id', $this->org->id)->count());
        $this->assertSame(0, DB::table('inventory_movements')->where('organization_id', $this->org->id)->whereIn('item_kind', ['raw_material', 'finished_feed'])->count());
        $this->assertSame(0, DomainEvent::withoutGlobalScopes()->where('organization_id', $this->org->id)->where('event_type', 'feed.issued')->count());
        $this->assertEquals(0.0, (float) DB::table('biological_asset_stage_balances')->where('organization_id', $this->org->id)->sum('carrying_value'));

        $lines = DB::table('journal_lines')->where('organization_id', $this->org->id);
        $this->assertEquals((float) (clone $lines)->sum('debit'), (float) (clone $lines)->sum('credit'), 'The ledger still balances.');

        $this->assertTrue(PigManagement::where('code', 'KEEP-1')->exists(), 'Pigs are not feed.');
        $this->assertTrue(FeedType::withoutGlobalScopes()->whereKey($otherFeed->id)->exists(), 'Another farm is not touched.');
    }

    public function test_it_refuses_a_farm_with_feed_sales(): void
    {
        DB::table('feed_sales')->insert(['organization_id' => $this->org->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => 1, 'unit_price' => 1,
            'sale_total' => 1, 'sale_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(\RuntimeException::class);
        app(FeedModuleReset::class)->run($this->org->id, dryRun: false);
    }

    public function test_the_command_only_deletes_with_force_and_keeps_a_backup(): void
    {
        Storage::fake('local');

        $this->artisan('feed:reset')->assertFailed();
        $this->artisan('feed:reset', ['--organization' => $this->org->id])->expectsOutputToContain('Nothing was changed')->assertSuccessful();
        $this->assertSame(1, FeedIssue::count());

        $this->artisan('feed:reset', ['--organization' => $this->org->id, '--force' => true])->expectsOutputToContain('Feed module cleared')->assertSuccessful();
        $this->assertSame(0, FeedIssue::count());

        $backup = collect(Storage::disk('local')->files('feed-reset'))->first();
        $this->assertNotNull($backup);
        $this->assertCount(1, json_decode(Storage::disk('local')->get($backup), true)['rows']['feed_issues']);
    }

    public function test_reset_restores_group_pool_and_backs_up_its_feed_movements(): void
    {
        $pool = \App\Models\Finance\GroupCostPool::firstOrFail();
        $this->assertEquals(50, (float) $pool->total_carrying_value);
        $rows = app(FeedModuleReset::class)->run($this->org->id, dryRun: false);
        $this->assertCount(1, $rows['group_cost_movements']);
        $this->assertCount(1, $rows['group_cost_pools']);
        $this->assertEquals(0, (float) $pool->fresh()->total_carrying_value);
        $this->assertNull($pool->fresh()->last_movement_id);
        $this->assertDatabaseCount('group_cost_movements', 0);
    }

    public function test_reset_preserves_earlier_livestock_costs_in_a_group_pool(): void
    {
        $group = Group::create(['name' => 'Earlier acquisition', 'type' => 'pen']);
        $opening = DB::transaction(fn () => app(\App\Services\Finance\Costing\GroupCostPoolService::class)
            ->recordAcquisition($group->id, PigManagement::firstOrFail()->id, '100', '20'));
        DB::transaction(fn () => app(IssueFeedToGroupService::class)->execute($this->user, [
            'group_id' => $group->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => 10,
        ]));
        app(FeedModuleReset::class)->run($this->org->id, dryRun: false);
        $pool = \App\Models\Finance\GroupCostPool::where('group_id', $group->id)->firstOrFail();
        $this->assertEquals(100, (float) $pool->total_carrying_value);
        $this->assertEquals(20, (float) $pool->total_weight_kg);
        $this->assertSame(1, $pool->on_hand_pig_count);
        $this->assertSame($opening->id, $pool->last_movement_id);
        $this->assertNull($pool->closed_at);
        $this->assertDatabaseHas('group_cost_movements', ['id' => $opening->id]);
    }

    public function test_reset_refuses_later_group_cost_activity_without_changing_any_records(): void
    {
        $pool = \App\Models\Finance\GroupCostPool::firstOrFail();
        DB::transaction(fn () => app(\App\Services\Finance\Costing\GroupCostPoolService::class)
            ->recordAcquisition($pool->group_id, PigManagement::firstOrFail()->id, '100', '20'));
        try {
            app(FeedModuleReset::class)->run($this->org->id, dryRun: false);
            $this->fail('Dependent cost history must prevent reset.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Later group cost activity', $exception->getMessage());
        }
        $this->assertSame(1, FeedIssue::count());
        $this->assertEquals(150, (float) $pool->fresh()->total_carrying_value);
        $this->assertDatabaseCount('group_cost_movements', 2);
    }

    public function test_reset_removes_pending_stock_count_events(): void
    {
        config(['finance.instant_posting.enabled' => false]);
        $permission = \App\Models\Permission::firstOrCreate(['name' => 'manage_feed_production'], ['description' => 'Manage production', 'module' => 'feed']);
        $this->user->role()->first()->permissions()->syncWithoutDetaching([$permission->id]);
        $count = app(\App\Services\Inventory\StockCountService::class)->record($this->user, [
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
            'lines' => [['item_kind' => 'finished_feed', 'item_id' => $this->feed->id, 'counted_qty' => 80]],
        ]);
        $event = DomainEvent::where('source_type', \App\Models\StockCountLine::class)->where('source_id', $count->lines->first()->id)->firstOrFail();
        $this->assertSame('pending', $event->status);
        app(FeedModuleReset::class)->run($this->org->id, dryRun: false);
        $this->assertDatabaseMissing('stock_count_lines', ['id' => $count->lines->first()->id]);
        $this->assertDatabaseMissing('domain_events', ['id' => $event->id]);
    }

    public function test_reset_refuses_mixed_purchases_and_preserves_material_references(): void
    {
        $material = RawMaterial::firstOrFail();
        $request = DB::table('purchase_requests')->insertGetId([
            'organization_id' => $this->org->id, 'request_no' => 'MIXED-REVIEW',
            'request_date' => now()->toDateString(), 'requested_by' => $this->user->id, 'status' => 'pending', 'total_amount' => 150,
        ]);
        $line = DB::table('purchase_request_items')->insertGetId([
            'purchase_request_id' => $request, 'item_name' => 'Maize', 'quantity' => 10,
            'unit_price' => 10, 'total_price' => 100, 'raw_material_id' => $material->id,
        ]);
        DB::table('purchase_request_items')->insert([
            'purchase_request_id' => $request, 'item_name' => 'Repairs', 'quantity' => 1, 'unit_price' => 50, 'total_price' => 50,
        ]);
        foreach ([true, false] as $dryRun) {
            try {
                app(FeedModuleReset::class)->run($this->org->id, dryRun: $dryRun);
                $this->fail('Mixed purchases must prevent reset.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('mixed purchase request', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('purchase_request_items', ['id' => $line, 'raw_material_id' => $material->id]);
        $this->assertSame(1, FeedIssue::count());
    }

    private function mapFeedAccounts(): void
    {
        foreach (['1220' => 'Finished Feed Inventory', '1230' => 'Live Pig Inventory', '1360' => 'Growers'] as $code => $name) {
            Account::firstOrCreate(['organization_id' => $this->org->id, 'code' => $code], [
                'name' => $name, 'account_type' => Account::TYPE_ASSET, 'currency_code' => 'GHS',
                'is_active' => true, 'allow_manual_journal' => true,
            ]);
        }
        InventoryAccountMapping::create([
            'organization_id' => $this->org->id, 'item_kind' => 'finished_feed',
            'category' => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN, 'is_default' => true,
            'inventory_account_id' => Account::where('organization_id', $this->org->id)->where('code', '1220')->value('id'),
        ]);
    }
}
