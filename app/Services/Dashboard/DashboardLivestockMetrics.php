<?php

namespace App\Services\Dashboard;

use App\Models\Farrowing;
use App\Models\Organization;
use App\Models\PigDeath;
use App\Models\PigletDeath;
use App\Models\PigManagement;
use App\Models\Setting;

class DashboardLivestockMetrics
{
    public function summary(?int $organizationId, bool $platformWide = false): array
    {
        $organizationIds = $organizationId
            ? collect([$organizationId])
            : ($platformWide ? Organization::query()->orderBy('id')->pluck('id') : collect());

        $summary = [
            'active_animals' => 0,
            'deaths_30_days' => 0,
            'left_30_days' => 0,
            'deaths_all_time' => 0,
            'mortality_rate_30_days' => 0.0,
            'stages' => ['piglet' => 0, 'weaner' => 0, 'grower' => 0, 'finisher' => 0, 'unclassified' => 0],
            'configured_organizations' => 0,
            'organization_count' => $organizationIds->count(),
        ];

        foreach ($organizationIds as $id) {
            $farm = $this->farmSummary((int) $id);
            $summary['active_animals'] += $farm['active_animals'];
            $summary['deaths_30_days'] += $farm['deaths_30_days'];
            $summary['left_30_days'] += $farm['left_30_days'];
            $summary['deaths_all_time'] += $farm['deaths_all_time'];
            $summary['configured_organizations'] += $farm['stages_configured'] ? 1 : 0;

            foreach ($summary['stages'] as $stage => $count) {
                $summary['stages'][$stage] += $farm['stages'][$stage];
            }
        }

        // Every animal on the farm at some point in the 30 days: those alive
        // now, those that died, and those sold or moved out. Leaving out the
        // ones that left made the rate look higher than it was.
        $populationAtRisk = $summary['active_animals'] + $summary['deaths_30_days'] + $summary['left_30_days'];
        $summary['mortality_rate_30_days'] = $populationAtRisk > 0
            ? round(($summary['deaths_30_days'] / $populationAtRisk) * 100, 2)
            : 0.0;
        $summary['stages_configured'] = $summary['organization_count'] > 0
            && $summary['configured_organizations'] === $summary['organization_count'];

        return $summary;
    }

    private function farmSummary(int $organizationId): array
    {
        $pigletMax = $this->positiveIntegerSetting($organizationId, 'stages.piglet.max_days');
        $weanerMax = $this->positiveIntegerSetting($organizationId, 'stages.weaner.max_days');
        $growerMax = $this->positiveIntegerSetting($organizationId, 'stages.grower.max_days');
        $configured = $pigletMax !== null && $weanerMax !== null && $growerMax !== null
            && $pigletMax < $weanerMax && $weanerMax < $growerMax;

        $activePigs = PigManagement::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->active()
            ->get(['id', 'date_of_birth']);

        $stages = ['piglet' => 0, 'weaner' => 0, 'grower' => 0, 'finisher' => 0, 'unclassified' => 0];

        foreach ($activePigs as $pig) {
            if (! $configured || ! $pig->date_of_birth) {
                $stages['unclassified']++;
                continue;
            }

            $age = max(0, (int) $pig->date_of_birth->diffInDays(now()));
            $stage = match (true) {
                $age <= $pigletMax => 'piglet',
                $age <= $weanerMax => 'weaner',
                $age <= $growerMax => 'grower',
                default => 'finisher',
            };
            $stages[$stage]++;
        }

        // Newborn litters are tracked as quantities until individual pig records
        // are created. Include them in Piglets so the dashboard reflects the
        // actual living population rather than only tagged animals. Always:
        // their deaths are always counted, so leaving the living ones out when
        // a farm has not set its stage ages overstated mortality. Without the
        // farm's piglet age, the default one bounds which litters count.
        $pigletWindow = $pigletMax ?? \App\Services\Feed\FeedStageResolver::DEFAULT_LIMITS['piglet'];
        $farrowings = Farrowing::withoutGlobalScopes()
            ->with(['pigletDeaths' => fn ($query) => $query->withoutGlobalScopes()])
            ->whereHas('sow', fn ($query) => $query->withoutGlobalScopes()->where('organization_id', $organizationId))
            ->where('status', 'active')
            ->whereDate('farrowing_date', '>=', now()->subDays($pigletWindow)->toDateString())
            ->get(['id', 'live_births']);

        $unweanedPiglets = (int) $farrowings->sum(
            fn (Farrowing $farrowing) => max(0, (int) $farrowing->live_births - (int) $farrowing->pigletDeaths->sum('count'))
        );
        $stages['piglet'] += $unweanedPiglets;

        $pigDeaths = PigDeath::withoutGlobalScopes()->where('organization_id', $organizationId);
        $pigletDeaths = PigletDeath::withoutGlobalScopes()
            ->whereHas('farrowing.sow', fn ($query) => $query->withoutGlobalScopes()->where('organization_id', $organizationId));

        $deaths30 = (clone $pigDeaths)->whereDate('death_date', '>=', now()->subDays(30)->toDateString())->count()
            + (int) (clone $pigletDeaths)->whereDate('death_date', '>=', now()->subDays(30)->toDateString())->sum('count');

        // Animals that left in the 30 days: sold (sales not reversed) or moved
        // out / culled, each counted once.
        $since = now()->subDays(30)->toDateString();
        $left30 = \Illuminate\Support\Facades\DB::table('pig_sales')
            ->where('organization_id', $organizationId)->whereNull('deleted_at')->whereDate('sale_date', '>=', $since)->pluck('pig_id')
            ->merge(\Illuminate\Support\Facades\DB::table('pig_exits')
                ->where('organization_id', $organizationId)->whereDate('exit_date', '>=', $since)->pluck('pig_id'))
            ->filter()->unique()->count();

        return [
            'active_animals' => $activePigs->count() + $unweanedPiglets,
            'deaths_30_days' => $deaths30,
            'left_30_days' => $left30,
            'deaths_all_time' => (clone $pigDeaths)->count() + (int) (clone $pigletDeaths)->sum('count'),
            'stages' => $stages,
            'stages_configured' => $configured,
        ];
    }

    private function positiveIntegerSetting(int $organizationId, string $key): ?int
    {
        $value = Setting::getForOrganization($organizationId, $key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
