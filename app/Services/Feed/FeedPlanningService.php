<?php

namespace App\Services\Feed;

use App\Models\FeedingStandard;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class FeedPlanningService
{
    public function __construct(private FeedStageResolver $stages, private FeedRecipeService $recipes) {}

    /** Setting key for the optional farm-wide wastage allowance, in percent. */
    public const WASTAGE_SETTING = 'feed.planning.wastage_percent';

    /**
     * Forecast feed and raw-material needs for a period.
     *
     * Growing pigs move up stages by age on each day of the period; breeding
     * animals keep today's stage. With $applyWastage, the farm's wastage
     * allowance is added to feed and ingredient needs (production and
     * purchasing); feeding expectations pass false and use the base ration.
     */
    public function calculate(int $organizationId, string $from, string $to, array $filters = [], bool $applyWastage = true): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        if ($days < 1 || $days > 366) {
            throw ValidationException::withMessages(['to' => 'Choose an inclusive planning period of 1 to 366 days.']);
        }
        $pigs = PigManagement::where('organization_id', $organizationId)->active()
            ->when($filters['group_id'] ?? null, fn ($q, $id) => $q->where('group_id', $id))
            ->when($filters['room_id'] ?? null, fn ($q, $id) => $q->where('room_id', $id))
            ->when($filters['structure_id'] ?? null, fn ($q, $id) => $q->whereHas('room', fn ($r) => $r->where('structure_id', $id)))
            ->with('room')->get();
        $limits = FeedStageResolver::limits($organizationId);
        $locations = [];
        $counts = [];
        $exceptions = [];
        $notes = [];
        // Each pig follows its age (growing pigs), its breeding cycle (sows
        // pregnant or suckling) or keeps today's stage through the period.
        $profiles = [];
        $overdueSows = 0;
        foreach ($pigs as $pig) {
            $stage = $this->stages->resolve($pig);
            if (! $stage) {
                $exceptions[] = "Animal {$pig->code} has no determinable feeding stage.";

                continue;
            }
            $profile = $this->stages->forecastProfile($pig, $stage);
            $overdueSows += ($profile['overdue'] ?? false) ? 1 : 0;
            $profiles[] = $profile + ['count' => 1];
            if (! empty($filters['stage']) && $filters['stage'] !== $stage) {
                continue;
            }
            $locations[] = ['stage' => $stage, 'room_id' => $pig->room_id, 'structure_id' => $pig->room?->structure_id];
            $counts[$stage] = ($counts[$stage] ?? 0) + 1;
        }
        if ($overdueSows > 0) {
            $notes[] = $overdueSows.' '.\Illuminate\Support\Str::plural('sow', $overdueSows).' still suckling a litter past weaning age in the records. They are planned as weaned from tomorrow; record the weaning to keep this right.';
        }

        // Piglets still on the sow are not animals on the system until they
        // are weaned, but they eat creep feed and will soon be weaners: count
        // each litter's live piglets from its farrowing date.
        $litterPiglets = 0;
        $overdueLitters = 0;
        foreach ($this->unweanedLitters($organizationId, $filters) as $litter) {
            $born = CarbonImmutable::parse($litter->farrowing_date)->startOfDay();
            $alive = max(0, (int) $litter->live_births - (int) $litter->piglet_deaths_count);
            if ($alive === 0) {
                continue;
            }
            // Past weaning age but not weaned in the records: very likely
            // weaned and not entered, so counting them could count them twice.
            if ((int) $born->diffInDays(CarbonImmutable::today()) > FeedStageResolver::weaningDays()) {
                $overdueLitters++;

                continue;
            }
            $profiles[] = ['born' => $born, 'count' => $alive, 'litter' => true];
            $stage = FeedStageResolver::stageForAge((int) $born->diffInDays(CarbonImmutable::today()), $limits);
            if (empty($filters['stage']) || $filters['stage'] === $stage) {
                $counts[$stage] = ($counts[$stage] ?? 0) + $alive;
                $litterPiglets += $alive;
            }
        }
        if ($overdueLitters > 0) {
            $notes[] = $overdueLitters.' '.\Illuminate\Support\Str::plural('litter', $overdueLitters).' past weaning age '.($overdueLitters === 1 ? 'is' : 'are').' not weaned in the records, so '.($overdueLitters === 1 ? 'its' : 'their').' piglets are not counted. Record the weaning to add them.';
        }
        $standards = FeedingStandard::where('organization_id', $organizationId)->whereDate('effective_from', '<=', $end)
            ->with('feedType')->orderByDesc('effective_from')->get()->groupBy('stage');
        $rows = [];
        $feeds = [];
        $sucklingUnplanned = false;
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dayCounts = [];
            $dayLitter = [];
            foreach ($profiles as $profile) {
                if (isset($profile['litter']) && $profile['born']->gt($date)) {
                    continue; // not born yet on this day
                }
                $stage = FeedStageResolver::stageOn($profile, $date, $limits);
                if (empty($filters['stage']) || $filters['stage'] === $stage) {
                    $dayCounts[$stage] = ($dayCounts[$stage] ?? 0) + $profile['count'];
                    if (isset($profile['litter'])) {
                        $dayLitter[$stage] = ($dayLitter[$stage] ?? 0) + $profile['count'];
                    }
                }
            }
            foreach ($dayCounts as $stage => $count) {
                $standard = ($standards[$stage] ?? collect())->first(fn ($row) => $row->effective_from->toDateString() <= $date->toDateString());
                if (! $standard || ! $standard->is_active || ! $standard->feedType?->is_active) {
                    // Suckling piglets with no creep ration: many farms feed
                    // none, so this is a note, not a hole in the plan.
                    if (($dayLitter[$stage] ?? 0) === $count) {
                        $sucklingUnplanned = true;

                        continue;
                    }
                    $exceptions[] = 'No active feeding standard/feed type for '.FeedStageResolver::STAGES[$stage].' on '.$date->toDateString().'.';

                    continue;
                }
                if (! empty($filters['feed_type_id']) && (int) $filters['feed_type_id'] !== (int) $standard->feed_type_id) {
                    continue;
                }
                $daily = round($count * (float) $standard->daily_kg, 4);
                $key = $standard->id;
                $rows[$key] ??= ['standard_id' => $standard->id, 'stage' => $stage, 'animal_count' => $count,
                    'feed_type_id' => $standard->feed_type_id, 'feed_type' => $standard->feedType->name,
                    'rate' => (float) $standard->daily_kg, 'basis' => $standard->stage_basis,
                    'daily_kg' => $daily, 'weekly_kg' => $daily * 7, 'monthly_kg' => $daily * $start->daysInMonth,
                    'required_kg' => 0, 'applicable_days' => 0, 'animal_days' => 0];
                $rows[$key]['required_kg'] += $daily;
                $rows[$key]['applicable_days']++;
                $rows[$key]['animal_days'] += $count;
                $id = $standard->feed_type_id;
                $feeds[$id] ??= ['feed_type_id' => $id, 'name' => $standard->feedType->name, 'required_kg' => 0];
                $feeds[$id]['required_kg'] += $daily;
            }
        }
        if ($sucklingUnplanned) {
            $notes[] = 'Piglets still on the sow are not in this plan: there is no Piglet ration. If you feed creep feed, set a Piglet ration in Setup.';
        }
        $demandComplete = $exceptions === [];
        $wastagePercent = $applyWastage ? $this->wastagePercent() : 0.0;
        $feedTypes = FeedType::where('organization_id', $organizationId)->whereKey(array_keys($feeds))->get()->keyBy('id');
        $materials = [];
        foreach ($feeds as $id => &$feed) {
            // A feed's own allowance wins over the farm's; blank means "use the farm's".
            $feed['wastage_percent'] = $applyWastage ? $this->wastagePercent($feedTypes[$id] ?? null) : 0.0;
            $feed['base_kg'] = round($feed['required_kg'], 4);
            $feed['wastage_kg'] = round($feed['base_kg'] * $feed['wastage_percent'] / 100, 4);
            $feed['required_kg'] = round($feed['base_kg'] + $feed['wastage_kg'], 4);
            $feed['available_kg'] = $this->available($organizationId, 'finished_feed', $id);
            $feed['production_kg'] = round(max(0, $feed['required_kg'] - $feed['available_kg']), 4);
            $dailyAverage = $feed['required_kg'] / $days;
            $feed['days_cover'] = $dailyAverage > 0 ? round($feed['available_kg'] / $dailyAverage, 2) : null;
            $feed['stock_out_date'] = $dailyAverage > 0 ? now()->addDays((int) floor($feed['available_kg'] / $dailyAverage))->toDateString() : null;
            try {
                $recipe = $this->recipes->resolve(FeedType::where('organization_id', $organizationId)->findOrFail($id), $start->toDateString());
                $feed['recipe_snapshot'] = $recipe;
                foreach ($recipe['ingredients'] as $ingredient) {
                    $materialId = $ingredient['raw_material_id'];
                    $materials[$materialId] ??= ['raw_material_id' => $materialId, 'name' => $ingredient['name'], 'unit' => 'kg', 'required_kg' => 0,
                        'available_kg' => $this->availableKg($organizationId, $materialId)];
                    $materials[$materialId]['required_kg'] += $feed['production_kg'] * (float) $ingredient['quantity_per_100kg'] / 100;
                }
            } catch (ValidationException $exception) {
                $exceptions[] = $feed['name'].': '.$exception->validator->errors()->first();
            }
        }
        unset($feed);
        foreach ($materials as &$material) {
            $material['required_kg'] = round($material['required_kg'], 4);
            $material['shortfall_kg'] = round(max(0, $material['required_kg'] - $material['available_kg']), 4);
            $material['suggested_purchase_kg'] = $material['shortfall_kg'];
        }
        unset($material);

        $wastageRates = array_values(array_unique(array_column($feeds, 'wastage_percent')));

        return ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'days' => $days,
            'stage_counts' => $counts, 'by_stage' => $this->byStage($counts, $rows),
            'litter_piglets' => $litterPiglets, 'notes' => array_values(array_unique($notes)),
            'locations' => $locations, 'population_as_of' => now()->toIso8601String(), 'eligible_animal_count' => $pigs->count(), 'animal_count' => array_sum($counts),
            'rows' => array_values($rows), 'feeds' => array_values($feeds), 'materials' => array_values($materials),
            'demand_complete' => $demandComplete,
            'exceptions' => array_values(array_unique($exceptions)), 'filters' => $filters,
            'required_kg' => round(array_sum(array_column($feeds, 'required_kg')), 4),
            'base_kg' => round(array_sum(array_column($feeds, 'base_kg')), 4),
            'wastage_kg' => round(array_sum(array_column($feeds, 'wastage_kg')), 4),
            'wastage_percent' => $wastagePercent,
            // Whether feeds use different allowances, so one figure would mislead.
            'wastage_varies' => count($wastageRates) > 1,
            'production_kg' => round(array_sum(array_column($feeds, 'production_kg')), 4)];
    }

    /**
     * Litters still on the sow, with their recorded piglet deaths, for this
     * farm and location filters (a litter is where its sow is).
     */
    private function unweanedLitters(int $organizationId, array $filters): \Illuminate\Support\Collection
    {
        return \App\Models\Farrowing::query()
            ->where('status', 'active')->whereNull('date_weaned')->whereDoesntHave('weanings')
            ->whereDate('farrowing_date', '<=', CarbonImmutable::today()->toDateString())
            ->whereHas('sow', fn ($q) => $q->where('organization_id', $organizationId)->whereNull('pigs_management.deleted_at')
                ->when($filters['group_id'] ?? null, fn ($w, $id) => $w->where('group_id', $id))
                ->when($filters['room_id'] ?? null, fn ($w, $id) => $w->where('room_id', $id))
                ->when($filters['structure_id'] ?? null, fn ($w, $id) => $w->whereHas('room', fn ($r) => $r->where('structure_id', $id))))
            ->withSum('pigletDeaths as piglet_deaths_count', 'count')
            ->get(['id', 'sow_id', 'farrowing_date', 'live_births']);
    }

    /**
     * The weeks ahead, one at a time, plus the whole period at once.
     *
     * Each week is the same calculation as any other plan: animals per stage
     * (growing pigs move up as they age), feed per feed type with its wastage.
     * The whole period counts stock once, gives what is still to mix and the
     * ingredients for it, and each feed's first week its stock does not cover.
     *
     * @return array{columns: list<array>, feeds: array<int, array>, stages: array<string, array>, whole: array, exceptions: \Illuminate\Support\Collection}
     */
    public function weekly(int $organizationId, CarbonImmutable $start, int $weeks): array
    {
        return $this->breakdown($organizationId, $start, $start->addDays($weeks * 7 - 1));
    }

    /**
     * Any date range, a week at a time: the same as weekly(), with the last
     * column shorter when the range is not whole weeks (a single day is one
     * column of one day).
     */
    public function breakdown(int $organizationId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $weeks = (int) ceil(((int) $start->diffInDays($end) + 1) / 7);
        $columns = [];
        $feeds = [];
        $stages = [];
        $exceptions = [];
        for ($i = 0; $i < $weeks; $i++) {
            $from = $start->addDays($i * 7);
            $to = $from->addDays(6)->min($end);
            $plan = $this->calculate($organizationId, $from->toDateString(), $to->toDateString());
            $columns[] = ['from' => $from, 'to' => $to, 'days' => (int) $from->diffInDays($to) + 1, 'required_kg' => $plan['required_kg']];
            foreach ($plan['feeds'] as $feed) {
                $feeds[$feed['feed_type_id']] ??= ['name' => $feed['name'], 'weeks' => array_fill(0, $weeks, 0.0)];
                $feeds[$feed['feed_type_id']]['weeks'][$i] = $feed['required_kg'];
            }
            foreach ($plan['by_stage'] as $stage) {
                $stages[$stage['stage']] ??= ['label' => $stage['label'], 'weeks' => array_fill(0, $weeks, 0.0)];
                $stages[$stage['stage']]['weeks'][$i] = round(collect($stage['rations'])->sum(fn ($r) => $r['average_animals'] * $r['days']) / end($columns)['days'], 1);
            }
            $exceptions = array_merge($exceptions, $plan['exceptions']);
        }

        $whole = $this->calculate($organizationId, $start->toDateString(), $end->toDateString());
        foreach ($whole['feeds'] as $feed) {
            $row = &$feeds[$feed['feed_type_id']];
            $row['stock_kg'] = $feed['available_kg'];
            $row['total_kg'] = round(array_sum($row['weeks']), 4);
            $row['to_mix_kg'] = $feed['production_kg'];
            $running = 0.0;
            $row['runs_out_week'] = null;
            foreach ($row['weeks'] as $i => $kg) {
                $running += $kg;
                if ($running > $feed['available_kg'] + 0.0001) {
                    $row['runs_out_week'] = $i;
                    break;
                }
            }
            unset($row);
        }
        $order = array_keys(FeedStageResolver::STAGES);
        uksort($stages, fn ($a, $b) => array_search($a, $order) <=> array_search($b, $order));

        return [
            'columns' => $columns, 'feeds' => $feeds, 'stages' => $stages, 'whole' => $whole,
            'exceptions' => collect($exceptions)->map(fn ($m) => preg_replace('/ on \d{4}-\d{2}-\d{2}\.$/', ' for part of this period.', $m))->unique()->values(),
        ];
    }

    /**
     * The wastage allowance in percent: the feed type's own when it has one,
     * otherwise the farm's; 0 when neither is set.
     */
    public function wastagePercent(?FeedType $feedType = null): float
    {
        if ($feedType !== null && $feedType->wastage_percent !== null) {
            return max(0.0, (float) $feedType->wastage_percent);
        }

        $value = \App\Models\Setting::get(self::WASTAGE_SETTING);

        return is_numeric($value) && (float) $value > 0 ? (float) $value : 0.0;
    }

    /**
     * One entry per stage that has animals today or feed in the period: the
     * count now, and each ration that applied (a stage can change ration part
     * way through), in the farm's stage order.
     *
     * @param  array<string, int>  $counts
     * @return list<array{stage: string, label: string, animals_today: int, rations: list<array>, required_kg: float}>
     */
    private function byStage(array $counts, array $rows): array
    {
        $rowsByStage = collect($rows)->groupBy('stage');
        $out = [];
        foreach (FeedStageResolver::STAGES as $stage => $label) {
            $rations = $rowsByStage[$stage] ?? collect();
            if (($counts[$stage] ?? 0) === 0 && $rations->isEmpty()) {
                continue;
            }
            $out[] = [
                'stage' => $stage, 'label' => $label, 'animals_today' => (int) ($counts[$stage] ?? 0),
                'rations' => $rations->map(fn (array $row) => [
                    'feed_type' => $row['feed_type'], 'rate' => $row['rate'], 'days' => $row['applicable_days'],
                    'average_animals' => round($row['animal_days'] / max(1, $row['applicable_days']), 1),
                    'required_kg' => round($row['required_kg'], 4),
                ])->values()->all(),
                'required_kg' => round((float) $rations->sum('required_kg'), 4),
            ];
        }

        return $out;
    }

    public function available(int $organizationId, string $kind, int $id): float
    {
        // The shared ledger is authoritative for both planning and posting.
        return max(0, (float) InventoryBalance::where('organization_id', $organizationId)
            ->where('item_kind', $kind)->where('item_id', $id)->value('on_hand_qty'));
    }

    /** Raw-material stock in kg; stock held in bags is converted by its kg per unit. */
    public function availableKg(int $organizationId, int $rawMaterialId): float
    {
        $kgPerUnit = RawMaterial::where('organization_id', $organizationId)->find($rawMaterialId)?->kgPerUnit() ?? 1.0;

        return round($this->available($organizationId, 'raw_material', $rawMaterialId) * $kgPerUnit, 4);
    }

    public function consumption(int $organizationId, string $from, string $to, array $filters = []): array
    {
        $issues = FeedIssue::where('organization_id', $organizationId)->notReversed()->whereDate('issued_at', '>=', $from)->whereDate('issued_at', '<=', $to)
            ->when($filters['group_id'] ?? null, fn ($q, $id) => $q->where('group_id', $id))
            ->when($filters['feed_type_id'] ?? null, fn ($q, $id) => $q->where('feed_type_id', $id))
            ->with(['group', 'feedType', 'room'])->orderBy('id')->get();

        return $issues->filter(function ($issue) use ($filters) {
            if (! empty($filters['stage']) && $issue->feeding_stage !== null
                && $issue->feeding_stage !== $filters['stage']) {
                return false;
            }
            $locationFilters = array_filter(array_intersect_key($filters, array_flip(['stage', 'room_id', 'structure_id'])),
                fn ($value) => $value !== null && $value !== '');
            if ($issue->feeding_stage !== null) {
                unset($locationFilters['stage']); // already checked against the recorded stage
            }
            if ($locationFilters === []) {
                return true;
            }
            // A feeding recorded for one pen is matched by that pen.
            if ($issue->room_id !== null && (isset($locationFilters['room_id']) || isset($locationFilters['structure_id']))) {
                return (! isset($locationFilters['room_id']) || (string) $issue->room_id === (string) $locationFilters['room_id'])
                    && (! isset($locationFilters['structure_id']) || (string) $issue->room?->structure_id === (string) $locationFilters['structure_id']);
            }

            // All selected fields must match the same saved location.
            return collect($issue->planning_snapshot['locations'] ?? [])->contains(function ($location) use ($locationFilters) {
                foreach ($locationFilters as $field => $value) {
                    if ((string) ($location[$field] ?? '') !== (string) $value) {
                        return false;
                    }
                }

                return true;
            });
        })->groupBy(fn ($issue) => $issue->issued_at->toDateString().'|'.$issue->group_id.'|'.$issue->feed_type_id)
            ->map(function ($rows) {
                $first = $rows->first();
                // Repeated deliveries share ONE daily target; never add the
                // same animal count/rate again with each truck or feed round.
                $planned = $first->planned_quantity_kg === null ? null : (float) $first->planned_quantity_kg;
                $actual = (float) $rows->sum('quantity_kg');
                $variance = $planned === null ? null : round($actual - $planned, 4);

                return ['date' => $first->issued_at->toDateString(), 'group' => $first->recipient_label,
                    'feed_type' => $first->planning_snapshot['feed_name'] ?? $first->feedType?->name, 'planned_kg' => $planned, 'actual_kg' => $actual,
                    'variance_kg' => $variance, 'variance_percent' => $planned > 0 ? round($variance / $planned * 100, 2) : null,
                    'stages' => array_values(array_unique(array_column($first->planning_snapshot['rows'] ?? [], 'stage'))),
                    // The pens fed, where they were recorded.
                    'rooms' => $rows->pluck('room.name')->filter()->unique()->values()->all(),
                    // The feedings behind this row, so a mistaken entry can be opened and reversed.
                    'issue_ids' => $rows->pluck('id')->all()];
            })->values()->all();
    }
}
