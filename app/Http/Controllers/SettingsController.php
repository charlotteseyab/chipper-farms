<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\PigSale;
use App\Models\PigDeath;
use App\Models\Mating;
use App\Models\Farrowing;
use App\Models\Weaning;
use App\Models\Structure;
use App\Models\Room;
use App\Models\User;
use App\Helpers\FlashHelper;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;

class SettingsController extends Controller
{
    /**
     * Show the settings setup page
     */
    public function setup()
    {
        $breeding = [
            'pregnancy_check_days' => Setting::get('breeding.cycle.pregnancy_check_days', config('breeding.cycle.pregnancy_check_days')),
            'gestation_days' => Setting::get('breeding.cycle.gestation_days', config('breeding.cycle.gestation_days')),
            'birth_to_weaning_days' => Setting::get('breeding.cycle.birth_to_weaning_days', config('breeding.cycle.birth_to_weaning_days')),
            'birth_to_maturity_days' => Setting::get('breeding.cycle.birth_to_maturity_days', config('breeding.cycle.birth_to_maturity_days')),
            'minimum_breeding_age_female' => Setting::get('breeding.parameters.minimum_breeding_age.female', config('breeding.parameters.minimum_breeding_age.female')),
            'minimum_breeding_age_male' => Setting::get('breeding.parameters.minimum_breeding_age.male', config('breeding.parameters.minimum_breeding_age.male')),
            'optimal_first_breeding_age_female' => Setting::get('breeding.parameters.optimal_first_breeding_age.female', config('breeding.parameters.optimal_first_breeding_age.female')),
            'optimal_first_breeding_age_male' => Setting::get('breeding.parameters.optimal_first_breeding_age.male', config('breeding.parameters.optimal_first_breeding_age.male')),
            'average_litter_size' => Setting::get('breeding.parameters.average_litter_size', config('breeding.parameters.average_litter_size')),
            'weaning_to_mating_rest' => Setting::get('breeding.parameters.weaning_to_mating_rest', config('breeding.parameters.weaning_to_mating_rest')),
            'maximum_breeding_age' => Setting::get('breeding.parameters.maximum_breeding_age', config('breeding.parameters.maximum_breeding_age')),
            'lactation_days' => Setting::get('breeding.parameters.lactation_days', config('breeding.parameters.lactation_days')),
        ];

        $pricing = [
            'live_weight_price' => Setting::get('sales.pricing.live_weight_price', 30.00),
            'carcass_weight_price' => Setting::get('sales.pricing.carcass_weight_price', 50.00),
        ];

        return view('settings.setup', compact('breeding', 'pricing'));
    }

    /**
     * Persist breeding settings
     */
    public function saveBreeding(Request $request)
    {
        $data = $request->validate([
            'pregnancy_check_days' => 'required|integer|min:7|max:30',
            'gestation_days' => 'required|integer|min:100|max:130',
            'birth_to_weaning_days' => 'required|integer|min:21|max:90',
            'birth_to_maturity_days' => 'required|integer|min:120|max:365',
            'minimum_breeding_age_female' => 'required|integer|min:150|max:400',
            'minimum_breeding_age_male' => 'required|integer|min:150|max:400',
            'optimal_first_breeding_age_female' => 'required|integer|min:150|max:500',
            'optimal_first_breeding_age_male' => 'required|integer|min:150|max:500',
            'average_litter_size' => 'required|numeric|min:1|max:25',
            'weaning_to_mating_rest' => 'required|integer|min:0|max:60',
            'maximum_breeding_age' => 'required|integer|min:365|max:3650',
            'lactation_days' => 'required|integer|min:21|max:120',
            'live_weight_price' => 'required|numeric|min:1|max:1000',
            'carcass_weight_price' => 'required|numeric|min:1|max:1000',
        ]);

        // Save individual keys to allow partial overrides
        Setting::set('breeding.cycle.pregnancy_check_days', $data['pregnancy_check_days']);
        Setting::set('breeding.cycle.gestation_days', $data['gestation_days']);
        Setting::set('breeding.cycle.birth_to_weaning_days', $data['birth_to_weaning_days']);
        Setting::set('breeding.cycle.birth_to_maturity_days', $data['birth_to_maturity_days']);
        Setting::set('breeding.parameters.minimum_breeding_age', [
            'female' => (int) $data['minimum_breeding_age_female'],
            'male' => (int) $data['minimum_breeding_age_male'],
        ]);
        Setting::set('breeding.parameters.optimal_first_breeding_age', [
            'female' => (int) $data['optimal_first_breeding_age_female'],
            'male' => (int) $data['optimal_first_breeding_age_male'],
        ]);
        Setting::set('breeding.parameters.average_litter_size', (float) $data['average_litter_size']);
        Setting::set('breeding.parameters.weaning_to_mating_rest', (int) $data['weaning_to_mating_rest']);
        Setting::set('breeding.parameters.maximum_breeding_age', (int) $data['maximum_breeding_age']);
        Setting::set('breeding.parameters.lactation_days', (int) $data['lactation_days']);

        // Save pricing settings
        Setting::set('sales.pricing.live_weight_price', (float) $data['live_weight_price']);
        Setting::set('sales.pricing.carcass_weight_price', (float) $data['carcass_weight_price']);

        return FlashHelper::successAndRedirect('Settings saved successfully.', 'settings.setup');
    }

    /**
     * Reset breeding settings to defaults
     */
    public function resetBreeding()
    {
        // Remove keys so config falls back to defaults
        foreach ([
            'breeding.cycle.pregnancy_check_days',
            'breeding.cycle.gestation_days',
            'breeding.cycle.birth_to_weaning_days',
            'breeding.cycle.birth_to_maturity_days',
            'breeding.parameters.minimum_breeding_age',
            'breeding.parameters.optimal_first_breeding_age',
            'breeding.parameters.average_litter_size',
            'breeding.parameters.weaning_to_mating_rest',
            'breeding.parameters.maximum_breeding_age',
            'breeding.parameters.lactation_days',
            'sales.pricing.live_weight_price',
            'sales.pricing.carcass_weight_price',
        ] as $key) {
            \App\Models\Setting::query()->where('key', $key)->delete();
        }

        return FlashHelper::successAndRedirect('Settings reset to defaults.', 'settings.setup');
    }

    /**
     * Show comprehensive system reports
     */
    public function systemReports(Request $request)
    {
        $from = $request->date('from');
        $to = $request->date('to');

        // Helper to apply optional date range on created_at
        $applyRange = function($query) use ($from, $to) {
            if ($from) { $query->whereDate('created_at', '>=', $from); }
            if ($to) { $query->whereDate('created_at', '<=', $to); }
            return $query;
        };

        // Executive Summary
        $summary = [
            'total_pigs' => $applyRange(PigManagement::query())->count(),
            'active_pigs' => $applyRange(PigManagement::active())->count(),
            'sold_pigs' => $applyRange(PigManagement::whereHas('sales'))->count(),
            'deceased_pigs' => $applyRange(PigManagement::whereHas('deaths'))->count(),
            'total_sales_revenue' => $applyRange(PigSale::query())->sum('sale_price'),
            'average_sale_price' => $applyRange(PigSale::query())->avg('sale_price'),
            'total_weights_recorded' => $applyRange(PigWeight::query())->count(),
            'total_structures' => Structure::count(),
            'total_rooms' => Room::count(),
            'total_users' => User::count(),
        ];

        // Pig Distribution
        $pigDistribution = [
            'by_stock_type' => $applyRange(PigManagement::selectRaw('stock_type, COUNT(*) as count')->groupBy('stock_type'))
                ->pluck('count', 'stock_type'),
            'by_gender' => $applyRange(PigManagement::selectRaw('gender, COUNT(*) as count')->groupBy('gender'))
                ->pluck('count', 'gender'),
            'by_breed' => $applyRange(PigManagement::selectRaw('breed, COUNT(*) as count')->groupBy('breed'))
                ->pluck('count', 'breed'),
        ];

        // Recent Activity (last 30 days)
        $recentActivity = [
            'new_pigs' => $applyRange(PigManagement::query())->count(),
            'weights_recorded' => $applyRange(PigWeight::query())->count(),
            'pigs_sold' => $applyRange(PigSale::query())->count(),
            'pigs_died' => $applyRange(PigDeath::query())->count(),
            'matings_planned' => $applyRange(Mating::query())->count(),
        ];

        // Financial Overview
        $financialOverview = [
            'total_revenue' => $applyRange(PigSale::query())->sum('sale_price'),
            'average_price_per_kg' => $applyRange(PigSale::query())->avg('sale_price'),
            'sales_by_month' => $applyRange(PigSale::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(sale_price) as revenue, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month'))
                ->get(),
        ];

        // Breeding Statistics
        $breedingStats = [
            'total_matings' => $applyRange(Mating::query())->count(),
            'total_farrowings' => $applyRange(Farrowing::query())->count(),
            'total_weanings' => $applyRange(Weaning::query())->count(),
            'average_litter_size' => $applyRange(Farrowing::query())->avg('total_piglets_born'),
            // Unify with PerformanceMetricController (pregnancy_status method)
            'pregnancy_rate' => (function() use ($applyRange) {
                $eligibleMatings = $applyRange(Mating::whereNotNull('female_id'));
                $pregnant = $applyRange(Mating::where('pregnancy_status', 'pregnant'));
                $numEligible = $eligibleMatings->count();
                $numPregnant = $pregnant->count();
                return $numEligible > 0 ? round(100 * $numPregnant / $numEligible, 1) : 0;
            })(),
        ];

        // Health & Mortality
        $healthStats = [
            'total_deaths' => $applyRange(PigDeath::query())->count(),
            'deaths_by_cause' => $applyRange(PigDeath::selectRaw('death_cause, COUNT(*) as count')->groupBy('death_cause'))
                ->pluck('count', 'death_cause'),
            'mortality_rate' => $applyRange(PigManagement::query())->count() > 0 
                ? ($applyRange(PigDeath::query())->count() / $applyRange(PigManagement::query())->count()) * 100 
                : 0,
        ];

        // Weight Analytics
        $weightAnalytics = [
            'average_weight' => $applyRange(PigWeight::query())->avg('weight'),
            'total_weight_records' => $applyRange(PigWeight::query())->count(),
            'weights_by_month' => $applyRange(PigWeight::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count, AVG(weight) as avg_weight')
                ->groupBy('month')
                ->orderBy('month'))
                ->get(),
        ];

        // Structure & Room Utilization
        $structureStats = [
            // Provide list of structures with identifiers for tab filtering
            'structures_by_status' => Structure::select('id', 'name', 'status')->orderBy('name')->get(),
            'room_utilization' => Room::withCount('pigs')->with('structure')->get(),
            'detailed_structures' => Structure::withCount('rooms')->get(),
        ];

        return view('settings.reports', compact(
            'summary',
            'pigDistribution', 
            'recentActivity',
            'financialOverview',
            'breedingStats',
            'healthStats',
            'weightAnalytics',
            'structureStats'
        ));
    }

    /**
     * Activity log list (login/logout)
     */
    public function activityLog(Request $request)
    {
        // Restrict to superuser only
        abort_unless(auth()->check() && auth()->user()->isSuperuser(), 403);

        $query = ActivityLog::with('user')->latest();

        // By default, show login, logout and login_attempt without requiring filters
        if (!$request->filled('action')) {
            $query->whereIn('action', ['login', 'logout', 'login_attempt']);
        } else {
            $query->where('action', $request->string('action'));
        }
        if ($request->filled('user')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->string('user') . '%')
                  ->orWhere('email', 'like', '%' . $request->string('user') . '%');
            });
        }

        $logs = $query->paginate(20);

        return view('settings.activity-log', compact('logs'));
    }

    /**
     * Export system reports as PDF
     */
    public function exportSystemReportsPdf()
    {
        // Get the same data as the main reports page
        $summary = [
            'total_pigs' => PigManagement::count(),
            'active_pigs' => PigManagement::active()->count(),
            'sold_pigs' => PigManagement::whereHas('sales')->count(),
            'deceased_pigs' => PigManagement::whereHas('deaths')->count(),
            'total_sales_revenue' => PigSale::sum('sale_price'),
            'average_sale_price' => PigSale::avg('sale_price'),
            'total_weights_recorded' => PigWeight::count(),
            'total_structures' => Structure::count(),
            'total_rooms' => Room::count(),
            'total_users' => User::count(),
        ];

        $pigDistribution = [
            'by_stock_type' => PigManagement::selectRaw('stock_type, COUNT(*) as count')
                ->groupBy('stock_type')
                ->pluck('count', 'stock_type'),
            'by_gender' => PigManagement::selectRaw('gender, COUNT(*) as count')
                ->groupBy('gender')
                ->pluck('count', 'gender'),
            'by_breed' => PigManagement::selectRaw('breed, COUNT(*) as count')
                ->groupBy('breed')
                ->pluck('count', 'breed'),
        ];

        $financialOverview = [
            'total_revenue' => PigSale::sum('sale_price'),
            'average_price_per_kg' => PigSale::avg('sale_price'),
            'sales_by_month' => PigSale::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(sale_price) as revenue, COUNT(*) as count')
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
        ];

        $breedingStats = [
            'total_matings' => Mating::count(),
            'total_farrowings' => Farrowing::count(),
            'total_weanings' => Weaning::count(),
            'average_litter_size' => Farrowing::avg('total_piglets_born'),
        ];

        $healthStats = [
            'total_deaths' => PigDeath::count(),
            'deaths_by_cause' => PigDeath::selectRaw('death_cause, COUNT(*) as count')
                ->groupBy('death_cause')
                ->pluck('count', 'death_cause'),
        ];

        $weightAnalytics = [
            'average_weight' => PigWeight::avg('weight'),
            'total_weight_records' => PigWeight::count(),
        ];

        $structureStats = [
            'structures_by_status' => Structure::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'room_utilization' => Room::withCount('pigs')->get(),
            'detailed_structures' => Structure::withCount('rooms')->get(),
        ];

        $pdf = \PDF::loadView('exports.system-reports-pdf', compact(
            'summary',
            'pigDistribution',
            'financialOverview', 
            'breedingStats',
            'healthStats',
            'weightAnalytics',
            'structureStats'
        ));

        return $pdf->download('system-reports-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export system reports as CSV
     */
    public function exportSystemReportsCsv()
    {
        $filename = 'system-reports-' . now()->format('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // Executive Summary
            fputcsv($file, ['EXECUTIVE SUMMARY']);
            fputcsv($file, ['Metric', 'Value']);
            fputcsv($file, ['Total Pigs', PigManagement::count()]);
            fputcsv($file, ['Active Pigs', PigManagement::active()->count()]);
            fputcsv($file, ['Sold Pigs', PigManagement::whereHas('sales')->count()]);
            fputcsv($file, ['Deceased Pigs', PigManagement::whereHas('deaths')->count()]);
            fputcsv($file, ['Total Sales Revenue', PigSale::sum('sale_price')]);
            fputcsv($file, ['Average Sale Price', PigSale::avg('sale_price')]);
            fputcsv($file, ['Total Weights Recorded', PigWeight::count()]);
            fputcsv($file, ['Total Structures', Structure::count()]);
            fputcsv($file, ['Total Rooms', Room::count()]);
            fputcsv($file, ['Total Users', User::count()]);
            fputcsv($file, []); // Empty row
            
            // Pig Distribution
            fputcsv($file, ['PIG DISTRIBUTION BY STOCK TYPE']);
            fputcsv($file, ['Stock Type', 'Count']);
            $stockTypes = PigManagement::selectRaw('stock_type, COUNT(*) as count')
                ->groupBy('stock_type')
                ->get();
            foreach ($stockTypes as $type) {
                fputcsv($file, [$type->stock_type, $type->count]);
            }
            fputcsv($file, []); // Empty row
            
            // Financial Overview
            fputcsv($file, ['FINANCIAL OVERVIEW']);
            fputcsv($file, ['Total Revenue', PigSale::sum('sale_price')]);
            fputcsv($file, ['Average Price per Sale', PigSale::avg('sale_price')]);
            fputcsv($file, []); // Empty row
            
            // Breeding Statistics
            fputcsv($file, ['BREEDING STATISTICS']);
            fputcsv($file, ['Metric', 'Value']);
            fputcsv($file, ['Total Matings', Mating::count()]);
            fputcsv($file, ['Total Farrowings', Farrowing::count()]);
            fputcsv($file, ['Total Weanings', Weaning::count()]);
            fputcsv($file, ['Average Litter Size', Farrowing::avg('total_piglets_born')]);
            fputcsv($file, []); // Empty row
            
            // Health & Mortality
            fputcsv($file, ['HEALTH & MORTALITY']);
            fputcsv($file, ['Total Deaths', PigDeath::count()]);
            fputcsv($file, ['Cause of Death', 'Count']);
            $deathsByCause = PigDeath::selectRaw('cause_of_death, COUNT(*) as count')
                ->groupBy('cause_of_death')
                ->get();
            foreach ($deathsByCause as $death) {
                fputcsv($file, [$death->cause_of_death, $death->count]);
            }
            fputcsv($file, []); // Empty row
            
            // Structure & Room Utilization
            fputcsv($file, ['STRUCTURE & ROOM UTILIZATION']);
            fputcsv($file, ['Total Structures', Structure::count()]);
            fputcsv($file, ['Total Rooms', Room::count()]);
            fputcsv($file, []); // Empty row
            
            // Structures by Status
            fputcsv($file, ['STRUCTURES BY STATUS']);
            fputcsv($file, ['Status', 'Count']);
            $structuresByStatus = Structure::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get();
            foreach ($structuresByStatus as $structure) {
                fputcsv($file, [ucfirst(str_replace('_', ' ', $structure->status)), $structure->count]);
            }
            fputcsv($file, []); // Empty row
            
            // Room Utilization
            fputcsv($file, ['ROOM UTILIZATION']);
            fputcsv($file, ['Room Name', 'Structure', 'Pigs Count', 'Capacity']);
            $roomUtilization = Room::withCount('pigs')->with('structure')->get();
            foreach ($roomUtilization as $room) {
                fputcsv($file, [
                    $room->name,
                    $room->structure->name ?? 'N/A',
                    $room->pigs_count,
                    $room->capacity ?? 'N/A'
                ]);
            }
            fputcsv($file, []); // Empty row
            
            // Detailed Structure Information
            fputcsv($file, ['DETAILED STRUCTURE INFORMATION']);
            fputcsv($file, ['Structure Name', 'Status', 'Capacity', 'Current Rooms', 'Available', 'Utilization %']);
            $detailedStructures = Structure::withCount('rooms')->get();
            foreach ($detailedStructures as $structure) {
                $capacity = $structure->capacity ?? 'Unlimited';
                $currentRooms = $structure->rooms_count;
                $available = $structure->available_capacity;
                $utilization = $structure->capacity ? round(($currentRooms / $structure->capacity) * 100, 1) : 'N/A';
                
                fputcsv($file, [
                    $structure->name,
                    ucfirst(str_replace('_', ' ', $structure->status)),
                    $capacity,
                    $currentRooms,
                    $available === -1 ? 'Unlimited' : $available,
                    $utilization
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
