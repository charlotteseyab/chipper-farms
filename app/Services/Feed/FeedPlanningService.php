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
        $locations = [];
        $counts = [];
        $exceptions = [];
        // Each pig either keeps today's stage or follows its age through the period.
        $profiles = [];
        foreach ($pigs as $pig) {
            $stage = $this->stages->resolve($pig);
            if (! $stage) {
                $exceptions[] = "Animal {$pig->code} has no determinable feeding stage.";

                continue;
            }
            $profiles[] = $this->stages->agesThroughStages($pig)
                ? ['born' => CarbonImmutable::parse($pig->date_of_birth)->startOfDay()]
                : ['stage' => $stage];
            if (! empty($filters['stage']) && $filters['stage'] !== $stage) {
                continue;
            }
            $locations[] = ['stage' => $stage, 'room_id' => $pig->room_id, 'structure_id' => $pig->room?->structure_id];
            $counts[$stage] = ($counts[$stage] ?? 0) + 1;
        }
        $standards = FeedingStandard::where('organization_id', $organizationId)->whereDate('effective_from', '<=', $end)
            ->with('feedType')->orderByDesc('effective_from')->get()->groupBy('stage');
        $rows = [];
        $feeds = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dayCounts = [];
            foreach ($profiles as $profile) {
                $stage = $profile['stage'] ?? FeedStageResolver::stageForAge((int) $profile['born']->diffInDays($date));
                if (empty($filters['stage']) || $filters['stage'] === $stage) {
                    $dayCounts[$stage] = ($dayCounts[$stage] ?? 0) + 1;
                }
            }
            foreach ($dayCounts as $stage => $count) {
                $standard = ($standards[$stage] ?? collect())->first(fn ($row) => $row->effective_from->toDateString() <= $date->toDateString());
                if (! $standard || ! $standard->is_active || ! $standard->feedType?->is_active) {
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
        $demandComplete = $exceptions === [];
        $wastagePercent = $applyWastage ? $this->wastagePercent() : 0.0;
        $materials = [];
        foreach ($feeds as $id => &$feed) {
            $feed['base_kg'] = round($feed['required_kg'], 4);
            $feed['wastage_kg'] = round($feed['base_kg'] * $wastagePercent / 100, 4);
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

        return ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'days' => $days,
            'locations' => $locations, 'population_as_of' => now()->toIso8601String(), 'eligible_animal_count' => $pigs->count(), 'animal_count' => array_sum($counts),
            'rows' => array_values($rows), 'feeds' => array_values($feeds), 'materials' => array_values($materials),
            'demand_complete' => $demandComplete,
            'exceptions' => array_values(array_unique($exceptions)), 'filters' => $filters,
            'required_kg' => round(array_sum(array_column($feeds, 'required_kg')), 4),
            'base_kg' => round(array_sum(array_column($feeds, 'base_kg')), 4),
            'wastage_kg' => round(array_sum(array_column($feeds, 'wastage_kg')), 4),
            'wastage_percent' => $wastagePercent,
            'production_kg' => round(array_sum(array_column($feeds, 'production_kg')), 4)];
    }

    /** The farm's wastage allowance in percent; 0 when none is set. */
    public function wastagePercent(): float
    {
        $value = \App\Models\Setting::get(self::WASTAGE_SETTING);

        return is_numeric($value) && (float) $value > 0 ? (float) $value : 0.0;
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
            ->with(['group', 'feedType'])->orderBy('id')->get();

        return $issues->filter(function ($issue) use ($filters) {
            if (! empty($filters['stage']) && $issue->feeding_stage !== null) {
                return $issue->feeding_stage === $filters['stage'];
            }
            foreach (['stage', 'room_id', 'structure_id'] as $field) {
                if (! empty($filters[$field]) && ! collect($issue->planning_snapshot['locations'] ?? [])->contains(fn ($location) => (string) ($location[$field] ?? '') === (string) $filters[$field])) {
                    return false;
                }
            }

            return true;
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
                    'stages' => array_values(array_unique(array_column($first->planning_snapshot['rows'] ?? [], 'stage')))];
            })->values()->all();
    }
}
