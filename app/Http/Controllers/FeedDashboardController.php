<?php

namespace App\Http\Controllers;

use App\Models\FeedIssue;
use App\Models\FeedProductionPlan;
use App\Models\RawMaterial;
use App\Services\Feed\FeedPerformanceService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedStageResolver;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * One page for the day's feed picture: who is due what today and what has
 * been fed, finished feed on hand against the next week, ingredients short
 * for the next two weeks, and feed conversion for the last 30 days.
 */
class FeedDashboardController extends Controller
{
    public function index(FeedPlanningService $planning, FeedPerformanceService $performance)
    {
        $orgId = app(CurrentOrganization::class)->requireId();
        $today = CarbonImmutable::today();

        $todayPlan = $planning->calculate($orgId, $today->toDateString(), $today->toDateString(), applyWastage: false);
        $expected = collect($todayPlan['rows'])->keyBy('stage');
        $animals = collect($todayPlan['locations'])->countBy('stage');
        $fed = FeedIssue::where('organization_id', $orgId)->notReversed()->whereNotNull('feeding_stage')
            ->whereDate('issued_at', $today->toDateString())
            ->selectRaw('feeding_stage, sum(quantity_kg) as kg')->groupBy('feeding_stage')->pluck('kg', 'feeding_stage');

        $stages = collect(FeedStageResolver::STAGES)
            ->map(function (string $label, string $stage) use ($expected, $animals, $fed) {
                $due = isset($expected[$stage]) ? (float) $expected[$stage]['daily_kg'] : null;
                $given = (float) ($fed[$stage] ?? 0);

                return [
                    'stage' => $stage, 'label' => $label, 'animals' => (int) ($animals[$stage] ?? 0),
                    'expected_kg' => $due, 'fed_kg' => $given,
                    'status' => match (true) {
                        $given <= 0 => 'not_fed',
                        $due === null => 'fed',
                        $given < $due * 0.9 => 'under',
                        $given > $due * 1.1 => 'over',
                        default => 'fed',
                    },
                ];
            })
            ->filter(fn (array $row) => $row['animals'] > 0 || $row['fed_kg'] > 0)
            ->values();

        $week = $planning->calculate($orgId, $today->toDateString(), $today->addDays(6)->toDateString());
        $fortnight = $planning->calculate($orgId, $today->toDateString(), $today->addDays(13)->toDateString());

        return view('feeds.dashboard.index', [
            'stages'      => $stages,
            'setupIncomplete' => ! $todayPlan['demand_complete'],
            'feeds'       => $week['feeds'],
            'wastagePercent' => $week['wastage_percent'],
            'shortMaterials' => collect($fortnight['materials'])->where('shortfall_kg', '>', 0)->values(),
            'lowMaterials' => RawMaterial::where('is_active', true)->whereColumn('quantity_in_stock', '<=', 'reorder_level')->orderBy('quantity_in_stock')->get(),
            'openPlans'   => FeedProductionPlan::where('status', 'planned')->count(),
            'performance' => $performance->forPeriod($orgId, $today->subDays(29), $today),
            'showCosts'   => request()->user()->hasPermission('view_finance'),
        ]);
    }

    public function performance(Request $request, FeedPerformanceService $performance)
    {
        $orgId = app(CurrentOrganization::class)->requireId();
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
        ]);
        $to = CarbonImmutable::parse($input['to'] ?? 'today');
        $from = CarbonImmutable::parse($input['from'] ?? $to->subDays(29)->toDateString());
        abort_if($from->diffInDays($to) > 366, 422, 'Choose a period of a year or less.');

        return view('feeds.performance.index', [
            'report' => $performance->forPeriod($orgId, $from, $to),
            'showCosts' => $request->user()->hasPermission('view_finance'),
        ]);
    }
}
