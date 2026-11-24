<?php

namespace App\Http\Controllers;

use App\Models\FeedIssue;
use App\Models\FeedProductionPlan;
use App\Models\RawMaterial;
use App\Services\Feed\FeedPerformanceService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedStageResolver;
use App\Services\Feed\FeedTodayService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use App\Services\Feed\FeedReminderService;
use App\Support\Qty;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * One page for the day's feed picture: who is due what today and what has
 * been fed, finished feed on hand against the next week, ingredients short
 * for the next two weeks, and feed conversion for the last 30 days.
 */
class FeedDashboardController extends Controller
{
    /**
     * The read-only day's picture, for people who review feed but do not
     * record it. Anyone who records feedings works from Today (the feeding
     * form), which carries the same "Needs doing" list.
     */
    public function index(FeedTodayService $today, FeedPerformanceService $performance)
    {
        if (request()->user()->hasPermission('manage_feed_issues')) {
            return redirect()->route('feed.issues.create');
        }

        $orgId = app(CurrentOrganization::class)->requireId();
        $summary = $today->summary($orgId);
        $week = $summary['week'];
        $date = CarbonImmutable::today();

        return view('feeds.dashboard.index', [
            'needs'       => $summary['needs'],
            'stages'      => $summary['stages'],
            'setupIncomplete' => $summary['setupIncomplete'],
            'feeds'       => $week['feeds'],
            'wastagePercent' => $week['wastage_kg'] > 0 ? (collect($week['feeds'])->first()['wastage_percent'] ?? $week['wastage_percent']) : 0,
            'wastageVaries' => $week['wastage_varies'] && $week['wastage_kg'] > 0,
            'shortMaterials' => collect($summary['fortnight']['materials'])->where('shortfall_kg', '>', 0)->values(),
            'lowMaterials' => $summary['lowMaterials'],
            'openPlans'   => $summary['openPlans'],
            'performance' => $performance->forPeriod($orgId, $date->subDays(29), $date),
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
