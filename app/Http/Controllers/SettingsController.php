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
use App\Models\Role;
use App\Helpers\FlashHelper;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityLog;

class SettingsController extends Controller
{
    /**
     * Show the settings setup page
     */
    public function setup()
    {
        $this->abortUnlessRole('superuser', 'admin');
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

        $stages = [
            'piglet_max_days' => Setting::get('stages.piglet.max_days', 45),
            'weaner_max_days' => Setting::get('stages.weaner.max_days', 105),
            'grower_max_days' => Setting::get('stages.grower.max_days', 180),
        ];

        $pricing = [
            'live_weight_price' => Setting::get('sales.pricing.live_weight_price', 30.00),
            'carcass_weight_price' => Setting::get('sales.pricing.carcass_weight_price', 50.00),
        ];

        $feedBagWeights = [
            'default_bag_weight_kg' => Setting::get('feed.units.default_bag_weight_kg', 50),
            'maize_bag_weight_kg' => Setting::get('feed.units.maize_bag_weight_kg', 50),
            'soybean_meal_bag_weight_kg' => Setting::get('feed.units.soybean_meal_bag_weight_kg', 50),
            'wheat_bran_bag_weight_kg' => Setting::get('feed.units.wheat_bran_bag_weight_kg', 50),
            'premix_bag_weight_kg' => Setting::get('feed.units.premix_bag_weight_kg', 25),
            'concentrate_bag_weight_kg' => Setting::get('feed.units.concentrate_bag_weight_kg', 50),
        ];

        $purchaseRequestFeedItems = Setting::purchaseFeedItems();
        
        return view('settings.setup', compact(
            'breeding',
            'pricing',
            'stages',
            'feedBagWeights',
            'purchaseRequestFeedItems',
        ));
    }

    /**
     * Persist breeding/settings page values
     */
    public function saveBreeding(Request $request)
    {
        $this->abortUnlessRole('superuser', 'admin');

        if ($request->has('feed_items_only')) {
            return $this->savePurchaseFeedItems($request);
        }

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
            'piglet_max_days' => 'required|integer|min:1|max:300',
            'weaner_max_days' => 'required|integer|min:' . ($request->input('piglet_max_days', 1) + 1) . '|max:400',
            'grower_max_days' => 'required|integer|min:' . ($request->input('weaner_max_days', 2) + 1) . '|max:600',

            'default_bag_weight_kg' => 'nullable|numeric|min:0.01|max:500',
            'maize_bag_weight_kg' => 'nullable|numeric|min:0.01|max:500',
            'soybean_meal_bag_weight_kg' => 'nullable|numeric|min:0.01|max:500',
            'wheat_bran_bag_weight_kg' => 'nullable|numeric|min:0.01|max:500',
            'premix_bag_weight_kg' => 'nullable|numeric|min:0.01|max:500',
            'concentrate_bag_weight_kg' => 'nullable|numeric|min:0.01|max:500',
            'feed_items' => 'nullable|array',
            'feed_items.*' => 'nullable|string|max:255',
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

        // Save growth stage settings
        Setting::set('stages.piglet.max_days', (int) $data['piglet_max_days']);
        Setting::set('stages.weaner.max_days', (int) $data['weaner_max_days']);
        Setting::set('stages.grower.max_days', (int) $data['grower_max_days']);

        // Save feed purchase unit / bag weight settings
        Setting::set('feed.units.default_bag_weight_kg', (float) ($data['default_bag_weight_kg'] ?? 50));
        Setting::set('feed.units.maize_bag_weight_kg', (float) ($data['maize_bag_weight_kg'] ?? 50));
        Setting::set('feed.units.soybean_meal_bag_weight_kg', (float) ($data['soybean_meal_bag_weight_kg'] ?? 50));
        Setting::set('feed.units.wheat_bran_bag_weight_kg', (float) ($data['wheat_bran_bag_weight_kg'] ?? 50));
        Setting::set('feed.units.premix_bag_weight_kg', (float) ($data['premix_bag_weight_kg'] ?? 25));
        Setting::set('feed.units.concentrate_bag_weight_kg', (float) ($data['concentrate_bag_weight_kg'] ?? 50));

        Setting::setPurchaseFeedItems($data['feed_items'] ?? []);

        return FlashHelper::successAndRedirect('Settings saved successfully.', 'settings.setup');
    }

    /**
     * Save only the purchase request feed item dropdown list.
     * This is intentionally separate from the main settings save so a feed
     * item can be added without being blocked by unrelated settings fields.
     */
    public function savePurchaseFeedItems(Request $request)
    {
        $this->abortUnlessRole('superuser', 'admin');

        $data = $request->validate([
            'feed_items' => ['nullable', 'array'],
            'feed_items.*' => ['nullable', 'string', 'max:255'],
            'new_feed_item' => ['nullable', 'string', 'max:255'],
        ]);

        $items = $data['feed_items'] ?? [];

        if (! empty($data['new_feed_item'])) {
            $items[] = $data['new_feed_item'];
        }

        Setting::setPurchaseFeedItems($items);

        return redirect()
            ->route('settings.setup')
            ->with('success', 'Purchase request feed items saved successfully.');
    }

    /**
     * Reset breeding settings to defaults
     */
    public function resetBreeding()
    {
        $this->abortUnlessRole('superuser', 'admin');
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
            'stages.piglet.max_days',
            'stages.weaner.max_days',
            'stages.grower.max_days',
            'feed.units.default_bag_weight_kg',
            'feed.units.maize_bag_weight_kg',
            'feed.units.soybean_meal_bag_weight_kg',
            'feed.units.wheat_bran_bag_weight_kg',
            'feed.units.premix_bag_weight_kg',
            'feed.units.concentrate_bag_weight_kg',
            'purchase_requests.feed_items',
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
        $this->abortUnlessRole('superuser', 'admin');
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

        $stages = [
            'piglet_max_days' => Setting::get('stages.piglet.max_days', 45),
            'weaner_max_days' => Setting::get('stages.weaner.max_days', 105),
            'grower_max_days' => Setting::get('stages.grower.max_days', 180)
        ];

        // Notification/Alert Analytics (live count by alert type)
        $realTimeAlerts = [
            'weight' => \App\Models\WeightAlert::where('acknowledged', false)->count(),
            'critical' => \App\Models\WeightAlert::where('severity', 'high')->where('acknowledged', false)->count(),
            'pending_weaning_alerts' => \App\Models\PigManagement::active()->whereNotNull('date_of_birth')->whereDoesntHave('weaning')->count(),
        ];
        $storedNotifications = [
            'total' => \DB::table('notifications')->count(),
            'unread' => \DB::table('notifications')->whereNull('read_at')->count(),
        ];
        // Soft deletes/archival
        $softDeletes = [
            'pigs_archived' => \App\Models\PigManagement::onlyTrashed()->count(),
            'piglet_deaths_archived' => \App\Models\PigletDeath::onlyTrashed()->count(),
            'sales_archived' => \App\Models\PigSale::onlyTrashed()->count(),
            'deaths_archived' => \App\Models\PigDeath::onlyTrashed()->count(),
            'pigs_active' => \App\Models\PigManagement::count(),
        ];
        // Piglet survivability
        $liveBirths = \App\Models\Farrowing::sum('live_births');
        $preWeaningDeaths = \App\Models\PigletDeath::whereHas('farrowing', function($q){ $q->where('status', 'active'); })->count();
        $pigletSurvivalRate = $liveBirths > 0 ? round(100 * ($liveBirths - $preWeaningDeaths) / $liveBirths, 2) : null;
        // Weaning details
        $totalLittersWeaned = \App\Models\Weaning::distinct('mother_id')->count('mother_id');
        $totalPigletsWeaned = \App\Models\Weaning::count();
        $avgWeaningAge = \App\Models\Weaning::avg('age_at_weaning_days');
        $avgWeaningWeight = \App\Models\Weaning::avg('weaning_weight');
        // Sales/Procurement breakdown
        $paidSales = \App\Models\PigSale::where('payment_status', 'paid')->count();
        $unpaidSales = \App\Models\PigSale::where('payment_status', '!=', 'paid')->count();
        // User roles
        $roleCounts = \App\Models\User::with('role')->get()->groupBy(fn($u)=>optional($u->role)->name ?? 'unknown')->map->count();

        return view('settings.reports', compact(
            'summary',
            'pigDistribution', 
            'recentActivity',
            'financialOverview',
            'breedingStats',
            'healthStats',
            'weightAnalytics',
            'structureStats',
            'stages',
            'realTimeAlerts',
            'storedNotifications',
            'softDeletes',
            'pigletSurvivalRate',
            'totalLittersWeaned',
            'totalPigletsWeaned',
            'avgWeaningAge',
            'avgWeaningWeight',
            'paidSales',
            'unpaidSales',
            'roleCounts'
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
        $this->abortUnlessRole('superuser', 'admin');
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
        $this->abortUnlessRole('superuser', 'admin');
        $filename = 'system-reports-' . now()->format('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $stages = [
            'piglet_max_days' => Setting::get('stages.piglet.max_days', 45),
            'weaner_max_days' => Setting::get('stages.weaner.max_days', 105),
            'grower_max_days' => Setting::get('stages.grower.max_days', 180)
        ];
        $callback = function() use ($stages) {
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
            $deathsByCause = PigDeath::selectRaw('death_cause, COUNT(*) as count')
                ->groupBy('death_cause')
                ->get();
            foreach ($deathsByCause as $death) {
                fputcsv($file, [$death->death_cause, $death->count]);
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
            
            // Growth stages section
            fputcsv($file, []);
            fputcsv($file, ['GROWTH STAGES & AGE REQUIREMENTS']);
            fputcsv($file, ['Stage', 'Max Age (days)']);
            fputcsv($file, ['Piglet', $stages['piglet_max_days']]);
            fputcsv($file, ['Weaner', $stages['weaner_max_days']]);
            fputcsv($file, ['Grower', $stages['grower_max_days']]);
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show users management page
     */
    public function users(Request $request)
    {
        // Restrict to superuser only
        abort_unless(auth()->check() && auth()->user()->isSuperuser(), 403);

        $users = User::with(['role'])->orderBy('created_at', 'desc')->paginate(10);
        $roles = Role::all();

        return view('settings.users', compact('users', 'roles'));
    }

    /**
     * Store a new user
     */
    public function storeUser(Request $request, \App\Services\Users\UserProvisioningService $provisioning)
    {
        // Restrict to superuser only
        abort_unless(auth()->check() && auth()->user()->isSuperuser(), 403);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:50', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'in:' . implode(',', User::allowedRoleNames())],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'active_status' => ['sometimes', 'boolean'],
            'organization_id' => ['sometimes', 'nullable', 'integer', 'exists:organizations,id'],
        ]);

        // CF-P0-001: this method used to build the User inline, never setting
        // organization_id and defaulting active_status to false — producing an
        // account that EnsureOrganizationContext and EnsureUserIsActive both
        // reject on the first request. All creation now goes through the single
        // provisioning service, which establishes organisation and role
        // transactionally and refuses to return an unusable account.
        $provisioning->provision($validated, $request->user());

        return redirect()->route('settings.users')->with('success', 'User created successfully.');
    }

    /**
     * Update a user
     */
    public function updateUser(Request $request, User $user)
    {
        // Restrict to superuser only
        abort_unless(auth()->check() && auth()->user()->isSuperuser(), 403);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:50', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'in:' . implode(',', User::allowedRoleNames())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'active_status' => ['sometimes', 'boolean'],
        ]);

        $user->fill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'active_status' => $request->has('active_status') ? (bool)$request->input('active_status') : false,
        ]);
        $user->syncRoleFields($validated['role']);
        $user->save();

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        return redirect()->route('settings.users')->with('success', 'User updated successfully.');
    }

    /**
     * Update user active status
     */
    public function updateUserStatus(Request $request, User $user)
    {
        // Restrict to superuser only
        abort_unless(auth()->check() && auth()->user()->isSuperuser(), 403);

        $validated = $request->validate([
            'active_status' => ['required', 'boolean'],
        ]);

        $user->update(['active_status' => $validated['active_status']]);

        $message = $validated['active_status']
            ? 'User has been activated successfully.'
            : 'User has been deactivated successfully.';

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Delete a user
     */
    public function deleteUser(User $user)
    {
        // Restrict to superuser only
        abort_unless(auth()->check() && auth()->user()->isSuperuser(), 403);

        // Prevent deleting any superuser account (protects the system even if UI is bypassed)
        if ($user->isSuperuser()) {
            return redirect()->route('settings.users')->with('error', 'You cannot delete a superuser account.');
        }

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('settings.users')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('settings.users')->with('success', 'User deleted successfully.');
    }

        
    public function general()
    {
        $this->abortUnlessRole('superuser', 'admin');
        // Use your Model's custom getAll logic or pluck manually
        // We use Setting::all() to get everything from the 'settings' table
        $settings = Setting::all()->pluck('value', 'key');

        return view('settings.general-settings', compact('settings'));
    }

    /**
     * 2. THE SAVING LOGIC: Your current function
     */
    public function updateGeneral(Request $request)
    {
        $this->abortUnlessRole('superuser', 'admin');
        $request->validate([
            'app_name'    => 'required|string|max:255',
            'currency'    => 'required|string|max:10',
            'date_format' => 'required|string',
            'time_format' => 'required|string',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $oldLogo = Setting::get('logo');
            if ($oldLogo) {
                \Storage::disk('public')->delete($oldLogo);
            }
            $path = $request->file('logo')->store('uploads/settings', 'public');
            Setting::set('logo', $path);
        }

        $data = $request->except(['_token', '_method', 'logo']);
        foreach ($data as $key => $value) {
            Setting::set($key, $value); 
        }

        return back()->with('success', 'General settings updated successfully.');
    }
}