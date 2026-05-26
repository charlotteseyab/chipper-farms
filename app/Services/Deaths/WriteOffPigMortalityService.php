<?php

namespace App\Services\Deaths;

use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\PigMortalityWriteoff;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Services\Finance\Costing\GroupCostPoolService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 — write off a pig's allocated cost basis on death.
 *
 * Computes the dead pig's share of its group's cost pool, persists a
 * pig_mortality_writeoffs row for operational audit, and records the
 * cost movement on the group pool. GL posting is done by the
 * subscriber that calls this service.
 *
 * WEIGHT FALLBACK PRECEDENCE
 *
 *   1. PigDeath::final_weight if not null and > 0 — the most accurate
 *      input (the operator recorded the pig's weight at death).
 *
 *   2. PigManagement::current_weight_kg_cache if set — the last-known
 *      weight, possibly slightly stale.
 *
 *   3. The group's average weight: pool.total_weight_kg / pool.on_hand_pig_count.
 *      Used when no per-pig data exists. Flagged on the writeoff row.
 *
 *   4. Zero — when nothing is known. The writeoff is then zero-value
 *      (audit row still written, no GL).
 *
 * IDEMPOTENCY
 *
 *   pig_mortality_writeoffs has UNIQUE(pig_death_id). The subscriber
 *   should not call this service twice for the same death; if it does,
 *   the second call observes the existing writeoff row and returns it.
 */
class WriteOffPigMortalityService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected GroupCostPoolService $poolService,
    ) {
    }

    /**
     * Compute + persist the writeoff. Returns the writeoff row.
     *
     * NULL return means: pig has no group (no pool to write off from)
     * OR the group pool is completely empty. Either way, no GL impact.
     */
    public function execute(PigDeath $death): ?PigMortalityWriteoff
    {
        $orgId = $this->currentOrganization->requireId();

        // Re-entry safety — return existing row if already processed.
        $existing = PigMortalityWriteoff::query()
            ->where('pig_death_id', $death->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $pig = PigManagement::withTrashed()->find($death->pig_id);
        if (! $pig) {
            // PigDeath cascade-deletes when the pig is hard-deleted, so
            // this shouldn't happen. Defensive.
            return null;
        }

        $groupId = (int) ($pig->group_id ?? 0);
        if ($groupId <= 0) {
            // No group → no pool → no writeoff (memo-only death).
            return null;
        }

        return DB::transaction(function () use ($death, $pig, $groupId, $orgId) {
            // Read the pool's CURRENT snapshot. We don't lock it here
            // because GroupCostPoolService takes the lock when we call
            // recordMortalityWriteoff. The snapshot read is for the
            // weight-fallback computation.
            $pool = GroupCostPool::query()
                ->where('organization_id', $orgId)
                ->where('group_id', $groupId)
                ->first();

            if (! $pool || $pool->isEmpty()) {
                // Pool empty → no value to write off.
                return null;
            }

            // Determine the pig's weight at death.
            [$pigWeight, $usedGroupAvg] = $this->resolveDeathWeight($death, $pig, $pool);

            // If we can't determine ANY weight (pool exists but
            // current_weight_kg_cache is null and pool count is zero
            // somehow), bail.
            if (bccomp($pigWeight, '0', 4) <= 0) {
                return null;
            }

            // Capture before-snapshots for the audit row.
            $valueBefore   = (string) $pool->total_carrying_value;
            $weightBefore  = (string) $pool->total_weight_kg;
            $countBefore   = (int) $pool->on_hand_pig_count;

            // Record the cost movement (this takes the row lock).
            $movement = $this->poolService->recordMortalityWriteoff(
                groupId:     $groupId,
                pigWeight:   $pigWeight,
                pigDeathId:  (int) $death->id,
                occurredAt:  CarbonImmutable::parse($death->death_date)->endOfDay(),
                reference:   sprintf('Mortality — %s (death #%d)', $pig->code ?? 'pig#'.$pig->id, $death->id),
                notes:       null,
            );

            // Persist the operational mirror.
            $writeoffAmount = (string) bcsub('0', (string) $movement->value_delta, 4);  // positive

            return PigMortalityWriteoff::create([
                'organization_id'           => $orgId,
                'pig_death_id'              => $death->id,
                'pig_id'                    => $pig->id,
                'group_id'                  => $groupId,
                'pig_weight_at_death_kg'    => $pigWeight,
                'group_weight_before_kg'    => $weightBefore,
                'group_value_before'        => $valueBefore,
                'group_pig_count_before'    => $countBefore,
                'used_group_average_weight' => $usedGroupAvg,
                'writeoff_amount'           => $writeoffAmount,
                'occurred_at'               => CarbonImmutable::parse($death->death_date)->endOfDay(),
                'group_cost_movement_id'    => $movement->id,
                'journal_batch_id'          => null,  // set after GL posts
            ]);
        });
    }

    /**
     * @return array{0: string, 1: bool}  [weight_kg as bcmath string, used_group_average]
     */
    protected function resolveDeathWeight(PigDeath $death, PigManagement $pig, GroupCostPool $pool): array
    {
        // 1. final_weight from death record
        $w = $death->final_weight !== null ? (float) $death->final_weight : 0.0;
        if ($w > 0) {
            return [number_format($w, 4, '.', ''), false];
        }

        // 2. current_weight_kg_cache on the pig
        $w = $pig->current_weight_kg_cache !== null ? (float) $pig->current_weight_kg_cache : 0.0;
        if ($w > 0) {
            return [number_format($w, 4, '.', ''), false];
        }

        // 3. group average
        if ((int) $pool->on_hand_pig_count > 0) {
            $avg = bcdiv(
                (string) $pool->total_weight_kg,
                (string) $pool->on_hand_pig_count,
                4,
            );
            return [$avg, true];
        }

        // 4. zero
        return ['0', false];
    }
}
