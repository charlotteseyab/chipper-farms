<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Farrowing;
use App\Models\Mating;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PigWeight;
use App\Models\PurchaseStockIntake;
use App\Models\WeightAlert;
use App\Services\Stock\PurchasedStockDashboardMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DataEntryController extends Controller
{
    public function dashboard()
    {
        // Enhanced pig counting including piglets
        $weanedPigs = PigManagement::active()->count();
        $weaningAge = (int) config('breeding.cycle.birth_to_weaning_days', 45);

        $recentFarrowings = Farrowing::with('pigletDeaths')
            ->where('farrowing_date', '>=', now()->subDays($weaningAge))
            ->where('status', 'active')
            ->get();

        $pigletsCount = 0;
        foreach ($recentFarrowings as $f) {
            $pigletsCount += max(
                0,
                (int) ($f->live_births ?? 0) - (int) $f->pigletDeaths->sum('count')
            );
        }

        $totalPigs = $weanedPigs + $pigletsCount;
        $todayWeights = PigWeight::whereDate('created_at', today())->count();
        $todayPigs = PigManagement::whereDate('created_at', today())->count();
        $weekWeights = PigWeight::where('created_at', '>=', now()->startOfWeek())->count();

        $pendingWeighing = PigManagement::active()
            ->whereDoesntHave('weights', function ($query) {
                $query->where('weigh_date', '>=', now()->subDays(7));
            })
            ->where('date_of_birth', '<=', now()->subDays(7))
            ->count();

        $activeAlerts = WeightAlert::where('acknowledged', false)->count();
        $overdueForWeighing = PigManagement::countOverdueForWeighing();
        $dueForWeighing = PigManagement::countDueForWeighing();
        $missingFirstWeight = PigManagement::active()->whereDoesntHave('weights')->count();
        $incompletePigRecords = PigManagement::active()
            ->where(function ($query) {
                $query->whereNull('breed')
                    ->orWhere('breed', '')
                    ->orWhereNull('gender')
                    ->orWhere('gender', '')
                    ->orWhereNull('date_of_birth')
                    ->orWhereNull('stock_type')
                    ->orWhere('stock_type', '');
            })
            ->count();
        $missingRoomAssignments = PigManagement::active()->whereNull('room_id')->count();
        $pendingPurchaseIntakes = PurchaseStockIntake::whereIn('status', [
            PurchaseStockIntake::STATUS_PENDING_RECEIVE,
            PurchaseStockIntake::STATUS_RECEIVED,
            PurchaseStockIntake::STATUS_QUARANTINED,
            PurchaseStockIntake::STATUS_APPROVED,
        ])->count();
        $purchasedStockMetrics = app(PurchasedStockDashboardMetrics::class)->summary();
        $recentImports = PurchaseStockIntake::whereDate('created_at', '>=', now()->subDays(7)->toDateString())->count();
        $pregnancyFollowUpsDue = Mating::query()->pregnancyFollowUpDue()->count();
        $pregnancyFollowUpsOverdue = Mating::query()->pregnancyFollowUpOverdue()->count();
        $uncertainPregnancies = Mating::uncertainPregnancy()->count();
        $validationIssues = $incompletePigRecords + $missingRoomAssignments + $pendingPurchaseIntakes + $pregnancyFollowUpsDue;
        $entriesToday = $todayWeights + $todayPigs
            + PigSale::whereDate('created_at', today())->count()
            + PigDeath::whereDate('created_at', today())->count()
            + Mating::whereDate('created_at', today())->count()
            + PurchaseStockIntake::whereDate('created_at', today())->count();

        // Chart data: last 7 days
        $dailyWeightsData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = PigWeight::whereDate('created_at', $date)->count();
            $dailyWeightsData[] = [
                'date' => $date->format('M j'),
                'count' => $count,
            ];
        }

        // Breeding KPIs
        $pregNum = Mating::where('pregnancy_status', 'pregnant')->count();
        $pregDenom = Mating::whereNotNull('female_id')->count();
        $pregnancyRate = $pregDenom > 0 ? round($pregNum / $pregDenom * 100, 1) : 0.0;

        $totalMatings = Mating::count();
        $totalFarrowings = Farrowing::whereNotNull('farrowing_date')->count();
        $farrowingRate = $totalMatings > 0 ? round($totalFarrowings / $totalMatings * 100, 1) : 0.0;

        $totalWeaned = \App\Models\Weaning::count();
        $totalBorn = Farrowing::sum('live_births');
        $weaningPercentage = $totalBorn > 0 ? round($totalWeaned / $totalBorn * 100, 1) : 0.0;

        $avgStillborns = (float) Farrowing::avg('stillbirths');
        $avgBirthWeight = (float) Farrowing::whereNotNull('average_birth_weight')->avg('average_birth_weight');

        // Recent Activity feed
        $recentWeights = PigWeight::with('pig')->latest()->limit(2)->get();
        $recentPigs = PigManagement::latest()->limit(1)->get();
        $recentSales = PigSale::with('pig')->latest()->limit(1)->get();
        $recentDeaths = PigDeath::with('pig')->latest()->limit(1)->get();
        $recentMatings = Mating::with(['female', 'male'])->latest()->limit(1)->get();

        $activities = collect();

        foreach ($recentWeights as $weight) {
            $activities->push([
                'type' => 'weight',
                'title' => 'Weight recorded',
                'description' => $weight->pig->ear_tag_number ?? 'Unknown pig',
                'details' => $weight->weight . 'kg',
                'time' => $weight->created_at,
                'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
                'color' => 'blue',
                'route' => route('weights.index'),
            ]);
        }

        foreach ($recentPigs as $pig) {
            $activities->push([
                'type' => 'pig',
                'title' => 'New pig added',
                'description' => trim(($pig->ear_tag_number ?? '') . ' (' . ($pig->breed ?? '') . ')'),
                'details' => trim(($pig->gender ?? '') . ' • ' . ($pig->stock_type ?? '')),
                'time' => $pig->created_at,
                'icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6',
                'color' => 'green',
                'route' => route('pigs.show', $pig->id),
            ]);
        }

        foreach ($recentSales as $sale) {
            $activities->push([
                'type' => 'sale',
                'title' => 'Pig sold',
                'description' => $sale->pig->ear_tag_number ?? 'Unknown pig',
                'details' => '₵' . number_format((float) ($sale->sale_price ?? 0), 2),
                'time' => $sale->created_at,
                'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1',
                'color' => 'purple',
                'route' => route('sales.show', $sale->id),
            ]);
        }

        foreach ($recentDeaths as $death) {
            $activities->push([
                'type' => 'death',
                'title' => 'Pig death recorded',
                'description' => $death->pig->ear_tag_number ?? 'Unknown pig',
                'details' => (string) ($death->cause_of_death ?? ''),
                'time' => $death->created_at,
                'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z',
                'color' => 'red',
                'route' => route('deaths.show', $death->id),
            ]);
        }

        foreach ($recentMatings as $mating) {
            $activities->push([
                'type' => 'mating',
                'title' => 'Mating planned',
                'description' => 'Sow: ' . ($mating->female->ear_tag_number ?? 'Unknown') . ' × Boar: ' . ($mating->male->ear_tag_number ?? 'Unknown'),
                'details' => $mating->mating_date ? $mating->mating_date->format('M j, Y') : 'TBD',
                'time' => $mating->created_at,
                'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
                'color' => 'pink',
                'route' => route('mating.index'),
            ]);
        }

        $recentActivities = $activities
            ->sortByDesc('time')
            ->take(5)
            ->values();

        $entryMetrics = [
            [
                'label' => 'Entries Today',
                'value' => $entriesToday,
                'sub' => $todayWeights . ' weights • ' . $todayPigs . ' pigs',
                'route' => route('weights.index'),
                'color' => 'metric-green',
            ],
            [
                'label' => 'Weights Recorded',
                'value' => $todayWeights,
                'sub' => 'Entries recorded today',
                'route' => route('weights.create'),
                'color' => 'metric-blue',
            ],
            [
                'label' => 'Pending Weighing',
                'value' => $pendingWeighing,
                'sub' => $dueForWeighing . ' due for weighing',
                'route' => route('weights.index'),
                'color' => 'metric-yellow',
            ],
            [
                'label' => 'Validation Issues',
                'value' => $validationIssues,
                'sub' => 'Missing or pending data',
                'route' => route('all-stocks.index'),
                'color' => 'metric-red',
            ],
            [
                'label' => 'Purchased Intake',
                'value' => $purchasedStockMetrics['pending_intakes'],
                'sub' => $purchasedStockMetrics['recent_intakes'] . ' new this week • ' . $purchasedStockMetrics['approved_waiting_stock'] . ' ready for stock',
                'route' => route('purchase-stock-intakes.index'),
                'color' => 'metric-slate',
            ],
        ];

        $needsDataEntry = collect([
            [
                'priority' => 'high',
                'item' => 'Overdue weights',
                'details' => 'Pigs past their weighing schedule',
                'count' => $overdueForWeighing,
                'route' => route('weights.index'),
            ],
            [
                'priority' => 'high',
                'item' => 'Missing first weight',
                'details' => 'Active pigs without an initial weight',
                'count' => $missingFirstWeight,
                'route' => route('weights.create'),
            ],
            [
                'priority' => 'medium',
                'item' => 'Incomplete pig records',
                'details' => 'Missing breed, gender, stock type, or birth date',
                'count' => $incompletePigRecords,
                'route' => route('all-stocks.index'),
            ],
            [
                'priority' => 'medium',
                'item' => 'Pending purchase intake',
                'details' => 'Purchased stock not fully added to stock',
                'count' => $pendingPurchaseIntakes,
                'route' => route('purchase-stock-intakes.index'),
            ],
            [
                'priority' => $pregnancyFollowUpsOverdue > 0 ? 'high' : 'medium',
                'item' => 'Pregnancy follow-ups',
                'details' => 'Uncertain pregnancies needing another check',
                'count' => $pregnancyFollowUpsDue,
                'route' => route('mating.manage'),
            ],
            [
                'priority' => 'low',
                'item' => 'Missing room assignment',
                'details' => 'Active pigs not assigned to housing',
                'count' => $missingRoomAssignments,
                'route' => route('all-stocks.index'),
            ],
        ])->filter(fn ($item) => $item['count'] > 0)->values();

        $completionBase = max(1, PigManagement::active()->count());
        $completePigRecords = max(0, $completionBase - $incompletePigRecords);
        $missingBreed = PigManagement::active()
            ->where(function ($query) {
                $query->whereNull('breed')->orWhere('breed', '');
            })
            ->count();
        $missingGender = PigManagement::active()
            ->where(function ($query) {
                $query->whereNull('gender')->orWhere('gender', '');
            })
            ->count();
        $missingDateOfBirth = PigManagement::active()->whereNull('date_of_birth')->count();
        $missingStockType = PigManagement::active()
            ->where(function ($query) {
                $query->whereNull('stock_type')->orWhere('stock_type', '');
            })
            ->count();
        $duplicateEarTags = PigManagement::select('ear_tag_number')
            ->whereNotNull('ear_tag_number')
            ->groupBy('ear_tag_number')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $dataQuality = [
            'completeness' => round(($completePigRecords / $completionBase) * 100),
            'duplicates' => $duplicateEarTags,
            'missing_fields' => $incompletePigRecords + $missingRoomAssignments,
            'failed_imports' => 0,
        ];

        $dataQualityDetails = collect([
            [
                'label' => 'Missing breed',
                'count' => $missingBreed,
                'where' => 'All Stock > Edit pig details',
                'route' => route('all-stocks.index'),
                'severity' => 'medium',
            ],
            [
                'label' => 'Missing gender',
                'count' => $missingGender,
                'where' => 'All Stock > Edit pig details',
                'route' => route('all-stocks.index'),
                'severity' => 'medium',
            ],
            [
                'label' => 'Missing date of birth',
                'count' => $missingDateOfBirth,
                'where' => 'All Stock > Edit pig details',
                'route' => route('all-stocks.index'),
                'severity' => 'medium',
            ],
            [
                'label' => 'Missing stock type',
                'count' => $missingStockType,
                'where' => 'All Stock > Edit pig details',
                'route' => route('all-stocks.index'),
                'severity' => 'medium',
            ],
            [
                'label' => 'Missing room assignment',
                'count' => $missingRoomAssignments,
                'where' => 'All Stock > Edit pig housing',
                'route' => route('all-stocks.index'),
                'severity' => 'low',
            ],
            [
                'label' => 'Missing first weight',
                'count' => $missingFirstWeight,
                'where' => 'Record Weight',
                'route' => route('weights.create'),
                'severity' => 'high',
            ],
            [
                'label' => 'Pending purchased stock intake',
                'count' => $pendingPurchaseIntakes,
                'where' => 'Purchased Stock Intake',
                'route' => route('purchase-stock-intakes.index'),
                'severity' => 'medium',
            ],
            [
                'label' => 'Duplicate ear tags',
                'count' => $duplicateEarTags,
                'where' => 'All Stock > Resolve ear tags',
                'route' => route('all-stocks.index'),
                'severity' => 'high',
            ],
        ])->filter(fn ($detail) => $detail['count'] > 0)->values();

        // Overdue pigs preview + modal data (computed, no Blade PHP needed)
        $overduePigsPreview = PigManagement::getOverdueForWeighing(30)->take(5)->map(function ($pig) {
            return [
                'pig' => $pig,
                'weighingStatus' => $pig->getWeighingStatus(),
                'daysSince' => $pig->getDaysSinceLastWeighing(),
            ];
        });

        $allOverduePigs = PigManagement::getOverdueForWeighing();
        $allOverduePigsDetails = $allOverduePigs->map(function ($pig) {
            $weighingStatus = $pig->getWeighingStatus();
            $daysSince = $pig->getDaysSinceLastWeighing();
            $lastWeighing = $pig->getLastWeighingDate();
            $lastWeight = $pig->weights()->latest('weigh_date')->first();
            $overdueDays = $daysSince - 14;

            return [
                'pig' => $pig,
                'weighingStatus' => $weighingStatus,
                'daysSince' => $daysSince,
                'lastWeighing' => $lastWeighing,
                'lastWeight' => $lastWeight,
                'overdueDays' => $overdueDays,
            ];
        });

        return view('dataentry.dashboard', compact(
            'weanedPigs',
            'weaningAge',
            'recentFarrowings',
            'pigletsCount',
            'totalPigs',
            'todayWeights',
            'todayPigs',
            'weekWeights',
            'pendingWeighing',
            'activeAlerts',
            'overdueForWeighing',
            'dueForWeighing',
            'dailyWeightsData',
            'pregnancyRate',
            'farrowingRate',
            'weaningPercentage',
            'uncertainPregnancies',
            'pregnancyFollowUpsDue',
            'pregnancyFollowUpsOverdue',
            'avgStillborns',
            'avgBirthWeight',
            'recentActivities',
            'entryMetrics',
            'needsDataEntry',
            'dataQuality',
            'dataQualityDetails',
            'validationIssues',
            'recentImports',
            'overduePigsPreview',
            'allOverduePigs',
            'allOverduePigsDetails',
            'purchasedStockMetrics',
        ));
    }

    public function getDailyWeightsData(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        // Default to last 7 days if no dates provided
        if (!$startDate || !$endDate) {
            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays(6)->format('Y-m-d');
        }
        
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        // Generate date range
        $dates = [];
        $weightsData = [];
        
        $current = $start->copy();
        while ($current->lte($end)) {
            $date = $current->format('Y-m-d');
            $count = PigWeight::whereDate('created_at', $date)->count();
            
            $dates[] = $current->format('M j');
            $weightsData[] = $count;
            
            $current->addDay();
        }
        
        return response()->json([
            'labels' => $dates,
            'data' => $weightsData
        ]);
    }
}
