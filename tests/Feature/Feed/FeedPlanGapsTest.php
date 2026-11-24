<?php

namespace Tests\Feature\Feed;

use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Structure;
use App\Models\User;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedStageResolver;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The three gaps against the implementation plan's Milestone 2: stage rules
 * from the farm's own growth-stage ages, projection by day, month and chosen
 * dates, and feed recorded by pen.
 */
class FeedPlanGapsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private FeedType $feed;

    private int $source = 950000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 2, 10)->setTime(8, 0));
        $this->org = Organization::create(['name' => 'Feed farm', 'slug' => 'feed-farm']);
        app(CurrentOrganization::class)->bind($this->org);
        $role = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view_feed_production', 'manage_feed_production', 'manage_feed_issues', 'view_group_pools', 'view_finance', 'view_raw_materials'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'finance']);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
        $this->actor = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin', 'role_id' => $role->id]);
        $maize = RawMaterial::create(['name' => 'Maize', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 2])->fresh();
        $this->feed = FeedType::create(['name' => 'Grower meal', 'is_active' => true])->fresh();
        $this->feed->recipes()->create(['raw_material_id' => $maize->id, 'quantity_per_100kg' => 100]);
        foreach (['weaner', 'grower'] as $stage) {
            app(FeedConfigurationService::class)->standard($this->actor, ['stage' => $stage, 'daily_kg' => 2, 'feed_type_id' => $this->feed->id, 'effective_from' => now()->toDateString()]);
        }
    }

    /* --- 1. Stage rules ------------------------------------------------ */

    public function test_without_farm_ages_the_standard_ones_apply(): void
    {
        $this->assertSame(['piglet' => 56, 'weaner' => 98, 'grower' => 154, 'farm_set' => false], FeedStageResolver::limits($this->org->id));
        $this->assertSame('weaner', app(FeedStageResolver::class)->resolve($this->pig(70)));
    }

    public function test_the_farms_growth_stage_ages_decide_the_feeding_stage(): void
    {
        $this->farmAges(30, 60, 120);
        $pig = $this->pig(70);  // a weaner by the standard ages, a grower by this farm's

        $this->assertSame('grower', app(FeedStageResolver::class)->resolve($pig));
        $this->assertSame(['grower' => 1], $this->plan('2026-02-10', '2026-02-10')['stage_counts']);

        $this->actingAs($this->actor)->get(route('feed-setup.index'))->assertOk()
            ->assertSee('piglet</strong> up to 30 days', false)->assertSee("These are your farm's growth-stage ages.");
    }

    public function test_a_forecast_moves_pigs_up_at_the_farms_ages(): void
    {
        $this->farmAges(30, 60, 120);
        $this->pig(58);  // weaner today, grower from day 61: in three days

        $plan = $this->plan('2026-02-10', '2026-02-16');
        $byStage = collect($plan['rows'])->groupBy('stage')->map->sum('applicable_days');

        $this->assertEquals(3, $byStage['weaner'], 'Days 58, 59 and 60.');
        $this->assertEquals(4, $byStage['grower']);
    }

    public function test_ages_that_do_not_increase_are_ignored(): void
    {
        $this->farmAges(90, 60, 120);

        $this->assertFalse(FeedStageResolver::limits($this->org->id)['farm_set']);
    }

    /* --- 2. Periods ------------------------------------------------------- */

    public function test_mix_and_buy_projects_today_the_month_and_chosen_dates(): void
    {
        $this->pig(120);

        $today = $this->actingAs($this->actor)->get(route('feed.mix-buy', ['period' => 'today']))->assertOk();
        $this->assertCount(1, $today->viewData('columns'));
        $this->assertEquals(2, $today->viewData('whole')['required_kg'], '1 grower × 2 kg × 1 day');

        $month = $this->actingAs($this->actor)->get(route('feed.mix-buy', ['period' => 'month']))->assertOk()->assertSee('the rest of this month');
        $this->assertSame('2026-02-28', $month->viewData('range')['to']->toDateString());
        $this->assertEquals(38, $month->viewData('whole')['required_kg'], '19 days from 10 to 28 Feb');

        $custom = $this->actingAs($this->actor)->get(route('feed.mix-buy', ['period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-10']))->assertOk();
        $this->assertCount(2, $custom->viewData('columns'), 'A week, then 3 days.');
        $this->assertSame(3, $custom->viewData('columns')[1]['days']);
        $this->assertEquals(20, $custom->viewData('whole')['required_kg']);
    }

    public function test_chosen_dates_must_make_sense(): void
    {
        $this->actingAs($this->actor)->get(route('feed.mix-buy', ['period' => 'custom', 'from' => '2026-03-10', 'to' => '2026-03-01']))->assertSessionHasErrors('to');
        $this->actingAs($this->actor)->get(route('feed.mix-buy', ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2027-06-01']))->assertSessionHasErrors('to');
    }

    public function test_mixing_returns_to_the_same_period(): void
    {
        $this->actingAs($this->actor)->post(route('feed-production.mix'), [
            'submission_key' => (string) Str::uuid(), 'feed_type_id' => $this->feed->id, 'batch_date' => now()->toDateString(),
            'batch_size_kg' => 1, 'return' => 'mix-buy', 'period' => 'month',
        ])->assertRedirect(route('feed.mix-buy', ['period' => 'month']));
    }

    /* --- 3. Feed by pen ------------------------------------------------- */

    public function test_a_feeding_can_record_the_pen_it_was_given_in(): void
    {
        [$penA, $penB] = $this->pens();
        $this->pig(120, $penA);
        $this->pig(120, $penB);
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('finished_feed', $this->feed->id, 50, 2, 'stock_log', ++$this->source));

        $this->actingAs($this->actor)->get(route('feed.issues.create'))->assertOk()
            ->assertSee('Whole stage (all pens)')->assertSee('Where growers are')->assertSee('Barn A · Pen 1');

        $this->actingAs($this->actor)->post(route('feed.issues.store'), ['entries' => [
            ['stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 2, 'room_id' => $penA->id, 'submission_key' => (string) Str::uuid()],
        ]])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'Grower · Barn A · Pen 1'));
        $this->actingAs($this->actor)->post(route('feed.issues.store'), ['entries' => [
            ['stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 2.5, 'room_id' => $penB->id, 'submission_key' => (string) Str::uuid()],
        ]])->assertSessionHasNoErrors();

        $this->assertSame([$penA->id, $penB->id], FeedIssue::orderBy('id')->pluck('room_id')->map(fn ($id) => (int) $id)->all());

        // Both pens add up against the stage's one daily ration (2 growers × 2 kg).
        $all = app(FeedPlanningService::class)->consumption($this->org->id, '2026-02-10', '2026-02-10');
        $this->assertCount(1, $all);
        $this->assertEquals(4, $all[0]['planned_kg']);
        $this->assertEquals(4.5, $all[0]['actual_kg']);
        $this->assertEqualsCanonicalizing(['Pen 1', 'Pen 2'], $all[0]['rooms']);

        // Filtered to one pen, only that pen's feeding.
        $one = app(FeedPlanningService::class)->consumption($this->org->id, '2026-02-10', '2026-02-10', ['room_id' => $penB->id]);
        $this->assertEquals(2.5, $one[0]['actual_kg']);

        $this->actingAs($this->actor)->get(route('feed.issues.index'))->assertOk()->assertSee('Grower · Barn A · Pen 2');
    }

    public function test_a_pen_on_another_farm_is_refused(): void
    {
        $other = Organization::create(['name' => 'Other', 'slug' => 'other']);
        $theirs = Room::withoutGlobalScopes()->create(['organization_id' => $other->id, 'structure_id' => Structure::withoutGlobalScopes()->create(['organization_id' => $other->id, 'name' => 'X', 'code' => 'X'])->id, 'name' => 'Theirs', 'code' => 'T1']);

        $this->actingAs($this->actor)->post(route('feed.issues.store'), ['entries' => [
            ['stage' => 'grower', 'feed_type_id' => $this->feed->id, 'quantity_kg' => 2, 'room_id' => $theirs->id],
        ]])->assertSessionHasErrors('entries.0.room_id');
    }

    /* --- 4. Sows through their cycle ----------------------------------- */

    public function test_a_pregnant_sow_is_planned_as_lactating_from_her_farrowing_date(): void
    {
        $this->sowRations();
        $sow = $this->sow();
        \App\Models\Mating::create(['male_id' => $this->boar()->id, 'female_id' => $sow->id, 'planned_date' => '2025-10-20', 'actual_date' => '2025-10-20',
            'status' => 'completed', 'pregnancy_status' => 'pregnant', 'expected_farrowing_date' => '2026-02-13']);

        $days = $this->daysByStage('2026-02-10', '2026-02-16');

        $this->assertEquals(3, $days['gestating_sow'], '10, 11 and 12 Feb.');
        $this->assertEquals(4, $days['lactating_sow'], 'From her expected farrowing on the 13th.');
        $this->assertSame(1, $this->plan('2026-02-10', '2026-02-10')['stage_counts']['gestating_sow'], 'Today is what the records say.');
    }

    public function test_a_lactating_sow_goes_back_to_other_sows_at_weaning_age(): void
    {
        $this->sowRations();
        $this->farrowing($this->sow(), daysAgo: 42, live: 0);  // 45-day weaning: 3 more days suckling

        $days = $this->daysByStage('2026-02-10', '2026-02-16');

        $this->assertEquals(3, $days['lactating_sow']);
        $this->assertEquals(4, $days['other_sows']);
    }

    public function test_an_overdue_weaning_moves_the_sow_on_from_tomorrow_with_a_note(): void
    {
        $this->sowRations();
        $this->farrowing($this->sow(), daysAgo: 60, live: 0);

        $plan = $this->plan('2026-02-10', '2026-02-12');
        $days = collect($plan['rows'])->groupBy('stage')->map->sum('applicable_days');

        $this->assertEquals(1, $days['lactating_sow'], 'Today, as recorded.');
        $this->assertEquals(2, $days['other_sows']);
        $this->assertStringContainsString('still suckling a litter past weaning age', implode(' ', $plan['notes']));
    }

    /* --- 5. Litters still on the sow ------------------------------------- */

    public function test_a_litters_live_piglets_are_counted_and_age_into_weaners(): void
    {
        $this->standard('piglet', 0.1);
        $litter = $this->farrowing($this->sow(), daysAgo: 50, live: 9);  // with the farm's ages below, piglet to 52 days
        \App\Models\PigletDeath::create(['farrowing_id' => $litter->id, 'death_date' => now()->subDays(40)->toDateString(), 'count' => 1, 'cause' => 'Crushed']);
        $this->farmAges(52, 98, 154);
        config(['breeding.cycle.birth_to_weaning_days' => 56]);

        $plan = $this->plan('2026-02-10', '2026-02-16');

        $this->assertSame(8, $plan['litter_piglets'], '9 born alive, 1 died.');
        $this->assertSame(8, $plan['stage_counts']['piglet']);
        $days = collect($plan['rows'])->groupBy('stage')->map->sum('animal_days');
        $this->assertEquals(3 * 8, $days['piglet'], 'Days 50, 51 and 52.');
        $this->assertEquals(4 * 8, $days['weaner'], 'Then weaners: they will be weaned onto stock.');
    }

    public function test_suckling_piglets_without_a_creep_ration_are_a_note_not_a_gap(): void
    {
        $this->sowRations();  // the sow herself has a ration; only her piglets have none
        $this->farrowing($this->sow(), daysAgo: 10, live: 8);

        $plan = $this->plan('2026-02-10', '2026-02-10');

        $this->assertTrue($plan['demand_complete'], 'Not feeding creep is a normal choice.');
        $this->assertStringContainsString('Piglets still on the sow are not in this plan', implode(' ', $plan['notes']));

        $this->actingAs($this->actor)->get(route('feed-setup.index'))->assertOk()->assertSee('+ 8 still on their sows');
    }

    public function test_a_litter_past_weaning_age_is_left_out_with_a_note(): void
    {
        $this->farrowing($this->sow(), daysAgo: 90, live: 10);

        $plan = $this->plan('2026-02-10', '2026-02-10');

        $this->assertSame(0, $plan['litter_piglets']);
        $this->assertStringContainsString('1 litter past weaning age is not weaned in the records', implode(' ', $plan['notes']));

        $this->actingAs($this->actor)->get(route('feed.mix-buy', ['period' => 'today']))->assertOk()
            ->assertSee('Good to know')->assertSee('Record the weaning to add them.');
    }

    private function sowRations(): void
    {
        foreach (['gestating_sow' => 2.5, 'lactating_sow' => 6, 'other_sows' => 2.5] as $stage => $kg) {
            $this->standard($stage, $kg);
        }
    }

    private function standard(string $stage, float $kg): void
    {
        app(FeedConfigurationService::class)->standard($this->actor, ['stage' => $stage, 'daily_kg' => $kg, 'feed_type_id' => $this->feed->id, 'effective_from' => now()->toDateString()]);
    }

    private function sow(): PigManagement
    {
        return PigManagement::create(['ear_tag_number' => '', 'date_of_birth' => now()->subYears(2), 'stock_type' => 'breeding', 'gender' => 'F']);
    }

    private function boar(): PigManagement
    {
        return PigManagement::create(['ear_tag_number' => '', 'date_of_birth' => now()->subYears(2), 'stock_type' => 'breeding', 'gender' => 'M']);
    }

    private function farrowing(PigManagement $sow, int $daysAgo, int $live): \App\Models\Farrowing
    {
        return \App\Models\Farrowing::create(['sow_id' => $sow->id, 'farrowing_date' => now()->subDays($daysAgo)->toDateString(), 'live_births' => $live,
            'total_piglets_born' => $live, 'number_of_males' => intdiv($live, 2), 'number_of_females' => $live - intdiv($live, 2),
            'recorded_by' => $this->actor->id, 'status' => 'active']);
    }

    private function daysByStage(string $from, string $to): \Illuminate\Support\Collection
    {
        return collect($this->plan($from, $to)['rows'])->groupBy('stage')->map->sum('applicable_days');
    }

    private function farmAges(int $piglet, int $weaner, int $grower): void
    {
        Setting::set('stages.piglet.max_days', $piglet);
        Setting::set('stages.weaner.max_days', $weaner);
        Setting::set('stages.grower.max_days', $grower);
    }

    private function pig(int $age, ?Room $room = null): PigManagement
    {
        return PigManagement::create(['ear_tag_number' => '', 'date_of_birth' => now()->subDays($age), 'stock_type' => 'standard', 'gender' => 'F',
            'group_id' => Group::firstOrCreate(['name' => 'Pen group', 'type' => 'pen'])->id, 'room_id' => $room?->id]);
    }

    /** @return array{0: Room, 1: Room} */
    private function pens(): array
    {
        $barn = Structure::create(['name' => 'Barn A', 'code' => 'A']);

        return [Room::create(['structure_id' => $barn->id, 'name' => 'Pen 1', 'code' => 'A1']), Room::create(['structure_id' => $barn->id, 'name' => 'Pen 2', 'code' => 'A2'])];
    }

    private function plan(string $from, string $to): array
    {
        return app(FeedPlanningService::class)->calculate($this->org->id, $from, $to);
    }
}
