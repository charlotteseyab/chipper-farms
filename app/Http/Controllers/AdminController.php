<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PigWeight;
use App\Models\PigSale;
use App\Models\PigDeath;
use App\Models\PigletDeath;
use App\Models\PigManagement;
use App\Models\Farrowing;
use App\Models\Mating;
use App\Models\Weaning;
use App\Models\WeightAlert;
use App\Models\User;
use App\Models\Setting;
use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Render the admin dashboard with all metrics pre-computed, organized by sections.
     */
    public function dashboard(Request $request)
    {
        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
        $liveWeightPrice = Setting::get('sales.pricing.live_weight_price', 30.00);

        // Get filter parameters
        $dateFrom = $request->get('date_from', now()->subMonths(6)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));
        $dateFromCarbon = Carbon::parse($dateFrom)->startOfDay();
        $dateToCarbon = Carbon::parse($dateTo)->endOfDay();

        // ========== STOCK SECTION ==========
        $recentFarrowings = Farrowing::with('pigletDeaths')
            ->where('farrowing_date', '>=', now()->subDays($weaningAge))
            ->where('status', 'active')
            ->get();
        $pigletsCount = 0;
        foreach ($recentFarrowings as $f) {
            $pigletsCount += max(0, (int)($f->live_births ?? 0) - (int)$f->pigletDeaths->sum('count'));
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

        // Stock trends (last 6 months)
        $stockTrendsLabels = [];
        $stockTrendsActive = [];
        $stockTrendsSold = [];
        $stockTrendsDeceased = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthEnd = $monthDate->copy()->endOfMonth();
            $stockTrendsLabels[] = $monthDate->format('M Y');
            $stockTrendsActive[] = PigManagement::where('created_at', '<=', $monthEnd)
                ->whereDoesntHave('deaths')->whereDoesntHave('sales')->count();
            $stockTrendsSold[] = PigSale::where('created_at', '<=', $monthEnd)->count();
            $stockTrendsDeceased[] = PigDeath::where('created_at', '<=', $monthEnd)->count();
        }

        // ========== MATING SECTION ==========
        $totalMatings = Mating::count();
        $plannedMatings = Mating::planned()->count();
        $completedMatings = Mating::completed()->count();
        $overdueMatings = Mating::overdue()->count();
        $upcomingMatings = Mating::upcoming()->count();
        $pregnantMatings = Mating::pregnant()->count();
        $notPregnantMatings = Mating::notPregnant()->count();
        $pregnancyChecksDue = Mating::pregnancyCheckDue()->count();
        $pregnancyChecksOverdue = Mating::pregnancyCheckOverdue()->count();
        $pregNum = Mating::where('pregnancy_status', 'pregnant')->count();
        $pregDenom = Mating::whereNotNull('female_id')->count();
        $pregnancyRate = $pregDenom > 0 ? round($pregNum / $pregDenom * 100, 1) : 0;
        $successfulMatings = Mating::where('pregnancy_status', 'pregnant')->count();
        $fertilityRate = $totalMatings > 0 ? round(($successfulMatings / $totalMatings) * 100, 1) : 0;

        // Mating trends (last 6 months)
        $matingTrendsLabels = [];
        $matingTrendsData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $matingTrendsLabels[] = $monthDate->format('M Y');
            $matingTrendsData[] = Mating::whereMonth('planned_date', $monthDate->month)
                ->whereYear('planned_date', $monthDate->year)->count();
        }

        // ========== GESTATION SECTION ==========
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
            ->map(function($m) {
                return $m->actual_date->diffInDays($m->actual_farrowing_date);
            })
            ->avg();
        $avgGestationDays = $avgGestationDays ? round($avgGestationDays, 1) : 0;

        // Gestation status chart data
        $gestationStatusData = [
            'Pregnant' => $totalPregnant,
            'Due This Week' => $dueThisWeek,
            'Due Next Week' => $dueNextWeek,
            'Overdue' => $overdueGestation,
        ];

        // ========== FARROWING SECTION ==========
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

        // Farrowing trends (last 6 months)
        $farrowingTrendsLabels = [];
        $farrowingTrendsData = [];
        $farrowingTrendsBorn = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $mFarrowings = Farrowing::whereMonth('farrowing_date', $monthDate->month)
                ->whereYear('farrowing_date', $monthDate->year)->get();
            $farrowingTrendsLabels[] = $monthDate->format('M Y');
            $farrowingTrendsData[] = $mFarrowings->count();
            $farrowingTrendsBorn[] = $mFarrowings->sum('live_births');
        }

        // ========== WEANING SECTION ==========
        $totalWeaned = Weaning::count();
        $weaningPercentage = $totalBorn > 0 ? round($totalWeaned / $totalBorn * 100, 1) : 0;
        $readyToWean = Farrowing::where('status', 'active')
            ->whereDate('farrowing_date', '<=', now()->subDays($weaningAge))
            ->whereDoesntHave('weanings', function ($query) {
                $query->where('maturity_completed', true);
            })->count();
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
        $completedWeanings = Weaning::where('maturity_completed', true)->count();
        $avgWeaningAge = Weaning::whereNotNull('age_at_weaning_days')->avg('age_at_weaning_days');
        $avgWeaningWeight = Weaning::whereNotNull('weaning_weight')->avg('weaning_weight');
        $weaningsThisMonth = Weaning::whereMonth('weaning_date', now()->month)
            ->whereYear('weaning_date', now()->year)->count();

        // Weaning trends (last 6 months)
        $weaningTrendsLabels = [];
        $weaningTrendsData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $weaningTrendsLabels[] = $monthDate->format('M Y');
            $weaningTrendsData[] = Weaning::whereMonth('weaning_date', $monthDate->month)
                ->whereYear('weaning_date', $monthDate->year)->count();
        }

        // ========== WEIGHT MANAGEMENT SECTION ==========
        $totalWeights = PigWeight::count();
        $overdueForWeighing = PigManagement::countOverdueForWeighing();
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
        $totalFarmValue = $totalWeight * $liveWeightPrice;
        $weightsThisWeek = PigWeight::where('created_at', '>=', now()->subWeek())->count();
        $weightsThisMonth = PigWeight::whereMonth('created_at', now()->month)->count();
        $criticalAlerts = WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();
        $totalAlerts = WeightAlert::where('acknowledged', false)->count();

        // Overdue pigs data
        $overduePigs = PigManagement::getOverdueForWeighing()->take(5);
        $allOverduePigs = PigManagement::getOverdueForWeighing();
        $mapPig = function ($pig) {
            $weighingStatus = $pig->getWeighingStatus();
            $daysSince = $pig->getDaysSinceLastWeighing();
            $lastWeighing = $pig->getLastWeighingDate();
            $lastWeight = $pig->weights()->latest('weigh_date')->first();
            $overdueDays = $daysSince - 30;
            return [
                'pig' => $pig,
                'weighing_status' => $weighingStatus,
                'days_since' => $daysSince,
                'last_weighing' => $lastWeighing,
                'last_weight' => $lastWeight,
                'overdue_days' => $overdueDays,
            ];
        };
        $overduePigsData = $overduePigs->map($mapPig);
        $allOverduePigsData = $allOverduePigs->map($mapPig);

        // Weight trends (last 30 days)
        $weightTrendsData = PigWeight::selectRaw('DATE(created_at) as date, AVG(weight) as avg_weight')
            ->whereIn('id', function ($query) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('pig_weights')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupBy('pig_id');
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        $weightLabels = $weightTrendsData->pluck('date')->map(fn($d) => Carbon::parse($d)->format('M j'))->toArray();
        $weightValues = $weightTrendsData->pluck('avg_weight')->toArray();

        // Weight trends by month (last 6 months)
        $weightMonthlyLabels = [];
        $weightMonthlyAvg = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthEnd = $monthDate->copy()->endOfMonth();
            $weightMonthlyLabels[] = $monthDate->format('M Y');
            $monthWeights = PigWeight::whereIn('id', function ($q) use ($monthEnd) {
                $q->select(DB::raw('MAX(id)'))
                    ->from('pig_weights')
                    ->where('created_at', '<=', $monthEnd)
                    ->groupBy('pig_id');
            })->avg('weight');
            $weightMonthlyAvg[] = round($monthWeights ?? 0, 1);
        }

        // Feed management has been removed from the system.
        $lowStockCount = 0;
        $totalFeedItems = 0;
        $totalFeedQuantity = 0;
        $pendingRequests = 0;
        $approvedRequests = 0;
        $rejectedRequests = 0;
        $totalRequests = 0;
        $pendingRequestValue = 0;
        $scheduledFeeds = 0;
        $fedToday = 0;
        $skippedToday = 0;
        $totalScheduled = 0;
        $feedTrendsLabels = [];
        $feedTrendsScheduled = [];
        $feedTrendsFed = [];

        // ========== HEALTH & PERFORMANCE SECTION ==========
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
        
        $severityCounts = HealthRecord::select('severity')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');
        
        $recentHealthRecords = HealthRecord::with('pig')
            ->latest('record_date')
            ->limit(5)
            ->get();
        
        $recentMedications = MedicationAdministration::with('pig')
            ->latest('administration_date')
            ->limit(5)
            ->get();

        // Health trends (last 6 months)
        $healthTrendsLabels = [];
        $healthTrendsRecords = [];
        $healthTrendsMedications = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $healthTrendsLabels[] = $monthDate->format('M Y');
            $healthTrendsRecords[] = HealthRecord::whereMonth('record_date', $monthDate->month)
                ->whereYear('record_date', $monthDate->year)->count();
            $healthTrendsMedications[] = MedicationAdministration::whereMonth('administration_date', $monthDate->month)
                ->whereYear('administration_date', $monthDate->year)->count();
        }

        // Sales data
        $totalSales = PigSale::count();
        $totalRevenue = PigSale::sum('sale_price');
        $avgSalePrice = PigSale::avg('sale_price');
        $salesThisMonth = PigSale::whereMonth('created_at', now()->month)->count();
        $maleSales = PigSale::whereHas('pig', fn($q) => $q->where('gender', 'M'))->count();
        $femaleSales = PigSale::whereHas('pig', fn($q) => $q->where('gender', 'F'))->count();

        return view('admin.dashboard', compact(
            // Filter
            'dateFrom', 'dateTo',
            // Stock
            'weanedPigs', 'pigletsCount', 'totalPigs', 'activePigs', 'deceasedPigs', 'soldPigs',
            'activeMaleCount', 'activeFemaleCount', 'breedingStock', 'standardStock', 'breedCounts',
            'mortalityRate', 'stockTrendsLabels', 'stockTrendsActive', 'stockTrendsSold', 'stockTrendsDeceased',
            // Mating
            'totalMatings', 'plannedMatings', 'completedMatings', 'overdueMatings', 'upcomingMatings',
            'pregnantMatings', 'notPregnantMatings', 'pregnancyChecksDue', 'pregnancyChecksOverdue',
            'pregnancyRate', 'fertilityRate', 'matingTrendsLabels', 'matingTrendsData',
            // Gestation
            'totalPregnant', 'totalFarrowed', 'dueThisWeek', 'dueNextWeek', 'overdueGestation',
            'farrowedThisMonth', 'avgGestationDays', 'gestationStatusData',
            // Farrowing
            'totalFarrowings', 'totalBorn', 'totalStillborns', 'avgLitterSize', 'avgStillborns',
            'avgBirthWeight', 'farrowingsThisMonth', 'farrowingsThisYear', 'survivalRate',
            'farrowingTrendsLabels', 'farrowingTrendsData', 'farrowingTrendsBorn',
            // Weaning
            'totalWeaned', 'weaningPercentage', 'readyToWean', 'dueSoonWeaning', 'overdueWeaning',
            'completedWeanings', 'avgWeaningAge', 'avgWeaningWeight', 'weaningsThisMonth',
            'weaningTrendsLabels', 'weaningTrendsData', 'weaningAge',
            // Weight
            'totalWeights', 'overdueForWeighing', 'dueForWeighing', 'avgWeight', 'totalWeight',
            'totalFarmValue', 'weightsThisWeek', 'weightsThisMonth', 'criticalAlerts', 'totalAlerts',
            'overduePigsData', 'allOverduePigsData', 'weightLabels', 'weightValues', 'weightMonthlyLabels', 'weightMonthlyAvg',
            // Feed
            'lowStockCount', 'totalFeedItems', 'totalFeedQuantity', 'pendingRequests', 'approvedRequests',
            'rejectedRequests', 'totalRequests', 'pendingRequestValue', 'scheduledFeeds', 'fedToday',
            'skippedToday', 'totalScheduled', 'feedTrendsLabels', 'feedTrendsScheduled', 'feedTrendsFed',
            // Health
            'totalHealthRecords', 'healthRecordsThisMonth', 'totalMedications', 'medicationsThisMonth',
            'topConditions', 'severityCounts', 'recentHealthRecords', 'recentMedications',
            'healthTrendsLabels', 'healthTrendsRecords', 'healthTrendsMedications',
            // Sales
            'totalSales', 'totalRevenue', 'avgSalePrice', 'salesThisMonth', 'maleSales', 'femaleSales',
            'liveWeightPrice'
        ));
    }

    /**
     * Get weight trends data for the admin dashboard chart
     */
    public function getWeightTrendsData(Request $request)
    {
        $fromDate = $request->get('from');
        $toDate = $request->get('to');
        
        // Validate dates
        if (!$fromDate || !$toDate) {
            return response()->json(['error' => 'From and to dates are required'], 400);
        }
        
        try {
            $from = Carbon::parse($fromDate);
            $to = Carbon::parse($toDate);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid date format'], 400);
        }
        
        // Get weight trends data for the specified date range - average of latest weight per pig for each day
        $weightTrendsData = PigWeight::selectRaw('DATE(created_at) as date, AVG(weight) as avg_weight')
            ->whereIn('id', function($query) use ($from, $to) {
                $query->select(\DB::raw('MAX(id)'))
                      ->from('pig_weights')
                      ->whereBetween('created_at', [$from, $to])
                      ->groupBy('pig_id');
            })
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Generate labels and values
        $labels = [];
        $values = [];
        
        if ($weightTrendsData->count() > 0) {
            $labels = $weightTrendsData->pluck('date')->map(function($date) {
                return Carbon::parse($date)->format('M j');
            })->toArray();
            $values = $weightTrendsData->pluck('avg_weight')->toArray();
        }
        // If no data, return empty arrays - no fallback data
        
        return response()->json([
            'labels' => $labels,
            'values' => $values
        ]);
    }
}
