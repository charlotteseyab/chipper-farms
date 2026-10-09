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

    public function resolve(PigManagement $pig, bool $useOverride = true): ?string
    {
        if ($useOverride) {
            $override = FeedStageOverride::where('organization_id', $pig->organization_id)
                ->where('pig_id', $pig->id)->latest('id')->first();
            if ($override?->stage) {
                return $override->stage;
            }
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

        return self::stageForAge((int) $pig->date_of_birth->diffInDays($today));
    }

    /** Growing-pig stage for an age in days (the existing thresholds). */
    public static function stageForAge(int $age): string
    {
        return match (true) {
            $age <= 56 => 'piglet', $age <= 98 => 'weaner', $age <= 154 => 'grower', default => 'finisher',
        };
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

        return ! FeedStageOverride::where('organization_id', $pig->organization_id)
            ->where('pig_id', $pig->id)->latest('id')->first()?->stage;
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
