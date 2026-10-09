<?php

namespace Tests\Concurrency;

class FeedWorkflowConcurrencyTest extends ConcurrencyTestCase
{
    public function test_concurrent_production_and_issue_retries_post_each_inventory_change_once(): void
    {
        $migration = $this->artisan('migrate', '--force');
        $this->assertSame(0, $migration['exit'], json_encode($migration));
        $slug = 'feed-race-'.bin2hex(random_bytes(5));
        $setup = $this->runConcurrently(1, <<<PHP
\$org = App\Models\Organization::create(['name' => 'Feed race', 'slug' => '{$slug}']);
app(App\Support\CurrentOrganization::class)->bind(\$org);
\$actor = App\Models\User::factory()->create(['organization_id' => \$org->id, 'role' => 'admin']);
\$feed = App\Models\FeedType::create(['name' => '{$slug}']);
\$group = App\Models\Group::create(['name' => 'Race group', 'type' => 'pen']);
\$material = App\Models\RawMaterial::create(['name' => 'Race maize', 'unit' => 'kg', 'quantity_in_stock' => 100, 'unit_cost' => 2]);
\$feed->recipes()->create(['raw_material_id' => \$material->id, 'quantity_per_100kg' => 100]);
Illuminate\Support\Facades\DB::transaction(fn () => app(App\Services\Finance\Inventory\InventoryService::class)->recordReceive('raw_material', \$material->id, 100, 2, 'stock_log', 900000 + \$material->id));
echo json_encode(['org' => \$org->id, 'actor' => \$actor->id, 'feed' => \$feed->id, 'group' => \$group->id, 'material' => \$material->id]);
PHP);
        $this->assertSame(0, $setup[0]['exit'], json_encode($setup));
        $ids = json_decode(trim($setup[0]['out']), true, flags: JSON_THROW_ON_ERROR);
        $bootstrap = "app(App\\Support\\CurrentOrganization::class)->bind(App\\Models\\Organization::findOrFail({$ids['org']})); \$actor = App\\Models\\User::findOrFail({$ids['actor']});";
        $key = '10000000-0000-4000-8000-'.str_pad((string) $ids['org'], 12, '0', STR_PAD_LEFT);
        $results = $this->runConcurrently(4, $bootstrap.<<<PHP
\$result = app(App\Services\Feed\ProduceFeedBatchService::class)->execute(\$actor, ['feed_type_id' => {$ids['feed']}, 'batch_date' => date('Y-m-d'), 'batch_size_kg' => 20, 'submission_key' => '{$key}']);
echo \$result['feed_batch']->id;
PHP, isolation: 'READ COMMITTED');
        foreach ($results as $result) {
            $this->assertSame(0, $result['exit'], json_encode($result));
        }
        $this->assertCount(1, array_unique(array_column($results, 'out')));
        $results = $this->runConcurrently(4, $bootstrap.<<<PHP
\$issue = app(App\Services\Feed\IssueFeedToGroupService::class)->execute(\$actor, ['group_id' => {$ids['group']}, 'feed_type_id' => {$ids['feed']}, 'quantity_kg' => 5, 'submission_key' => '{$key}']);
echo \$issue->id;
PHP, isolation: 'READ COMMITTED');
        foreach ($results as $result) {
            $this->assertSame(0, $result['exit'], json_encode($result));
        }
        $this->assertCount(1, array_unique(array_column($results, 'out')));
        $verify = $this->runConcurrently(1, $bootstrap.<<<PHP
\$planner = app(App\Services\Feed\FeedPlanningService::class);
echo json_encode([App\Models\FeedBatch::count(), App\Models\FeedIssue::count(), \$planner->available({$ids['org']}, 'raw_material', {$ids['material']}), \$planner->available({$ids['org']}, 'finished_feed', {$ids['feed']})]);
PHP);
        $this->assertSame(0, $verify[0]['exit'], json_encode($verify));
        $this->assertEquals([1, 1, 80, 15], json_decode(trim($verify[0]['out']), true));
    }
}
