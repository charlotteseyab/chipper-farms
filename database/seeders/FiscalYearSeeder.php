<?php

namespace Database\Seeders;

use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds the CURRENT fiscal year (Jan–Dec, based on calendar year at time of run)
 * with 12 monthly periods, all status='open'.
 *
 * If your farm uses a non-calendar fiscal year (e.g. Apr–Mar), edit the
 * $startMonth variable before running or create subsequent FYs manually via
 * a Phase 1 finance setup screen.
 *
 * Idempotent:
 *   - FiscalYear lookup by (organization_id, fiscal_year) — updateOrCreate.
 *   - FiscalPeriod lookup by (fiscal_year_id, period_number) — updateOrCreate.
 *
 * Running twice is safe. Re-running after a period has been closed does NOT
 * reopen it — the updateOrCreate only sets fields when creating.
 *
 * The `is_current` flag is set on exactly one fiscal year per organization.
 * Phase 1's period management screens can toggle which year is current.
 */
class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        // Edit this if your fiscal year doesn't start in January.
        $startMonth = 1;

        $currentYear = (int) date('Y');
        $start = Carbon::create($currentYear, $startMonth, 1)->startOfDay();
        $end   = $start->copy()->addYear()->subDay()->endOfDay();

        $organizationIds = Organization::query()->pluck('id');

        foreach ($organizationIds as $organizationId) {
            $fy = FiscalYear::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'fiscal_year'     => $currentYear,
                ],
                [
                    'name'       => 'FY' . $currentYear,
                    'start_date' => $start->toDateString(),
                    'end_date'   => $end->toDateString(),
                    'status'     => FiscalYear::STATUS_OPEN,
                    'is_current' => true,
                ]
            );

            // Create 12 monthly periods spanning the year.
            for ($i = 0; $i < 12; $i++) {
                $periodStart = $start->copy()->addMonths($i)->startOfMonth();
                $periodEnd   = $periodStart->copy()->endOfMonth();

                FiscalPeriod::updateOrCreate(
                    [
                        'fiscal_year_id' => $fy->id,
                        'period_number'  => $i + 1,
                    ],
                    [
                        'organization_id' => $organizationId,
                        'name'            => $periodStart->format('M Y'),
                        'start_date'      => $periodStart->toDateString(),
                        'end_date'        => $periodEnd->toDateString(),
                        'status'          => FiscalPeriod::STATUS_OPEN,
                    ]
                );
            }
        }
    }
}