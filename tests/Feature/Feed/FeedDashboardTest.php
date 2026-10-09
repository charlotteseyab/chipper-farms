<?php

namespace Tests\Feature\Feed;

use App\Models\FeedType;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Role;
use App\Models\User;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Feed\FeedPerformanceService;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Milestone 2: feed dashboard and feed conversion. */
class FeedDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private FeedType $grower;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(9, 0));
        $this->org = Organization::create(['name' => 'Dashboard farm', 'slug' => 'dashboard-farm']);
        app(CurrentOrganization::class)->bind($this->org);
        $role = Role::firstOrCreate(['name' => 'admin']);
        foreach (['view_feed_production', 'manage_feed_production', 'manage_feed_issues', 'view_finance'] as $name) {
            $role->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['name' => $name], ['description' => $name, 'module' => 'feed'])->id);
        }
        $this->actor = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin', 'role_id' => $role->id]);
        $this->actingAs($this->actor);
        $this->grower = FeedType::create(['name' => 'Grower'])->fresh();
        app(FeedConfigurationService::class)->standard($this->actor, ['stage' => 'grower', 'feed_type_id' => $this->grower->id, 'daily_kg' => 2, 'effective_from' => now()->toDateString()]);
        DB::transaction(fn () => app(InventoryService::class)->recordReceive('finished_feed', $this->grower->id, 500, 4, 'stock_log', 1));
    }

    public function test_feed_conversion_divides_feed_by_gain_for_the_stage(): void
    {
        // A grower (about 120 days old) gains 30 kg over the month.
        $pig = $this->pig(120);
        $this->weigh($pig, '2026-03-01', 50);
        $this->weigh($pig, '2026-03-31', 80);
        $this->feed('2026-03-10', 60);
        $this->feed('2026-03-20', 30);

        $report = app(FeedPerformanceService::class)->forPeriod($this->org->id, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));
        $grower = $report['rows']['grower'];

        $this->assertEquals(90, $grower['feed_kg']);
        $this->assertEquals(30, $grower['gain_kg']);
        $this->assertEquals(3.0, $grower['fcr']);
        $this->assertEquals(12.0, $grower['cost_per_kg_gain'], '90 kg at GH₵ 4 over 30 kg gained');
        $this->assertSame(1, $grower['pigs_weighed']);
        $this->assertFalse($grower['partial']);
    }

    public function test_conversion_is_flagged_partial_when_not_every_pig_was_weighed(): void
    {
        $weighed = $this->pig(120);
        $this->pig(121);
        $this->weigh($weighed, '2026-03-01', 50);
        $this->weigh($weighed, '2026-03-31', 70);
        $this->feed('2026-03-10', 60);

        $grower = app(FeedPerformanceService::class)->forPeriod($this->org->id, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'))['rows']['grower'];

        $this->assertSame(1, $grower['pigs_weighed']);
        $this->assertSame(2, $grower['pigs_now']);
        $this->assertTrue($grower['partial']);
    }

    public function test_dashboard_shows_today_against_the_ration(): void
    {
        $this->pig(120);
        $this->pig(130);
        $this->feed(now()->toDateTimeString(), 3);   // ration is 2 x 2 = 4 kg

        $this->get(route('feed-dashboard.index'))
            ->assertOk()
            ->assertSee('Under ration')
            ->assertSee('of 4 kg by ration')
            ->assertSee('Covered');

        $this->get(route('feed-performance.index'))->assertOk()->assertSee('Feed Conversion');
    }

    private function pig(int $ageDays): PigManagement
    {
        static $n = 0;

        return PigManagement::create(['ear_tag_number' => '', 'code' => 'DASH-'.(++$n), 'breed' => 'LW', 'gender' => 'F',
            'stock_type' => 'standard', 'date_of_birth' => now()->subDays($ageDays)->toDateString()]);
    }

    private function weigh(PigManagement $pig, string $date, float $kg): void
    {
        PigWeight::create(['pig_id' => $pig->id, 'weight' => $kg, 'weigh_date' => $date, 'weight_unit' => 'kg', 'weighed_by' => $this->actor->id]);
    }

    private function feed(string $when, float $kg): void
    {
        app(IssueFeedToGroupService::class)->execute($this->actor, [
            'feeding_stage' => 'grower', 'feed_type_id' => $this->grower->id, 'quantity_kg' => $kg, 'issued_at' => $when,
        ]);
    }
}
