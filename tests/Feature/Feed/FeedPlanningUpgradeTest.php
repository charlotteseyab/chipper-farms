<?php

namespace Tests\Feature\Feed;

use App\Models\Farrowing;
use App\Models\FeedBatch;
use App\Models\FeedingStandard;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\InventoryMovement;
use App\Models\Group;
use App\Models\Mating;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedProductionPlanService;
use App\Services\Feed\FeedRecipeService;
use App\Services\Feed\FeedStageResolver;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Feed\ProduceFeedBatchService;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FeedPlanningUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private FeedType $feed;

    private RawMaterial $maize;

    private RawMaterial $soya;

    private Group $group;

    private int $source = 700000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 2, 1)->startOfDay());
        $this->org = Organization::create(['name' => 'Feed farm', 'slug' => 'feed-farm']);
        app(CurrentOrganization::class)->bind($this->org);
        $role = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view_feed_production', 'manage_feed_production', 'manage_feed_issues', 'view_group_pools', 'view_finance'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'finance']);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
        $this->actor = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin', 'role_id' => $role->id]);
        $this->feed = FeedType::create(['name' => 'Shared ration'])->fresh();
        $this->group = Group::create(['name' => 'Growing group', 'type' => 'pen']);
        $this->maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 1000, 'unit_cost' => 2])->fresh();
        $this->soya = RawMaterial::create(['name' => 'Soya', 'unit' => 'kg', 'quantity_in_stock' => 1000, 'unit_cost' => 4])->fresh();
        $this->receive('raw_material', $this->maize->id, 1000, 2);
        $this->receive('raw_material', $this->soya->id, 1000, 4);
        $this->feed->recipes()->createMany($this->ingredients());
    }

    private function ingredients(float $maize = 70, float $soya = 30): array
    {
        return [['raw_material_id' => $this->maize->id, 'quantity_per_100kg' => $maize],
            ['raw_material_id' => $this->soya->id, 'quantity_per_100kg' => $soya]];
    }

    private function receive(string $kind, int $id, float $qty, float $cost = 2): void
    {
        DB::transaction(fn () => app(InventoryService::class)->recordReceive($kind, $id, $qty, $cost, 'stock_log', ++$this->source));
    }

    private function pig(int $age, array $extra = []): PigManagement
    {
        return PigManagement::create($extra + ['ear_tag_number' => '', 'date_of_birth' => now()->subDays($age), 'stock_type' => 'standard', 'gender' => 'F', 'group_id' => $this->group->id]);
    }

    private function standard(string $stage = 'grower', float $rate = 2, string $from = '2026-02-01'): FeedingStandard
    {
        return app(FeedConfigurationService::class)->standard($this->actor, ['stage' => $stage, 'daily_kg' => $rate,
            'feed_type_id' => $this->feed->id, 'effective_from' => $from]);
    }

    private function plan(string $to = '2026-02-07'): array
    {
        return app(FeedPlanningService::class)->calculate($this->org->id, '2026-02-01', $to);
    }

    public function test_daily_weekly_calendar_month_and_shared_stock_are_calculated_once(): void
    {
        $this->pig(120);
        $this->pig(80);
        $this->standard();
        $this->standard('weaner', 1);
        $this->receive('finished_feed', $this->feed->id, 10);
        $week = $this->plan();
        $this->assertSame(2, $week['animal_count']);
        $this->assertEquals(21, $week['required_kg']);
        $this->assertEquals(11, $week['production_kg']);
        $this->assertEquals(7.7, $week['materials'][0]['required_kg']);
        $this->assertEquals(3.3, $week['materials'][1]['required_kg']);
        // Stage ageing: the 80-day weaner (1 kg) turns grower (2 kg) at 99 days, on 20 Feb.
        // Grower 28 x 2 = 56; weaner 19 days x 1 = 19; then grower 9 days x 2 = 18. Total 93.
        $this->assertEquals(93, $this->plan('2026-02-28')['required_kg']);
        $this->assertEquals(3, $this->plan('2026-02-01')['required_kg']);
        $this->assertEquals(0, $this->plan('2026-02-01')['production_kg']);
    }

    public function test_growing_pigs_move_up_stages_during_the_forecast_but_breeding_animals_do_not(): void
    {
        $this->standard('weaner', 1);
        $this->standard();                       // grower 2 kg
        $this->standard('boar', 3);
        $this->pig(97);                          // weaner today, grower from day 3 (99 days)
        PigManagement::create(['ear_tag_number' => '', 'date_of_birth' => now()->subDays(400), 'stock_type' => 'breeding', 'gender' => 'M', 'group_id' => $this->group->id]);

        $plan = app(FeedPlanningService::class)->calculate($this->org->id, '2026-02-01', '2026-02-05');

        // Weaner 2 days x 1 + grower 3 days x 2 = 8; the boar stays a boar: 5 x 3 = 15.
        $this->assertEquals(23, $plan['required_kg']);
        $rows = collect($plan['rows'])->keyBy('stage');
        $this->assertSame(2, $rows['weaner']['applicable_days']);
        $this->assertSame(3, $rows['grower']['applicable_days']);
    }

    public function test_wastage_allowance_raises_production_and_purchasing_but_not_feeding_expectations(): void
    {
        $this->standard();
        $this->pig(120);
        Setting::set(FeedPlanningService::WASTAGE_SETTING, '10');

        $plan = $this->plan('2026-02-05');      // 5 days x 2 kg = 10 kg by ration
        $this->assertEquals(10, $plan['base_kg']);
        $this->assertEquals(1, $plan['wastage_kg']);
        $this->assertEquals(11, $plan['required_kg']);
        $this->assertEquals(11, $plan['production_kg']);
        $this->assertEquals(7.7, $plan['materials'][0]['required_kg'], '70% maize of 11 kg');

        $feeding = app(FeedPlanningService::class)->calculate($this->org->id, '2026-02-01', '2026-02-01', [], applyWastage: false);
        $this->assertEquals(2, $feeding['required_kg'], 'Feeding expectations use the ration only.');
    }

    public function test_missing_standards_are_exceptions_not_default_rations(): void
    {
        $this->pig(120);
        $plan = $this->plan();
        $this->assertEquals(0, $plan['required_kg']);
        $this->assertNotEmpty($plan['exceptions']);
        $this->assertStringContainsString('Grower', $plan['exceptions'][0]);
    }

    public function test_effective_versions_split_a_forecast_without_rewriting_earlier_rate(): void
    {
        $this->pig(120);
        $old = $this->standard();
        $this->standard('grower', 3, '2026-02-04');
        $this->assertEquals(18, $this->plan()['required_kg']);
        $this->assertEquals(2, $old->fresh()->daily_kg);
        $this->expectException(\LogicException::class);
        $old->update(['daily_kg' => 99]);
    }

    public function test_invalid_or_duplicate_standard_is_rejected(): void
    {
        $this->standard();
        $this->expectException(ValidationException::class);
        $this->standard();
    }

    public function test_nonpositive_standard_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->standard('grower', -1);
    }

    public function test_existing_age_boundaries_and_boar_stages(): void
    {
        foreach ([20 => 'piglet', 56 => 'piglet', 57 => 'weaner', 98 => 'weaner', 99 => 'grower', 154 => 'grower', 155 => 'finisher'] as $age => $stage) {
            $this->assertSame($stage, app(FeedStageResolver::class)->resolve($this->pig($age)));
        }
        $this->assertSame('boar', app(FeedStageResolver::class)->resolve($this->pig(300, ['stock_type' => 'breeding', 'gender' => 'M'])));
    }

    public function test_lactation_and_gestation_use_existing_lifecycle_records(): void
    {
        $sow = $this->pig(400, ['stock_type' => 'breeding']);
        $boar = $this->pig(400, ['stock_type' => 'breeding', 'gender' => 'M']);
        Mating::create(['female_id' => $sow->id, 'male_id' => $boar->id, 'planned_date' => '2025-10-01', 'actual_date' => '2025-10-01', 'status' => 'completed', 'pregnancy_status' => 'pregnant']);
        $this->assertSame('gestating_sow', app(FeedStageResolver::class)->resolve($sow));
        $farrowing = Farrowing::create(['sow_id' => $sow->id, 'boar_id' => $boar->id, 'farrowing_date' => '2026-01-25', 'status' => 'active', 'total_piglets_born' => 5, 'live_births' => 5, 'recorded_by' => $this->actor->id]);
        $this->assertSame('lactating_sow', app(FeedStageResolver::class)->resolve($sow));
        $farrowing->update(['date_weaned' => '2026-02-01']);
        $this->assertSame('other_sows', app(FeedStageResolver::class)->resolve($sow));
    }

    public function test_override_is_audited_and_archive_removes_future_demand(): void
    {
        $pig = $this->pig(120);
        $this->standard();
        $this->standard('finisher', 4);
        app(FeedConfigurationService::class)->override($this->actor, $pig, 'finisher', 'Farm manager reviewed body condition.');
        $this->assertEquals(28, $this->plan()['required_kg']);
        $this->assertDatabaseHas('feed_stage_overrides', ['pig_id' => $pig->id, 'previous_stage' => 'grower', 'stage' => 'finisher']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'feed_stage_overridden']);
        $pig->delete();
        $this->assertSame(0, $this->plan()['animal_count']);
    }

    public function test_recipe_must_total_one_hundred_and_versions_are_preserved(): void
    {
        $service = app(FeedRecipeService::class);
        $first = $service->save($this->actor, ['feed_type_id' => $this->feed->id, 'items' => $this->ingredients()]);
        $second = $service->save($this->actor, ['feed_type_id' => $this->feed->id, 'items' => $this->ingredients(60, 40), 'effective_from' => '2026-02-02']);
        $this->assertSame($first->id, $service->resolve($this->feed, '2026-02-01')['version_id']);
        $this->assertSame($second->id, $service->resolve($this->feed, '2026-02-02')['version_id']);
        $this->assertEquals(70, $first->fresh()->ingredients[0]['quantity_per_100kg']);
        $this->expectException(ValidationException::class);
        $service->validateIngredients($this->org->id, $this->ingredients(70, 40));
    }

    public function test_planning_production_issue_variance_and_duplicate_confirmations_end_to_end(): void
    {
        $this->pig(120);
        $this->standard();
        $projection = $this->plan();
        $service = app(FeedProductionPlanService::class);
        $plan = $service->create($this->actor, ['feed_type_id' => $this->feed->id, 'planned_quantity_kg' => $projection['production_kg'], 'production_date' => '2026-02-01', 'submission_key' => (string) Str::uuid()]);
        $this->assertSame(0, FeedBatch::count());
        $this->assertEquals(1000, $this->maize->fresh()->quantity_in_stock);
        $data = ['batch_date' => '2026-02-01', 'finished_quantity_kg' => 14, 'actual_quantities' => [$this->maize->id => 9.8, $this->soya->id => 4.2]];
        $completed = $service->complete($this->actor, $plan, $data);
        $service->complete($this->actor, $plan, $data);
        $this->assertSame(1, FeedBatch::count());
        $this->assertEquals(990.2, $this->maize->fresh()->quantity_in_stock);
        $this->assertEquals(14, app(FeedPlanningService::class)->available($this->org->id, 'finished_feed', $this->feed->id));
        $payload = ['group_id' => $this->group->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => 3, 'submission_key' => (string) Str::uuid()];
        $issue = app(IssueFeedToGroupService::class)->execute($this->actor, $payload);
        $again = app(IssueFeedToGroupService::class)->execute($this->actor, $payload);
        $this->assertSame($issue->id, $again->id);
        $this->assertEquals(11, app(FeedPlanningService::class)->available($this->org->id, 'finished_feed', $this->feed->id));
        $report = app(FeedPlanningService::class)->consumption($this->org->id, '2026-02-01', '2026-02-01');
        $this->assertEquals(2, $report[0]['planned_kg']);
        $this->assertEquals(1, $report[0]['variance_kg']);
        $this->assertEquals(50, $report[0]['variance_percent']);
        app(FeedConfigurationService::class)->override($this->actor, $this->group->pigs->first(), 'finisher', 'Changed after delivery.');
        $this->assertEquals(2, $issue->fresh()->planned_quantity_kg);
    }

    public function test_multiple_deliveries_do_not_multiply_the_daily_plan(): void
    {
        $this->pig(120);
        $this->standard();
        $this->receive('finished_feed', $this->feed->id, 20);
        foreach ([1, 2] as $qty) {
            app(IssueFeedToGroupService::class)->execute($this->actor, ['group_id' => $this->group->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => $qty, 'submission_key' => (string) Str::uuid()]);
        }
        $row = app(FeedPlanningService::class)->consumption($this->org->id, '2026-02-01', '2026-02-01')[0];
        $this->assertEquals(2, $row['planned_kg']);
        $this->assertEquals(3, $row['actual_kg']);
    }

    public function test_shortage_is_based_on_net_production_and_purchase_form_is_prefilled(): void
    {
        $this->pig(120);
        $this->standard('grower', 1000);
        $plan = $this->plan('2026-02-01');
        $this->assertEquals(0, $plan['materials'][0]['shortfall_kg']);
        $plan = $this->plan();
        $this->assertEquals(3900, $plan['materials'][0]['shortfall_kg']);
        $this->actingAs($this->actor)->get(route('purchase-requests.create', ['planning_material' => $this->maize->id, 'planning_from' => '2026-02-01', 'planning_to' => '2026-02-07']))
            ->assertOk()->assertSee('3900')->assertSee('Feed planning shortage');
    }

    public function test_missing_material_rolls_back_production_plan_confirmation(): void
    {
        $service = app(FeedProductionPlanService::class);
        $plan = $service->create($this->actor, ['feed_type_id' => $this->feed->id, 'planned_quantity_kg' => 10000, 'production_date' => '2026-02-01', 'submission_key' => (string) Str::uuid()]);
        try {
            $service->complete($this->actor, $plan, ['batch_date' => '2026-02-01', 'finished_quantity_kg' => 10000]);
            $this->fail('Insufficient stock must stop production.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame('planned', $plan->fresh()->status);
        $this->assertSame(0, FeedBatch::count());
        $this->assertEquals(1000, $this->maize->fresh()->quantity_in_stock);
    }

    public function test_planning_screen_and_unprivileged_configuration_access(): void
    {
        $this->standard();
        $this->pig(120);
        $this->actingAs($this->actor)->get(route('mill.plan'))->assertRedirect(route('feed.mix-buy'));
        $this->get(route('feed.mix-buy'))->assertOk()->assertSee('Mix &amp; buy', false)->assertSee('Shared ration');
        $this->get(route('feed.planned-vs-actual'))->assertOk()->assertSee('Planned vs actual');
        $this->get(route('feed-setup.index'))->assertOk()->assertSee('History and settings')->assertSee('Standard history and scheduled changes');
        $role = Role::firstOrCreate(['name' => 'viewer']);
        $viewer = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'dataentry', 'role_id' => $role->id]);
        $this->actingAs($viewer)->post(route('planning-reports.standards'), [])->assertForbidden();
        $this->post(route('planning-reports.override'), [])->assertForbidden();
        $this->post(route('feed-production.plans'), [])->assertForbidden();
    }

    public function test_sold_dead_archived_and_unknown_age_animals_are_handled(): void
    {
        $this->standard();
        $this->pig(120);
        $sold = $this->pig(120);
        $dead = $this->pig(120);
        $archived = $this->pig(120);
        PigSale::create(['pig_id' => $sold->id, 'sale_date' => now(), 'sale_price' => 100, 'sold_by' => 'Test']);
        PigDeath::create(['pig_id' => $dead->id, 'death_date' => now(), 'death_cause' => 'unknown', 'recorded_by' => $this->actor->id]);
        $archived->delete();
        $this->assertEquals(1, $this->plan()['animal_count']);
        $this->assertEquals(14, $this->plan()['required_kg']);
        $unknown = $this->pig(120, ['date_of_birth' => now()->addDay()]);
        $this->assertNull(app(FeedStageResolver::class)->resolve($unknown));
        $this->assertFalse($this->plan()['demand_complete']);
    }

    public function test_empty_population_and_leap_month_decimal_requirements(): void
    {
        $this->assertSame(0, $this->plan()['animal_count']);
        $this->assertEquals(0, $this->plan()['production_kg']);
        $this->travelTo(now()->setDate(2028, 2, 1));
        $this->pig(120);
        $this->standard('grower', 1.2345, '2028-02-01');
        $plan = app(FeedPlanningService::class)->calculate($this->org->id, '2028-02-01', '2028-02-29');
        $this->assertEquals(35.8005, $plan['required_kg']);
        $this->receive('finished_feed', $this->feed->id, 100);
        $this->assertEquals(0, app(FeedPlanningService::class)->calculate($this->org->id, '2028-02-01', '2028-02-29')['production_kg']);
    }

    public function test_bad_recipes_units_inactive_materials_and_cross_farm_material_are_rejected(): void
    {
        $service = app(FeedRecipeService::class);
        foreach ([$this->ingredients(60, 30), [], [['raw_material_id' => 999999, 'quantity_per_100kg' => 100]], [$this->ingredients()[0], $this->ingredients()[0]]] as $items) {
            try {
                $service->validateIngredients($this->org->id, $items);
                $this->fail('Invalid recipe accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        foreach ([['unit' => 'bag'], ['unit' => 'kg', 'is_active' => false]] as $change) {
            $this->maize->update($change);
            try {
                $service->validateIngredients($this->org->id, $this->ingredients());
                $this->fail('Invalid material accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $other = Organization::create(['name' => 'Other feed farm', 'slug' => 'other-feed-farm']);
        try {
            $service->validateIngredients($other->id, $this->ingredients());
            $this->fail('Cross farm recipe accepted');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_issue_plan_does_not_depend_on_recipe_when_finished_feed_already_exists(): void
    {
        $this->pig(120);
        $this->standard();
        $this->receive('finished_feed', $this->feed->id, 10);
        $this->feed->recipes()->delete();
        $issue = app(IssueFeedToGroupService::class)->execute($this->actor, ['group_id' => $this->group->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => 1]);
        $this->assertEquals(2, $issue->planned_quantity_kg);
        $row = app(FeedPlanningService::class)->consumption($this->org->id, '2026-02-01', '2026-02-01', ['stage' => 'grower'])[0];
        $this->assertEquals(-1, $row['variance_kg']);
        $this->assertEquals(-50, $row['variance_percent']);
        $this->assertEmpty(app(FeedPlanningService::class)->consumption($this->org->id, '2026-02-01', '2026-02-01', ['stage' => 'piglet']));
        $this->actingAs($this->actor)->get(route('feed.issues.show', $issue))->assertOk()->assertSee('Saved daily feeding plan');
    }

    public function test_production_and_issue_roll_back_when_final_audit_fails(): void
    {
        $this->pig(120);
        $this->standard();
        $this->receive('finished_feed', $this->feed->id, 10);
        $before = InventoryMovement::count();
        $this->mock(FeedConfigurationService::class)->shouldReceive('audit')->twice()->andThrow(new \RuntimeException('Audit unavailable'));
        foreach ([fn () => app(ProduceFeedBatchService::class)->execute($this->actor, ['feed_type_id' => $this->feed->id, 'batch_date' => '2026-02-01', 'batch_size_kg' => 10]),
            fn () => app(IssueFeedToGroupService::class)->execute($this->actor, ['group_id' => $this->group->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => 2])] as $operation) {
            try {
                $operation();
                $this->fail('Audit failure must roll back');
            } catch (\RuntimeException $e) {
                $this->assertSame('Audit unavailable', $e->getMessage());
            }
        }
        $this->assertSame(0, FeedBatch::count());
        $this->assertSame(0, FeedIssue::count());
        $this->assertSame($before, InventoryMovement::count());
        $this->assertEquals(1000, $this->maize->fresh()->quantity_in_stock);
        $this->assertEquals(10, app(FeedPlanningService::class)->available($this->org->id, 'finished_feed', $this->feed->id));
    }

    public function test_decimal_actual_production_and_recipe_history_render(): void
    {
        $version = app(FeedRecipeService::class)->save($this->actor, ['feed_type_id' => $this->feed->id, 'items' => $this->ingredients()]);
        $batch = app(ProduceFeedBatchService::class)->execute($this->actor, ['feed_type_id' => $this->feed->id, 'batch_date' => '2026-02-01', 'batch_size_kg' => 1.1111, 'actual_quantities' => [$this->maize->id => 0.7777, $this->soya->id => 0.3333]])['feed_batch'];
        $this->assertEquals(1.1111, $batch->batch_size_kg);
        $this->assertEquals(0.7777, $batch->items->first()->quantity_used);
        $this->assertEquals($version->id, $batch->recipe_snapshot['version_id']);
        $this->actingAs($this->actor)->get(route('feed-production.index'))->assertOk()->assertSee('Recipe used / expected ingredients');
        // Recipe history moved from Mill → Mix & batches to Feed Setup → settings.
        $this->actingAs($this->actor)->get(route('feed-setup.index'))->assertOk()->assertSee('Recipe history')->assertSee('version #'.$version->id);
    }

    public function test_alert_configuration_is_audited_and_permissions_are_not_based_on_role_name(): void
    {
        $this->actingAs($this->actor)->post(route('planning-reports.alerts'), ['variance_percent' => 10, 'cover_days' => 3])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(10, Setting::get('feed.alerts.variance_percent'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'feed_alert_thresholds_updated']);
        foreach (['manager', 'dataentry', 'treasurer', 'accountant'] as $name) {
            $role = Role::firstOrCreate(['name' => $name]);
            $role->permissions()->detach();
            $actor = User::factory()->create(['organization_id' => $this->org->id, 'role' => $name, 'role_id' => $role->id]);
            $this->actingAs($actor)->get(route('planning-reports.index'))->assertForbidden();
            foreach (['planning-reports.standards', 'planning-reports.override', 'planning-reports.alerts', 'feed-production.recipes.store', 'feed-production.plans'] as $route) {
                $this->post(route($route), [])->assertForbidden();
            }
        }
    }

    public function test_legacy_issue_target_stays_unknown_on_later_deliveries_and_rollback_preserves_history(): void
    {
        $this->pig(120);
        $this->standard();
        $this->receive('finished_feed', $this->feed->id, 10);
        $first = app(IssueFeedToGroupService::class)->execute($this->actor, ['group_id' => $this->group->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => 1]);
        // Represents a row created before planning snapshots existed.
        DB::table('feed_issues')->where('id', $first->id)->update(['planning_snapshot' => null, 'planned_quantity_kg' => null]);
        $next = app(IssueFeedToGroupService::class)->execute($this->actor, ['group_id' => $this->group->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => 1]);
        $this->assertNull($next->planned_quantity_kg);
        $this->assertSame([], $next->planning_snapshot['rows']);
        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2028_10_14_000000_extend_feed_planning_and_history.php'))->down();
    }

    public function test_large_population_decimal_demand_and_zero_target_percentage(): void
    {
        $pig = $this->pig(120);
        $this->standard('grower', 1.2345);
        $template = $pig->getAttributes();
        unset($template['id']);
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = array_merge($template, ['code' => 'FEED-LOAD-'.$i, 'local_id' => 10000 + $i]);
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('pigs_management')->insert($chunk);
        }
        $plan = $this->plan('2026-02-01');
        $this->assertSame(1001, $plan['animal_count']);
        $this->assertEquals(1235.7345, $plan['required_kg']);
        $empty = Group::create(['name' => 'Empty group', 'type' => 'pen']);
        $this->receive('finished_feed', $this->feed->id, 10);
        app(IssueFeedToGroupService::class)->execute($this->actor, ['group_id' => $empty->id, 'feed_type_id' => $this->feed->id, 'quantity_kg' => 1]);
        $row = app(FeedPlanningService::class)->consumption($this->org->id, '2026-02-01', '2026-02-01')[0];
        $this->assertNull($row['variance_percent']);
        $this->assertEquals(1, $row['variance_kg']);
    }

    public function test_simple_planning_views_keep_filters_and_setup_separate(): void
    {
        $this->standard();
        $this->pig(120);
        $other = Group::create(['name' => 'Other group', 'type' => 'pen']);
        $this->pig(120, ['group_id' => $other->id]);
        $response = $this->actingAs($this->actor)->get(route('planning-reports.index', ['group_id' => $this->group->id]));
        $response->assertOk()->assertSee('Feed to prepare')->assertDontSee('Save feeding standard');
        $this->assertEquals(14, $response->viewData('plan')['required_kg']);
        $this->get(route('planning-reports.index', ['tab' => 'setup']))->assertOk()->assertSee('Save feeding standard')->assertDontSee('Feed to prepare');
        $report = $this->get(route('planning-reports.index', ['tab' => 'consumption', 'period' => 'weekly']));
        $report->assertOk()->assertSee('No feed issues recorded');
        $this->assertSame('2026-01-26', $report->viewData('plan')['from']);
        $this->assertSame('2026-02-01', $report->viewData('plan')['to']);
        $this->get(route('planning-reports.index', ['period' => 'custom']))->assertSessionHasErrors(['from', 'to']);
        $this->get(route('planning-reports.index', ['period' => 'custom', 'from' => '2026-02-07', 'to' => '2026-02-01']))->assertSessionHasErrors('to');
    }

    public function test_feeding_sheet_shows_each_stage_herd_feed_and_expected_kg_and_flags_partial_demand(): void
    {
        $this->standard();
        $this->pig(120);
        $this->pig(80);
        $otherFeed = FeedType::create(['name' => 'Weaner only feed'])->fresh();
        app(FeedConfigurationService::class)->standard($this->actor, ['stage' => 'weaner', 'daily_kg' => 1, 'feed_type_id' => $otherFeed->id, 'effective_from' => '2026-02-01']);
        $this->receive('finished_feed', $this->feed->id, 10);
        $response = $this->actingAs($this->actor)->get(route('feed.issues.create'))->assertOk();
        $stages = $response->viewData('stages')->keyBy('stage');
        $this->assertSame(1, $stages['grower']['animal_count']);
        $this->assertSame($this->feed->id, $stages['grower']['feed_type_id']);
        $this->assertEquals(2, $stages['grower']['expected_kg']);
        $this->assertSame($otherFeed->id, $stages['weaner']['feed_type_id']);
        $this->assertSame(0, $stages['boar']['animal_count']);
        $this->assertNull($stages['boar']['expected_kg']);
        $this->assertTrue($response->viewData('demandComplete'));
        $this->pig(120, ['date_of_birth' => now()->addDay()]);
        $response = $this->get(route('feed.issues.create'))->assertOk();
        $this->assertFalse($response->viewData('demandComplete'));
        $response->assertSee('Some expected amounts are missing');
    }

    public function test_incomplete_forecast_cannot_prefill_a_purchase_request(): void
    {
        $this->standard('grower', 1000);
        $this->pig(120);
        $this->pig(80);
        $this->actingAs($this->actor)->get(route('purchase-requests.create', ['planning_material' => $this->maize->id, 'planning_from' => '2026-02-01', 'planning_to' => '2026-02-07']))->assertStatus(422);
    }
}
