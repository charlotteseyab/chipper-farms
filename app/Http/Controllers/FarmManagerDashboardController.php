<?php

namespace App\Http\Controllers;

use App\Models\Farrowing;
use App\Models\Mating;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\WeightAlert;
use Illuminate\Support\Collection;

class FarmManagerDashboardController extends Controller
{
    public function __invoke()
    {
        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);

        $weanedActive = PigManagement::active()->count();
        $recentFarrowings = Farrowing::with('pigletDeaths')
            ->where('farrowing_date', '>=', now()->subDays($weaningAge))
            ->where('status', 'active')
            ->get();

        $activePigletsAdjusted = $recentFarrowings->reduce(function ($carry, Farrowing $farrowing) {
            $liveBirths = (int) ($farrowing->live_births ?? 0);
            $deaths = (int) $farrowing->pigletDeaths->sum('count');
            return $carry + max(0, $liveBirths - $deaths);
        }, 0);

        $totalStockActive = $weanedActive + $activePigletsAdjusted;
        $breedingStockActive = PigManagement::active()->where('stock_type', 'breeding')->count();
        $standardStockActive = PigManagement::active()->where('stock_type', 'standard')->count();

        $breedingStockTotal = PigManagement::where('stock_type', 'breeding')->count();
        $breedingFemales = PigManagement::where('stock_type', 'breeding')->where('gender', 'F')->count();
        $breedingMales = PigManagement::where('stock_type', 'breeding')->where('gender', 'M')->count();

        $criticalAlerts = WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();

        $totalWeaned = PigManagement::count();
        $totalPiglets = Farrowing::where('farrowing_date', '>=', now()->subDays($weaningAge))
            ->where('status', 'active')
            ->sum('live_births');
        $totalPigsAll = $totalWeaned + $totalPiglets;
        $totalDeaths = PigDeath::count();
        $mortalityRate = $totalPigsAll > 0 ? ($totalDeaths / $totalPigsAll) * 100 : 0;

        $totalMatings = Mating::count();
        $successfulMatings = Mating::where('pregnancy_status', 'pregnant')->count();
        $fertilityRate = $totalMatings > 0 ? ($successfulMatings / $totalMatings) * 100 : 0;

        $overdueForWeighing = PigManagement::countOverdueForWeighing();

        $highAlerts = WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();
        $mediumAlerts = WeightAlert::where('severity', 'medium')->where('acknowledged', false)->count();
        $lowAlerts = WeightAlert::where('severity', 'low')->where('acknowledged', false)->count();

        $totalWeightRecords = PigWeight::count();
        $recentWeightRecords = PigWeight::where('created_at', '>=', now()->subDays(30))->count();

        $breedingStats = [
            'breeding_stock_ratio' => $totalPigsAll > 0 ? round(($breedingStockTotal / $totalPigsAll) * 100) : 0,
            'avg_weight_records' => $totalPigsAll > 0 ? round($totalWeightRecords / max($totalPigsAll, 1), 1) : 0,
            'recent_activity_rate' => $totalPigsAll > 0 ? round(($recentWeightRecords / $totalPigsAll) * 100) : 0,
            'data_completeness' => $totalPigsAll > 0 ? round((PigManagement::whereHas('weights')->count() / $totalPigsAll) * 100) : 0,
        ];

        $stockDistributionTotals = [
            'breeding' => $breedingStockTotal,
            'standard' => PigManagement::where('stock_type', 'standard')->count(),
        ];

        $breedCounts = [
            'lw' => PigManagement::where('breed', 'LW')->count(),
            'dr' => PigManagement::where('breed', 'DR')->count(),
            'lwdr' => PigManagement::where('breed', 'LWDR')->count(),
        ];

        $upcomingEvents = $this->buildUpcomingEvents();

        return view('manager.dashboard', [
            'totalStockActive' => $totalStockActive,
            'weanedActive' => $weanedActive,
            'activePigletsAdjusted' => $activePigletsAdjusted,
            'breedingStockActive' => $breedingStockActive,
            'standardStockActive' => $standardStockActive,
            'breedingStockTotal' => $breedingStockTotal,
            'breedingFemales' => $breedingFemales,
            'breedingMales' => $breedingMales,
            'criticalAlerts' => $criticalAlerts,
            'mortalityRate' => $mortalityRate,
            'totalDeaths' => $totalDeaths,
            'totalMatings' => $totalMatings,
            'successfulMatings' => $successfulMatings,
            'fertilityRate' => $fertilityRate,
            'overdueForWeighing' => $overdueForWeighing,
            'highAlerts' => $highAlerts,
            'mediumAlerts' => $mediumAlerts,
            'lowAlerts' => $lowAlerts,
            'totalWeightRecords' => $totalWeightRecords,
            'recentWeightRecords' => $recentWeightRecords,
            'breedingStats' => $breedingStats,
            'stockDistributionTotals' => $stockDistributionTotals,
            'breedCounts' => $breedCounts,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }

    protected function buildUpcomingEvents(): array
    {
        $events = [];

        $needWeighing = PigManagement::whereDoesntHave('weights', function ($query) {
            $query->where('created_at', '>=', now()->subDays(30));
        })->take(3)->get();

        foreach ($needWeighing as $pig) {
            $events[] = [
                'title' => $pig->ear_tag_number . ' weighing overdue',
                'date' => now(),
                'days_until' => 0,
                'icon' => 'scale',
                'color' => 'red',
            ];
        }

        $breedingChecks = PigManagement::where('stock_type', 'breeding')
            ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= 365')
            ->whereDoesntHave('weights', function ($query) {
                $query->where('created_at', '>=', now()->subDays(60));
            })
            ->take(2)
            ->get();

        foreach ($breedingChecks as $pig) {
            $events[] = [
                'title' => $pig->ear_tag_number . ' health check recommended',
                'date' => now()->addDays(7),
                'days_until' => 7,
                'icon' => 'heart',
                'color' => 'pink',
            ];
        }

        $maturingPigs = PigManagement::whereRaw('DATEDIFF(NOW(), date_of_birth) >= 150')
            ->whereRaw('DATEDIFF(NOW(), date_of_birth) <= 180')
            ->take(2)
            ->get();

        foreach ($maturingPigs as $pig) {
            $maturityDate = optional($pig->date_of_birth)?->copy()->addDays(180) ?? now();
            $events[] = [
                'title' => $pig->ear_tag_number . ' approaching maturity',
                'date' => $maturityDate,
                'days_until' => (int) now()->diffInDays($maturityDate, false),
                'icon' => 'calendar',
                'color' => 'blue',
            ];
        }

        usort($events, fn ($a, $b) => $a['days_until'] <=> $b['days_until']);

        return array_slice($events, 0, 5);
    }
}
