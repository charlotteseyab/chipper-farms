<?php

namespace App\Services\Feed;

use App\Models\FeedBatch;
use App\Models\FeedIssue;
use App\Models\FeedProductionPlan;
use App\Models\Setting;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Support\Qty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The at-a-glance cards at the top of the feed pages that had none:
 * Feedings, Planned vs actual, Mix & batches and Stock counts. Built from the
 * same figures as the lists below them. Cards: label, value, hint, url, tone.
 */
class FeedGlanceService
{
    public function __construct(protected FeedPlanningService $planning)
    {
    }

    public function feedings(int $orgId): array
    {
        $today = CarbonImmutable::today();
        $issues = FeedIssue::where('organization_id', $orgId);
        $fedToday = (clone $issues)->notReversed()->whereDate('issued_at', $today->toDateString());
        $due = collect($this->planning->calculate($orgId, $today->toDateString(), $today->toDateString(), applyWastage: false)['rows'])
            ->filter(fn ($row) => (float) $row['daily_kg'] > 0);
        $stagesDue = $due->pluck('stage')->unique();
        $stagesFed = (clone $fedToday)->whereNotNull('feeding_stage')->pluck('feeding_stage')->unique();
        $notFed = $stagesDue->diff($stagesFed)->count();
        $week = (clone $issues)->notReversed()->where('issued_at', '>=', $today->subDays(6));
        $reversed = (clone $issues)->whereNotNull('reversed_at')->where('reversed_at', '>=', $today->subDays(6))->count();

        return [
            $this->card('Fed today', Qty::kg((clone $fedToday)->sum('quantity_kg')).' kg', 'ration '.Qty::kg($due->sum('daily_kg')).' kg', route('feed.issues.index', ['date' => $today->toDateString()])),
            $this->card('Stages still to feed', $notFed, $stagesDue->count() - $notFed.' of '.$stagesDue->count().' recorded today',
                $notFed ? route('feed.issues.create') : null, $notFed ? 'warn' : 'good'),
            $this->card('Last 7 days', Qty::kg((clone $week)->sum('quantity_kg')).' kg', (clone $week)->count().' feedings'),
            $this->card('Reversed, 7 days', $reversed, 'feedings corrected', null, $reversed ? 'warn' : 'neutral'),
        ];
    }

    /** @param  Collection<int, array>  $rows  every planned-vs-actual row for the period, before paging */
    public function variance(Collection $rows): array
    {
        // Only days with a ration can be compared; all feed given is still shown.
        $known = $rows->filter(fn ($row) => (float) ($row['planned_kg'] ?? 0) > 0);
        $planned = (float) $known->sum('planned_kg');
        $comparable = (float) $known->sum('actual_kg');
        $given = (float) $rows->sum('actual_kg');
        $threshold = Setting::get('feed.alerts.variance_percent');
        $flagged = is_numeric($threshold)
            ? $rows->filter(fn ($row) => $row['variance_percent'] !== null && abs($row['variance_percent']) >= (float) $threshold)->count()
            : null;
        $difference = $comparable - $planned;
        $unmatched = $rows->count() - $known->count();

        return [
            $this->card('Ration for the period', Qty::kg($planned).' kg', $known->count().' feeding day'.($known->count() === 1 ? '' : 's').' with a ration'),
            $this->card('Feed given', Qty::kg($given).' kg', $unmatched ? Qty::kg($given - $comparable).' kg had no ration to compare' : 'all compared with a ration'),
            $this->card('Difference', ($difference > 0 ? '+' : '').Qty::kg($difference).' kg', $planned > 0 ? ($difference > 0 ? '+' : '').Qty::kg($difference / $planned * 100, 1).'% of ration, where compared' : null,
                null, $planned > 0 && abs($difference / $planned) >= 0.1 ? 'warn' : 'neutral'),
            $this->card('Need review', $flagged ?? '—', $flagged === null ? 'set a variance alert to flag these' : 'over '.Qty::kg($threshold).'% from ration',
                null, $flagged ? 'warn' : 'neutral'),
        ];
    }

    public function production(int $orgId): array
    {
        $today = CarbonImmutable::today();
        $waiting = FeedProductionPlan::where('status', 'planned')->count();
        $month = FeedBatch::whereNull('reversed_at')->whereDate('batch_date', '>=', $today->startOfMonth()->toDateString());
        $lowest = collect($this->planning->calculate($orgId, $today->toDateString(), $today->addDays(6)->toDateString())['feeds'])
            ->filter(fn ($feed) => $feed['days_cover'] !== null)->sortBy('days_cover')->first();
        $low = (float) FeedReminderService::setting('cover_days');
        $urgent = min((float) FeedReminderService::setting('cover_urgent_days'), $low);

        return [
            $this->card('Plans to confirm', $waiting, $waiting ? 'waiting to be mixed' : 'nothing waiting', null, $waiting ? 'warn' : 'good'),
            $this->card('Batches this month', (clone $month)->count(), $today->format('F')),
            $this->card('Feed mixed this month', Qty::kg((clone $month)->sum('batch_size_kg')).' kg', 'not counting reversed batches'),
            $this->card('Lowest feed in stock', $lowest ? Qty::kg($lowest['days_cover'], 1).' days' : '—', $lowest['name'] ?? 'no feed with a ration',
                route('mill.plan'), ! $lowest ? 'neutral' : ($lowest['days_cover'] < $urgent ? 'alert' : ($lowest['days_cover'] < $low ? 'warn' : 'good'))),
        ];
    }

    public function stockCounts(): array
    {
        $last = StockCount::latest('counted_at')->first();
        $today = CarbonImmutable::today();
        $days = $last ? (int) CarbonImmutable::parse($last->counted_at)->startOfDay()->diffInDays($today) : null;
        $differences = $last ? StockCountLine::where('stock_count_id', $last->id)->where('difference_qty', '!=', 0)->count() : 0;

        return [
            $this->card('Last count', $last ? $last->counted_at->format('j M Y') : 'Never', $last ? ($days === 0 ? 'today' : $days.' day'.($days === 1 ? '' : 's').' ago') : 'count the store to start',
                $last ? route('stock-counts.show', $last) : null, $last === null || $days > 31 ? 'warn' : 'good'),
            $this->card('Differences found', $differences, $last ? 'items that did not match in the last count' : null, null, $differences ? 'warn' : 'neutral'),
            $this->card('Counts this month', StockCount::where('counted_at', '>=', $today->startOfMonth())->count(), $today->format('F')),
            $this->card('Counts this year', StockCount::where('counted_at', '>=', $today->startOfYear())->count(), $today->format('Y')),
        ];
    }

    private function card(string $label, mixed $value, ?string $hint = null, ?string $url = null, string $tone = 'neutral'): array
    {
        return ['label' => $label, 'value' => (string) $value, 'hint' => $hint, 'url' => $url, 'tone' => $tone];
    }
}
