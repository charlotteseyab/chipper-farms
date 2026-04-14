<?php

namespace App\Http\Controllers;

use App\Models\FeedType;
use App\Models\PigManagement;
use App\Models\Setting;
use Illuminate\Http\Request;

class FeedPlanningController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'weekly');

        $days = match ($period) {
            'bi-weekly' => 14,
            'monthly' => 30,
            default => 7,
        };

        $stagePlan = $this->buildStagePlan();

        $feedTypes = FeedType::with(['recipes.rawMaterial'])
            ->get()
            ->keyBy(fn ($feedType) => strtolower(trim($feedType->name)));

        $feedProjectionRows = [];
        $ingredientTotals = [];
        $missingRecipes = [];
        $totalFeedNeeded = 0;
        $totalRequiredValue = 0;
        $totalPurchaseCost = 0;

        foreach ($stagePlan as $plan) {
            $feedNeeded = $plan['animal_count'] * $plan['kg_per_animal_per_day'] * $days;
            $totalFeedNeeded += $feedNeeded;

            $feedProjectionRows[] = [
                'stage' => $plan['stage'],
                'feed_type_name' => $plan['feed_type_name'],
                'animal_count' => $plan['animal_count'],
                'kg_per_animal_per_day' => $plan['kg_per_animal_per_day'],
                'days' => $days,
                'feed_needed' => $feedNeeded,
            ];

            $feedType = $feedTypes->get(strtolower(trim($plan['feed_type_name'])));

            if (!$feedType || $feedType->recipes->isEmpty()) {
                $missingRecipes[] = $plan['feed_type_name'];
                continue;
            }

            $multiplier = $feedNeeded / 100;

            foreach ($feedType->recipes as $recipe) {
                $requiredQty = (float) $recipe->quantity_per_100kg * $multiplier;
                $material = $recipe->rawMaterial;

                if (!$material) {
                    continue;
                }

                if (!isset($ingredientTotals[$material->id])) {
                    $ingredientTotals[$material->id] = [
                        'name' => $material->name,
                        'category' => $material->category,
                        'unit' => $material->unit,
                        'required_quantity' => 0,
                        'available_quantity' => (float) $material->quantity_in_stock,
                        'unit_cost' => (float) $material->unit_cost,
                        'required_value' => 0,
                    ];
                }

                $ingredientTotals[$material->id]['required_quantity'] += $requiredQty;
                $ingredientTotals[$material->id]['required_value'] += $requiredQty * (float) $material->unit_cost;
                $totalRequiredValue += $requiredQty * (float) $material->unit_cost;
            }
        }

        $ingredientProjectionRows = [];
        $shortageRows = [];

        foreach ($ingredientTotals as $row) {
            $shortage = max(0, $row['required_quantity'] - $row['available_quantity']);
            $bagWeight = $this->getBagWeightForMaterial($row['name']);
            $bagsNeeded = $shortage > 0 && $bagWeight > 0
                ? (int) ceil($shortage / $bagWeight)
                : 0;

            $purchaseCost = $shortage * (float) $row['unit_cost'];
            $totalPurchaseCost += $purchaseCost;

            $row['shortage_quantity'] = $shortage;
            $row['bag_weight_kg'] = $bagWeight;
            $row['bags_to_buy'] = $bagsNeeded;
            $row['purchase_cost'] = $purchaseCost;
            $row['status'] = $shortage > 0 ? 'shortage' : 'enough';

            $ingredientProjectionRows[] = $row;

            if ($shortage > 0) {
                $shortageRows[] = $row;
            }
        }

        usort($ingredientProjectionRows, fn ($a, $b) => strcmp($a['name'], $b['name']));
        usort($shortageRows, fn ($a, $b) => $b['shortage_quantity'] <=> $a['shortage_quantity']);

        $stats = [
            'total_feed_needed' => $totalFeedNeeded,
            'total_required_value' => $totalRequiredValue,
            'total_purchase_cost' => $totalPurchaseCost,
            'ingredients_needed' => count($ingredientProjectionRows),
            'shortages' => count($shortageRows),
        ];

        return view('feeds.planning-reports.index', compact(
            'period',
            'days',
            'stats',
            'feedProjectionRows',
            'ingredientProjectionRows',
            'shortageRows',
            'missingRecipes'
        ));
    }

    protected function buildStagePlan(): array
    {
        $activePigs = PigManagement::query()
            ->active()
            ->with(['femaleMatings', 'maleMatings'])
            ->get();

        $feedRates = [
            'piglet_starter' => $this->getFeedSettingFloat('feed.stages.piglet_starter', 0.50),
            'weaner' => $this->getFeedSettingFloat('feed.stages.weaner', 1.00),
            'grower' => $this->getFeedSettingFloat('feed.stages.grower', 1.50),
            'finisher' => $this->getFeedSettingFloat('feed.stages.finisher', 2.00),
            'boar' => $this->getFeedSettingFloat('feed.stages.boar', 2.50),
            'pregnant_sow' => $this->getFeedSettingFloat('feed.stages.pregnant_sow', 3.00),
            'other_sows' => $this->getFeedSettingFloat('feed.stages.other_sows', 3.00),
        ];

        $counts = [
            'Piglet Starter' => 0,
            'Weaner' => 0,
            'Grower' => 0,
            'Finisher' => 0,
            'Boar' => 0,
            'Pregnant Sow' => 0,
            'Other Sows' => 0,
        ];

        foreach ($activePigs as $pig) {
            $ageDays = (int) $pig->current_age_in_days;
            $stockType = strtolower((string) $pig->stock_type);
            $gender = strtoupper((string) $pig->gender);

            if ($stockType === 'breeding') {
                if ($gender === 'M') {
                    $counts['Boar']++;
                    continue;
                }

                if ($gender === 'F') {
                    $isPregnant = $pig->femaleMatings()
                        ->where('status', 'completed')
                        ->whereIn('pregnancy_status', ['pregnant', 'pending', 'uncertain'])
                        ->exists();

                    if ($isPregnant) {
                        $counts['Pregnant Sow']++;
                    } else {
                        $counts['Other Sows']++;
                    }

                    continue;
                }
            }

            if ($ageDays <= 56) {
                $counts['Piglet Starter']++;
            } elseif ($ageDays <= 98) {
                $counts['Weaner']++;
            } elseif ($ageDays <= 154) {
                $counts['Grower']++;
            } else {
                $counts['Finisher']++;
            }
        }

        $rows = [];

        if ($counts['Piglet Starter'] > 0) {
            $rows[] = [
                'stage' => 'Piglet Starter',
                'feed_type_name' => 'Piglet Starter',
                'animal_count' => $counts['Piglet Starter'],
                'kg_per_animal_per_day' => $feedRates['piglet_starter'],
            ];
        }

        if ($counts['Weaner'] > 0) {
            $rows[] = [
                'stage' => 'Weaner',
                'feed_type_name' => 'Weaner',
                'animal_count' => $counts['Weaner'],
                'kg_per_animal_per_day' => $feedRates['weaner'],
            ];
        }

        if ($counts['Grower'] > 0) {
            $rows[] = [
                'stage' => 'Grower',
                'feed_type_name' => 'Grower',
                'animal_count' => $counts['Grower'],
                'kg_per_animal_per_day' => $feedRates['grower'],
            ];
        }

        if ($counts['Finisher'] > 0) {
            $rows[] = [
                'stage' => 'Finisher',
                'feed_type_name' => 'Finisher',
                'animal_count' => $counts['Finisher'],
                'kg_per_animal_per_day' => $feedRates['finisher'],
            ];
        }

        if ($counts['Boar'] > 0) {
            $rows[] = [
                'stage' => 'Boar',
                'feed_type_name' => 'Boar',
                'animal_count' => $counts['Boar'],
                'kg_per_animal_per_day' => $feedRates['boar'],
            ];
        }

        if ($counts['Pregnant Sow'] > 0) {
            $rows[] = [
                'stage' => 'Pregnant Sow',
                'feed_type_name' => 'Pregnant Sow',
                'animal_count' => $counts['Pregnant Sow'],
                'kg_per_animal_per_day' => $feedRates['pregnant_sow'],
            ];
        }

        if ($counts['Other Sows'] > 0) {
            $rows[] = [
                'stage' => 'Other Sows',
                'feed_type_name' => 'Pregnant Sow',
                'animal_count' => $counts['Other Sows'],
                'kg_per_animal_per_day' => $feedRates['other_sows'],
            ];
        }

        return $rows;
    }

    protected function getFeedSettingFloat(string $key, float $default): float
    {
        $value = Setting::get($key, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    protected function getBagWeightForMaterial(string $materialName): float
    {
        $name = strtolower(trim($materialName));

        $map = [
            'maize' => 'feed.units.maize_bag_weight_kg',
            'soybean meal' => 'feed.units.soybean_meal_bag_weight_kg',
            'wheat bran' => 'feed.units.wheat_bran_bag_weight_kg',
            'premix' => 'feed.units.premix_bag_weight_kg',
            'concentrate' => 'feed.units.concentrate_bag_weight_kg',
        ];

        foreach ($map as $keyword => $settingKey) {
            if (str_contains($name, $keyword)) {
                return $this->getFeedSettingFloat(
                    $settingKey,
                    $this->getFeedSettingFloat('feed.units.default_bag_weight_kg', 50)
                );
            }
        }

        return $this->getFeedSettingFloat('feed.units.default_bag_weight_kg', 50);
    }
}