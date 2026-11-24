<?php

namespace App\Services\Feed;

use App\Models\FeedIssue;
use App\Models\FeedProductionPlan;
use App\Models\RawMaterial;
use App\Support\Qty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The day's feed picture, for the Today page and the read-only overview:
 * each stage's ration against what has been fed, and what needs someone to
 * act, most urgent first, each with where to go.
 *
 * Uses the same thresholds as the feed reminders, so the page and the bell
 * agree.
 */
class FeedTodayService
{
    public function __construct(private FeedPlanningService $planning) {}

    /**
     * Each feeding stage with animals today: its ration and what has been fed.
     * The cheap part of summary(), for dashboards.
     *
     * @return Collection<int, array{stage: string, label: string, animals: int, expected_kg: ?float, fed_kg: float, status: string}>
     */
    public function stagesToday(int $orgId): Collection
    {
        return $this->today($orgId)[0];
    }

    /** @return array{0: Collection, 1: array} today's stages and today's plan */
    private function today(int $orgId): array
    {
        $today = CarbonImmutable::today();

        $todayPlan = $this->planning->calculate($orgId, $today->toDateString(), $today->toDateString(), applyWastage: false);
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

        return [$stages, $todayPlan];
    }

    /** @return array{stages: Collection, needs: list<array>, week: array, fortnight: array, lowMaterials: Collection, openPlans: int, setupIncomplete: bool} */
    public function summary(int $orgId): array
    {
        $today = CarbonImmutable::today();
        [$stages, $todayPlan] = $this->today($orgId);

        $week = $this->planning->calculate($orgId, $today->toDateString(), $today->addDays(6)->toDateString());
        $fortnight = $this->planning->calculate($orgId, $today->toDateString(), $today->addDays(13)->toDateString());
        $lowMaterials = RawMaterial::where('is_active', true)->where('reorder_level', '>', 0)->whereColumn('quantity_in_stock', '<=', 'reorder_level')->orderBy('quantity_in_stock')->get();
        $openPlans = FeedProductionPlan::where('status', 'planned')->count();
        $setupIncomplete = ! $todayPlan['demand_complete'];

        return [
            'stages' => $stages,
            'needs' => $this->needs($stages, collect($week['feeds']), $lowMaterials, collect($week['materials'])->where('shortfall_kg', '>', 0), $openPlans, $setupIncomplete),
            'week' => $week, 'fortnight' => $fortnight, 'lowMaterials' => $lowMaterials, 'openPlans' => $openPlans, 'setupIncomplete' => $setupIncomplete,
        ];
    }

    /** @return list<array{title: string, detail: string, url: string, action: string, tone: string}> */
    private function needs(Collection $stages, Collection $feeds, Collection $lowMaterials, Collection $shortMaterials, int $openPlans, bool $setupIncomplete): array
    {
        $needs = [];
        $names = fn (Collection $items, string $key = 'label') => $items->pluck($key)->take(6)->implode(', ').($items->count() > 6 ? ' and '.($items->count() - 6).' more' : '');
        $kg = fn ($v) => Qty::kg($v, 1);

        $low = (float) FeedReminderService::setting('cover_days');
        $urgent = min((float) FeedReminderService::setting('cover_urgent_days'), $low);
        $critical = $feeds->filter(fn ($f) => $f['days_cover'] !== null && $f['days_cover'] < $urgent);
        $running = $feeds->filter(fn ($f) => $f['days_cover'] !== null && $f['days_cover'] >= $urgent && $f['days_cover'] < $low);
        if ($critical->isNotEmpty()) {
            $needs[] = ['title' => 'Mix now: '.$critical->map(fn ($f) => $f['name'].' ('.$kg($f['days_cover']).' days left)')->implode(', '),
                'detail' => 'The feed will run out before the next mix otherwise.', 'url' => route('feed.mix-buy'), 'action' => 'Mix & buy', 'tone' => 'alert'];
        }

        $due = $stages->filter(fn ($row) => $row['expected_kg'] !== null && $row['expected_kg'] > 0);
        $notFed = $due->where('status', 'not_fed');
        if ($notFed->isNotEmpty()) {
            $late = now()->format('H:i') >= (string) FeedReminderService::setting('feeding_due_by');
            $needs[] = ['title' => $notFed->count().' '.Str::plural('stage', $notFed->count()).' not fed yet: '.$names($notFed),
                'detail' => $late ? 'Feeding is normally recorded by '.FeedReminderService::setting('feeding_due_by').'.' : 'Record each stage as it is fed.',
                'url' => route('feed.issues.create').'#feeding', 'action' => 'Record feeding', 'tone' => $late ? 'alert' : 'warn'];
        }
        $off = $due->whereIn('status', ['under', 'over']);
        if ($off->isNotEmpty()) {
            $needs[] = ['title' => 'Fed far from the ration: '.$off->map(fn ($row) => $row['label'].' ('.$kg($row['fed_kg']).' of '.$kg($row['expected_kg']).' kg)')->implode(', '),
                'detail' => 'More than 10% over or under. Check for a typing slip, or top up the feeding.',
                'url' => route('feed.planned-vs-actual', ['period' => 'today']), 'action' => 'Check', 'tone' => 'warn'];
        }

        if ($shortMaterials->isNotEmpty()) {
            $needs[] = ['title' => 'Not enough ingredients to mix the next 7 days of feed',
                'detail' => $shortMaterials->take(5)->map(fn ($m) => $m['name'].' short '.$kg($m['shortfall_kg']).' kg')->implode(', '),
                'url' => route('feed.mix-buy'), 'action' => 'Buy', 'tone' => 'alert'];
        }
        if ($openPlans > 0) {
            $needs[] = ['title' => $openPlans.' planned '.Str::plural('mix', $openPlans).' still to confirm',
                'detail' => 'Confirm each one once it is mixed, or cancel it.', 'url' => route('feed-production.index'), 'action' => 'Open mixes', 'tone' => 'warn'];
        }
        if ($running->isNotEmpty()) {
            $needs[] = ['title' => 'Running low: '.$running->map(fn ($f) => $f['name'].' ('.$kg($f['days_cover']).' days left)')->implode(', '),
                'detail' => 'Mix again soon, and check the ingredients are in.', 'url' => route('feed.mix-buy'), 'action' => 'Mix & buy', 'tone' => 'warn'];
        }
        if ($lowMaterials->isNotEmpty()) {
            $out = $lowMaterials->filter(fn ($m) => (float) $m->quantity_in_stock <= 0)->count();
            $needs[] = ['title' => $lowMaterials->count().' '.Str::plural('ingredient', $lowMaterials->count()).' low'.($out ? ', '.$out.' out' : '').': '.$names($lowMaterials, 'name'),
                'detail' => 'At or below their reorder level.', 'url' => route('raw-materials.index'),
                'action' => 'Stock', 'tone' => $out ? 'alert' : 'warn'];
        }
        if ($setupIncomplete) {
            $needs[] = ['title' => 'Some stages have animals but no daily ration', 'detail' => 'Their expected amounts are missing until a ration is set.',
                'url' => route('feed-setup.index').'#step-standards', 'action' => 'Finish setup', 'tone' => 'warn'];
        }

        return $needs;
    }
}
