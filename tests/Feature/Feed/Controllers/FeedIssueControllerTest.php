<?php

namespace Tests\Feature\Feed\Controllers;

use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\Group;
use App\Models\Permission;
use App\Services\Finance\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $resp = $this->actingAs($this->user)->get(route('finance.feed.issues.index'));
        $resp->assertOk();
    }

    public function test_index_forbidden_without_permission(): void
    {
        $resp = $this->actingAs($this->user)->get(route('finance.feed.issues.index'));
        $resp->assertForbidden();
    }

    public function test_create_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('view_group_pools');  // can view but not manage

        $resp = $this->actingAs($this->user)->get(route('finance.feed.issues.create'));
        $resp->assertForbidden();
    }

    public function test_create_explains_empty_group_dropdown(): void
    {
        $this->grantPermission('manage_feed_issues');

        $feedType = FeedType::create(['name' => 'Grower Feed']);
        $this->app->make(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id,
            quantity: 1000.0,
            totalCost: 5000.0,
            feedBatchId: 1,
        );

        $this->actingAs($this->user)
            ->get(route('finance.feed.issues.create'))
            ->assertOk()
            ->assertSee('No pig groups exist yet.')
            ->assertSee('name="new_group_name"', false);
    }

    public function test_store_creates_issue_and_redirects_to_show(): void
    {
        $this->grantPermission('manage_feed_issues');
        $this->grantPermission('view_group_pools');

        $group = Group::create(['name' => 'G-1', 'type' => 'pen']);
        $feedType = FeedType::create(['name' => 'Grower Feed']);

        $this->app->make(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id, quantity: 1000.0, totalCost: 5000.0, feedBatchId: 1,
        );

        $resp = $this->actingAs($this->user)->post(route('finance.feed.issues.store'), [
            'group_id'     => $group->id,
            'feed_type_id' => $feedType->id,
            'quantity_kg'  => 50.0,
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $this->assertDatabaseHas('feed_issues', [
            'group_id'     => $group->id,
            'feed_type_id' => $feedType->id,
            'quantity_kg'  => '50.0000',
        ]);
    }

    public function test_store_can_create_group_inline_when_none_selected(): void
    {
        $this->grantPermission('manage_feed_issues');
        $this->grantPermission('view_group_pools');

        $feedType = FeedType::create(['name' => 'Starter Feed']);
        $this->app->make(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id,
            quantity: 1000.0,
            totalCost: 5000.0,
            feedBatchId: 2,
        );

        $resp = $this->actingAs($this->user)->post(route('finance.feed.issues.store'), [
            'new_group_name' => 'Grower Pen 1',
            'new_group_type' => 'pen',
            'feed_type_id' => $feedType->id,
            'quantity_kg' => 25.0,
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $group = Group::where('name', 'Grower Pen 1')->first();
        $this->assertNotNull($group);

        $this->assertDatabaseHas('feed_issues', [
            'group_id' => $group->id,
            'feed_type_id' => $feedType->id,
            'quantity_kg' => '25.0000',
        ]);
    }

    public function test_store_with_invalid_quantity_returns_errors(): void
    {
        $this->grantPermission('manage_feed_issues');

        $group = Group::create(['name' => 'G-1', 'type' => 'pen']);
        $feedType = FeedType::create(['name' => 'X']);

        $resp = $this->actingAs($this->user)
            ->from(route('finance.feed.issues.create'))
            ->post(route('finance.feed.issues.store'), [
                'group_id'     => $group->id,
                'feed_type_id' => $feedType->id,
                'quantity_kg'  => -5,
            ]);

        $resp->assertRedirect(route('finance.feed.issues.create'));
        $resp->assertSessionHasErrors('quantity_kg');
    }

    protected function grantPermission(string $code): void
    {
        $perm = Permission::firstOrCreate(['name' => $code], [
            'name' => $code, 'description' => 'test', 'module' => 'finance',
        ]);
        $this->user->role()->first()?->permissions()->syncWithoutDetaching([$perm->id]);
    }
}
