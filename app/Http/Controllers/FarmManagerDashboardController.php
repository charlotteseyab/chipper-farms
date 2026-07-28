<?php

namespace App\Http\Controllers;

use App\Models\Farrowing;
use App\Models\Mating;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\WeightAlert;
use App\Services\Stock\PurchasedStockDashboardMetrics;

class FarmManagerDashboardController extends Controller
{
    public function __invoke()
{
    $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);

    $activeBaseQuery = PigManagement::active();

    $weanedActive = (clone $activeBaseQuery)->count();

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
    $purchasedStockMetrics = app(PurchasedStockDashboardMetrics::class)->summary();

    $breedingStockActive = (clone $activeBaseQuery)
        ->where('stock_type', 'breeding')
        ->count();

    $standardStockActive = (clone $activeBaseQuery)
        ->where('stock_type', 'standard')
        ->count();

    $breedingStockTotal = $breedingStockActive;

    $breedingFemales = (clone $activeBaseQuery)
        ->where('stock_type', 'breeding')
        ->where('gender', 'F')
        ->count();

    $breedingMales = (clone $activeBaseQuery)
        ->where('stock_type', 'breeding')
        ->where('gender', 'M')
        ->count();

    $criticalAlerts = WeightAlert::where('severity', 'high')
        ->where('acknowledged', false)
        ->count();

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
        'breeding_stock_ratio' => $totalStockActive > 0 ? round(($breedingStockActive / $totalStockActive) * 100) : 0,
        'avg_weight_records' => $totalPigsAll > 0 ? round($totalWeightRecords / max($totalPigsAll, 1), 1) : 0,
        'recent_activity_rate' => $totalPigsAll > 0 ? round(($recentWeightRecords / $totalPigsAll) * 100) : 0,
        'data_completeness' => $totalPigsAll > 0 ? round((PigManagement::whereHas('weights')->count() / $totalPigsAll) * 100) : 0,
    ];

    $stockDistributionTotals = [
        'breeding' => $breedingStockActive,
        'standard' => $standardStockActive,
        'piglets' => $activePigletsAdjusted,
    ];

    $breedCounts = [
        'lw' => (clone $activeBaseQuery)->where('breed', 'LW')->count(),
        'dr' => (clone $activeBaseQuery)->where('breed', 'DR')->count(),
        'lwdr' => (clone $activeBaseQuery)->where('breed', 'LWDR')->count(),
    ];

    $upcomingEvents = $this->buildUpcomingEvents();
    $pregnancyChecksDue = Mating::pregnancyCheckDue()->count();
    $pregnancyChecksOverdue = Mating::pregnancyCheckOverdue()->count();
    $uncertainPregnancies = Mating::uncertainPregnancy()->count();
    $pregnancyFollowUpsDue = Mating::query()->pregnancyFollowUpDue()->count();
    $pregnancyFollowUpsOverdue = Mating::query()->pregnancyFollowUpOverdue()->count();
    $readyToWean = Farrowing::where('status', 'active')
        ->whereDate('farrowing_date', '<=', now()->subDays($weaningAge))
        ->whereDoesntHave('weanings', function ($query) {
            $query->where('maturity_completed', true);
        })
        ->count();
    $overdueWeaning = Farrowing::where('status', 'active')
        ->whereDate('farrowing_date', '<', now()->subDays($weaningAge + 7))
        ->whereDoesntHave('weanings', function ($query) {
            $query->where('maturity_completed', true);
        })
        ->count();
    $maturityReviews = PigManagement::active()
        ->whereBetween('date_of_birth', [
            now()->subDays(190)->toDateString(),
            now()->subDays(150)->toDateString(),
        ])
        ->count();

    $allOverduePigs = PigManagement::query()
    ->get()
    ->filter(function ($pig) {
        return $pig->isOverdueForWeighing();
    })
    ->values();

    $overduePigsPreview = $allOverduePigs->take(5)->map(function ($pig) {
        $lastWeight = $pig->weights()->latest('weigh_date')->first();
        $lastWeighing = $lastWeight?->weigh_date ? \Carbon\Carbon::parse($lastWeight->weigh_date) : null;
        $daysSince = $lastWeighing ? $lastWeighing->diffInDays(now()) : null;
    
        return [
            'pig' => $pig,
            'lastWeight' => $lastWeight,
            'lastWeighing' => $lastWeighing,
            'daysSince' => $daysSince,
            'overdueDays' => $daysSince,
            'weighingStatus' => [
                'message' => 'Overdue for weighing',
                'color' => 'red',
                'icon' => 'Warning',
            ],
        ];
    });
    
    $allOverduePigsDetails = $allOverduePigs->map(function ($pig) {
        $lastWeight = $pig->weights()->latest('weigh_date')->first();
        $lastWeighing = $lastWeight?->weigh_date ? \Carbon\Carbon::parse($lastWeight->weigh_date) : null;
        $daysSince = $lastWeighing ? $lastWeighing->diffInDays(now()) : null;
    
        return [
            'pig' => $pig,
            'lastWeight' => $lastWeight,
            'lastWeighing' => $lastWeighing,
            'daysSince' => $daysSince,
            'overdueDays' => $daysSince,
            'weighingStatus' => [
                'message' => 'Overdue for weighing',
                'color' => 'red',
                'icon' => 'Warning',
            ],
        ];
    });

    $managerWorkQueue = collect([
        [
            'type' => 'Weight',
            'item' => 'Overdue weighing',
            'details' => 'Animals are late for routine weighing.',
            'count' => $overdueForWeighing,
            'due' => 'Overdue',
            'severity' => 'high',
            'route' => route('weight-alerts.index'),
        ],
        [
            'type' => 'Breeding',
            'item' => 'Pregnancy checks',
            'details' => 'Completed matings waiting for pregnancy confirmation.',
            'count' => $pregnancyChecksDue,
            'due' => $pregnancyChecksOverdue > 0 ? 'Overdue' : 'Today',
            'severity' => $pregnancyChecksOverdue > 0 ? 'high' : 'medium',
            'route' => route('mating.manage'),
        ],
        [
            'type' => 'Breeding',
            'item' => 'Pregnancy follow-ups',
            'details' => 'Uncertain pregnancies needing another check.',
            'count' => $pregnancyFollowUpsDue,
            'due' => $pregnancyFollowUpsOverdue > 0 ? 'Overdue' : 'Scheduled',
            'severity' => $pregnancyFollowUpsOverdue > 0 ? 'high' : 'medium',
            'route' => route('mating.manage'),
        ],
        [
            'type' => 'Weaning',
            'item' => 'Litters ready to wean',
            'details' => 'Active farrowings past the configured weaning age.',
            'count' => $readyToWean,
            'due' => $overdueWeaning > 0 ? 'Overdue' : 'This week',
            'severity' => $overdueWeaning > 0 ? 'high' : 'medium',
            'route' => route('weaning.dashboard'),
        ],
        [
            'type' => 'Health',
            'item' => 'Critical health/weight alerts',
            'details' => 'Unacknowledged high-severity alerts need review.',
            'count' => $criticalAlerts,
            'due' => 'Today',
            'severity' => $criticalAlerts > 0 ? 'high' : 'low',
            'route' => route('weight-alerts.index'),
        ],
        [
            'type' => 'Stock',
            'item' => 'Maturity review',
            'details' => 'Young pigs approaching review age.',
            'count' => $maturityReviews,
            'due' => 'This week',
            'severity' => $maturityReviews > 0 ? 'low' : 'low',
            'route' => route('all-stocks.index'),
        ],
    ])->filter(fn ($item) => $item['count'] > 0)->values();

    $weightTrendLabels = [];
    $weightTrendValues = [];
    $weightTrendRows = PigWeight::selectRaw('DATE(weigh_date) as weigh_day, AVG(weight) as avg_weight')
        ->whereDate('weigh_date', '>=', now()->subDays(6)->toDateString())
        ->groupBy('weigh_day')
        ->orderBy('weigh_day')
        ->pluck('avg_weight', 'weigh_day');

    for ($i = 6; $i >= 0; $i--) {
        $date = now()->subDays($i);
        $key = $date->toDateString();
        $weightTrendLabels[] = $date->format('M j');
        $weightTrendValues[] = round((float) ($weightTrendRows[$key] ?? 0), 1);
    }

    $breedingReadiness = [
        'ready' => PigManagement::availableForBreeding()->count(),
        'due_soon' => PigManagement::breedingAge()->count(),
        'not_ready' => max(0, $breedingStockActive - PigManagement::availableForBreeding()->count()),
    ];

    $recentActivity = collect([
        [
            'label' => 'Weight records',
            'description' => $recentWeightRecords . ' records added in the last 30 days',
            'when' => '30 days',
            'severity' => 'low',
        ],
        [
            'label' => 'High alerts',
            'description' => $highAlerts . ' unacknowledged high-severity alerts',
            'when' => 'Now',
            'severity' => $highAlerts > 0 ? 'high' : 'low',
        ],
        [
            'label' => 'Pregnancy checks',
            'description' => $pregnancyChecksDue . ' checks due',
            'when' => 'Today',
            'severity' => $pregnancyChecksOverdue > 0 ? 'high' : 'medium',
        ],
        [
            'label' => 'Weaning review',
            'description' => $readyToWean . ' litters ready',
            'when' => 'This week',
            'severity' => $overdueWeaning > 0 ? 'high' : 'medium',
        ],
    ]);

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
        'allOverduePigs' => $allOverduePigs,
        'overduePigsPreview' => $overduePigsPreview,
        'allOverduePigsDetails' => $allOverduePigsDetails,
        'pregnancyChecksDue' => $pregnancyChecksDue,
        'pregnancyChecksOverdue' => $pregnancyChecksOverdue,
        'uncertainPregnancies' => $uncertainPregnancies,
        'pregnancyFollowUpsDue' => $pregnancyFollowUpsDue,
        'pregnancyFollowUpsOverdue' => $pregnancyFollowUpsOverdue,
        'readyToWean' => $readyToWean,
        'overdueWeaning' => $overdueWeaning,
        'maturityReviews' => $maturityReviews,
        'managerWorkQueue' => $managerWorkQueue,
        'weightTrendLabels' => $weightTrendLabels,
        'weightTrendValues' => $weightTrendValues,
        'breedingReadiness' => $breedingReadiness,
        'recentActivity' => $recentActivity,
        'purchasedStockMetrics' => $purchasedStockMetrics,
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
            ->whereDate('date_of_birth', '<=', now()->subDays(365)->toDateString())
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

        $maturingPigs = PigManagement::whereBetween('date_of_birth', [
                now()->subDays(180)->toDateString(),
                now()->subDays(150)->toDateString(),
            ])
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
