<?php

namespace App\Services\Feed;

use App\Models\FeedIssue;
use App\Models\PigManagement;
use App\Models\PigWeight;
use Carbon\CarbonImmutable;

/**
 * Feed conversion per feeding stage for a period.
 *
 * Feed comes from feedings recorded against each stage. Weight gain comes from
 * consecutive weighings inside the period, each gain credited to the stage the
 * pig was in at the midpoint between the two weighings. Conversion (kg feed
 * per kg gained) is only shown for growing stages; breeding animals are not
 * expected to gain, so they show feed and cost only.
 *
 * The result is only as good as the weighing: when few of a stage's pigs were
 * weighed, coverage is reported so the figure is not mistaken for the herd's.
 */
class FeedPerformanceService
{
    public const GROWING_STAGES = ['piglet', 'weaner', 'grower', 'finisher'];

    public function __construct(private FeedStageResolver $stages)
    {
    }

    /** @return array{from: string, to: string, rows: array<string, array>, totals: array, unstaged_feed_kg: float} */
    public function forPeriod(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];
        foreach (FeedStageResolver::STAGES as $stage => $label) {
            $rows[$stage] = ['stage' => $stage, 'label' => $label, 'growing' => in_array($stage, self::GROWING_STAGES, true),
                'feed_kg' => 0.0, 'feed_cost' => 0.0, 'gain_kg' => 0.0, 'pigs_weighed' => [], 'pigs_now' => 0];
        }

        $issues = FeedIssue::where('organization_id', $organizationId)->notReversed()
            ->whereDate('issued_at', '>=', $from->toDateString())->whereDate('issued_at', '<=', $to->toDateString())
            ->get(['feeding_stage', 'quantity_kg', 'total_cost']);
        $unstaged = 0.0;
        foreach ($issues as $issue) {
            if ($issue->feeding_stage && isset($rows[$issue->feeding_stage])) {
                $rows[$issue->feeding_stage]['feed_kg'] += (float) $issue->quantity_kg;
                $rows[$issue->feeding_stage]['feed_cost'] += (float) $issue->total_cost;
            } else {
                $unstaged += (float) $issue->quantity_kg;   // older feedings recorded per group
            }
        }

        $weights = PigWeight::query()
            ->whereHas('pig', fn ($q) => $q->withTrashed()->where('organization_id', $organizationId))
            ->whereDate('weigh_date', '>=', $from->toDateString())->whereDate('weigh_date', '<=', $to->toDateString())
            ->with(['pig' => fn ($q) => $q->withTrashed()])
            ->orderBy('weigh_date')->orderBy('id')
            ->get()->groupBy('pig_id');

        foreach ($weights as $pigWeights) {
            $pig = $pigWeights->first()->pig;
            if (! $pig || $pigWeights->count() < 2) {
                continue;
            }
            for ($i = 1; $i < $pigWeights->count(); $i++) {
                [$before, $after] = [$pigWeights[$i - 1], $pigWeights[$i]];
                $midpoint = CarbonImmutable::parse($before->weigh_date)->addDays(
                    intdiv((int) CarbonImmutable::parse($before->weigh_date)->diffInDays($after->weigh_date), 2)
                );
                $stage = $this->stageOn($pig, $midpoint);
                if ($stage === null || ! $rows[$stage]['growing']) {
                    continue;
                }
                $rows[$stage]['gain_kg'] += $this->kg($after) - $this->kg($before);
                $rows[$stage]['pigs_weighed'][$pig->id] = true;
            }
        }

        foreach (PigManagement::where('organization_id', $organizationId)->active()->get() as $pig) {
            if ($stage = $this->stages->resolve($pig)) {
                $rows[$stage]['pigs_now']++;
            }
        }

        foreach ($rows as &$row) {
            $row['pigs_weighed'] = count($row['pigs_weighed']);
            $row['fcr'] = $row['growing'] && $row['gain_kg'] > 0 && $row['feed_kg'] > 0 ? round($row['feed_kg'] / $row['gain_kg'], 2) : null;
            $row['cost_per_kg_gain'] = $row['growing'] && $row['gain_kg'] > 0 && $row['feed_cost'] > 0 ? round($row['feed_cost'] / $row['gain_kg'], 2) : null;
            $row['partial'] = $row['growing'] && $row['pigs_now'] > 0 && $row['pigs_weighed'] < $row['pigs_now'];
        }
        unset($row);

        $growing = collect($rows)->where('growing', true);
        $feed = (float) $growing->sum('feed_kg');
        $gain = (float) $growing->sum('gain_kg');

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'rows' => $rows, 'unstaged_feed_kg' => round($unstaged, 2),
            'totals' => [
                'feed_kg' => round((float) collect($rows)->sum('feed_kg'), 2),
                'feed_cost' => round((float) collect($rows)->sum('feed_cost'), 2),
                'growing_fcr' => $gain > 0 && $feed > 0 ? round($feed / $gain, 2) : null,
                'growing_gain_kg' => round($gain, 2),
            ],
        ];
    }

    /** @var array<int, array> each farm's growth-stage ages, read once */
    private array $stageLimits = [];

    /** Stage on a date: growing pigs by their age then, others by today's stage. */
    private function stageOn(PigManagement $pig, CarbonImmutable $date): ?string
    {
        if ($this->stages->agesThroughStages($pig)) {
            $this->stageLimits[(int) $pig->organization_id] ??= FeedStageResolver::limits((int) $pig->organization_id);

            return FeedStageResolver::stageForAge((int) CarbonImmutable::parse($pig->date_of_birth)->diffInDays($date),
                $this->stageLimits[(int) $pig->organization_id]);
        }

        return $pig->trashed() ? null : $this->stages->resolve($pig);
    }

    private function kg(PigWeight $weight): float
    {
        $value = (float) $weight->weight;

        return in_array(strtolower((string) $weight->weight_unit), ['lb', 'lbs'], true) ? $value * 0.45359237 : $value;
    }
}
