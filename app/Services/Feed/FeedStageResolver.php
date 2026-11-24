<?php

namespace App\Services\Feed;

use App\Models\FeedStageOverride;
use App\Models\Group;
use App\Models\PigManagement;
use App\Models\Weaning;
use Carbon\CarbonImmutable;

class FeedStageResolver
{
    public const STAGES = [
        'piglet' => 'Piglet', 'weaner' => 'Weaner', 'grower' => 'Grower',
        'finisher' => 'Finisher', 'gestating_sow' => 'Gestating Sow',
        'lactating_sow' => 'Lactating Sow', 'boar' => 'Boar', 'other_sows' => 'Other Sows',
    ];

    /** Used when the farm has not set its own growth-stage ages. */
    public const DEFAULT_LIMITS = ['piglet' => 56, 'weaner' => 98, 'grower' => 154];

    /** @var array<int, array> limits already read this request, by farm */
    private array $limitsByFarm = [];

    /** @var array<int, array<int, ?string>> each farm's latest override per pig, read once */
    private array $overridesByFarm = [];

    /**
     * The pig's manual stage override, if any. A farm's overrides are read in
     * one query and kept for the request (the resolver is request-scoped):
     * pages resolve every pig's stage several times, and a query per pig per
     * call made the feed pages grow by thousands of queries with the herd.
     */
    public function overrideFor(PigManagement $pig): ?string
    {
        $orgId = (int) $pig->organization_id;
        $this->overridesByFarm[$orgId] ??= FeedStageOverride::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->orderBy('id')
            ->get(['pig_id', 'stage'])
            ->pluck('stage', 'pig_id') // ordered by id: the latest override wins
            ->all();

        return $this->overridesByFarm[$orgId][$pig->id] ?? null;
    }

    /** Forget cached overrides after one is saved in this request. */
    public function forgetOverrides(): void
    {
        $this->overridesByFarm = [];
    }

    /**
     * The last day of age in each growing stage, for a farm.
     *
     * The same growth-stage ages the farm sets in Settings → Setup (and the
     * dashboard counts by), so feed and the dashboard never disagree about who
     * is a grower. Used only when all three are set and increase; otherwise
     * the defaults. A pig is in a stage up to and including its last day.
     *
     * @return array{piglet: int, weaner: int, grower: int, farm_set: bool}
     */
    public static function limits(?int $organizationId): array
    {
        $read = fn (string $stage) => $organizationId === null ? null
            : \App\Models\Setting::getForOrganization($organizationId, "stages.{$stage}.max_days");
        $farm = ['piglet' => $read('piglet'), 'weaner' => $read('weaner'), 'grower' => $read('grower')];
        $valid = collect($farm)->every(fn ($v) => is_numeric($v) && (int) $v > 0)
            && (int) $farm['piglet'] < (int) $farm['weaner'] && (int) $farm['weaner'] < (int) $farm['grower'];

        return $valid
            ? ['piglet' => (int) $farm['piglet'], 'weaner' => (int) $farm['weaner'], 'grower' => (int) $farm['grower'], 'farm_set' => true]
            : self::DEFAULT_LIMITS + ['farm_set' => false];
    }

    public function resolve(PigManagement $pig, bool $useOverride = true): ?string
    {
        if ($useOverride && ($override = $this->overrideFor($pig))) {
            return $override;
        }
        $today = CarbonImmutable::today();
        if ($pig->stock_type === 'breeding') {
            if (in_array(strtoupper((string) $pig->gender), ['M', 'MALE'], true)) {
                return 'boar';
            }
            if (! in_array(strtoupper((string) $pig->gender), ['F', 'FEMALE'], true)) {
                return null;
            }
            $farrowing = $pig->farrowingsAsSow()->whereDate('farrowing_date', '<=', $today)->latest('farrowing_date')->first();
            if ($farrowing && $farrowing->status === 'active' && ! $farrowing->date_weaned
                && ! Weaning::where('farrowing_id', $farrowing->id)->exists()) {
                return 'lactating_sow';
            }
            $mating = $pig->femaleMatings()->where('status', 'completed')->whereDate('actual_date', '<=', $today)->latest('actual_date')->latest('id')->first();
            if ($mating?->pregnancy_status === 'pregnant'
                && (! $farrowing || $mating->actual_date->gt($farrowing->farrowing_date))) {
                return 'gestating_sow';
            }

            return 'other_sows';
        }
        if (! $pig->date_of_birth || $pig->date_of_birth->isFuture()) {
            return null;
        }

        $orgId = (int) $pig->organization_id;
        $this->limitsByFarm[$orgId] ??= self::limits($orgId);

        return self::stageForAge((int) $pig->date_of_birth->diffInDays($today), $this->limitsByFarm[$orgId]);
    }

    /**
     * Growing-pig stage for an age in days. Pass the farm's limits()
     * (callers working through many pigs read them once); without them the
     * defaults apply.
     */
    public static function stageForAge(int $age, ?array $limits = null): string
    {
        $limits ??= self::DEFAULT_LIMITS;

        return match (true) {
            $age <= $limits['piglet'] => 'piglet', $age <= $limits['weaner'] => 'weaner', $age <= $limits['grower'] => 'grower', default => 'finisher',
        };
    }

    /** Days from farrowing to weaning, from the breeding settings. */
    public static function weaningDays(): int
    {
        return max(1, (int) config('breeding.cycle.birth_to_weaning_days', 45));
    }

    /**
     * How a pig's stage will change over a forecast.
     *
     *   ['born' => date]        growing pig: its stage follows its age
     *   ['timeline' => [...]]   breeding sow: [from date, stage] steps
     *   ['stage' => stage]      stays as it is today
     *
     * A sow confirmed pregnant becomes lactating on her expected farrowing
     * date and goes back to "other sows" once the litter reaches weaning age;
     * a lactating sow does the same from her farrowing. Today always keeps
     * the stage her records give: when a farrowing or weaning is overdue (the
     * records have not caught up), the change is taken from tomorrow. Boars,
     * other sows and manual overrides stay as they are — matings cannot be
     * forecast.
     */
    public function forecastProfile(PigManagement $pig, string $todayStage): array
    {
        if ($this->agesThroughStages($pig)) {
            return ['born' => CarbonImmutable::parse($pig->date_of_birth)->startOfDay()];
        }
        $overridden = $this->overrideFor($pig);
        if ($overridden || ! in_array($todayStage, ['gestating_sow', 'lactating_sow'], true)) {
            return ['stage' => $todayStage];
        }

        $today = CarbonImmutable::today();
        $tomorrow = $today->addDay();
        $later = fn (CarbonImmutable $date) => $date->gt($today) ? $date : $tomorrow;

        if ($todayStage === 'lactating_sow') {
            $farrowing = $pig->farrowingsAsSow()->whereDate('farrowing_date', '<=', $today)->latest('farrowing_date')->first();
            if (! $farrowing) {
                return ['stage' => $todayStage];
            }
            $weaned = $later(CarbonImmutable::parse($farrowing->farrowing_date)->addDays(self::weaningDays()));

            return ['timeline' => [[$today, 'lactating_sow'], [$weaned, 'other_sows']], 'overdue' => $weaned->equalTo($tomorrow)];
        }

        $mating = $pig->femaleMatings()->where('status', 'completed')->where('pregnancy_status', 'pregnant')
            ->whereNotNull('expected_farrowing_date')->latest('actual_date')->latest('id')->first();
        if (! $mating) {
            return ['stage' => $todayStage];
        }
        $farrows = $later(CarbonImmutable::parse($mating->expected_farrowing_date)->startOfDay());

        return ['timeline' => [[$today, 'gestating_sow'], [$farrows, 'lactating_sow'], [$farrows->addDays(self::weaningDays()), 'other_sows']]];
    }

    /** The stage a forecastProfile() gives on a date. */
    public static function stageOn(array $profile, CarbonImmutable $date, array $limits): string
    {
        if (isset($profile['born'])) {
            return self::stageForAge((int) $profile['born']->diffInDays($date), $limits);
        }
        if (isset($profile['timeline'])) {
            $stage = $profile['timeline'][0][1];
            foreach ($profile['timeline'] as [$from, $step]) {
                if ($date->gte($from)) {
                    $stage = $step;
                }
            }

            return $stage;
        }

        return $profile['stage'];
    }

    /**
     * Whether this pig's stage follows its age, so forecasts can move it up
     * stages over time. Breeding animals and manual overrides stay put: their
     * stage depends on events (mating, farrowing) that cannot be forecast.
     */
    public function agesThroughStages(PigManagement $pig): bool
    {
        if ($pig->stock_type === 'breeding' || ! $pig->date_of_birth) {
            return false;
        }

        return ! $this->overrideFor($pig);
    }

    /** Biological-asset stage that a feeding stage's feed is capitalised into. */
    public static function bioStage(string $stage): string
    {
        return match ($stage) {
            'gestating_sow', 'lactating_sow', 'other_sows' => 'sow',
            default => $stage,
        };
    }

    /**
     * The system group that carries feed cost for a feeding stage. Created on
     * first use; the caller must hold the organization lock.
     */
    public static function groupFor(int $organizationId, string $stage): Group
    {
        if (! isset(self::STAGES[$stage])) {
            throw new \InvalidArgumentException("Unknown feeding stage '{$stage}'.");
        }

        return Group::withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $organizationId, 'feeding_stage' => $stage],
            ['name' => self::STAGES[$stage].' (feeding stage)', 'type' => 'stage',
                'description' => 'System group that carries feed issued to this stage.'],
        );
    }

    public static function basis(string $stage): string
    {
        return in_array($stage, ['piglet', 'weaner', 'grower', 'finisher'], true) ? 'existing_age_rules' : 'lifecycle';
    }
}
