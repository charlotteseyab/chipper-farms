<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Farrowing;
use App\Models\HealthRecord;
use App\Models\Mating;
use App\Models\MedicationAdministration;
use App\Models\PigDeath;
use App\Models\PigletDeath;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\User;
use App\Models\Weaning;
use App\Models\WeightAlert;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperuserController extends Controller
{
    public function dashboard(Request $request)
    {
        $range = $request->query('range', 'mtd');
        $selectedUserId = $request->query('user_id');
        $customStartDate = $request->query('start_date');
        $customEndDate = $request->query('end_date');

        $startDate = null;
        $endDate = now();

        if ($customStartDate && $customEndDate) {
            $startDate = Carbon::parse($customStartDate)->startOfDay();
            $endDate = Carbon::parse($customEndDate)->endOfDay();
            $range = 'custom';
        } else {
            switch ($range) {
                case 'today':
                    $startDate = now()->startOfDay();
                    break;
                case '7d':
                    $startDate = now()->subDays(6)->startOfDay();
                    break;
                case 'mtd':
                    $startDate = now()->startOfMonth();
                    break;
                case 'ytd':
                    $startDate = now()->startOfYear();
                    break;
                case 'all':
                default:
                    $startDate = null;
                    $range = 'all';
                    break;
            }
        }

        $selectedUser = $selectedUserId ? User::find($selectedUserId) : null;
        $dashboardUsers = User::orderBy('first_name')->orderBy('last_name')->get();

        $globalKpis = [];
        $alertOverview = [];
        $activityLog = collect();
        $userHealth = [];
        $userFarmMetrics = null;
        $financialTrends = [];

        if (!$selectedUser) {
            $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);

            $recentFarrowings = Farrowing::with('pigletDeaths')
                ->where('farrowing_date', '>=', now()->subDays($weaningAge))
                ->where('status', 'active')
                ->get();

            $pigletsCount = 0;
            foreach ($recentFarrowings as $farrowing) {
                $pigletsCount += max(
                    0,
                    (int) ($farrowing->live_births ?? 0) - (int) $farrowing->pigletDeaths->sum('count')
                );
            }

            $weanedPigs = PigManagement::active()->count();
            $totalPigsGlobal = $weanedPigs + $pigletsCount;

            $totalFarrowingsGlobal = Farrowing::when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('farrowing_date', [$startDate, $endDate]);
                })
                ->count();

            $totalWeanedGlobal = Weaning::when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->count();

            $salesQuery = PigSale::query();
            if ($startDate) {
                $salesQuery->whereBetween('sale_date', [$startDate, $endDate]);
            }
            $totalSalesGlobal = $salesQuery->count();
            $totalRevenueGlobal = $salesQuery->sum('sale_price');

            $alertsQuery = WeightAlert::where('acknowledged', false);
            if ($startDate) {
                $alertsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            $globalKpis = [
                'total_pigs' => $totalPigsGlobal,
                'total_farrowings' => $totalFarrowingsGlobal,
                'total_weaned' => $totalWeanedGlobal,
                'total_sales' => $totalSalesGlobal,
                'total_revenue' => $totalRevenueGlobal,
                'critical_alerts' => (clone $alertsQuery)->where('severity', 'high')->count(),
            ];

            $alertOverview = [
                'high_weight_alerts' => (clone $alertsQuery)->where('severity', 'high')->count(),
                'open_alerts' => $alertsQuery->count(),
                'health_records_this_month' => HealthRecord::when($startDate, function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('record_date', [$startDate->toDateString(), $endDate->toDateString()]);
                    }, function ($q) {
                        $q->whereMonth('record_date', now()->month)
                          ->whereYear('record_date', now()->year);
                    })
                    ->count(),
            ];

            $activityLog = ActivityLog::when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            $totalUsers = User::count();
            $activeUsers = User::where('active_status', true)->count();

            $userHealth = [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'inactive' => $totalUsers - $activeUsers,
            ];

            $financialTrends = $this->getFinancialTrends($startDate, $endDate);
        } else {
            $liveWeightPrice = Setting::get('sales.pricing.live_weight_price', 30.00);

            $userTotalPigs = PigManagement::count();
            $userActivePigs = PigManagement::active()->count();
            $userBreedingStock = PigManagement::where('stock_type', 'breeding')->count();
            $userStandardStock = PigManagement::where('stock_type', 'standard')->count();
            $userActiveMale = PigManagement::active()->where('gender', 'M')->count();
            $userActiveFemale = PigManagement::active()->where('gender', 'F')->count();

            $userBreedCounts = [
                'LW' => PigManagement::where('breed', 'LW')->count(),
                'DR' => PigManagement::where('breed', 'DR')->count(),
                'LWDR' => PigManagement::where('breed', 'LWDR')->count(),
            ];

            $userDeceasedPigs = PigManagement::deceased()->count();
            $userSoldPigs = PigManagement::sold()->count();

            $userTotalDeaths = PigDeath::count() + (int) PigletDeath::sum('count');
            $userMortalityRate = $userTotalPigs > 0
                ? round(($userTotalDeaths / $userTotalPigs) * 100, 1)
                : 0;

            $userFarrowingsQuery = Farrowing::query();
            if ($startDate) {
                $userFarrowingsQuery->whereBetween('farrowing_date', [$startDate, $endDate]);
            }

            $userTotalFarrowings = $userFarrowingsQuery->count();
            $userTotalBorn = $userFarrowingsQuery->sum('live_births');
            $userTotalStillborns = $userFarrowingsQuery->sum('stillbirths');
            $userAvgLitterSize = $userFarrowingsQuery->avg('live_births');
            $userSurvivalRate = $userTotalBorn > 0
                ? round((($userTotalBorn - $userTotalStillborns) / $userTotalBorn) * 100, 1)
                : 0;

            $userWeanedQuery = Weaning::query();
            if ($startDate) {
                $userWeanedQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $userTotalWeaned = $userWeanedQuery->count();
            $userWeaningPercentage = $userTotalBorn > 0
                ? round(($userTotalWeaned / $userTotalBorn) * 100, 1)
                : 0;

            $userWeightsQuery = PigWeight::query();
            if ($startDate) {
                $userWeightsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $userTotalWeights = $userWeightsQuery->count();

            $userAvgWeight = PigWeight::whereIn('id', function ($query) {
                    $query->select(DB::raw('MAX(id)'))
                        ->from('pig_weights')
                        ->groupBy('pig_id');
                })
                ->avg('weight');

            $userTotalWeight = PigWeight::whereIn('id', function ($query) {
                    $query->select(DB::raw('MAX(id)'))
                        ->from('pig_weights')
                        ->groupBy('pig_id');
                })
                ->sum('weight');

            $userFarmValue = $userTotalWeight * $liveWeightPrice;

            $userSalesQuery = PigSale::query();
            if ($startDate) {
                $userSalesQuery->whereBetween('sale_date', [$startDate, $endDate]);
            }
            $userTotalSales = $userSalesQuery->count();
            $userTotalRevenue = $userSalesQuery->sum('sale_price');

            $userMatingsQuery = Mating::query();
            if ($startDate) {
                $userMatingsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            $userTotalMatings = $userMatingsQuery->count();
            $userPregnantMatings = (clone $userMatingsQuery)->where('pregnancy_status', 'pregnant')->count();
            $userPregNum = (clone $userMatingsQuery)->where('pregnancy_status', 'pregnant')->count();
            $userPregDenom = (clone $userMatingsQuery)->whereNotNull('female_id')->count();
            $userPregnancyRate = $userPregDenom > 0
                ? round($userPregNum / $userPregDenom * 100, 1)
                : 0;

            $userHealthQuery = HealthRecord::query();
            if ($startDate) {
                $userHealthQuery->whereBetween('record_date', [$startDate, $endDate]);
            }
            $userTotalHealthRecords = $userHealthQuery->count();

            $userTopConditions = HealthRecord::when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('record_date', [$startDate, $endDate]);
                })
                ->select('condition')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('condition')
                ->orderByDesc('count')
                ->limit(5)
                ->get();

            $userWeightAlerts = WeightAlert::where('acknowledged', false)
                ->when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->latest()
                ->take(5)
                ->get();

            $userCriticalAlerts = WeightAlert::where('severity', 'high')
                ->where('acknowledged', false)
                ->when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->count();

            $userOpenAlerts = WeightAlert::where('acknowledged', false)
                ->when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->count();

            $userActivityLog = ActivityLog::when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            $userFarmMetrics = [
                'owner' => $selectedUser,
                'total_pigs' => $userTotalPigs,
                'active_pigs' => $userActivePigs,
                'breeding_stock' => $userBreedingStock,
                'standard_stock' => $userStandardStock,
                'active_male' => $userActiveMale,
                'active_female' => $userActiveFemale,
                'breed_counts' => $userBreedCounts,
                'deceased_pigs' => $userDeceasedPigs,
                'sold_pigs' => $userSoldPigs,
                'mortality_rate' => $userMortalityRate,
                'total_farrowings' => $userTotalFarrowings,
                'total_born' => $userTotalBorn,
                'total_stillborns' => $userTotalStillborns,
                'avg_litter_size' => $userAvgLitterSize ? round($userAvgLitterSize, 1) : 0,
                'survival_rate' => $userSurvivalRate,
                'total_weaned' => $userTotalWeaned,
                'weaning_percentage' => $userWeaningPercentage,
                'total_weights' => $userTotalWeights,
                'avg_weight' => $userAvgWeight ? round($userAvgWeight, 1) : 0,
                'total_weight' => $userTotalWeight ? round($userTotalWeight, 1) : 0,
                'farm_value' => $userFarmValue ? round($userFarmValue, 0) : 0,
                'total_sales' => $userTotalSales,
                'total_revenue' => $userTotalRevenue ? round($userTotalRevenue, 0) : 0,
                'total_matings' => $userTotalMatings,
                'pregnant_matings' => $userPregnantMatings,
                'pregnancy_rate' => $userPregnancyRate,
                'total_health_records' => $userTotalHealthRecords,
                'top_conditions' => $userTopConditions,
                'weight_alerts' => $userWeightAlerts,
                'critical_alerts' => $userCriticalAlerts,
                'open_alerts' => $userOpenAlerts,
                'low_stock_feeds' => collect(),
                'low_stock_count' => 0,
                'activity_log' => $userActivityLog,
                'users' => User::orderBy('first_name')->orderBy('last_name')->get(),
            ];
        }

        $pendingRequests = collect();
        $pendingRequestCount = 0;
        $pendingRequestValue = 0;
        $recentApprovals = collect();
        $inventory = collect();
        $lowStockFeeds = collect();
        $lowStockCount = 0;

        $overdueWeighings = PigManagement::countOverdueForWeighing();
        $dueWeighings = PigManagement::countDueForWeighing();

        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
        $readyToWean = Farrowing::where('status', 'active')
            ->whereDate('farrowing_date', '<=', now()->subDays($weaningAge))
            ->whereDoesntHave('weanings', function ($query) {
                $query->where('maturity_completed', true);
            })
            ->count();

        $weightAlerts = WeightAlert::where('acknowledged', false)->latest()->take(5)->get();

        $user = auth()->user();
        $recentNotifications = $user ? $user->notifications()->latest()->take(6)->get() : collect();
        $unreadNotifications = $user ? $user->unreadNotifications()->count() : 0;

        $recentFarrowings = Farrowing::with('pigletDeaths')
            ->where('farrowing_date', '>=', now()->subDays($weaningAge))
            ->where('status', 'active')
            ->get();

        $pigletsCount = 0;
        foreach ($recentFarrowings as $farrowing) {
            $pigletsCount += max(
                0,
                (int) ($farrowing->live_births ?? 0) - (int) $farrowing->pigletDeaths->sum('count')
            );
        }

        $weanedPigs = PigManagement::active()->count();
        $totalPigs = $weanedPigs + $pigletsCount;
        $activePigs = PigManagement::active()->count();
        $deceasedPigs = PigManagement::deceased()->count();
        $soldPigs = PigManagement::sold()->count();
        $activeMaleCount = PigManagement::active()->where('gender', 'M')->count();
        $activeFemaleCount = PigManagement::active()->where('gender', 'F')->count();
        $breedingStock = PigManagement::where('stock_type', 'breeding')->count();
        $standardStock = PigManagement::where('stock_type', 'standard')->count();

        $breedCounts = [
            'LW' => PigManagement::where('breed', 'LW')->count(),
            'DR' => PigManagement::where('breed', 'DR')->count(),
            'LWDR' => PigManagement::where('breed', 'LWDR')->count(),
        ];

        $totalDeaths = PigDeath::count() + (int) PigletDeath::sum('count');
        $mortalityRate = $totalPigs > 0 ? ($totalDeaths / $totalPigs) * 100 : 0;

        $totalMatings = Mating::count();
        $plannedMatings = Mating::planned()->count();
        $completedMatings = Mating::completed()->count();
        $pregnantMatings = Mating::pregnant()->count();
        $pregnancyChecksDue = Mating::pregnancyCheckDue()->count();
        $pregnancyChecksOverdue = Mating::pregnancyCheckOverdue()->count();
        $upcomingMatings = Mating::upcoming()->count();
        $pregNum = Mating::where('pregnancy_status', 'pregnant')->count();
        $pregDenom = Mating::whereNotNull('female_id')->count();
        $pregnancyRate = $pregDenom > 0 ? round($pregNum / $pregDenom * 100, 1) : 0;
        $successfulMatings = Mating::where('pregnancy_status', 'pregnant')->count();
        $fertilityRate = $totalMatings > 0 ? round(($successfulMatings / $totalMatings) * 100, 1) : 0;

        $totalPregnant = Mating::where('pregnancy_status', 'pregnant')->count();
        $totalFarrowed = Mating::where('pregnancy_status', 'farrowed')->count();
        $dueThisWeek = Mating::where('pregnancy_status', 'pregnant')
            ->whereBetween('expected_farrowing_date', [now(), now()->addWeek()])
            ->count();
        $dueNextWeek = Mating::where('pregnancy_status', 'pregnant')
            ->whereBetween('expected_farrowing_date', [now()->addWeek(), now()->addWeeks(2)])
            ->count();
        $overdueGestation = Mating::where('pregnancy_status', 'pregnant')
            ->where('expected_farrowing_date', '<', now())
            ->count();
        $farrowedThisMonth = Mating::where('pregnancy_status', 'farrowed')
            ->whereMonth('actual_farrowing_date', now()->month)
            ->whereYear('actual_farrowing_date', now()->year)
            ->count();

        $avgGestationDays = Mating::whereNotNull('actual_farrowing_date')
            ->whereNotNull('actual_date')
            ->get()
            ->map(fn ($mating) => $mating->actual_date->diffInDays($mating->actual_farrowing_date))
            ->avg();
        $avgGestationDays = $avgGestationDays ? round($avgGestationDays, 1) : 0;

        $totalFarrowings = Farrowing::whereNotNull('farrowing_date')->count();
        $totalBorn = Farrowing::sum('live_births');
        $totalStillborns = Farrowing::sum('stillbirths');
        $avgLitterSize = Farrowing::avg('live_births');
        $avgStillborns = Farrowing::avg('stillbirths');
        $avgBirthWeight = Farrowing::whereNotNull('average_birth_weight')->avg('average_birth_weight');
        $farrowingsThisMonth = Farrowing::whereMonth('farrowing_date', now()->month)
            ->whereYear('farrowing_date', now()->year)
            ->count();
        $farrowingsThisYear = Farrowing::whereYear('farrowing_date', now()->year)->count();
        $survivalRate = $totalBorn > 0 ? round((($totalBorn - $totalStillborns) / $totalBorn) * 100, 1) : 0;

        $totalWeaned = Weaning::count();
        $weaningPercentage = $totalBorn > 0 ? round($totalWeaned / $totalBorn * 100, 1) : 0;
        $dueSoonWeaning = Farrowing::where('status', 'active')
            ->whereBetween('farrowing_date', [now()->subDays($weaningAge + 7), now()->subDays($weaningAge)])
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
        $avgWeaningAge = Weaning::whereNotNull('age_at_weaning_days')->avg('age_at_weaning_days');
        $avgWeaningWeight = Weaning::whereNotNull('weaning_weight')->avg('weaning_weight');

        $totalWeights = PigWeight::count();
        $dueForWeighing = PigManagement::countDueForWeighing();

        $avgWeight = PigWeight::whereIn('id', function ($query) {
            $query->select(DB::raw('MAX(id)'))
                ->from('pig_weights')
                ->groupBy('pig_id');
        })->avg('weight');

        $totalWeight = PigWeight::whereIn('id', function ($query) {
            $query->select(DB::raw('MAX(id)'))
                ->from('pig_weights')
                ->groupBy('pig_id');
        })->sum('weight');

        $liveWeightPrice = Setting::get('sales.pricing.live_weight_price', 30.00);
        $totalFarmValue = $totalWeight * $liveWeightPrice;
        $weightsThisWeek = PigWeight::where('created_at', '>=', now()->subWeek())->count();

        $totalFeedItems = 0;
        $totalFeedQuantity = 0;
        $scheduledFeeds = 0;
        $fedToday = 0;
        $totalScheduled = 0;

        $totalHealthRecords = HealthRecord::count();
        $healthRecordsThisMonth = HealthRecord::whereMonth('record_date', now()->month)->count();
        $totalMedications = MedicationAdministration::count();
        $medicationsThisMonth = MedicationAdministration::whereMonth('administration_date', now()->month)->count();

        $topConditions = HealthRecord::select('condition')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('condition')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $criticalAlerts = WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();
        $totalAlerts = WeightAlert::where('acknowledged', false)->count();

        $totalSales = PigSale::count();
        $totalRevenue = PigSale::sum('sale_price');

        $dataQuality = [
            'missing_ear_tag' => PigManagement::whereNull('ear_tag_number')->orWhere('ear_tag_number', '')->count(),
            'missing_dob' => PigManagement::whereNull('date_of_birth')->count(),
            'missing_gender' => PigManagement::whereNull('gender')->orWhere('gender', '')->count(),
            'missing_breed' => PigManagement::whereNull('breed')->orWhere('breed', '')->count(),
            'no_weights_30d' => PigManagement::whereDoesntHave('weights', function ($q) {
                $q->where('weigh_date', '>=', now()->subDays(30));
            })->count(),
            'duplicate_ear_tags' => PigManagement::whereNotNull('ear_tag_number')
                ->where('ear_tag_number', '!=', '')
                ->select('ear_tag_number')
                ->groupBy('ear_tag_number')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
        ];

        $unpaidSalesBase = PigSale::query()
            ->where('payment_status', '!=', 'paid')
            ->whereNotNull('sale_date');

        if ($startDate) {
            $unpaidSalesBase->whereBetween('sale_date', [$startDate, $endDate]);
        }

        $unpaidRows = (clone $unpaidSalesBase)->get(['sale_date', 'sale_price']);

        $receivablesAging = [
            '0_7' => ['count' => 0, 'amount' => 0.0],
            '8_30' => ['count' => 0, 'amount' => 0.0],
            '31_60' => ['count' => 0, 'amount' => 0.0],
            '60_plus' => ['count' => 0, 'amount' => 0.0],
            'total' => ['count' => 0, 'amount' => 0.0],
        ];

        foreach ($unpaidRows as $row) {
            $days = Carbon::parse($row->sale_date)->diffInDays(now());
            $amount = (float) ($row->sale_price ?? 0);

            $bucket = '60_plus';
            if ($days <= 7) {
                $bucket = '0_7';
            } elseif ($days <= 30) {
                $bucket = '8_30';
            } elseif ($days <= 60) {
                $bucket = '31_60';
            }

            $receivablesAging[$bucket]['count']++;
            $receivablesAging[$bucket]['amount'] += $amount;
            $receivablesAging['total']['count']++;
            $receivablesAging['total']['amount'] += $amount;
        }

        $opsSnapshot = [
            'overdue_weighings' => (int) $overdueWeighings,
            'due_weighings' => (int) $dueWeighings,
            'overdue_weaning' => (int) $overdueWeaning,
            'due_soon_weaning' => (int) $dueSoonWeaning,
            'preg_checks_overdue' => (int) $pregnancyChecksOverdue,
            'preg_checks_due' => (int) $pregnancyChecksDue,
            'overdue_gestation' => (int) $overdueGestation,
            'low_stock_items' => (int) $lowStockCount,
            'critical_alerts' => (int) $criticalAlerts,
        ];

        return view('superuser.dashboard', compact(
            'range',
            'startDate',
            'endDate',
            'dashboardUsers',
            'selectedUserId',
            'selectedUser',
            'globalKpis',
            'alertOverview',
            'activityLog',
            'userHealth',
            'userFarmMetrics',
            'liveWeightPrice',
            'pendingRequests',
            'pendingRequestCount',
            'pendingRequestValue',
            'recentApprovals',
            'inventory',
            'lowStockFeeds',
            'lowStockCount',
            'overdueWeighings',
            'dueWeighings',
            'weaningAge',
            'readyToWean',
            'weightAlerts',
            'recentNotifications',
            'unreadNotifications',
            'totalPigs',
            'weanedPigs',
            'pigletsCount',
            'activePigs',
            'deceasedPigs',
            'soldPigs',
            'activeMaleCount',
            'activeFemaleCount',
            'breedingStock',
            'standardStock',
            'breedCounts',
            'mortalityRate',
            'totalMatings',
            'plannedMatings',
            'completedMatings',
            'pregnantMatings',
            'pregnancyChecksDue',
            'pregnancyChecksOverdue',
            'upcomingMatings',
            'pregnancyRate',
            'fertilityRate',
            'totalPregnant',
            'totalFarrowed',
            'dueThisWeek',
            'dueNextWeek',
            'overdueGestation',
            'farrowedThisMonth',
            'avgGestationDays',
            'totalFarrowings',
            'totalBorn',
            'avgLitterSize',
            'avgStillborns',
            'avgBirthWeight',
            'farrowingsThisMonth',
            'farrowingsThisYear',
            'survivalRate',
            'totalWeaned',
            'weaningPercentage',
            'dueSoonWeaning',
            'overdueWeaning',
            'avgWeaningAge',
            'avgWeaningWeight',
            'totalWeights',
            'dueForWeighing',
            'avgWeight',
            'totalWeight',
            'totalFarmValue',
            'weightsThisWeek',
            'totalFeedItems',
            'totalFeedQuantity',
            'scheduledFeeds',
            'fedToday',
            'totalScheduled',
            'totalHealthRecords',
            'healthRecordsThisMonth',
            'totalMedications',
            'medicationsThisMonth',
            'topConditions',
            'criticalAlerts',
            'totalAlerts',
            'totalSales',
            'totalRevenue',
            'financialTrends',
            'dataQuality',
            'receivablesAging',
            'opsSnapshot'
        ));
    }

    private function getFinancialTrends(?Carbon $startDate, Carbon $endDate): array
    {
        if (!$startDate) {
            $startDate = now()->subMonths(12)->startOfMonth();
        }

        $saleDateMonth = $this->monthExpression('sale_date');

        $revenueByMonth = PigSale::selectRaw("{$saleDateMonth} as month, SUM(sale_price) as revenue, COUNT(*) as sales_count")
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $avgPriceByMonth = PigSale::selectRaw("{$saleDateMonth} as month, AVG(sale_price) as avg_price")
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $unpaidSales = PigSale::where('payment_status', '!=', 'paid')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->sum('sale_price');

        $unpaidSalesCount = PigSale::where('payment_status', '!=', 'paid')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->count();

        return [
            'revenue_by_month' => $revenueByMonth,
            'avg_price_by_month' => $avgPriceByMonth,
            'feed_costs_by_month' => collect(),
            'unpaid_sales_amount' => $unpaidSales,
            'unpaid_sales_count' => $unpaidSalesCount,
        ];
    }

    private function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, \"%Y-%m\")";
    }

    public function exportPdf(Request $request)
    {
        $range = $request->query('range', 'mtd');
        $selectedUserId = $request->query('user_id');
        $customStartDate = $request->query('start_date');
        $customEndDate = $request->query('end_date');

        $startDate = null;
        $endDate = now();

        if ($customStartDate && $customEndDate) {
            $startDate = Carbon::parse($customStartDate)->startOfDay();
            $endDate = Carbon::parse($customEndDate)->endOfDay();
        } else {
            switch ($range) {
                case 'today':
                    $startDate = now()->startOfDay();
                    break;
                case '7d':
                    $startDate = now()->subDays(6)->startOfDay();
                    break;
                case 'mtd':
                    $startDate = now()->startOfMonth();
                    break;
                case 'ytd':
                    $startDate = now()->startOfYear();
                    break;
            }
        }

        $selectedUser = $selectedUserId ? User::find($selectedUserId) : null;

        $globalKpis = [
            'total_pigs' => PigManagement::active()->count(),
            'total_revenue' => PigSale::when($startDate, fn ($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))->sum('sale_price'),
            'total_sales' => PigSale::when($startDate, fn ($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))->count(),
        ];

        $pdf = \PDF::loadView('superuser.dashboard-export-pdf', compact(
            'range',
            'startDate',
            'endDate',
            'selectedUser',
            'globalKpis'
        ));

        $filename = 'superuser-dashboard-' . now()->format('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }

    public function exportCsv(Request $request)
    {
        $range = $request->query('range', 'mtd');
        $customStartDate = $request->query('start_date');
        $customEndDate = $request->query('end_date');

        $startDate = null;
        $endDate = now();

        if ($customStartDate && $customEndDate) {
            $startDate = Carbon::parse($customStartDate)->startOfDay();
            $endDate = Carbon::parse($customEndDate)->endOfDay();
        } else {
            switch ($range) {
                case 'today':
                    $startDate = now()->startOfDay();
                    break;
                case '7d':
                    $startDate = now()->subDays(6)->startOfDay();
                    break;
                case 'mtd':
                    $startDate = now()->startOfMonth();
                    break;
                case 'ytd':
                    $startDate = now()->startOfYear();
                    break;
            }
        }

        $filename = 'superuser-dashboard-' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($startDate, $endDate) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['SUPERUSER DASHBOARD EXPORT']);
            fputcsv($file, ['Generated:', now()->format('Y-m-d H:i:s')]);
            fputcsv($file, ['Date Range:', $startDate ? $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d') : 'All Time']);
            fputcsv($file, []);

            fputcsv($file, ['GLOBAL SUMMARY']);
            fputcsv($file, ['Metric', 'Value']);
            fputcsv($file, ['Total Pigs', PigManagement::active()->count()]);
            fputcsv($file, ['Total Revenue', PigSale::when($startDate, fn ($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))->sum('sale_price')]);
            fputcsv($file, ['Total Sales', PigSale::when($startDate, fn ($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))->count()]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
