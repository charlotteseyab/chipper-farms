<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Farrowing;
use App\Models\Mating;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PigWeight;
use App\Models\WeightAlert;
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

        $pendingWeighing = PigManagement::active()
            ->whereDoesntHave('weights', function ($query) {
                $query->where('weigh_date', '>=', now()->subDays(7));
            })
            ->where('date_of_birth', '<=', now()->subDays(7))
            ->count();

        $activeAlerts = WeightAlert::where('acknowledged', false)->count();
        $overdueForWeighing = PigManagement::countOverdueForWeighing();

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
            'pendingWeighing',
            'activeAlerts',
            'overdueForWeighing',
            'dailyWeightsData',
            'pregnancyRate',
            'farrowingRate',
            'weaningPercentage',
            'avgStillborns',
            'avgBirthWeight',
            'recentActivities',
            'overduePigsPreview',
            'allOverduePigs',
            'allOverduePigsDetails',
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
