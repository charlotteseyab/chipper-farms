<?php

namespace App\Http\Controllers;

use App\Models\FeedingStandard;
use App\Models\FeedRecipeVersion;
use App\Models\FeedType;
use App\Models\RawMaterial;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedRecipeService;
use App\Services\Feed\FeedStageResolver;
use App\Support\CurrentOrganization;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * One page that walks a farm through everything feed needs before it works:
 * materials → feed types → recipes → daily rations per stage → feed in stock.
 * Saving goes through the existing feed production and planning endpoints.
 */
class FeedSetupController extends Controller
{
    public function index(FeedPlanningService $planning, FeedRecipeService $recipes)
    {
        $orgId = app(CurrentOrganization::class)->requireId();
        $today = now()->toDateString();

        $materials = $this->materials($orgId, $planning);
        $costPerKg = $materials->pluck('cost_per_kg', 'id');

        $duplicateNames = FeedType::all()
            ->groupBy(fn (FeedType $type) => mb_strtolower(trim($type->name)))
            ->filter(fn (Collection $types) => $types->count() > 1)
            ->keys();

        $feedTypes = FeedType::orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn (FeedType $type) => $this->feedTypeRow($type, $orgId, $today, $recipes, $planning, $costPerKg, $duplicateNames));

        $todayPlan = $planning->calculate($orgId, $today, $today);
        $animalCounts = collect($todayPlan['locations'])->countBy('stage');
        // Piglets still on their sows: not animals on the system yet, shown on the Piglet line.
        $suckling = (int) ($todayPlan['litter_piglets'] ?? 0);
        $standards = FeedingStandard::with('feedType')->orderByDesc('effective_from')->orderByDesc('id')->get()->groupBy('stage');
        $stages = collect(FeedStageResolver::STAGES)->map(function (string $label, string $stage) use ($standards, $animalCounts, $today, $suckling) {
            $versions = $standards[$stage] ?? collect();
            $current = $versions->first(fn (FeedingStandard $s) => $s->effective_from->toDateString() <= $today);
            $usable = $current && $current->is_active && $current->feedType?->is_active;
            $animals = (int) ($animalCounts[$stage] ?? 0);

            return [
                'stage'     => $stage,
                'label'     => $label,
                'animals'   => $animals,
                'current'   => $usable ? $current : null,
                'scheduled' => $versions->filter(fn (FeedingStandard $s) => $s->effective_from->toDateString() > $today)->sortBy('effective_from')->first(),
                'daily_kg'  => $usable ? round($animals * (float) $current->daily_kg, 2) : null,
                'needs_standard' => $animals > 0 && ! $usable,
                'suckling'  => $stage === 'piglet' ? $suckling : 0,
            ];
        })->values();

        $activeTypes = $feedTypes->where('is_active', true);
        $steps = [
            'materials'  => $materials->where('usable', true)->isNotEmpty(),
            'feed_types' => $activeTypes->isNotEmpty() && $duplicateNames->isEmpty(),
            'recipes'    => $activeTypes->isNotEmpty() && $activeTypes->every(fn (array $type) => $type['recipe_ok']),
            'standards'  => $stages->contains(fn (array $s) => $s['animals'] > 0) && ! $stages->contains('needs_standard', true),
            // Whether feed is mixed is day-to-day stock (Stock, Mix & buy), not setup.
        ];

        // Everything that used to be on "Ration history & settings", so the
        // whole of setup is one page.
        $settings = [
            'standards'         => FeedingStandard::with('feedType')->orderBy('stage')->orderByDesc('effective_from')->get(),
            'stages'            => FeedStageResolver::STAGES,
            'feedTypes'         => FeedType::where('is_active', true)->orderBy('name')->get(),
            'animals'           => \App\Models\PigManagement::active()->orderBy('code')->get(['id', 'code']),
            'recipeVersions'    => FeedRecipeVersion::with('feedType')->orderByDesc('effective_from')->orderByDesc('id')->get(),
            'varianceThreshold' => \App\Models\Setting::get('feed.alerts.variance_percent'),
            'compact'           => true,
        ];

        return view('feeds.setup.index', compact('materials', 'feedTypes', 'stages', 'steps', 'settings'));
    }

    private function materials(int $orgId, FeedPlanningService $planning): Collection
    {
        return RawMaterial::where('is_active', true)->with('inventoryBalance')->orderBy('name')->get()
            ->map(function (RawMaterial $material) use ($orgId, $planning) {
                $kgPerUnit = $material->kgPerUnit();
                $wac = (float) ($material->inventoryBalance?->weighted_avg_cost ?: $material->unit_cost);

                return [
                    'id'          => $material->id,
                    'name'        => $material->name,
                    'unit'        => $material->unit,
                    'usable'      => $kgPerUnit !== null,
                    'stock_kg'    => $kgPerUnit !== null ? $planning->availableKg($orgId, $material->id) : null,
                    'cost_per_kg' => $kgPerUnit !== null ? round($wac / $kgPerUnit, 4) : null,
                ];
            });
    }

    private function feedTypeRow(FeedType $type, int $orgId, string $today, FeedRecipeService $recipes,
        FeedPlanningService $planning, Collection $costPerKg, Collection $duplicateNames): array
    {
        $row = [
            'id'           => $type->id,
            'name'         => $type->name,
            'notes'        => $type->notes,
            'is_active'    => (bool) $type->is_active,
            'wastage_percent' => $type->wastage_percent !== null ? (float) $type->wastage_percent : null,
            'duplicate'    => $duplicateNames->contains(mb_strtolower(trim($type->name))),
            'stock_kg'     => $planning->available($orgId, 'finished_feed', $type->id),
            'recipe_ok'    => false,
            'recipe_error' => null,
            'ingredients'  => [],
            'cost_per_kg'  => null,
        ];

        try {
            $recipe = $recipes->resolve($type, $today);
            $row['recipe_ok'] = true;
            $row['ingredients'] = $recipe['ingredients'];
            $row['cost_per_kg'] = round(collect($recipe['ingredients'])->sum(
                fn (array $i) => (float) $i['quantity_per_100kg'] / 100 * (float) ($costPerKg[$i['raw_material_id']] ?? 0)
            ), 4);
        } catch (ValidationException $exception) {
            $row['recipe_error'] = $exception->validator->errors()->first();
            $row['ingredients'] = $this->draftIngredients($type);
            $total = collect($row['ingredients'])->sum(fn (array $i) => (float) $i['quantity_per_100kg']);
            if ($row['ingredients'] !== [] && abs($total - 100) > 0.0001) {
                $row['recipe_error'] = sprintf('The saved recipe adds up to %s kg; it must add up to exactly 100 kg.', rtrim(rtrim(number_format($total, 4), '0'), '.'));
            }
        }

        return $row;
    }

    /** The latest saved ingredients, valid or not, so a broken recipe can be fixed in place. */
    private function draftIngredients(FeedType $type): array
    {
        $version = FeedRecipeVersion::where('feed_type_id', $type->id)->latest('effective_from')->latest('id')->first();
        $rows = $version ? $version->ingredients : $type->recipes()->with('rawMaterial')->get()->map(fn ($r) => [
            'raw_material_id' => $r->raw_material_id, 'name' => $r->rawMaterial?->name, 'quantity_per_100kg' => (string) $r->quantity_per_100kg,
        ])->all();

        return array_values($rows);
    }
}
