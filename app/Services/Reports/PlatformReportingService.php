<?php

namespace App\Services\Reports;

use App\Models\ActivityLog;
use App\Models\Farrowing;
use App\Models\FeedIssue;
use App\Models\HealthRecord;
use App\Models\Mating;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PigWeight;
use App\Models\PurchaseRequest;
use App\Models\Storefront\StorefrontOrder;
use App\Models\User;
use App\Models\Weaning;
use App\Models\WeightAlert;
use Carbon\CarbonImmutable;

class PlatformReportingService
{
    public function build(CarbonImmutable $from, CarbonImmutable $to, ?int $organizationId = null): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $organizations = Organization::withCount(['users' => fn ($query) => $query->where('role', '!=', 'superuser')])
            ->when($organizationId, fn ($query) => $query->whereKey($organizationId))
            ->orderBy('name')->get();

        $farmReports = app(SystemReportService::class);

        $rows = $organizations->map(function (Organization $organization) use ($from, $to, $farmReports): array {
            $orgId = (int) $organization->id;
            // Same herd, death and mortality figures as the farm report.
            $period = $farmReports->periodSummary($orgId, $from, $to);
            $pigs = PigManagement::query()->forOrganization($orgId);
            $periodPigs = (clone $pigs)->withTrashed()->whereBetween('created_at', [$from, $to]);
            $sales = PigSale::query()->forOrganization($orgId)->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()]);
            $openOrders = StorefrontOrder::query()->forOrganization($orgId)->open();
            $orders = StorefrontOrder::query()->forOrganization($orgId)->whereBetween('created_at', [$from, $to]);
            $alerts = WeightAlert::query()->where('acknowledged', false)->whereHas('pig', fn ($query) => $query->forOrganization($orgId));
            $matings = Mating::query()->whereHas('female', fn ($query) => $query->forOrganization($orgId))->whereBetween('actual_date', [$from, $to]);
            $farrowings = Farrowing::query()->whereHas('sow', fn ($query) => $query->forOrganization($orgId))->whereBetween('farrowing_date', [$from, $to]);
            $weanings = Weaning::query()->whereHas('pig', fn ($query) => $query->forOrganization($orgId))->whereBetween('weaning_date', [$from, $to]);
            // Health cases (not voided) opened in the period, by the farm they belong to.
            $health = HealthRecord::query()->withoutGlobalScopes()->where('organization_id', $orgId)->whereNull('voided_at')->whereNull('deleted_at')
                ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()]);
            // Reversed feedings were mistakes, not feed given.
            $feed = FeedIssue::query()->forOrganization($orgId)->notReversed()->whereBetween('issued_at', [$from, $to]);
            $purchases = PurchaseRequest::query()->forOrganization($orgId)->whereBetween('request_date', [$from->toDateString(), $to->toDateString()]);
            $weights = PigWeight::query()->whereHas('pig', fn ($query) => $query->forOrganization($orgId))->whereBetween('weigh_date', [$from->toDateString(), $to->toDateString()]);
            $activity = ActivityLog::query()->where('organization_id', $orgId)->whereBetween('created_at', [$from, $to]);
            $active = (clone $pigs)->active()->count();
            $missingDetails = (clone $pigs)->active()->where(function ($query) {
                $query->whereNull('ear_tag_number')->orWhere('ear_tag_number', '')
                    ->orWhereNull('gender')->orWhere('gender', '')
                    ->orWhereNull('breed')->orWhere('breed', '');
            })->count();
            $dataQuality = $active > 0 ? max(0, round((1 - ($missingDetails / $active)) * 100)) : 100;
            $alertCount = (clone $alerts)->count();
            $criticalAlerts = (clone $alerts)->where('severity', 'high')->count();
            $status = match (true) {
                ! $organization->is_active => 'Inactive',
                $criticalAlerts > 0 || $dataQuality < 70 => 'Needs attention',
                $alertCount > 0 || $dataQuality < 90 => 'Watch',
                default => 'Healthy',
            };

            return [
                'id' => $orgId,
                'name' => $organization->name,
                'active' => (bool) $organization->is_active,
                'staff' => (int) $organization->users_count,
                'active_pigs' => $active,
                'added' => (clone $periodPigs)->count(),
                'sold' => (clone $sales)->count(),
                'deaths' => $period['deaths'] + $period['piglet_deaths'],
                'pig_deaths' => $period['deaths'],
                'piglet_deaths' => $period['piglet_deaths'],
                'mortality_rate' => (float) ($period['mortality_rate'] ?? 0),
                'revenue' => (float) ((clone $sales)->sum('sale_price') ?? 0),
                'open_orders' => (clone $openOrders)->count(),
                'order_pipeline' => (float) ((clone $openOrders)->sum('total') ?? 0),
                'orders' => (clone $orders)->count(),
                'completed_orders' => (clone $orders)->where('status', StorefrontOrder::STATUS_COMPLETED)->count(),
                'paid_orders' => (clone $orders)->whereIn('payment_status', [StorefrontOrder::PAY_PAID, StorefrontOrder::PAY_PAYSTACK_VERIF])->count(),
                'storefront_revenue' => (float) ((clone $orders)->whereIn('payment_status', [StorefrontOrder::PAY_PAID, StorefrontOrder::PAY_PAYSTACK_VERIF])->sum('total') ?? 0),
                'alerts' => $alertCount,
                'critical_alerts' => $criticalAlerts,
                'data_quality' => $dataQuality,
                'status' => $status,
                'matings' => (clone $matings)->count(),
                'pregnant' => (clone $matings)->whereIn('pregnancy_status', ['pregnant', 'farrowed', 'lost_pregnancy', 'aborted'])->count(),
                // Of matings whose pregnancy has been checked.
                'checked_matings' => (clone $matings)->whereIn('pregnancy_status', ['pregnant', 'farrowed', 'lost_pregnancy', 'aborted', 'not_pregnant'])->count(),
                'farrowings' => (clone $farrowings)->count(),
                'live_births' => (int) ((clone $farrowings)->sum('live_births') ?? 0),
                'weanings' => (clone $weanings)->count(),
                'health_records' => (clone $health)->count(),
                'open_health_cases' => (clone $health)->whereIn('case_status', ['open', 'under_treatment'])->count(),
                'feed_issued_kg' => (float) ((clone $feed)->sum('quantity_kg') ?? 0),
                'feed_cost' => (float) ((clone $feed)->sum('total_cost') ?? 0),
                'purchase_requests' => (clone $purchases)->count(),
                'purchase_value' => (float) ((clone $purchases)->sum('total_amount') ?? 0),
                'pending_purchases' => (clone $purchases)->whereIn('status', ['pending', 'submitted', 'approved'])->count(),
                'approved_purchases' => (clone $purchases)->whereIn('status', ['approved', 'paid', 'received', 'completed'])->count(),
                'weight_records' => (clone $weights)->count(),
                'average_weight' => round((float) ((clone $weights)->avg('weight') ?? 0), 1),
                'audit_events' => (clone $activity)->count(),
                'active_users' => (clone $activity)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            ];
        })->values();

        $activityQuery = ActivityLog::query()->whereBetween('created_at', [$from, $to])
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId));

        $activityByModule = (clone $activityQuery)
            ->selectRaw("COALESCE(NULLIF(module, ''), 'system') as module_name, COUNT(*) as event_count")
            ->groupBy('module_name')
            ->orderByDesc('event_count')
            ->limit(8)
            ->get();

        $useDailyTrend = $from->diffInDays($to) <= 45;
        $activityTrend = (clone $activityQuery)->get(['created_at'])
            ->groupBy(fn (ActivityLog $activity) => $activity->created_at->format($useDailyTrend ? 'Y-m-d' : 'Y-m'))
            ->map(fn ($events, $period) => [
                'period' => $period,
                'label' => CarbonImmutable::parse($period.($useDailyTrend ? '' : '-01'))->format($useDailyTrend ? 'd M' : 'M Y'),
                'events' => $events->count(),
            ])->sortKeys()->values();

        $commercialTrend = PigSale::query()->whereNotNull('organization_id')
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->get(['sale_date', 'sale_price'])
            ->groupBy(fn ($sale) => $sale->sale_date->format($useDailyTrend ? 'Y-m-d' : 'Y-m'))
            ->map(fn ($sales, $period) => [
                'period' => $period,
                'label' => CarbonImmutable::parse($period.($useDailyTrend ? '' : '-01'))->format($useDailyTrend ? 'd M' : 'M Y'),
                'revenue' => (float) $sales->sum('sale_price'),
                'sales' => $sales->count(),
            ])->sortKeys()->values();

        $totals = [
            'organizations' => $rows->count(),
            'active_organizations' => $rows->where('active', true)->count(),
            'staff' => $rows->sum('staff'),
            'platform_users' => User::query()->where('role', 'superuser')->count(),
            'active_pigs' => $rows->sum('active_pigs'),
            'added' => $rows->sum('added'),
            'sold' => $rows->sum('sold'),
            'deaths' => $rows->sum('deaths'),
            'revenue' => $rows->sum('revenue'),
            'open_orders' => $rows->sum('open_orders'),
            'order_pipeline' => $rows->sum('order_pipeline'),
            'alerts' => $rows->sum('alerts'),
            'critical_alerts' => $rows->sum('critical_alerts'),
            'matings' => $rows->sum('matings'),
            'farrowings' => $rows->sum('farrowings'),
            'live_births' => $rows->sum('live_births'),
            'weanings' => $rows->sum('weanings'),
            'health_records' => $rows->sum('health_records'),
            'open_health_cases' => $rows->sum('open_health_cases'),
            'feed_issued_kg' => $rows->sum('feed_issued_kg'),
            'feed_cost' => $rows->sum('feed_cost'),
            'purchase_requests' => $rows->sum('purchase_requests'),
            'purchase_value' => $rows->sum('purchase_value'),
            'pending_purchases' => $rows->sum('pending_purchases'),
            'approved_purchases' => $rows->sum('approved_purchases'),
            'orders' => $rows->sum('orders'),
            'completed_orders' => $rows->sum('completed_orders'),
            'paid_orders' => $rows->sum('paid_orders'),
            'storefront_revenue' => $rows->sum('storefront_revenue'),
            'weight_records' => $rows->sum('weight_records'),
            'average_weight' => $rows->sum('weight_records') > 0
                ? round($rows->sum(fn ($row) => $row['average_weight'] * $row['weight_records']) / $rows->sum('weight_records'), 1)
                : 0,
            'audited_events' => (clone $activityQuery)->count(),
            'active_users' => (clone $activityQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'active_modules' => (clone $activityQuery)->whereNotNull('module')->where('module', '!=', '')->distinct('module')->count('module'),
            'platform_events' => (clone $activityQuery)->whereNull('organization_id')->count(),
        ];

        $portfolio = [
            'healthy' => $rows->where('status', 'Healthy')->count(),
            'watch' => $rows->where('status', 'Watch')->count(),
            'attention' => $rows->where('status', 'Needs attention')->count(),
            'inactive' => $rows->where('status', 'Inactive')->count(),
            'average_data_quality' => $rows->count() ? round($rows->avg('data_quality'), 1) : 100,
        ];

        $monthlyRevenue = PigSale::query()->whereNotNull('organization_id')
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->get(['sale_date', 'sale_price'])->groupBy(fn ($sale) => $sale->sale_date->format('Y-m'))
            ->map(fn ($sales, $month) => ['month' => $month, 'revenue' => (float) $sales->sum('sale_price'), 'sales' => $sales->count()])
            ->sortKeys()->values();

        $recentActivity = ActivityLog::query()->with('user.organization:id,name')
            ->whereBetween('created_at', [$from, $to])
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->latest()->limit(8)->get();

        return compact('from', 'to', 'rows', 'totals', 'portfolio', 'monthlyRevenue', 'recentActivity', 'activityByModule', 'activityTrend', 'commercialTrend');
    }
}
