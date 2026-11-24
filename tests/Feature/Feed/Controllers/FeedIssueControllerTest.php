<?php

namespace Tests\Feature\Feed\Controllers;

use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\Group;
use App\Models\Permission;
use App\Services\Feed\FeedStageResolver;
use App\Services\Finance\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class FeedIssueControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_index_renders_for_viewer(): void
    {
        $this->grantPermission('view_group_pools');

        $resp = $this->actingAs($this->user)->get(route('feed.issues.index'));
        $resp->assertOk();
    }

    public function test_index_forbidden_without_permission(): void
    {
        $resp = $this->actingAs($this->user)->get(route('feed.issues.index'));
        $resp->assertForbidden();
    }

    public function test_create_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('view_group_pools');  // can view but not manage

        $resp = $this->actingAs($this->user)->get(route('feed.issues.create'));
        $resp->assertForbidden();
    }

    public function test_create_shows_one_row_per_feeding_stage(): void
    {
        $this->grantPermission('manage_feed_issues');
        $this->stockFeed('Grower Feed', 1000.0);

        $resp = $this->actingAs($this->user)
            ->get(route('feed.issues.create'))
            ->assertOk()
            ->assertSee('Today')->assertSee('id="feeding"', false);

        foreach (FeedStageResolver::STAGES as $stage => $label) {
            $resp->assertSee('value="'.$stage.'"', false);
        }
        $resp->assertDontSee('new_group_name', false);
    }

    public function test_store_records_one_issue_per_filled_stage(): void
    {
        $this->grantPermission('manage_feed_issues');
        $this->grantPermission('view_group_pools');

        $grower = $this->stockFeed('Grower Feed', 1000.0);
        $sow = $this->stockFeed('Sow Feed', 500.0);

        $resp = $this->actingAs($this->user)->post(route('feed.issues.store'), [
            'entries' => [
                ['stage' => 'grower', 'feed_type_id' => $grower->id, 'quantity_kg' => 50, 'submission_key' => (string) Str::uuid()],
                ['stage' => 'gestating_sow', 'feed_type_id' => $sow->id, 'quantity_kg' => 12.5, 'submission_key' => (string) Str::uuid()],
                ['stage' => 'boar', 'feed_type_id' => $sow->id, 'quantity_kg' => '', 'submission_key' => (string) Str::uuid()],
            ],
        ]);

        $resp->assertRedirect(route('feed.issues.create'));
        $resp->assertSessionHas('success');

        $this->assertDatabaseHas('feed_issues', ['feeding_stage' => 'grower', 'feed_type_id' => $grower->id, 'quantity_kg' => '50.0000']);
        $this->assertDatabaseHas('feed_issues', ['feeding_stage' => 'gestating_sow', 'feed_type_id' => $sow->id, 'quantity_kg' => '12.5000']);
        $this->assertDatabaseMissing('feed_issues', ['feeding_stage' => 'boar']);

        // Each stage is carried by its own system group, hidden from animal group lists.
        $stageGroup = Group::where('feeding_stage', 'grower')->firstOrFail();
        $this->assertSame(1, Group::where('feeding_stage', 'grower')->count());
        $this->assertFalse(Group::animalGroups()->whereKey($stageGroup->id)->exists());
    }

    public function test_store_reuses_the_stage_group_across_feedings(): void
    {
        $this->grantPermission('manage_feed_issues');
        $feed = $this->stockFeed('Grower Feed', 1000.0);

        foreach ([10, 20] as $qty) {
            $this->actingAs($this->user)->post(route('feed.issues.store'), [
                'entries' => [['stage' => 'grower', 'feed_type_id' => $feed->id, 'quantity_kg' => $qty, 'submission_key' => (string) Str::uuid()]],
            ])->assertSessionHas('success');
        }

        $this->assertSame(1, Group::where('feeding_stage', 'grower')->count());
        $this->assertSame(2, FeedIssue::where('feeding_stage', 'grower')->count());
        $this->assertSame(1, FeedIssue::where('feeding_stage', 'grower')->distinct()->count('group_id'));
    }

    public function test_store_rejects_a_round_that_exceeds_stock_without_saving(): void
    {
        $this->grantPermission('manage_feed_issues');
        $feed = $this->stockFeed('Grower Feed', 60.0);

        $this->actingAs($this->user)
            ->from(route('feed.issues.create'))
            ->post(route('feed.issues.store'), [
                'entries' => [
                    ['stage' => 'grower', 'feed_type_id' => $feed->id, 'quantity_kg' => 40, 'submission_key' => (string) Str::uuid()],
                    ['stage' => 'finisher', 'feed_type_id' => $feed->id, 'quantity_kg' => 40, 'submission_key' => (string) Str::uuid()],
                ],
            ])
            ->assertRedirect(route('feed.issues.create'))
            ->assertSessionHas('error');

        $this->assertSame(0, FeedIssue::count());
    }

    public function test_store_requires_at_least_one_quantity(): void
    {
        $this->grantPermission('manage_feed_issues');
        $feed = $this->stockFeed('Grower Feed', 100.0);

        $this->actingAs($this->user)
            ->from(route('feed.issues.create'))
            ->post(route('feed.issues.store'), [
                'entries' => [['stage' => 'grower', 'feed_type_id' => $feed->id, 'quantity_kg' => '']],
            ])
            ->assertRedirect(route('feed.issues.create'))
            ->assertSessionHasErrors('entries');
    }

    public function test_store_with_invalid_quantity_returns_errors(): void
    {
        $this->grantPermission('manage_feed_issues');
        $feed = FeedType::create(['name' => 'X']);

        $this->actingAs($this->user)
            ->from(route('feed.issues.create'))
            ->post(route('feed.issues.store'), [
                'entries' => [['stage' => 'grower', 'feed_type_id' => $feed->id, 'quantity_kg' => -5]],
            ])
            ->assertRedirect(route('feed.issues.create'))
            ->assertSessionHasErrors('entries.0.quantity_kg');
    }

    public function test_farm_staff_can_record_feedings_without_finance_access(): void
    {
        $this->grantPermission('manage_feed_issues');
        $feed = $this->stockFeed('Grower Feed', 100.0);

        $this->actingAs($this->user)->get(route('feed.issues.create'))
            ->assertOk()
            ->assertDontSee('Estimated feed cost');

        $this->actingAs($this->user)->post(route('feed.issues.store'), [
            'entries' => [['stage' => 'grower', 'feed_type_id' => $feed->id, 'quantity_kg' => 10, 'submission_key' => (string) Str::uuid()]],
        ])->assertRedirect(route('feed.issues.create'));

        $this->actingAs($this->user)->get(route('feed.issues.index'))
            ->assertOk()
            ->assertSee('Grower')
            ->assertDontSee('Unit cost');
    }

    public function test_finance_users_still_see_feeding_costs(): void
    {
        $this->grantPermission('manage_feed_issues');
        $this->grantPermission('view_finance');
        $this->stockFeed('Grower Feed', 100.0);

        $this->actingAs($this->user)->get(route('feed.issues.create'))->assertSee('Estimated feed cost');
        $this->actingAs($this->user)->get(route('feed.issues.index'))->assertSee('Unit cost');
    }

    public function test_old_finance_feeding_links_redirect(): void
    {
        $this->grantPermission('manage_feed_issues');

        $this->actingAs($this->user)->get('/finance/feed/issues')->assertRedirect('/feed/issues');
        $this->actingAs($this->user)->get('/finance/feed/issues/create')->assertRedirect('/feed/issues/create');
    }

    protected function stockFeed(string $name, float $kg): FeedType
    {
        static $batchId = 1;
        $feedType = FeedType::create(['name' => $name]);
        $this->app->make(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id, quantity: $kg, totalCost: $kg * 5, feedBatchId: $batchId++,
        );

        return $feedType;
    }

    protected function grantPermission(string $code): void
    {
        $perm = Permission::firstOrCreate(['name' => $code], [
            'name' => $code, 'description' => 'test', 'module' => 'finance',
        ]);
        $this->user->role()->first()?->permissions()->syncWithoutDetaching([$perm->id]);
    }
}
