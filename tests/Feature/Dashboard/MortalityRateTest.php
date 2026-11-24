<?php

namespace Tests\Feature\Dashboard;

use App\Models\Farrowing;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PigletDeath;
use App\Services\Dashboard\DashboardLivestockMetrics;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 30-day mortality = deaths ÷ every animal on the farm in those 30 days
 * (alive now + died + sold or moved out).
 */
class MortalityRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_rate_counts_every_animal_on_the_farm_in_the_period(): void
    {
        // A farm that has not set its growth-stage ages.
        $farm = Organization::create(['name' => 'Farm', 'slug' => 'farm']);
        $this->app->make(CurrentOrganization::class)->bind($farm);
        $pig = fn (string $tag) => PigManagement::create(['organization_id' => $farm->id, 'ear_tag_number' => $tag,
            'code' => $tag, 'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'breeding', 'date_of_birth' => now()->subYears(2)->toDateString()]);

        $sow = $pig('SOW');
        $pig('P-1');
        $sold = $pig('P-2');

        // A litter of 10, 2 of which died this week: 8 alive, unweaned.
        $litter = Farrowing::create(['sow_id' => $sow->id, 'farrowing_date' => now()->subDays(10)->toDateString(),
            'live_births' => 10, 'total_piglets_born' => 10, 'status' => 'active',
            'recorded_by' => \App\Models\User::factory()->create(['organization_id' => $farm->id])->id]);
        PigletDeath::create(['farrowing_id' => $litter->id, 'death_date' => now()->subDays(3)->toDateString(), 'count' => 2, 'cause' => 'crushed', 'recorded_by' => $litter->recorded_by]);

        // One pig sold this month (it leaves the herd).
        DB::table('pig_sales')->insert(['organization_id' => $farm->id, 'pig_id' => $sold->id, 'sale_date' => now()->subDays(5)->toDateString(),
            'sale_price' => 100, 'buyer_name' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $sold->delete();

        $metrics = app(DashboardLivestockMetrics::class)->summary($farm->id);

        // Alive: sow + P-1 + 8 piglets = 10. Died: 2. Left: 1. 2 ÷ 13.
        $this->assertSame(10, $metrics['active_animals'], 'Unweaned piglets count even when stage ages are not set.');
        $this->assertSame(2, $metrics['deaths_30_days']);
        $this->assertSame(1, $metrics['left_30_days']);
        $this->assertSame(round(2 / 13 * 100, 2), $metrics['mortality_rate_30_days']);
        $this->assertSame(8, $metrics['stages']['piglet']);
    }
}
