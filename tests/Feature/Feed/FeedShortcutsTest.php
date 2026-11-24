<?php

namespace Tests\Feature\Feed;

use App\Models\FeedProductionPlan;
use App\Models\FeedType;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\User;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * One-step shortcuts between the parts of feed: fill a feeding round from the
 * ration, plan every mix the forecast needs, and request every short
 * ingredient at once.
 */
class FeedShortcutsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private FeedType $grower;

    private FeedType $sow;

    private RawMaterial $maize;

    private RawMaterial $soya;

    private int $source = 900000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 2, 1)->setTime(8, 0));
        $this->org = Organization::create(['name' => 'Feed farm', 'slug' => 'feed-farm']);
        app(CurrentOrganization::class)->bind($this->org);
        $role = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view_feed_production', 'manage_feed_production', 'manage_feed_issues', 'view_group_pools', 'view_finance', 'view_raw_materials'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'finance']);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
        $this->actor = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin', 'role_id' => $role->id]);
        // Only 5 kg of each ingredient: any real plan is short of both.
        $this->maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 5, 'unit_cost' => 2])->fresh();
        $this->soya = RawMaterial::create(['name' => 'Soya', 'unit' => 'kg', 'quantity_in_stock' => 5, 'unit_cost' => 4])->fresh();
        $this->receive('raw_material', $this->maize->id, 5);
        $this->receive('raw_material', $this->soya->id, 5);
        $this->grower = $this->feedType('Grower meal');
        $this->sow = $this->feedType('Sow meal');

        $group = Group::create(['name' => 'Pen 1', 'type' => 'pen']);
        PigManagement::create(['ear_tag_number' => '', 'date_of_birth' => now()->subDays(120), 'stock_type' => 'standard', 'gender' => 'F', 'group_id' => $group->id]);
        PigManagement::create(['ear_tag_number' => '', 'date_of_birth' => now()->subDays(400), 'stock_type' => 'breeding', 'gender' => 'F', 'group_id' => $group->id]);
        $this->standard('grower', 2, $this->grower);
        $this->standard('other_sows', 3, $this->sow);
    }

    public function test_plan_all_mixes_creates_one_plan_per_feed_that_needs_producing(): void
    {
        $key = (string) Str::uuid();
        $this->planAll($key)->assertRedirect(route('feed-production.index'))->assertSessionHas('success');

        $plans = FeedProductionPlan::where('status', 'planned')->get()->keyBy('feed_type_id');
        $this->assertCount(2, $plans);
        $this->assertEquals(14, $plans[$this->grower->id]->planned_quantity_kg, '2 kg × 7 days');
        $this->assertEquals(21, $plans[$this->sow->id]->planned_quantity_kg, '3 kg × 7 days');

        $this->planAll($key);
        $this->assertSame(2, FeedProductionPlan::count(), 'A double-submitted form plans nothing twice.');
    }

    public function test_a_feed_already_waiting_to_be_mixed_is_not_planned_again(): void
    {
        $this->actingAs($this->actor)->post(route('feed-production.plans'), ['feed_type_id' => $this->grower->id, 'production_date' => '2026-02-01',
            'planned_quantity_kg' => 14, 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors();

        $this->planAll((string) Str::uuid())->assertSessionHas('success', fn ($m) => str_contains($m, 'Already planned, left as they are: Grower meal'));

        $this->assertSame(1, FeedProductionPlan::where('feed_type_id', $this->grower->id)->count());
        $this->assertSame(1, FeedProductionPlan::where('feed_type_id', $this->sow->id)->count());
    }

    public function test_request_all_shortages_prefills_every_short_ingredient(): void
    {
        $this->actingAs($this->actor)->get(route('purchase-requests.create', ['planning_from' => '2026-02-01', 'planning_to' => '2026-02-07', 'planning_material' => 'all']))
            ->assertOk()
            ->assertViewHas('prefillItems', function (array $items) {
                $byMaterial = collect($items)->keyBy('raw_material_id');

                // 35 kg to mix at 70/30, less 5 kg of each in stock.
                return $byMaterial->count() === 2
                    && (float) $byMaterial[$this->maize->id]['quantity'] === 19.5
                    && (float) $byMaterial[$this->soya->id]['quantity'] === 5.5;
            })
            ->assertSee('Maize: required 24.5 kg');
    }

    public function test_mix_and_buy_offers_a_mix_for_each_feed_and_one_purchase_request(): void
    {
        $this->actingAs($this->actor)->get(route('feed.mix-buy', ['weeks' => 1]))->assertOk()
            ->assertSeeInOrder(['Mix feed', 'Grower meal', 'Sow meal', 'Buy ingredients'])
            ->assertSee('name="batch_size_kg"', false)
            ->assertSee('Buy all 2 short on one purchase request')
            ->assertSee('planning_material=all', false);

        $this->actingAs($this->actor)->get(route('mill.plan'))->assertRedirect(route('feed.mix-buy'));
        $this->actingAs($this->actor)->get(route('mill.projection', ['weeks' => 4]))->assertRedirect(route('feed.mix-buy', ['weeks' => 4]));
    }

    public function test_mixing_from_mix_and_buy_is_one_step(): void
    {
        $this->receive('raw_material', $this->maize->id, 1000);
        $this->receive('raw_material', $this->soya->id, 1000);

        $this->actingAs($this->actor)->post(route('feed-production.mix'), [
            'submission_key' => (string) Str::uuid(), 'feed_type_id' => $this->grower->id, 'batch_date' => now()->toDateString(),
            'batch_size_kg' => 14, 'return' => 'mix-buy', 'weeks' => 1,
        ])->assertRedirect(route('feed.mix-buy', ['weeks' => 1]))->assertSessionHas('success', fn ($m) => str_contains($m, 'Mixed 14 kg of Grower meal'));

        $this->assertSame(1, \App\Models\FeedBatch::count());
        $this->assertSame(0, FeedProductionPlan::count(), 'No plan to confirm afterwards.');
        $this->actingAs($this->actor)->get(route('feed.mix-buy', ['weeks' => 1]))->assertOk()->assertSeeInOrder(['Grower meal', 'Enough']);
    }

    public function test_mixing_more_than_the_store_holds_says_what_is_missing(): void
    {
        // 5 kg of each ingredient: enough for about 7 kg of a 70/30 feed, so the box is capped and says why.
        $this->actingAs($this->actor)->get(route('feed.mix-buy', ['weeks' => 1]))->assertOk()
            ->assertSee('The store only has enough ingredients for 7 kg');

        $this->actingAs($this->actor)->post(route('feed-production.mix'), [
            'submission_key' => (string) Str::uuid(), 'feed_type_id' => $this->grower->id, 'batch_date' => now()->toDateString(),
            'batch_size_kg' => 100, 'return' => 'mix-buy',
        ])->assertRedirect(route('feed.mix-buy'))->assertSessionHas('mix_error');

        $this->assertSame(0, \App\Models\FeedBatch::count());
    }

    public function test_the_feeding_form_can_fill_every_stage_from_the_ration(): void
    {
        $this->receive('finished_feed', $this->grower->id, 50);
        $this->receive('finished_feed', $this->sow->id, 50);

        $this->actingAs($this->actor)->get(route('feed.issues.create'))->assertOk()
            ->assertSee("Fill all with today's ration", false);
    }

    public function test_the_projection_shows_each_weeks_feed_and_when_stock_runs_out(): void
    {
        $this->receive('finished_feed', $this->grower->id, 20);  // 14 kg a week: lasts into week 2

        $response = $this->actingAs($this->actor)->get(route('feed.mix-buy', ['weeks' => 4]))->assertOk()
            ->assertSee('Week by week');

        $feeds = $response->viewData('feeds');
        $grower = $feeds[$this->grower->id];
        $sow = $feeds[$this->sow->id];
        $this->assertEquals([14, 14, 14, 14], $grower['weeks'], '1 grower × 2 kg × 7 days');
        $this->assertEquals([21, 21, 21, 21], $sow['weeks'], '1 sow × 3 kg × 7 days');
        $this->assertSame(1, $grower['runs_out_week'], '20 kg covers week 1 (14) but not week 2 (28).');
        $this->assertSame(0, $sow['runs_out_week'], 'No sow feed in stock at all.');
        $this->assertEquals(20, $grower['stock_kg']);
    }

    public function test_the_projection_follows_growers_into_the_next_stage(): void
    {
        // The grower is 120 days old: a finisher from day 155, in week 6. Finisher has no ration here.
        $response = $this->actingAs($this->actor)->get(route('feed.mix-buy', ['weeks' => 8]))->assertOk()
            ->assertSee('Some animals are not counted yet')
            ->assertSee('Finisher');

        $stages = $response->viewData('stages');
        $this->assertEquals(1, $stages['grower']['weeks'][0]);
        $this->assertEquals(0, $stages['grower']['weeks'][7], 'By week 8 the grower has moved on.');
    }

    public function test_several_ingredients_can_go_on_one_purchase_request_from_raw_materials(): void
    {
        $this->maize->update(['reorder_level' => 50]);
        $this->soya->update(['reorder_level' => 20]);

        $this->actingAs($this->actor)->get(route('raw-materials.index'))->assertOk()
            ->assertSee('Request purchase for selected')
            ->assertSee('Select all low / out of stock (2)');

        $this->actingAs($this->actor)->get(route('purchase-requests.create', ['restock_materials' => [$this->maize->id, $this->soya->id]]))
            ->assertOk()
            ->assertViewHas('prefillItems', function (array $items) {
                $byMaterial = collect($items)->keyBy('raw_material_id');

                return $byMaterial->count() === 2
                    && (float) $byMaterial[$this->maize->id]['quantity'] === 45.0
                    && (float) $byMaterial[$this->soya->id]['quantity'] === 15.0;
            });
    }

    public function test_the_plan_offers_a_request_even_for_a_single_shortage(): void
    {
        $this->receive('raw_material', $this->soya->id, 1000);  // only maize is short now

        $this->actingAs($this->actor)->get(route('feed.mix-buy', ['weeks' => 1]))->assertOk()
            ->assertSee('Buy all 1 short on one purchase request');
    }

    public function test_every_feed_page_has_the_same_four_tabs(): void
    {
        foreach (['feed.issues.create', 'feed.mix-buy', 'raw-materials.index', 'feed-setup.index'] as $route) {
            $this->actingAs($this->actor)->get(route($route))->assertOk()
                ->assertSeeInOrder(['Today', 'Mix &amp; buy', 'Stock', 'Setup'], false);
        }
    }

    public function test_stock_shows_feed_ready_to_use_next_to_the_ingredients(): void
    {
        $this->receive('finished_feed', $this->grower->id, 20);

        $this->actingAs($this->actor)->get(route('raw-materials.index'))->assertOk()
            ->assertSeeInOrder(['Feed ready to use', 'Grower meal', '20', 'Lasts about 10 days', 'Ingredients in the store']);
    }

    private function planAll(string $key)
    {
        return $this->actingAs($this->actor)->post(route('feed-production.plans.all'), [
            'planning_from' => '2026-02-01', 'planning_to' => '2026-02-07', 'submission_key' => $key,
        ]);
    }

    private function feedType(string $name): FeedType
    {
        $type = FeedType::create(['name' => $name, 'is_active' => true])->fresh();
        $type->recipes()->createMany([
            ['raw_material_id' => $this->maize->id, 'quantity_per_100kg' => 70],
            ['raw_material_id' => $this->soya->id, 'quantity_per_100kg' => 30],
        ]);

        return $type;
    }

    private function standard(string $stage, float $rate, FeedType $feed): void
    {
        app(FeedConfigurationService::class)->standard($this->actor, ['stage' => $stage, 'daily_kg' => $rate,
            'feed_type_id' => $feed->id, 'effective_from' => '2026-02-01']);
    }

    private function receive(string $kind, int $id, float $qty): void
    {
        DB::transaction(fn () => app(InventoryService::class)->recordReceive($kind, $id, $qty, 2, 'stock_log', ++$this->source));
    }
    public function test_bulk_retry_replays_original_quantities_even_when_stock_covers_all_demand(): void
    {
        $this->actingAs($this->actor);
        $body = ['submission_key' => (string) Str::uuid(), 'planning_from' => '2026-02-01', 'planning_to' => '2026-02-07'];
        $this->post(route('feed-production.plans.all'), $body)->assertSessionHas('success');
        $original = FeedProductionPlan::orderBy('id')->get()->toArray();
        $this->receive('finished_feed', $this->grower->id, 100);
        $this->receive('finished_feed', $this->sow->id, 100);
        $this->post(route('feed-production.plans.all'), $body)->assertRedirect(route('feed-production.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Grower meal 14 kg') && str_contains($message, 'Sow meal 21 kg'));
        $this->assertSame($original, FeedProductionPlan::orderBy('id')->get()->toArray());
    }

    public function test_same_day_recipe_correction_preserves_original_and_existing_plan_snapshot(): void
    {
        $this->actingAs($this->actor);
        $body = ['feed_type_id' => $this->grower->id, 'entry_mode' => 'batch', 'effective_from' => '2026-02-01',
            'items' => [['raw_material_id' => $this->maize->id, 'quantity_kg' => 70], ['raw_material_id' => $this->soya->id, 'quantity_kg' => 30]]];
        $this->post(route('feed-production.recipes.store'), $body)->assertSessionHasNoErrors();
        $first = \App\Models\FeedRecipeVersion::firstOrFail();
        $plan = app(\App\Services\Feed\FeedProductionPlanService::class)->create($this->actor, [
            'feed_type_id' => $this->grower->id, 'production_date' => '2026-02-01',
            'planned_quantity_kg' => 10, 'submission_key' => (string) Str::uuid(),
        ]);
        $snapshot = $plan->recipe_snapshot;
        $body['items'][0]['quantity_kg'] = 60;
        $body['items'][1]['quantity_kg'] = 40;
        $this->post(route('feed-production.recipes.store'), $body)->assertSessionHasNoErrors();
        $latest = \App\Models\FeedRecipeVersion::latest('id')->firstOrFail();
        $this->assertNotSame($first->id, $latest->id);
        $this->assertEquals(70, $first->fresh()->ingredients[0]['quantity_per_100kg']);
        $resolved = app(\App\Services\Feed\FeedRecipeService::class)->resolve($this->grower, '2026-02-01');
        $this->assertSame($latest->id, $resolved['version_id']);
        $this->assertEquals(60, $resolved['ingredients'][0]['quantity_per_100kg']);
        $this->assertSame($snapshot, $plan->fresh()->recipe_snapshot);
    }

}
