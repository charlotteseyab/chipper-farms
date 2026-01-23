<?php

namespace App\Http\Controllers;

use App\Models\FeedInventory;
use App\Models\FeedPurchaseRequest;
use App\Models\FeedSchedule;
use App\Models\Farrowing;
use App\Models\HealthRecord;
use App\Models\Mating;
use App\Models\MedicationAdministration;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PigWeight;
use App\Models\PigletDeath;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Weaning;
use App\Models\WeightAlert;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SuperuserController extends Controller
{
    public function dashboard(Request $request)
    {
        // Global filters: date range + selected user
        $range = $request->query('range', 'mtd'); // today, 7d, mtd, ytd, all, custom
        $selectedUserId = $request->query('user_id');
        $customStartDate = $request->query('start_date');
        $customEndDate = $request->query('end_date');

        // Compute date bounds (not yet applied to all queries, but ready for per-section filtering)
        $startDate = null;
        $endDate = now();

        // If custom dates are provided, use them; otherwise use predefined ranges
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

        // Workspace support has been removed from the app. For now, keep the "View as User"
        // dropdown as a UI affordance without switching dashboard data context.
        // A full user-attributed dashboard (by recorded_by/sold_by/weighed_by) can be added next.
        $selectedUser = null;
        $dashboardUsers = User::orderBy('first_name')->orderBy('last_name')->get();

        // Aggregate metrics for all workspaces (only when no specific user is selected)
        $workspaceMetrics = [];
        $workspaceMetricsMax = [];
        $globalKpis = [];
        $topFarms = collect();
        $bottomFarms = collect();
        $alertOverview = [];
        $activityLog = collect();
        $userHealth = [];
        $userFarmMetrics = null;
        $financialTrends = [];
        $workspaceRevenueComparison = [];

        if (!$selectedUser) {
            $workspaces = Workspace::orderBy('name')->get();

            // Aggregated counts grouped by workspace_id
            $pigsByWs = PigManagement::withoutGlobalScope('workspace')
                ->select('workspace_id', DB::raw('COUNT(*) as total'))
                ->groupBy('workspace_id')
                ->pluck('total', 'workspace_id');

            $farrowingsByWs = Farrowing::withoutGlobalScope('workspace')
                ->select('workspace_id', DB::raw('COUNT(*) as total'))
                ->groupBy('workspace_id')
                ->pluck('total', 'workspace_id');

            $weightsByWs = PigWeight::withoutGlobalScope('workspace')
                ->select('workspace_id', DB::raw('COUNT(*) as total'))
                ->groupBy('workspace_id')
                ->pluck('total', 'workspace_id');

            $healthByWs = HealthRecord::withoutGlobalScope('workspace')
                ->select('workspace_id', DB::raw('COUNT(*) as total'))
                ->groupBy('workspace_id')
                ->pluck('total', 'workspace_id');

            $usersByWs = User::select('workspace_id', DB::raw('COUNT(*) as total'))
                ->groupBy('workspace_id')
                ->pluck('total', 'workspace_id');

            foreach ($workspaces as $ws) {
                $workspaceMetrics[] = [
                    'id' => $ws->id,
                    'name' => $ws->name,
                    'code' => $ws->code,
                    'users' => (int) ($usersByWs[$ws->id] ?? 0),
                    'pigs' => (int) ($pigsByWs[$ws->id] ?? 0),
                    'farrowings' => (int) ($farrowingsByWs[$ws->id] ?? 0),
                    'weights' => (int) ($weightsByWs[$ws->id] ?? 0),
                    'health' => (int) ($healthByWs[$ws->id] ?? 0),
                ];
            }

            // Compute max values for relative bar sizing in the view
            $maxPigs = collect($workspaceMetrics)->max('pigs') ?: 1;
            $maxFarrowings = collect($workspaceMetrics)->max('farrowings') ?: 1;
            $maxWeights = collect($workspaceMetrics)->max('weights') ?: 1;
            $maxHealth = collect($workspaceMetrics)->max('health') ?: 1;

            $workspaceMetricsMax = [
                'pigs' => $maxPigs,
                'farrowings' => $maxFarrowings,
                'weights' => $maxWeights,
                'health' => $maxHealth,
            ];

            // Global KPIs (simple cross-workspace view, timeframe-aware where possible)
            $totalPigsGlobal = array_sum(array_column($workspaceMetrics, 'pigs'));
            $totalFarrowingsGlobal = array_sum(array_column($workspaceMetrics, 'farrowings'));

            $totalWeanedGlobal = Weaning::withoutGlobalScope('workspace')
                ->when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->count();

            $salesQuery = PigSale::withoutGlobalScope('workspace');
            if ($startDate) {
                $salesQuery->whereBetween('sale_date', [$startDate, $endDate]);
            }
            $totalSalesGlobal = $salesQuery->count();
            $totalRevenueGlobal = $salesQuery->sum('sale_price');

            $alertsQuery = WeightAlert::where('acknowledged', false);
            if ($startDate) {
                $alertsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $totalCriticalAlertsGlobal = (clone $alertsQuery)
                ->where('severity', 'high')
                ->count();

            $globalKpis = [
                'total_pigs' => $totalPigsGlobal,
                'total_farrowings' => $totalFarrowingsGlobal,
                'total_weaned' => $totalWeanedGlobal,
                'total_sales' => $totalSalesGlobal,
                'total_revenue' => $totalRevenueGlobal,
                'critical_alerts' => $totalCriticalAlertsGlobal,
            ];

            // Top / bottom farms by pigs
            $metricsCollection = collect($workspaceMetrics);
            $topFarms = $metricsCollection->sortByDesc('pigs')->take(3);
            $bottomFarms = $metricsCollection
                ->filter(fn ($ws) => $ws['pigs'] > 0)
                ->sortBy('pigs')
                ->take(3);

            // Alert & risk overview (global, timeframe-aware)
            $highWeightAlerts = $totalCriticalAlertsGlobal;
            $openAlerts = $alertsQuery->count();

            $healthQuery = HealthRecord::withoutGlobalScope('workspace');
            if ($startDate) {
                $healthQuery->whereBetween('record_date', [$startDate->toDateString(), $endDate->toDateString()]);
            } else {
                $healthQuery->whereMonth('record_date', now()->month);
            }
            $healthRecordsThisPeriodGlobal = $healthQuery->count();

            $alertOverview = [
                'high_weight_alerts' => $highWeightAlerts,
                'open_alerts' => $openAlerts,
                'health_records_this_month' => $healthRecordsThisPeriodGlobal,
            ];

            // Recent admin / system activity (timeframe-aware)
            $activityLog = ActivityLog::when($startDate, function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            // User / login health
            $totalUsers = User::count();
            $activeUsers = User::where('active_status', true)->count();
            $inactiveUsers = $totalUsers - $activeUsers;

            $userHealth = [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'inactive' => $inactiveUsers,
            ];

            // Financial Trends Data
            $financialTrends = $this->getFinancialTrends($startDate, $endDate);
            
            // Workspace Revenue Comparison
            $workspaceRevenueComparison = $this->getWorkspaceRevenueComparison($startDate, $endDate);
        } else {
            // User-specific metrics: scope all queries to the selected user's workspace
            $userWorkspaceId = $selectedUser->workspace_id;
            
            if (!$userWorkspaceId) {
                // User has no workspace assigned - show empty state
                $userFarmMetrics = null;
            } else {
                // Get workspace info
                $userWorkspace = Workspace::find($userWorkspaceId);
        $liveWeightPrice = Setting::get('sales.pricing.live_weight_price', 30.00);

                // Stock metrics (timeframe-aware)
                $userPigsQuery = PigManagement::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId);
                $userTotalPigs = $userPigsQuery->count();
                $userActivePigs = $userPigsQuery->clone()->active()->count();
                $userBreedingStock = $userPigsQuery->clone()->where('stock_type', 'breeding')->count();
                $userStandardStock = $userPigsQuery->clone()->where('stock_type', 'standard')->count();
                $userActiveMale = $userPigsQuery->clone()->active()->where('gender', 'M')->count();
                $userActiveFemale = $userPigsQuery->clone()->active()->where('gender', 'F')->count();
                $userBreedCounts = [
                    'LW' => $userPigsQuery->clone()->where('breed', 'LW')->count(),
                    'DR' => $userPigsQuery->clone()->where('breed', 'DR')->count(),
                    'LWDR' => $userPigsQuery->clone()->where('breed', 'LWDR')->count(),
                ];
                $userDeceasedPigs = PigManagement::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId)
                    ->deceased()
                    ->count();
                $userSoldPigs = PigManagement::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId)
                    ->sold()
                    ->count();
                $userTotalDeaths = PigDeath::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId)
                    ->count() + (int) PigletDeath::whereHas('farrowing', function ($q) use ($userWorkspaceId) {
                        $q->where('workspace_id', $userWorkspaceId);
                    })
                    ->sum('count');
                $userMortalityRate = $userTotalPigs > 0 ? round(($userTotalDeaths / $userTotalPigs) * 100, 1) : 0;
                
                // Farrowing metrics (timeframe-aware)
                $userFarrowingsQuery = Farrowing::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId);
                if ($startDate) {
                    $userFarrowingsQuery->whereBetween('farrowing_date', [$startDate, $endDate]);
                }
                $userTotalFarrowings = $userFarrowingsQuery->count();
                $userTotalBorn = $userFarrowingsQuery->sum('live_births');
                $userTotalStillborns = $userFarrowingsQuery->sum('stillbirths');
                $userAvgLitterSize = $userFarrowingsQuery->avg('live_births');
                $userSurvivalRate = $userTotalBorn > 0 ? round((($userTotalBorn - $userTotalStillborns) / $userTotalBorn) * 100, 1) : 0;
                
                // Weaning metrics (timeframe-aware)
                $userWeanedQuery = Weaning::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId);
                if ($startDate) {
                    $userWeanedQuery->whereBetween('created_at', [$startDate, $endDate]);
                }
                $userTotalWeaned = $userWeanedQuery->count();
                $userWeaningPercentage = $userTotalBorn > 0 ? round(($userTotalWeaned / $userTotalBorn) * 100, 1) : 0;
                
                // Weight metrics (timeframe-aware)
                $userWeightsQuery = PigWeight::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId);
                if ($startDate) {
                    $userWeightsQuery->whereBetween('created_at', [$startDate, $endDate]);
                }
                $userTotalWeights = $userWeightsQuery->count();
                $userAvgWeight = PigWeight::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId)
                    ->whereIn('id', function ($query) use ($userWorkspaceId) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from('pig_weights')
                            ->where('workspace_id', $userWorkspaceId)
                            ->groupBy('pig_id');
                    })
                    ->avg('weight');
                $userTotalWeight = PigWeight::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId)
                    ->whereIn('id', function ($query) use ($userWorkspaceId) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from('pig_weights')
                            ->where('workspace_id', $userWorkspaceId)
                            ->groupBy('pig_id');
                    })
                    ->sum('weight');
                $userFarmValue = $userTotalWeight * $liveWeightPrice;
                
                // Sales metrics (timeframe-aware)
                $userSalesQuery = PigSale::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId);
                if ($startDate) {
                    $userSalesQuery->whereBetween('sale_date', [$startDate, $endDate]);
                }
                $userTotalSales = $userSalesQuery->count();
                $userTotalRevenue = $userSalesQuery->sum('sale_price');
                
                // Mating metrics (timeframe-aware)
                $userMatingsQuery = Mating::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId);
                if ($startDate) {
                    $userMatingsQuery->whereBetween('created_at', [$startDate, $endDate]);
                }
                $userTotalMatings = $userMatingsQuery->count();
                $userPregnantMatings = $userMatingsQuery->clone()->where('pregnancy_status', 'pregnant')->count();
                $userPregNum = $userMatingsQuery->clone()->where('pregnancy_status', 'pregnant')->count();
                $userPregDenom = $userMatingsQuery->clone()->whereNotNull('female_id')->count();
                $userPregnancyRate = $userPregDenom > 0 ? round($userPregNum / $userPregDenom * 100, 1) : 0;
                
                // Health metrics (timeframe-aware)
                $userHealthQuery = HealthRecord::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId);
                if ($startDate) {
                    $userHealthQuery->whereBetween('record_date', [$startDate, $endDate]);
                }
                $userTotalHealthRecords = $userHealthQuery->count();
                $userTopConditions = HealthRecord::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId)
                    ->when($startDate, function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('record_date', [$startDate, $endDate]);
                    })
                    ->select('condition')
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('condition')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get();
                
                // Alerts specific to this workspace (filter via pig->workspace_id)
                $userPigIds = PigManagement::withoutGlobalScope('workspace')
                    ->where('workspace_id', $userWorkspaceId)
                    ->pluck('id');
                $userWeightAlerts = WeightAlert::whereIn('pig_id', $userPigIds)
                    ->where('acknowledged', false)
                    ->when($startDate, function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    })
                    ->latest()
                    ->take(5)
                    ->get();
                $userCriticalAlerts = WeightAlert::whereIn('pig_id', $userPigIds)
                    ->where('severity', 'high')
                    ->where('acknowledged', false)
                    ->when($startDate, function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    })
                    ->count();
                $userOpenAlerts = WeightAlert::whereIn('pig_id', $userPigIds)
                    ->where('acknowledged', false)
                    ->when($startDate, function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    })
                    ->count();
                
                // Feed inventory (global - not workspace-specific)
                $userFeedInventory = FeedInventory::with('feedType')->get();
                $userLowStockFeeds = $userFeedInventory->filter(function ($item) {
                    $unit = strtolower($item->unit ?? 'kg');
                    if (str_contains($unit, 'bag')) {
                        return $item->quantity < 5;
                    }
                    return $item->quantity < 50;
                })->sortBy('quantity')->values();
                $userLowStockCount = $userLowStockFeeds->count();
                
                // Recent activity for this workspace (filter via user->workspace_id)
                $userWorkspaceUserIds = User::where('workspace_id', $userWorkspaceId)->pluck('id');
                $userActivityLog = ActivityLog::whereIn('user_id', $userWorkspaceUserIds)
                    ->when($startDate, function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    })
                    ->orderByDesc('created_at')
                    ->limit(6)
                    ->get();
                
                // Other users in the same workspace
                $workspaceUsers = User::where('workspace_id', $userWorkspaceId)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get();
                
                $userFarmMetrics = [
                    'workspace' => $userWorkspace,
                    'workspace_users' => $workspaceUsers,
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
                    'low_stock_feeds' => $userLowStockFeeds,
                    'low_stock_count' => $userLowStockCount,
                    'activity_log' => $userActivityLog,
                ];
            }
        }

        $pendingRequests = FeedPurchaseRequest::where('status', 'pending')
            ->orderByDesc('priority')
            ->orderBy('needed_by')
            ->take(6)
            ->get();

        $pendingRequestCount = FeedPurchaseRequest::where('status', 'pending')->count();
        $pendingRequestValue = FeedPurchaseRequest::where('status', 'pending')->sum('cost_ghc');

        $recentApprovals = FeedPurchaseRequest::where('status', 'approved')
            ->latest('decided_at')
            ->latest('updated_at')
            ->take(5)
            ->get();

        $inventory = FeedInventory::with('feedType')->get();
        $lowStockFeeds = $inventory->filter(function ($item) {
            $unit = strtolower($item->unit ?? 'kg');
            if (str_contains($unit, 'bag')) {
                return $item->quantity < 5;
            }

            return $item->quantity < 50;
        })->sortBy('quantity')->values();
        $lowStockCount = $lowStockFeeds->count();

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
        $recentNotifications = $user
            ? $user->notifications()->latest()->take(6)->get()
            : collect();
        $unreadNotifications = $user ? $user->unreadNotifications()->count() : 0;

        // Stock data
        $recentFarrowings = Farrowing::with('pigletDeaths')
            ->where('farrowing_date', '>=', now()->subDays($weaningAge))
            ->where('status', 'active')
            ->get();
        $pigletsCount = 0;
        foreach ($recentFarrowings as $farrowing) {
            $pigletsCount += max(0, (int)($farrowing->live_births ?? 0) - (int)$farrowing->pigletDeaths->sum('count'));
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

        // Mating data
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

        // Gestation data
        $totalPregnant = Mating::where('pregnancy_status', 'pregnant')->count();
        $totalFarrowed = Mating::where('pregnancy_status', 'farrowed')->count();
        $dueThisWeek = Mating::where('pregnancy_status', 'pregnant')
            ->whereBetween('expected_farrowing_date', [now(), now()->addWeek()])->count();
        $dueNextWeek = Mating::where('pregnancy_status', 'pregnant')
            ->whereBetween('expected_farrowing_date', [now()->addWeek(), now()->addWeeks(2)])->count();
        $overdueGestation = Mating::where('pregnancy_status', 'pregnant')
            ->where('expected_farrowing_date', '<', now())->count();
        $farrowedThisMonth = Mating::where('pregnancy_status', 'farrowed')
            ->whereMonth('actual_farrowing_date', now()->month)
            ->whereYear('actual_farrowing_date', now()->year)->count();
        $avgGestationDays = Mating::whereNotNull('actual_farrowing_date')
            ->whereNotNull('actual_date')
            ->get()
            ->map(fn ($mating) => $mating->actual_date->diffInDays($mating->actual_farrowing_date))
            ->avg();
        $avgGestationDays = $avgGestationDays ? round($avgGestationDays, 1) : 0;

        // Farrowing data
        $totalFarrowings = Farrowing::whereNotNull('farrowing_date')->count();
        $totalBorn = Farrowing::sum('live_births');
        $totalStillborns = Farrowing::sum('stillbirths');
        $avgLitterSize = Farrowing::avg('live_births');
        $avgStillborns = Farrowing::avg('stillbirths');
        $avgBirthWeight = Farrowing::whereNotNull('average_birth_weight')->avg('average_birth_weight');
        $farrowingsThisMonth = Farrowing::whereMonth('farrowing_date', now()->month)
            ->whereYear('farrowing_date', now()->year)->count();
        $farrowingsThisYear = Farrowing::whereYear('farrowing_date', now()->year)->count();
        $survivalRate = $totalBorn > 0 ? round((($totalBorn - $totalStillborns) / $totalBorn) * 100, 1) : 0;

        // Weaning data
        $totalWeaned = Weaning::count();
        $weaningPercentage = $totalBorn > 0 ? round($totalWeaned / $totalBorn * 100, 1) : 0;
        $dueSoonWeaning = Farrowing::where('status', 'active')
            ->whereBetween('farrowing_date', [now()->subDays($weaningAge + 7), now()->subDays($weaningAge)])
            ->whereDoesntHave('weanings', function ($query) {
                $query->where('maturity_completed', true);
            })->count();
        $overdueWeaning = Farrowing::where('status', 'active')
            ->whereDate('farrowing_date', '<', now()->subDays($weaningAge + 7))
            ->whereDoesntHave('weanings', function ($query) {
                $query->where('maturity_completed', true);
            })->count();
        $avgWeaningAge = Weaning::whereNotNull('age_at_weaning_days')->avg('age_at_weaning_days');
        $avgWeaningWeight = Weaning::whereNotNull('weaning_weight')->avg('weaning_weight');

        // Weight data
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

        // Feed data
        $totalFeedItems = $inventory->count();
        $totalFeedQuantity = $inventory->sum('quantity');
        $scheduledFeeds = FeedSchedule::where('status', 'planned')->count();
        $fedToday = FeedSchedule::whereDate('scheduled_for', today())
            ->where('status', 'fed')->count();
        $totalScheduled = FeedSchedule::count();

        // Health data
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

        // Sales data
        $totalSales = PigSale::count();
        $totalRevenue = PigSale::sum('sale_price');

        // =========================
        // Data Quality (Global)
        // =========================
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

        // =========================
        // Receivables Aging (Global)
        // =========================
        $unpaidSalesBase = PigSale::query()
            ->where('payment_status', '!=', 'paid')
            ->whereNotNull('sale_date');
        if ($startDate) {
            $unpaidSalesBase->whereBetween('sale_date', [$startDate, $endDate]);
        }

        $unpaidRows = (clone $unpaidSalesBase)
            ->get(['sale_date', 'sale_price']);

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

        // =========================
        // Overdue / Upcoming Snapshot (Global)
        // =========================
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
            // Global filters
            'range',
            'startDate',
            'endDate',
            'dashboardUsers',
            'selectedUserId',
            'selectedUser',
            'workspaceMetrics',
            'workspaceMetricsMax',
            'globalKpis',
            'topFarms',
            'bottomFarms',
            'alertOverview',
            'activityLog',
            'userHealth',
            'userFarmMetrics',
            'liveWeightPrice',

            // ========== PENDING REQUESTS SECTION ==========
            'pendingRequests','pendingRequestCount','pendingRequestValue','recentApprovals','inventory',
            'lowStockFeeds','lowStockCount','overdueWeighings','dueWeighings','weaningAge','readyToWean',
            'weightAlerts','recentNotifications','unreadNotifications',

            // ========== STOCK SECTION ==========
            'totalPigs','weanedPigs','pigletsCount','activePigs','deceasedPigs','soldPigs',
            'activeMaleCount','activeFemaleCount','breedingStock','standardStock','breedCounts',
            'mortalityRate',

            // ========== MATING SECTION ==========
            'totalMatings','plannedMatings','completedMatings','pregnantMatings','pregnancyChecksDue',
            'pregnancyChecksOverdue','upcomingMatings','pregnancyRate','fertilityRate','totalPregnant',
            'totalFarrowed','dueThisWeek','dueNextWeek','overdueGestation','farrowedThisMonth','avgGestationDays',
            'totalFarrowings','totalBorn','avgLitterSize','avgStillborns','avgBirthWeight','farrowingsThisMonth',
            'farrowingsThisYear','survivalRate','totalWeaned','weaningPercentage','dueSoonWeaning','overdueWeaning',
            'avgWeaningAge','avgWeaningWeight',

            // ========== WEIGHT SECTION ==========
            'totalWeights','dueForWeighing','avgWeight','totalWeight','liveWeightPrice','totalFarmValue',
            'weightsThisWeek',

            // ========== FEED SECTION ==========
            'totalFeedItems','totalFeedQuantity','scheduledFeeds','fedToday','totalScheduled',
            // ========== HEALTH SECTION ==========
            'totalHealthRecords','healthRecordsThisMonth','totalMedications','medicationsThisMonth',
            'topConditions','criticalAlerts','totalAlerts',
            
            // ========== SALES SECTION ==========
            'totalSales','totalRevenue',
            
            // ========== FINANCIAL TRENDS ==========
            'financialTrends','workspaceRevenueComparison',

            // ========== NEW: OPERATIONS MODULES ==========
            'dataQuality',
            'receivablesAging',
            'opsSnapshot'
        ));
    }

    /**
     * Get financial trends data for charts
     */
    private function getFinancialTrends(?Carbon $startDate, Carbon $endDate): array
    {
        // Default to last 12 months if no date range
        if (!$startDate) {
            $startDate = now()->subMonths(12)->startOfMonth();
        }

        // Revenue by month
        $revenueByMonth = PigSale::withoutGlobalScope('workspace')
            ->selectRaw('DATE_FORMAT(sale_date, "%Y-%m") as month, SUM(sale_price) as revenue, COUNT(*) as sales_count')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Average sale price by month
        $avgPriceByMonth = PigSale::withoutGlobalScope('workspace')
            ->selectRaw('DATE_FORMAT(sale_date, "%Y-%m") as month, AVG(sale_price) as avg_price')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Feed purchase costs by month
        $feedCostsByMonth = FeedPurchaseRequest::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(cost_ghc) as total_cost')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Unpaid sales
        $unpaidSales = PigSale::withoutGlobalScope('workspace')
            ->where('payment_status', '!=', 'paid')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->sum('sale_price');

        $unpaidSalesCount = PigSale::withoutGlobalScope('workspace')
            ->where('payment_status', '!=', 'paid')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->count();

        return [
            'revenue_by_month' => $revenueByMonth,
            'avg_price_by_month' => $avgPriceByMonth,
            'feed_costs_by_month' => $feedCostsByMonth,
            'unpaid_sales_amount' => $unpaidSales,
            'unpaid_sales_count' => $unpaidSalesCount,
        ];
    }

    /**
     * Get workspace revenue comparison
     */
    private function getWorkspaceRevenueComparison(?Carbon $startDate, Carbon $endDate): array
    {
        $query = PigSale::withoutGlobalScope('workspace')
            ->select('workspace_id', DB::raw('SUM(sale_price) as revenue'), DB::raw('COUNT(*) as sales_count'))
            ->groupBy('workspace_id');

        if ($startDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }

        $revenueByWorkspace = $query->get();

        $workspaces = Workspace::all()->keyBy('id');
        $comparison = [];

        foreach ($revenueByWorkspace as $item) {
            $workspace = $workspaces->get($item->workspace_id);
            if ($workspace) {
                $comparison[] = [
                    'workspace_id' => $item->workspace_id,
                    'workspace_name' => $workspace->name,
                    'revenue' => (float) $item->revenue,
                    'sales_count' => (int) $item->sales_count,
                ];
            }
        }

        // Sort by revenue descending
        usort($comparison, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $comparison;
    }

    /**
     * Export dashboard data as PDF
     */
    public function exportPdf(Request $request)
    {
        // Get the same data as dashboard
        $range = $request->query('range', 'mtd');
        $selectedUserId = $request->query('user_id');
        $customStartDate = $request->query('start_date');
        $customEndDate = $request->query('end_date');

        // Calculate date range (same logic as dashboard)
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
        
        // Get summary data
        $workspaces = Workspace::orderBy('name')->get();
        $workspaceMetrics = [];
        foreach ($workspaces as $ws) {
            $pigs = PigManagement::withoutGlobalScope('workspace')
                ->where('workspace_id', $ws->id)
                ->count();
            $revenue = PigSale::withoutGlobalScope('workspace')
                ->where('workspace_id', $ws->id)
                ->when($startDate, fn($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))
                ->sum('sale_price');
            
            $workspaceMetrics[] = [
                'name' => $ws->name,
                'code' => $ws->code,
                'pigs' => $pigs,
                'revenue' => $revenue,
            ];
        }

        $globalKpis = [
            'total_pigs' => PigManagement::withoutGlobalScope('workspace')->count(),
            'total_revenue' => PigSale::withoutGlobalScope('workspace')
                ->when($startDate, fn($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))
                ->sum('sale_price'),
            'total_sales' => PigSale::withoutGlobalScope('workspace')
                ->when($startDate, fn($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))
                ->count(),
        ];

        $pdf = \PDF::loadView('superuser.dashboard-export-pdf', compact(
            'range', 'startDate', 'endDate', 'selectedUser', 'workspaceMetrics', 'globalKpis'
        ));

        $filename = 'superuser-dashboard-' . now()->format('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Export dashboard data as CSV
     */
    public function exportCsv(Request $request)
    {
        $range = $request->query('range', 'mtd');
        $selectedUserId = $request->query('user_id');
        $customStartDate = $request->query('start_date');
        $customEndDate = $request->query('end_date');

        // Calculate date range
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

        $workspaces = Workspace::orderBy('name')->get();

        $callback = function() use ($workspaces, $startDate, $endDate) {
            $file = fopen('php://output', 'w');
            
            // Header
            fputcsv($file, ['SUPERUSER DASHBOARD EXPORT']);
            fputcsv($file, ['Generated:', now()->format('Y-m-d H:i:s')]);
            fputcsv($file, ['Date Range:', $startDate ? $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d') : 'All Time']);
            fputcsv($file, []); // Empty row

            // Global Summary
            fputcsv($file, ['GLOBAL SUMMARY']);
            fputcsv($file, ['Metric', 'Value']);
            fputcsv($file, ['Total Pigs', PigManagement::withoutGlobalScope('workspace')->count()]);
            fputcsv($file, ['Total Revenue', PigSale::withoutGlobalScope('workspace')
                ->when($startDate, fn($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))
                ->sum('sale_price')]);
            fputcsv($file, ['Total Sales', PigSale::withoutGlobalScope('workspace')
                ->when($startDate, fn($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))
                ->count()]);
            fputcsv($file, []); // Empty row

            // Workspace Breakdown
            fputcsv($file, ['WORKSPACE BREAKDOWN']);
            fputcsv($file, ['Workspace', 'Code', 'Pigs', 'Revenue', 'Sales Count']);
            
            foreach ($workspaces as $ws) {
                $pigs = PigManagement::withoutGlobalScope('workspace')
                    ->where('workspace_id', $ws->id)
                    ->count();
                $revenue = PigSale::withoutGlobalScope('workspace')
                    ->where('workspace_id', $ws->id)
                    ->when($startDate, fn($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))
                    ->sum('sale_price');
                $salesCount = PigSale::withoutGlobalScope('workspace')
                    ->where('workspace_id', $ws->id)
                    ->when($startDate, fn($q) => $q->whereBetween('sale_date', [$startDate, $endDate]))
                    ->count();
                
                fputcsv($file, [
                    $ws->name,
                    $ws->code,
                    $pigs,
                    number_format($revenue, 2),
                    $salesCount,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
