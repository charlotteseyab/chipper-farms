<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_scoped_reports_for_a_selected_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.reports', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Farm reports')
            ->assertSee('Herd movement reconciliation')
            ->assertSee('01 Aug 2026')
            ->assertSee('31 Aug 2026');
    }

    public function test_reports_reject_an_inverted_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('settings.reports'))
            ->get(route('settings.reports', ['from' => '2026-08-31', 'to' => '2026-08-01']))
            ->assertRedirect(route('settings.reports'))
            ->assertSessionHasErrors('to');
    }

    public function test_csv_export_uses_the_selected_period(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('settings.reports.export.csv', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('2026-08-01 to 2026-08-31', $response->streamedContent());
    }

    public function test_pdf_export_renders_for_the_selected_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.reports.export.pdf', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * The farm report counts what the system records today: a reversed sale
     * is not an exit, weaned piglets are not "manually added", a mating not
     * checked yet is not a failed pregnancy, and open health cases come from
     * the case status.
     */
    public function test_the_farm_report_reflects_how_records_are_kept(): void
    {
        $user = User::factory()->create();
        $org = $user->organization_id;
        app(\App\Support\CurrentOrganization::class)->bind($user->organization);
        $this->travelTo('2026-08-20 12:00');
        $pig = fn (string $code, string $gender = 'F') => \App\Models\PigManagement::create(['organization_id' => $org, 'ear_tag_number' => $code,
            'code' => $code, 'breed' => 'LW', 'gender' => $gender, 'stock_type' => 'standard', 'date_of_birth' => '2026-01-01']);

        $sow = $pig('SOW');
        $boar = $pig('BOAR', 'M');
        $weaned = $pig('WEANED');
        \Illuminate\Support\Facades\DB::table('weanings')->insert(['pig_id' => $weaned->id, 'weaning_date' => '2026-08-10',
            'age_at_weaning_days' => 30, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $sold = $pig('SOLD');
        $sale = \Illuminate\Support\Facades\DB::table('pig_sales')->insertGetId(['organization_id' => $org, 'pig_id' => $sold->id, 'sale_date' => '2026-08-15',
            'sale_price' => 100, 'buyer_name' => 'B', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => now()]); // reversed

        foreach (['pregnant', 'not_pregnant', 'pending'] as $status) {
            \Illuminate\Support\Facades\DB::table('matings')->insert(['male_id' => $boar->id, 'female_id' => $sow->id,
                'planned_date' => '2026-08-05', 'actual_date' => '2026-08-05', 'pregnancy_status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        }
        \Illuminate\Support\Facades\DB::table('health_records')->insert([
            ['organization_id' => $org, 'pig_id' => $sow->id, 'record_date' => '2026-08-12', 'condition' => 'Cough', 'case_status' => 'under_treatment', 'voided_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $org, 'pig_id' => $sow->id, 'record_date' => '2026-08-13', 'condition' => 'Lame', 'case_status' => 'closed', 'voided_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $org, 'pig_id' => $sow->id, 'record_date' => '2026-08-14', 'condition' => 'Typo', 'case_status' => 'open', 'voided_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $report = app(\App\Services\Reports\SystemReportService::class)->build($org, \Carbon\CarbonImmutable::parse('2026-08-01'), \Carbon\CarbonImmutable::parse('2026-08-31'));

        $this->assertSame(1, $report['herdMovement']['weaned_into_stock']);
        $this->assertSame(3, $report['herdMovement']['manually_added']);
        $this->assertSame(0, $report['herdMovement']['sold'], 'A reversed sale did not take the pig away.');
        $this->assertSame(4, $report['herdMovement']['closing']);
        $this->assertSame(0, $report['herdMovement']['variance']);
        $this->assertSame(2, $report['breeding']['pregnancy_checked']);
        $this->assertEquals(50.0, $report['breeding']['pregnancy_rate'], 'The mating still pending a check is not a failure.');
        $this->assertSame(2, $report['health']['cases_opened'], 'The voided case is left out.');
        $this->assertSame(1, $report['health']['open_now']);
        $this->assertSame($user->organization_id, app(\App\Support\CurrentOrganization::class)->id(), 'The current farm is restored after the report.');
    }

    public function test_the_csv_herd_columns_line_up_with_their_values(): void
    {
        $user = User::factory()->create();

        $csv = $this->actingAs($user)->get(route('settings.reports.export.csv', ['from' => '2026-08-01', 'to' => '2026-08-31']))->streamedContent();
        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $header = array_search(['HERD MOVEMENT RECONCILIATION'], $lines, true);

        $this->assertSame(['Opening', 'Weaned into stock', 'Bought', 'Added or imported', 'Sold', 'Deaths', 'Transferred or culled', 'Closing', 'Variance'], $lines[$header + 1]);
        $this->assertCount(9, $lines[$header + 2]);
    }
}
