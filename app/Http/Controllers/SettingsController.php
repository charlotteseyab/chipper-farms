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
use App\Models\Organization;
use App\Helpers\FlashHelper;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\ActivityLog;
use App\Services\Reports\SystemReportService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;

class SettingsController extends Controller
{
    /**
     * Show the settings setup page
     */
    public function setup()
    {
        $this->abortUnlessRole('superuser', 'admin');
        $platformView = auth()->user()->isSuperuser();
        $minimumAges = Setting::get('breeding.parameters.minimum_breeding_age', []);
        $optimalAges = Setting::get('breeding.parameters.optimal_first_breeding_age', []);
        $minimumWeights = Setting::get('breeding.parameters.minimum_breeding_weight', []);
        $optimalWeights = Setting::get('breeding.parameters.optimal_breeding_weight', []);
        $breeding = [
            'pregnancy_check_days' => Setting::get('breeding.cycle.pregnancy_check_days'),
            'gestation_days' => Setting::get('breeding.cycle.gestation_days'),
            'birth_to_weaning_days' => Setting::get('breeding.cycle.birth_to_weaning_days'),
            'birth_to_maturity_days' => Setting::get('breeding.cycle.birth_to_maturity_days'),
            'minimum_breeding_age_female' => $minimumAges['female'] ?? null,
            'minimum_breeding_age_male' => $minimumAges['male'] ?? null,
            'optimal_first_breeding_age_female' => $optimalAges['female'] ?? null,
            'optimal_first_breeding_age_male' => $optimalAges['male'] ?? null,
            'minimum_breeding_weight_female' => $minimumWeights['female'] ?? null,
            'minimum_breeding_weight_male' => $minimumWeights['male'] ?? null,
            'optimal_breeding_weight_female' => $optimalWeights['female'] ?? null,
            'optimal_breeding_weight_male' => $optimalWeights['male'] ?? null,
            'average_litter_size' => Setting::get('breeding.parameters.average_litter_size'),
            'weaning_to_mating_rest' => Setting::get('breeding.parameters.weaning_to_mating_rest'),
            'maximum_breeding_age' => Setting::get('breeding.parameters.maximum_breeding_age'),
            'lactation_days' => Setting::get('breeding.parameters.lactation_days'),
        ];

        $stages = [
            'piglet_max_days' => Setting::get('stages.piglet.max_days'),
            'weaner_max_days' => Setting::get('stages.weaner.max_days'),
            'grower_max_days' => Setting::get('stages.grower.max_days'),
        ];

        $pricing = [
            'live_weight_price' => Setting::get('sales.pricing.live_weight_price'),
            'carcass_weight_price' => Setting::get('sales.pricing.carcass_weight_price'),
        ];

        $feedBagWeights = [
            'default_bag_weight_kg' => Setting::get('feed.units.default_bag_weight_kg'),
            'maize_bag_weight_kg' => Setting::get('feed.units.maize_bag_weight_kg'),
            'soybean_meal_bag_weight_kg' => Setting::get('feed.units.soybean_meal_bag_weight_kg'),
            'wheat_bran_bag_weight_kg' => Setting::get('feed.units.wheat_bran_bag_weight_kg'),
            'premix_bag_weight_kg' => Setting::get('feed.units.premix_bag_weight_kg'),
            'concentrate_bag_weight_kg' => Setting::get('feed.units.concentrate_bag_weight_kg'),
        ];

        $purchaseRequestFeedItems = Setting::purchaseFeedItems();
        $animalBreeds = Setting::animalBreeds();

        if ($platformView) {
            $breeding = array_fill_keys(array_keys($breeding), null);
            $pricing = array_fill_keys(array_keys($pricing), null);
            $stages = array_fill_keys(array_keys($stages), null);
            $feedBagWeights = array_fill_keys(array_keys($feedBagWeights), null);
            $purchaseRequestFeedItems = [];
            $animalBreeds = [];
        }
        
        return view('settings.setup', compact(
            'breeding',
            'pricing',
            'stages',
            'feedBagWeights',
            'purchaseRequestFeedItems',
            'animalBreeds',
            'platformView',
        ));
    }

    /**
     * Persist breeding/settings page values
     */
    public function saveBreeding(Request $request)
    {
        $this->abortUnlessRole('superuser', 'admin');
        abort_unless($request->user()->organization_id, 403, 'Operational setup must be completed by a farm administrator.');

        if ($request->has('feed_items_only')) {
            return $this->savePurchaseFeedItems($request);
        }
        if ($request->has('breeds_only')) {
            return $this->saveAnimalBreeds($request);
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
            'minimum_breeding_weight_female' => 'required|numeric|min:20|max:500',
            'minimum_breeding_weight_male' => 'required|numeric|min:20|max:500',
            'optimal_breeding_weight_female' => 'required|numeric|min:20|max:500',
            'optimal_breeding_weight_male' => 'required|numeric|min:20|max:500',
            'average_litter_size' => 'required|numeric|min:1|max:25',
            'weaning_to_mating_rest' => 'required|integer|min:0|max:60',
            'maximum_breeding_age' => 'required|integer|min:365|max:3650',
            'lactation_days' => 'required|integer|min:21|max:120',
            'live_weight_price' => 'required|numeric|min:1|max:1000',
            'carcass_weight_price' => 'required|numeric|min:1|max:1000',
            'piglet_max_days' => 'required|integer|min:1|max:300',
            'weaner_max_days' => 'required|integer|min:' . ($request->input('piglet_max_days', 1) + 1) . '|max:400',
            'grower_max_days' => 'required|integer|min:' . ($request->input('weaner_max_days', 2) + 1) . '|max:600',

            'default_bag_weight_kg' => 'required|numeric|min:0.01|max:500',
            'maize_bag_weight_kg' => 'required|numeric|min:0.01|max:500',
            'soybean_meal_bag_weight_kg' => 'required|numeric|min:0.01|max:500',
            'wheat_bran_bag_weight_kg' => 'required|numeric|min:0.01|max:500',
            'premix_bag_weight_kg' => 'required|numeric|min:0.01|max:500',
            'concentrate_bag_weight_kg' => 'required|numeric|min:0.01|max:500',
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
        Setting::set('breeding.parameters.minimum_breeding_weight', [
            'female' => (float) $data['minimum_breeding_weight_female'],
            'male' => (float) $data['minimum_breeding_weight_male'],
        ]);
        Setting::set('breeding.parameters.optimal_breeding_weight', [
            'female' => (float) $data['optimal_breeding_weight_female'],
            'male' => (float) $data['optimal_breeding_weight_male'],
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
        Setting::set('feed.units.default_bag_weight_kg', (float) $data['default_bag_weight_kg']);
        Setting::set('feed.units.maize_bag_weight_kg', (float) $data['maize_bag_weight_kg']);
        Setting::set('feed.units.soybean_meal_bag_weight_kg', (float) $data['soybean_meal_bag_weight_kg']);
        Setting::set('feed.units.wheat_bran_bag_weight_kg', (float) $data['wheat_bran_bag_weight_kg']);
        Setting::set('feed.units.premix_bag_weight_kg', (float) $data['premix_bag_weight_kg']);
        Setting::set('feed.units.concentrate_bag_weight_kg', (float) $data['concentrate_bag_weight_kg']);

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
        abort_unless($request->user()->organization_id, 403, 'Operational setup must be completed by a farm administrator.');

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

    public function saveAnimalBreeds(Request $request)
    {
        $this->abortUnlessRole('superuser', 'admin');
        abort_unless($request->user()->organization_id, 403, 'Operational setup must be completed by a farm administrator.');

        $data = $request->validate([
            'breed_names' => ['nullable', 'array'],
            'breed_names.*' => ['nullable', 'string', 'max:100'],
            'remove_breeds' => ['nullable', 'array'],
            'remove_breeds.*' => ['string', 'max:20'],
            'new_breed_code' => ['nullable', 'alpha_dash', 'max:20'],
            'new_breed_name' => ['nullable', 'string', 'max:100', 'required_with:new_breed_code'],
        ]);

        $breeds = Setting::animalBreeds();
        foreach ($data['breed_names'] ?? [] as $code => $name) {
            if (array_key_exists($code, $breeds) && trim((string) $name) !== '') {
                $breeds[$code] = trim($name);
            }
        }

        foreach ($data['remove_breeds'] ?? [] as $code) {
            $organizationId = (int) $request->user()->organization_id;
            $inUse = PigManagement::where('organization_id', $organizationId)->where('breed', $code)->exists()
                || \App\Models\PurchaseStockIntake::where('organization_id', $organizationId)->where('breed', $code)->exists();
            if ($inUse) {
                return back()->withErrors(['breeds' => "{$code} cannot be removed because it is used by existing stock."])->withInput();
            }
            unset($breeds[$code]);
        }

        if (! empty($data['new_breed_name'])) {
            $code = ! empty($data['new_breed_code'])
                ? strtoupper(trim($data['new_breed_code']))
                : $this->generateBreedCode($data['new_breed_name'], array_keys($breeds));
            if (array_key_exists($code, $breeds)) {
                return back()->withErrors(['new_breed_code' => 'That breed code already exists.'])->withInput();
            }
            $breeds[$code] = trim($data['new_breed_name']);
        }

        if ($breeds === []) {
            return back()->withErrors(['breeds' => 'At least one animal breed must remain available.']);
        }

        Setting::setAnimalBreeds($breeds);

        return redirect()->route('settings.setup')->with('success', 'Animal breeds saved successfully.');
    }

    protected function generateBreedCode(string $name, array $existingCodes): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $base = count($words) > 1
            ? implode('', array_map(fn ($word) => substr($word, 0, 1), $words))
            : substr($words[0] ?? 'BRD', 0, 4);
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $base) ?: 'BRD');
        $code = $base;
        $suffix = 2;
        while (in_array($code, $existingCodes, true)) {
            $code = substr($base, 0, 16).$suffix++;
        }

        return $code;
    }

    /**
     * Reset breeding settings to defaults
     */
    public function resetBreeding()
    {
        $this->abortUnlessRole('superuser', 'admin');
        $organizationId = auth()->user()->organization_id;
        abort_unless($organizationId, 403, 'Farm setup can only be cleared from a farm administrator account.');

        // Remove only this farm's choices. Other farms and platform settings
        // must never be touched by this action.
        foreach ([
            'breeding.cycle.pregnancy_check_days',
            'breeding.cycle.gestation_days',
            'breeding.cycle.birth_to_weaning_days',
            'breeding.cycle.birth_to_maturity_days',
            'breeding.parameters.minimum_breeding_age',
            'breeding.parameters.optimal_first_breeding_age',
            'breeding.parameters.minimum_breeding_weight',
            'breeding.parameters.optimal_breeding_weight',
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
            \App\Models\Setting::query()
                ->where('organization_id', $organizationId)
                ->where('key', $key)
                ->delete();
        }

        return FlashHelper::successAndRedirect('This farm’s setup values were cleared.', 'settings.setup');
    }

    /**
     * Show comprehensive system reports
     */
    public function systemReports(Request $request, SystemReportService $reports, CurrentOrganization $currentOrganization, \App\Services\Reports\PlatformReportingService $platformReports)
    {
        $this->abortUnlessRole('superuser', 'admin');
        [$from, $to] = $this->reportPeriod($request);
        if (auth()->user()->isSuperuser() && (! $request->filled('organization_id') || $request->input('organization_id') === 'all')) {
            $activeTab = $request->validate([
                'tab' => ['nullable', 'string', Rule::in(['overview', 'livestock', 'breeding', 'health', 'weights', 'feed', 'procurement', 'sales', 'finance', 'users', 'risk'])],
            ])['tab'] ?? 'overview';
            return view('settings.platform-reports', array_merge(
                $platformReports->build($from, $to),
                ['reportOrganizations' => Organization::active()->orderBy('name')->get(), 'activeTab' => $activeTab]
            ));
        }
        $organizationId = $this->reportOrganizationId($request, $currentOrganization);

        return view('settings.reports', array_merge(
            $reports->build($organizationId, $from, $to),
            ['reportOrganizations' => auth()->user()->isSuperuser() ? Organization::active()->orderBy('name')->get() : collect(), 'reportOrganizationId' => $organizationId]
        ));
    }

    /**
     * Activity log list (login/logout)
     */
    public function activityLog(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ($user->isSuperuser() || $user->isAdmin()), 403);

        $filters = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'string', 'max:100'],
            'record' => ['nullable', 'string', 'max:150'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = ActivityLog::query()->with(['user', 'organization']);

        if ($user->isAdmin()) {
            $query->where('organization_id', $user->organization_id);
        } elseif (! empty($filters['organization_id'])) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['user'])) {
            $term = trim($filters['user']);
            $query->where(function ($search) use ($term): void {
                $search->where('name', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($users) => $users
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
            });
        }
        if (! empty($filters['record'])) {
            $term = trim($filters['record']);
            $query->where(function ($search) use ($term): void {
                $search->where('record_label', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $summaryQuery = clone $query;
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'today' => (clone $summaryQuery)->whereDate('created_at', today())->count(),
            'users' => (clone $summaryQuery)->whereNotNull('user_id')->distinct()->count('user_id'),
            'attention' => (clone $summaryQuery)->where(function ($attention): void {
                $attention->whereIn('action', ['login_attempt', 'failed', 'declined', 'deleted', 'archived', 'cancelled', 'refunded'])
                    ->orWhere('action', 'like', '%failed%');
            })->count(),
        ];

        $logs = $query->latest()->paginate(25)->withQueryString();
        $modules = ActivityLog::query()
            ->when($user->isAdmin(), fn ($items) => $items->where('organization_id', $user->organization_id))
            ->whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $actions = ActivityLog::query()
            ->when($user->isAdmin(), fn ($items) => $items->where('organization_id', $user->organization_id))
            ->whereNotNull('action')->distinct()->orderBy('action')->pluck('action');
        $organizations = $user->isSuperuser()
            ? Organization::withTrashed()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('settings.activity-log', compact('logs', 'summary', 'modules', 'actions', 'organizations'));
    }

    /**
     * Export system reports as PDF
     */
    public function exportSystemReportsPdf(Request $request, SystemReportService $reports, CurrentOrganization $currentOrganization, \App\Services\Reports\PlatformReportingService $platformReports)
    {
        $this->abortUnlessRole('superuser', 'admin');
        [$from, $to] = $this->reportPeriod($request);
        if (auth()->user()->isSuperuser() && (! $request->filled('organization_id') || $request->input('organization_id') === 'all')) {
            $data = $platformReports->build($from, $to);
            return \PDF::loadView('exports.platform-reports-pdf', $data)->download('platform-report-'.now()->format('Y-m-d').'.pdf');
        }
        $data = $reports->build($this->reportOrganizationId($request, $currentOrganization), $from, $to);
        $pdf = \PDF::loadView('exports.system-reports-pdf', $data);

        return $pdf->download('system-reports-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export system reports as CSV
     */
    public function exportSystemReportsCsv(Request $request, SystemReportService $reports, CurrentOrganization $currentOrganization, \App\Services\Reports\PlatformReportingService $platformReports)
    {
        $this->abortUnlessRole('superuser', 'admin');
        [$from, $to] = $this->reportPeriod($request);
        if (auth()->user()->isSuperuser() && (! $request->filled('organization_id') || $request->input('organization_id') === 'all')) {
            $platform = $platformReports->build($from, $to);
            return response()->streamDownload(function () use ($platform) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Organization', 'Active pigs', 'Added', 'Sold', 'Deaths', 'Revenue', 'Open orders', 'Pipeline', 'Alerts', 'Data quality', 'Status']);
                foreach ($platform['rows'] as $row) {
                    fputcsv($out, [$row['name'], $row['active_pigs'], $row['added'], $row['sold'], $row['deaths'], $row['revenue'], $row['open_orders'], $row['order_pipeline'], $row['alerts'], $row['data_quality'].'%', $row['status']]);
                }
                fclose($out);
            }, 'platform-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }
        $report = $reports->build($this->reportOrganizationId($request, $currentOrganization), $from, $to);
        $filename = 'system-reports-' . now()->format('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($report) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['FARM PERFORMANCE REPORT']);
            fputcsv($file, ['Period', $report['from']->format('Y-m-d').' to '.$report['to']->format('Y-m-d')]);
            fputcsv($file, ['Generated', now()->toDateTimeString()]);
            fputcsv($file, []);
            fputcsv($file, ['EXECUTIVE OVERVIEW']);
            fputcsv($file, ['Metric', 'Value']);
            fputcsv($file, ['Closing Herd', $report['current']['closing_herd']]);
            fputcsv($file, ['Sales Revenue (GHS)', $report['current']['revenue']]);
            fputcsv($file, ['Average Sale (GHS)', $report['current']['average_sale']]);
            fputcsv($file, ['Average Price per kg (GHS)', $report['current']['average_price_per_kg']]);
            fputcsv($file, ['Mortality Rate (%)', $report['current']['mortality_rate'] ?? 'N/A']);
            fputcsv($file, ['Pig deaths', $report['current']['deaths']]);
            fputcsv($file, ['Piglet deaths', $report['current']['piglet_deaths']]);
            fputcsv($file, []);

            fputcsv($file, ['HERD MOVEMENT RECONCILIATION']);
            // Columns named by key, so a header can never drift from its values again.
            $movement = ['opening' => 'Opening', 'weaned_into_stock' => 'Weaned into stock', 'purchased' => 'Bought',
                'manually_added' => 'Added or imported', 'sold' => 'Sold', 'deaths' => 'Deaths',
                'transferred_or_culled' => 'Transferred or culled', 'closing' => 'Closing', 'variance' => 'Variance'];
            fputcsv($file, array_values($movement));
            fputcsv($file, array_map(fn ($key) => $report['herdMovement'][$key], array_keys($movement)));
            fputcsv($file, []);

            fputcsv($file, ['BREEDING']);
            foreach ($report['breeding'] as $metric => $value) {
                fputcsv($file, [str($metric)->replace('_', ' ')->title(), $value ?? 'N/A']);
            }
            fputcsv($file, []);

            fputcsv($file, ['PIGLET TRACKING']);
            foreach (['litters', 'total_born', 'live_births', 'stillbirths', 'pre_weaning_deaths', 'currently_alive', 'weaned', 'survival_rate', 'average_litter_size', 'average_birth_weight', 'average_weaning_age', 'average_weaning_weight'] as $metric) {
                fputcsv($file, [str($metric)->replace('_', ' ')->title(), $report['pigletTracking'][$metric] ?? 'N/A']);
            }
            fputcsv($file, []);

            fputcsv($file, ['HERD COMPOSITION']);
            fputcsv($file, ['Stock Type', 'Head']);
            foreach ($report['distribution'] as $row) {
                fputcsv($file, [$row->label, $row->total]);
            }
            fputcsv($file, []);

            fputcsv($file, ['PIG SALES']);
            fputcsv($file, ['Recorded on the farm', $report['pigSales']['direct'], $report['pigSales']['direct_revenue']]);
            fputcsv($file, ['Through the shop', $report['pigSales']['shop'], $report['pigSales']['shop_revenue']]);
            fputcsv($file, []);
            fputcsv($file, ['BOUGHT ANIMALS']);
            foreach (['intakes' => 'Purchases', 'animals_bought' => 'Animals received', 'added_to_stock' => 'Added to stock', 'lost_in_quarantine' => 'Lost in quarantine', 'cost' => 'Cost incl. transport (GHS)', 'waiting' => 'Still on the way to stock'] as $key => $label) {
                fputcsv($file, [$label, $report['purchases'][$key]]);
            }
            fputcsv($file, []);
            fputcsv($file, ['HEALTH']);
            foreach (['cases_opened' => 'Cases opened', 'animals_affected' => 'Animals affected', 'cases_finished' => 'Resolved or closed', 'open_now' => 'Open now', 'severe_open' => 'Severe open', 'doses' => 'Doses given', 'medicine_cost' => 'Medicine used (GHS)', 'under_withdrawal' => 'Under withdrawal today'] as $key => $label) {
                fputcsv($file, [$label, $report['health'][$key]]);
            }
            foreach ($report['health']['doses_by_purpose'] as $purpose => $count) {
                fputcsv($file, ['Doses: '.$purpose, $count]);
            }
            fputcsv($file, []);
            fputcsv($file, ['STOREFRONT']);
            fputcsv($file, ['Orders', $report['storefront']['orders']]);
            fputcsv($file, ['Completed', $report['storefront']['completed']]);
            fputcsv($file, ['Open orders', $report['storefront']['open']]);
            fputcsv($file, ['Realised revenue (GHS)', $report['storefront']['realised_revenue']]);
            fputcsv($file, ['Open pipeline (GHS)', $report['storefront']['pipeline']]);
            fputcsv($file, []);

            fputcsv($file, ['FINANCE LEDGER']);
            foreach (['revenue', 'cost_of_sales', 'gross_profit', 'operating_expenses', 'net_profit', 'inventory_value'] as $metric) {
                fputcsv($file, [str($metric)->replace('_', ' ')->title(), $report['finance'][$metric]]);
            }
            fputcsv($file, []);

            fputcsv($file, ['GROWTH & FEED']);
            fputcsv($file, ['Weight records', $report['weights']['records']]);
            fputcsv($file, ['Average recorded weight (kg)', $report['weights']['average_kg']]);
            fputcsv($file, ['Feed given (kg)', $report['feed']['issued_kg']]);
            fputcsv($file, ['Feed given cost (GHS)', $report['feed']['issued_cost']]);
            fputcsv($file, ['Feed mixed (kg)', $report['feed']['mixed_kg']]);
            fputcsv($file, ['Feed mixed cost (GHS)', $report['feed']['mixed_cost']]);
            foreach ($report['feed']['by_stage'] as $row) {
                fputcsv($file, ['Feed given: '.$row->name.' (kg)', $row->quantity_kg]);
            }
            fputcsv($file, []);

            fputcsv($file, ['ROOM OCCUPANCY']);
            fputcsv($file, ['Structure', 'Room', 'Animals', 'Capacity', 'Utilization %']);
            foreach ($report['facilities']['details'] as $room) {
                fputcsv($file, [
                    $room->structure?->name ?? 'Unassigned', $room->name, $room->pigs_count,
                    $room->capacity ?? 'Unlimited', $room->capacity > 0 ? round(($room->pigs_count / $room->capacity) * 100, 1) : 'N/A',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function reportPeriod(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from'] ?? now()->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString());

        return [$from, $to];
    }

    private function reportOrganizationId(Request $request, CurrentOrganization $currentOrganization): int
    {
        if (! auth()->user()->isSuperuser()) {
            return $currentOrganization->requireId();
        }

        $organization = Organization::active()
            ->when($request->filled('organization_id'), fn ($query) => $query->whereKey($request->integer('organization_id')))
            ->orderBy('name')
            ->firstOrFail();
        $currentOrganization->bind($organization);

        return (int) $organization->id;
    }

    /**
     * Show users management page
     */
    public function users(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->isSuperuser() || $actor->isAdmin()), 403);

        $baseUsers = User::query()
            ->when($actor->isAdmin(), fn ($query) => $query->where('organization_id', $actor->organization_id));
        $userStats = [
            'total' => (clone $baseUsers)->count(),
            'active' => (clone $baseUsers)->where('active_status', true)->count(),
            'inactive' => (clone $baseUsers)->where('active_status', false)->count(),
            'farms' => $actor->isSuperuser() ? Organization::active()->count() : 1,
        ];

        $users = (clone $baseUsers)->with(['roleRecord', 'organization'])
            ->when($actor->isSuperuser() && $request->filled('organization_id'), fn ($query) => $query->where('organization_id', $request->integer('organization_id')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('q')).'%';
                $query->where(fn ($nested) => $nested->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->input('role')))
            ->when($request->input('status') === 'active', fn ($query) => $query->where('active_status', true))
            ->when($request->input('status') === 'inactive', fn ($query) => $query->where('active_status', false))
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();
        $organizations = $actor->isSuperuser() ? Organization::active()->orderBy('name')->get() : collect();
        $availableRoles = $actor->isSuperuser()
            ? array_values(array_diff(User::allowedRoleNames(), ['customer']))
            : array_values(array_diff(User::allowedRoleNames(), ['superuser', 'customer']));

        return view('settings.users', compact('users', 'organizations', 'availableRoles', 'userStats'));
    }

    /**
     * Store a new user
     */
    public function storeUser(
        Request $request,
        \App\Services\Users\UserProvisioningService $provisioning,
        \App\Services\Email\EmailTemplateService $emails,
    )
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->isSuperuser() || $actor->isAdmin()), 403);

        if ($actor->isSuperuser() && $request->input('account_scope') === 'platform') {
            $request->merge(['role' => 'superuser', 'organization_id' => null]);
        }

        // Older builds soft-deleted users. Those invisible rows still occupy
        // the unique email index and would make a genuinely deleted address
        // appear unavailable. Permanently clear only a matching retired row
        // that this actor is authorized to manage before uniqueness runs.
        $requestedEmail = strtolower(trim((string) $request->input('email')));
        if ($requestedEmail !== '') {
            $retiredUsers = User::onlyTrashed()
                ->where('email', $requestedEmail)
                ->when($actor->isAdmin(), fn ($query) => $query->where('organization_id', $actor->organization_id))
                ->get();

            foreach ($retiredUsers as $retiredUser) {
                DB::transaction(function () use ($retiredUser): void {
                    DB::table('sessions')->where('user_id', $retiredUser->id)->delete();
                    DB::table('password_reset_tokens')->where('email', $retiredUser->email)->delete();
                    $retiredUser->forceDelete();
                });
            }

            $request->merge(['email' => $requestedEmail]);
        }

        $validated = $request->validate([
            'account_scope' => [$actor->isSuperuser() ? 'required' : 'sometimes', 'in:farm,platform'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:50', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'in:' . implode(',', $actor->isSuperuser()
                ? array_values(array_diff(User::allowedRoleNames(), ['customer']))
                : array_values(array_diff(User::allowedRoleNames(), ['superuser', 'customer'])))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'active_status' => ['sometimes', 'boolean'],
            'organization_id' => ['sometimes', 'nullable', 'integer', 'exists:organizations,id'],
        ]);

        if ($actor->isAdmin()) {
            abort_if($validated['role'] === 'superuser', 403, 'Farm administrators cannot create platform superusers.');
            $validated['organization_id'] = $actor->organization_id;
        } elseif (($validated['account_scope'] ?? 'farm') === 'farm' && $validated['role'] === 'superuser') {
            return back()->withErrors(['role' => 'Choose a farm staff role for a farm account.'])->withInput();
        } elseif ($validated['role'] !== 'superuser' && empty($validated['organization_id'])) {
            return back()->withErrors(['organization_id' => 'Select the farm this user belongs to.'])->withInput();
        }

        // CF-P0-001: this method used to build the User inline, never setting
        // organization_id and defaulting active_status to false — producing an
        // account that EnsureOrganizationContext and EnsureUserIsActive both
        // reject on the first request. All creation now goes through the single
        // provisioning service, which establishes organisation and role
        // transactionally and refuses to return an unusable account.
        $user = $provisioning->provision($validated, $request->user());

        // Farm users receive the same secure first-login experience as the
        // administrator created with a new organization. Platform superusers
        // deliberately have no organization, so organization-owned templates
        // do not apply to them.
        $emailSent = false;
        if ($user->organization_id) {
            $user->loadMissing('organization');
            $resetToken = \Illuminate\Support\Facades\Password::broker()->createToken($user);
            $passwordUrl = route('password.reset', [
                'token' => $resetToken,
                'email' => $user->email,
            ]);

            $emailSent = $emails->send(
                (int) $user->organization_id,
                'users.account_created',
                $user->email,
                [
                    'recipient_name' => $user->name,
                    'organization_name' => $user->organization?->name ?? config('app.name'),
                    'role_name' => match ($user->role) {
                        'admin' => 'Administrator',
                        'farmmanager' => 'Farm Manager',
                        'dataentry' => 'Data Entry Officer',
                        'accountant' => 'Accountant',
                        'treasurer' => 'Treasurer',
                        'salesperson' => 'Salesperson',
                        'mill_operator' => 'Mill Operator',
                        default => ucfirst(str_replace('_', ' ', $user->role)),
                    },
                    'login_url' => route('login'),
                    'login_email' => $user->email,
                    'temporary_password' => $validated['password'],
                    'action_url' => $passwordUrl,
                    'store_name' => $user->organization?->name ?? config('app.name'),
                    'support_email' => $user->organization?->email,
                    'support_phone' => $user->organization?->phone,
                ],
            );
        }

        if (! $user->organization_id) {
            return redirect()->route('settings.users')->with('success', 'Platform user created successfully.');
        }

        return redirect()->route('settings.users')->with(
            $emailSent ? 'success' : 'warning',
            $emailSent
                ? 'User created successfully. Login instructions were emailed to them.'
                : 'User created successfully, but the welcome email was not sent. Check whether the template is active and the email server is configured.',
        );
    }

    /**
     * Update a user
     */
    public function updateUser(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->isSuperuser() || $actor->isAdmin()), 403);
        $this->authorizeUserAdministration($actor, $user);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:50', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'in:' . implode(',', $actor->isSuperuser()
                ? array_values(array_diff(User::allowedRoleNames(), ['customer']))
                : array_values(array_diff(User::allowedRoleNames(), ['superuser', 'customer'])))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'active_status' => ['sometimes', 'boolean'],
        ]);

        if ($actor->isAdmin() && $validated['role'] === 'superuser') {
            abort(403, 'Farm administrators cannot assign the platform superuser role.');
        }

        if ($actor->isSuperuser() && $user->isSuperuser() && $validated['role'] !== 'superuser') {
            return back()->withErrors(['role' => 'Create a farm user instead of converting a platform superuser.']);
        }

        if (! $user->isSuperuser() && $validated['role'] === 'superuser') {
            return back()->withErrors(['role' => 'A farm account cannot be converted into a platform superuser.']);
        }

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
        $actor = $request->user();
        abort_unless($actor && ($actor->isSuperuser() || $actor->isAdmin()), 403);
        $this->authorizeUserAdministration($actor, $user);

        $validated = $request->validate([
            'active_status' => ['required', 'boolean'],
        ]);

        if ($user->is($actor) && ! $validated['active_status']) {
            return response()->json(['success' => false, 'message' => 'You cannot deactivate your own account.'], 422);
        }

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
        $actor = auth()->user();
        abort_unless($actor && ($actor->isSuperuser() || $actor->isAdmin()), 403);
        $this->authorizeUserAdministration($actor, $user);

        // Prevent deleting any superuser account (protects the system even if UI is bypassed)
        if ($user->isSuperuser()) {
            return redirect()->route('settings.users')->with('error', 'You cannot delete a superuser account.');
        }

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('settings.users')->with('error', 'You cannot delete your own account.');
        }

        DB::transaction(function () use ($user): void {
            // User removal is permanent across both platform and farm-admin
            // management paths. Clear authentication artifacts first so a
            // deleted identity cannot retain a session or reset link.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->forceDelete();
        });

        return redirect()->route('settings.users')->with('success', 'User permanently deleted successfully.');
    }

    private function authorizeUserAdministration(User $actor, User $target): void
    {
        if ($actor->isSuperuser()) {
            return;
        }

        abort_unless(
            $actor->isAdmin()
            && ! $target->isSuperuser()
            && (int) $actor->organization_id === (int) $target->organization_id,
            404
        );
    }

        
    public function general()
    {
        $this->abortUnlessRole('superuser', 'admin');
        // Use your Model's custom getAll logic or pluck manually
        // We use Setting::all() to get everything from the 'settings' table
        $settings = Setting::getAll();

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
