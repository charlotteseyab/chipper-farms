<?php

namespace App\Services\Finance\BiologicalAssets;

use App\Models\Finance\Account;
use App\Models\Group;
use App\Models\PigManagement;
use App\Finance\Resolvers\FinanceAccountResolver;
use Illuminate\Support\Collection;

/**
 * Resolves the biological-asset stage (and its GL account) for a pig or a
 * group, from configuration in config/biological_assets.php.
 *
 * Two resolution entry points:
 *
 *   forPig(PigManagement): stage of a single animal. Breeding animals
 *     (stock_type = 'breeding') resolve by gender to sow/boar; non-breeding
 *     resolve by age through the fattening path piglet -> finisher.
 *
 *   forGroup(Group|int): stage of a whole cost-pool group. Because cost
 *     pools are per-group and a group is normally a managed cohort, we
 *     resolve the group's representative stage:
 *       - if ANY live pig in the group is breeding stock -> breeding path
 *         (sow if any females, else boar, else breeding_stock);
 *       - otherwise fattening path by the MEDIAN age of live pigs.
 *     An empty/unknown group falls back to the configured fallback stage.
 *
 * The resolver never throws on dirty data: missing age or empty group
 * yields the fallback stage so a single bad record can't block posting.
 * The reconciliation report surfaces anything parked on the fallback.
 */
class BiologicalAssetStageResolver
{
    /** @var array<string, array<string, mixed>> */
    protected array $stages;

    public function __construct(
        protected FinanceAccountResolver $accountResolver,
    ) {
        $this->stages = (array) config('biological_assets.stages', []);
    }

    /**
     * Resolve a pig's stage key (e.g. 'grower', 'sow').
     */
    public function stageForPig(PigManagement $pig): string
    {
        if (($pig->stock_type ?? 'standard') === 'breeding') {
            return $this->breedingStageForGender($pig->gender);
        }

        $ageDays = (int) ($pig->current_age_in_days ?? 0);
        return $this->fatteningStageForAge($ageDays);
    }

    /**
     * Resolve a group's representative stage key.
     *
     * @param  Group|int  $group
     */
    public function stageForGroup(Group|int $group): string
    {
        $groupId = $group instanceof Group ? $group->id : (int) $group;

        /** @var Collection<int, PigManagement> $pigs */
        $pigs = PigManagement::query()
            ->where('group_id', $groupId)
            ->get(['id', 'stock_type', 'gender', 'date_of_birth']);

        if ($pigs->isEmpty()) {
            return (string) config('biological_assets.fallback_stage', 'grower');
        }

        // Any breeding animal -> breeding path.
        $breeders = $pigs->filter(fn (PigManagement $p) => ($p->stock_type ?? 'standard') === 'breeding');
        if ($breeders->isNotEmpty()) {
            $hasFemale = $breeders->contains(fn (PigManagement $p) => $this->isFemale($p->gender));
            $hasMale   = $breeders->contains(fn (PigManagement $p) => $this->isMale($p->gender));

            if ($hasFemale && ! $hasMale) {
                return 'sow';
            }
            if ($hasMale && ! $hasFemale) {
                return 'boar';
            }
            return 'breeding_stock'; // mixed or unknown gender
        }

        // Fattening path by median age.
        $ages = $pigs
            ->map(fn (PigManagement $p) => $p->current_age_in_days ?? 0)
            ->filter(fn ($a) => $a > 0)
            ->sort()
            ->values();

        if ($ages->isEmpty()) {
            return (string) config('biological_assets.fallback_stage', 'grower');
        }

        $medianAge = (int) $ages->get((int) floor(($ages->count() - 1) / 2));
        return $this->fatteningStageForAge($medianAge);
    }

    /**
     * The GL Account for a stage key.
     */
    public function accountForStage(string $stageKey): Account
    {
        $code = $this->stages[$stageKey]['account_code']
            ?? throw new \InvalidArgumentException("Unknown biological-asset stage '{$stageKey}'.");

        return $this->accountResolver->byCode($code);
    }

    /**
     * Convenience: GL account for a group in one call.
     *
     * @param  Group|int  $group
     */
    public function accountForGroup(Group|int $group): Account
    {
        return $this->accountForStage($this->stageForGroup($group));
    }

    /**
     * The clearing account (1230) kept transitional in Phase 7.
     */
    public function clearingAccount(): Account
    {
        return $this->accountResolver->byCode(
            (string) config('biological_assets.clearing_account_code', '1230')
        );
    }

    /**
     * Stage metadata (label, classification, etc.) for display/reports.
     *
     * @return array<string, mixed>
     */
    public function stageMeta(string $stageKey): array
    {
        return $this->stages[$stageKey] ?? [];
    }

    /**
     * All stage keys in config order.
     *
     * @return array<int, string>
     */
    public function allStageKeys(): array
    {
        return array_keys($this->stages);
    }

    // ----------------------------------------------------------------

    protected function fatteningStageForAge(int $ageDays): string
    {
        $chosen = null;
        foreach ($this->stages as $key => $def) {
            if (($def['path'] ?? null) !== 'fattening') {
                continue;
            }
            $min = $def['min_age_days'];
            if ($min === null) {
                continue;
            }
            if ($ageDays >= (int) $min) {
                $chosen = $key; // ascending config order -> last match wins
            }
        }

        return $chosen ?? (string) config('biological_assets.fallback_stage', 'grower');
    }

    protected function breedingStageForGender(?string $gender): string
    {
        if ($this->isFemale($gender)) {
            return 'sow';
        }
        if ($this->isMale($gender)) {
            return 'boar';
        }
        return 'breeding_stock';
    }

    protected function isFemale(?string $gender): bool
    {
        $g = strtolower((string) $gender);
        return in_array($g, ['female', 'f', 'sow', 'gilt'], true);
    }

    protected function isMale(?string $gender): bool
    {
        $g = strtolower((string) $gender);
        return in_array($g, ['male', 'm', 'boar'], true);
    }
}
