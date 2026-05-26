<?php

namespace App\Services\Finance\Costing;

use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\GroupOpeningPoolImport;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Phase 5 — Group cost pool engine.
 *
 * The pig-side counterpart to Phase 4's InventoryService. Maintains
 * per-group running totals of pig count, carrying value, and weight.
 *
 * RESPONSIBILITIES
 *
 *   1. Persist exactly one group_cost_movements row per business event.
 *   2. Update the group_cost_pools snapshot atomically with the movement.
 *   3. Enforce the allocation invariants (see RULES below).
 *   4. Be idempotent on (source_type, source_id) via DB UNIQUE +
 *      typed DuplicateGroupCostMovementException.
 *
 * NOT RESPONSIBILITIES
 *
 *   - Posting to GL. Subscribers do that, reading from the movement
 *     row (which carries the journal_batch_id forward-link once posted).
 *   - Computing pig-side weight changes from pig_weights — callers
 *     pass the relevant weight in.
 *   - Reading the source row (pig_sale, pig_death, etc.). Callers pass
 *     the pre-resolved inputs.
 *
 * RULES (per-kg basis allocation)
 *
 *   The pool tracks three running quantities: pig_count, weight, value.
 *
 *   feed_issue(qty_kg=Q, total_cost=C, weight_added=W=0):
 *     The pool gains C in value but NO weight (feed itself doesn't
 *     have a body weight; only pigs do). The feed cost will be
 *     allocated to pigs proportional to their weight at sale time.
 *       value_after  = value_before + C
 *       weight_after = weight_before   (unchanged)
 *       count_after  = count_before    (unchanged)
 *
 *   sale_cogs(pig_weight=W):
 *       per_kg_basis = value_before / weight_before  (if weight_before > 0)
 *       cogs_amount  = W × per_kg_basis
 *       value_after  = value_before  - cogs_amount
 *       weight_after = weight_before - W
 *       count_after  = count_before  - 1
 *
 *   mortality_writeoff(pig_weight=W or group_avg):
 *       Identical math to sale_cogs. Different GL destination.
 *
 *   transfer_out(pig_weight=W):
 *       Identical math to sale_cogs. The withdrawn value is the
 *       "transferred_value" that the destination pool gains.
 *
 *   transfer_in(pig_weight=W, value=V):
 *       value_after  = value_before  + V
 *       weight_after = weight_before + W
 *       count_after  = count_before  + 1
 *
 *   opening(count=N, weight=W, value=V):
 *       The pool is opened with declared snapshot. Refused if the pool
 *       already has any movement (use adjustment for corrections).
 *
 * EDGE CASES
 *
 *   - Sale from empty pool (weight_before = 0):
 *     per_kg_basis is 0. cogs_amount is 0. The COGS subscriber writes
 *     cogs_amount=0 to pig_sales and posts cogs_posting_status='skipped'
 *     (because a 0-value journal isn't useful). Operationally this
 *     means: pig was sold but no cost was ever attributed to its group,
 *     which is itself a finding the audit report should surface.
 *
 *   - Sale of more weight than the pool has: refused unless
 *     allow_overdraw=true. The math would produce a negative pool,
 *     which silently corrupts subsequent allocations.
 *
 *   - Concurrent writes to the same pool: serialised by
 *     SELECT...FOR UPDATE on the group_cost_pools row. Phase 4 pattern.
 *
 * PRECISION
 *
 *   Same as Phase 4: bcmath strings at scale 6 for ratios, scale 4 for
 *   stored quantity/value, scale 0 for pig counts. Tiny residual clamp
 *   (CLAMP_THRESHOLD = '0.0001') protects against float-induced dust.
 *
 * TRANSACTIONS
 *
 *   The service does NOT open its own transaction. Callers wrap their
 *   full business sequence (e.g. "create feed_issue row + record raw
 *   feed usage + record pool feed_issue + post GL") in one transaction.
 */
class GroupCostPoolService
{
    public const SCALE_RATIO    = 6;
    public const SCALE_QTY      = 4;
    public const CLAMP_THRESHOLD = '0.0001';

    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    // ================================================================
    // Public API — one method per event class
    // ================================================================

    public function recordFeedIssue(
        int $groupId,
        string $totalCost,
        int $feedIssueId,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): GroupCostMovement {
        $this->assertNonNegativeString('total_cost', $totalCost);

        return $this->commit(
            groupId:     $groupId,
            eventType:   GroupCostMovement::EVENT_FEED_ISSUE,
            sourceType:  GroupCostMovement::SOURCE_FEED_ISSUE,
            sourceId:    $feedIssueId,
            occurredAt:  $occurredAt ?? CarbonImmutable::now(),
            actorUserId: $actorUserId,
            reference:   $reference,
            notes:       $notes,
            eventPigWeight: null,
            openingImportId: null,
            compute: function (array $before) use ($totalCost) {
                // Feed brings value but no weight/count.
                $newValue = bcadd($before['value'], $totalCost, self::SCALE_QTY);

                $perKg = $this->perKgBasis($before['value'], $before['weight']);

                return [
                    'count_delta'         => 0,
                    'value_delta'         => $totalCost,
                    'weight_delta'        => '0',
                    'count_after'         => (int) $before['count'],
                    'value_after'         => $newValue,
                    'weight_after'        => $before['weight'],
                    'event_per_kg_basis'  => $perKg,
                ];
            },
        );
    }

    /**
     * Record a sale COGS allocation. Returns the movement; the COGS
     * amount is on $movement->value_delta (negative).
     *
     * @param  bool  $allowOverdraw  When true, allow the math even if the
     *                               pool has insufficient value/weight.
     *                               Default false: refuse and throw.
     */
    public function recordSaleCogs(
        int $groupId,
        string $pigWeight,
        int $pigSaleId,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null,
        bool $allowOverdraw = false,
    ): GroupCostMovement {
        $this->assertNonNegativeString('pig_weight', $pigWeight);

        return $this->commit(
            groupId:        $groupId,
            eventType:      GroupCostMovement::EVENT_SALE_COGS,
            sourceType:     GroupCostMovement::SOURCE_PIG_SALE,
            sourceId:       $pigSaleId,
            occurredAt:     $occurredAt ?? CarbonImmutable::now(),
            actorUserId:    $actorUserId,
            reference:      $reference,
            notes:          $notes,
            eventPigWeight: $pigWeight,
            openingImportId: null,
            compute: fn (array $before) => $this->computeOutflow(
                $before, $pigWeight, $groupId, $allowOverdraw, decrementCount: true,
            ),
        );
    }

    public function recordMortalityWriteoff(
        int $groupId,
        string $pigWeight,
        int $pigDeathId,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null,
        bool $allowOverdraw = false,
    ): GroupCostMovement {
        $this->assertNonNegativeString('pig_weight', $pigWeight);

        return $this->commit(
            groupId:        $groupId,
            eventType:      GroupCostMovement::EVENT_MORTALITY_WRITEOFF,
            sourceType:     GroupCostMovement::SOURCE_PIG_DEATH,
            sourceId:       $pigDeathId,
            occurredAt:     $occurredAt ?? CarbonImmutable::now(),
            actorUserId:    $actorUserId,
            reference:      $reference,
            notes:          $notes,
            eventPigWeight: $pigWeight,
            openingImportId: null,
            compute: fn (array $before) => $this->computeOutflow(
                $before, $pigWeight, $groupId, $allowOverdraw, decrementCount: true,
            ),
        );
    }

    public function recordTransferOut(
        int $groupId,
        string $pigWeight,
        int $transferId,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null,
        bool $allowOverdraw = false,
    ): GroupCostMovement {
        $this->assertNonNegativeString('pig_weight', $pigWeight);

        // Source id encoding: transferId × 10 + 0 for OUT side (so that
        // OUT and IN sides of the same transfer have distinct source_ids
        // for the UNIQUE constraint).
        $sourceId = $transferId * 10 + 0;

        return $this->commit(
            groupId:        $groupId,
            eventType:      GroupCostMovement::EVENT_TRANSFER_OUT,
            sourceType:     GroupCostMovement::SOURCE_PIG_GROUP_TRANSFER,
            sourceId:       $sourceId,
            occurredAt:     $occurredAt ?? CarbonImmutable::now(),
            actorUserId:    $actorUserId,
            reference:      $reference,
            notes:          $notes,
            eventPigWeight: $pigWeight,
            openingImportId: null,
            compute: fn (array $before) => $this->computeOutflow(
                $before, $pigWeight, $groupId, $allowOverdraw, decrementCount: true,
            ),
        );
    }

    public function recordTransferIn(
        int $groupId,
        string $pigWeight,
        string $transferredValue,
        int $transferId,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): GroupCostMovement {
        $this->assertNonNegativeString('pig_weight', $pigWeight);
        $this->assertNonNegativeString('transferred_value', $transferredValue);

        $sourceId = $transferId * 10 + 1;

        return $this->commit(
            groupId:        $groupId,
            eventType:      GroupCostMovement::EVENT_TRANSFER_IN,
            sourceType:     GroupCostMovement::SOURCE_PIG_GROUP_TRANSFER,
            sourceId:       $sourceId,
            occurredAt:     $occurredAt ?? CarbonImmutable::now(),
            actorUserId:    $actorUserId,
            reference:      $reference,
            notes:          $notes,
            eventPigWeight: $pigWeight,
            openingImportId: null,
            compute: function (array $before) use ($pigWeight, $transferredValue) {
                $newCount  = (int) $before['count'] + 1;
                $newValue  = bcadd($before['value'],  $transferredValue, self::SCALE_QTY);
                $newWeight = bcadd($before['weight'], $pigWeight,        self::SCALE_QTY);

                $perKg = $this->perKgBasis($before['value'], $before['weight']);

                return [
                    'count_delta'        => 1,
                    'value_delta'        => $transferredValue,
                    'weight_delta'       => $pigWeight,
                    'count_after'        => $newCount,
                    'value_after'        => $newValue,
                    'weight_after'       => $newWeight,
                    'event_per_kg_basis' => $perKg,
                ];
            },
        );
    }

    /**
     * Opening pool entry. Refuses if the pool already has any
     * movement.
     */
    public function recordOpening(
        int $groupId,
        int $pigCount,
        string $totalWeight,
        string $totalValue,
        GroupOpeningPoolImport $import,
        int $importRowIndex,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
    ): GroupCostMovement {
        if ($pigCount < 0) {
            throw new \InvalidArgumentException("pig_count must be non-negative, got {$pigCount}");
        }
        $this->assertNonNegativeString('total_weight', $totalWeight);
        $this->assertNonNegativeString('total_value',  $totalValue);

        // Refuse opening if the pool already has movements.
        $existing = GroupCostMovement::query()
            ->where('group_id', $groupId)
            ->exists();

        if ($existing) {
            throw new \DomainException(sprintf(
                'Refusing opening pool for group #%d: pool already contains movements. '
                . 'Use manual adjustment instead.',
                $groupId,
            ));
        }

        // Source id encoding: importId × 100000 + rowIndex.
        $sourceId = $import->id * 100000 + $importRowIndex;

        $movement = $this->commit(
            groupId:         $groupId,
            eventType:       GroupCostMovement::EVENT_OPENING,
            sourceType:      GroupCostMovement::SOURCE_GROUP_OPENING_IMPORT,
            sourceId:        $sourceId,
            occurredAt:      $occurredAt ?? CarbonImmutable::parse($import->cutover_date)->endOfDay(),
            actorUserId:     $actorUserId ?? $import->performed_by_user_id,
            reference:       'Opening pool import #' . $import->id,
            notes:           null,
            eventPigWeight:  null,
            openingImportId: $import->id,
            compute: function () use ($pigCount, $totalWeight, $totalValue) {
                $perKg = $this->perKgBasis($totalValue, $totalWeight);

                return [
                    'count_delta'        => $pigCount,
                    'value_delta'        => $totalValue,
                    'weight_delta'       => $totalWeight,
                    'count_after'        => $pigCount,
                    'value_after'        => $totalValue,
                    'weight_after'       => $totalWeight,
                    'event_per_kg_basis' => $perKg,
                ];
            },
        );

        // Also stamp opened_at on the pool.
        GroupCostPool::query()
            ->where('group_id', $groupId)
            ->whereNull('opened_at')
            ->update(['opened_at' => CarbonImmutable::parse($import->cutover_date)->endOfDay()]);

        return $movement;
    }

    // ================================================================
    // Internal — outflow computation shared by sale/mortality/transfer_out
    // ================================================================

    /**
     * @param  array{count:int,value:string,weight:string}  $before
     * @return array{count_delta:int,value_delta:string,weight_delta:string,count_after:int,value_after:string,weight_after:string,event_per_kg_basis:string}
     */
    protected function computeOutflow(
        array $before,
        string $pigWeight,
        int $groupId,
        bool $allowOverdraw,
        bool $decrementCount,
    ): array {
        $perKg     = $this->perKgBasis($before['value'], $before['weight']);
        $cogsValue = bcmul($pigWeight, $perKg, self::SCALE_QTY);

        // Overdraw protection.
        if (! $allowOverdraw) {
            if (bccomp($cogsValue, $before['value'], self::SCALE_QTY) > 0) {
                throw new InsufficientPoolException(
                    groupId:   $groupId,
                    dimension: 'value',
                    requested: $cogsValue,
                    available: $before['value'],
                );
            }
            if (bccomp($pigWeight, $before['weight'], self::SCALE_QTY) > 0) {
                throw new InsufficientPoolException(
                    groupId:   $groupId,
                    dimension: 'weight',
                    requested: $pigWeight,
                    available: $before['weight'],
                );
            }
            if ($decrementCount && (int) $before['count'] <= 0) {
                throw new InsufficientPoolException(
                    groupId:   $groupId,
                    dimension: 'count',
                    requested: '1',
                    available: (string) $before['count'],
                );
            }
        }

        $newValue  = bcsub($before['value'],  $cogsValue, self::SCALE_QTY);
        $newWeight = bcsub($before['weight'], $pigWeight, self::SCALE_QTY);
        $newCount  = $decrementCount ? max(0, (int) $before['count'] - 1) : (int) $before['count'];

        // Tiny-residual clamp.
        if ($this->isCloseToZero($newValue)) {
            $newValue = '0';
        }
        if ($this->isCloseToZero($newWeight)) {
            $newWeight = '0';
        }

        return [
            'count_delta'        => $decrementCount ? -1 : 0,
            'value_delta'        => bcsub('0', $cogsValue, self::SCALE_QTY),
            'weight_delta'       => bcsub('0', $pigWeight, self::SCALE_QTY),
            'count_after'        => $newCount,
            'value_after'        => $newValue,
            'weight_after'       => $newWeight,
            'event_per_kg_basis' => $perKg,
        ];
    }

    // ================================================================
    // Shared commit path
    // ================================================================

    /**
     * @param  \Closure(array{count:int,value:string,weight:string}): array  $compute
     */
    protected function commit(
        int $groupId,
        string $eventType,
        string $sourceType,
        int $sourceId,
        CarbonImmutable $occurredAt,
        ?int $actorUserId,
        ?string $reference,
        ?string $notes,
        ?string $eventPigWeight,
        ?int $openingImportId,
        \Closure $compute,
    ): GroupCostMovement {
        $orgId = $this->currentOrganization->requireId();

        // Race-safe pool acquisition: firstOrCreate at zero values
        // OUTSIDE the lock, then lock-and-read inside. Same pattern as
        // Phase 4 InventoryService.
        GroupCostPool::query()
            ->where('organization_id', $orgId)
            ->where('group_id', $groupId)
            ->firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'group_id'        => $groupId,
                ],
                [
                    'on_hand_pig_count'    => 0,
                    'total_carrying_value' => '0',
                    'total_weight_kg'      => '0',
                ],
            );

        $pool = GroupCostPool::query()
            ->where('organization_id', $orgId)
            ->where('group_id', $groupId)
            ->lockForUpdate()
            ->first();

        if (! $pool) {
            throw new \RuntimeException(sprintf(
                'Pool row for group #%d disappeared between upsert and lock — '
                . 'unexpected database state.',
                $groupId,
            ));
        }

        $before = [
            'count'  => (int) $pool->on_hand_pig_count,
            'value'  => (string) $pool->total_carrying_value,
            'weight' => (string) $pool->total_weight_kg,
        ];

        $after = $compute($before);

        try {
            $movement = GroupCostMovement::create([
                'organization_id'      => $orgId,
                'group_id'             => $groupId,
                'balance_id'           => $pool->id,
                'event_type'           => $eventType,
                'source_type'          => $sourceType,
                'source_id'            => $sourceId,
                'pig_count_delta'      => $after['count_delta'],
                'value_delta'          => $after['value_delta'],
                'weight_delta'         => $after['weight_delta'],
                'pig_count_before'     => $before['count'],
                'value_before'         => $before['value'],
                'weight_before'        => $before['weight'],
                'pig_count_after'      => $after['count_after'],
                'value_after'          => $after['value_after'],
                'weight_after'         => $after['weight_after'],
                'event_pig_weight_kg'  => $eventPigWeight,
                'event_per_kg_basis'   => $after['event_per_kg_basis'] ?? null,
                'occurred_at'          => $occurredAt,
                'performed_by_user_id' => $actorUserId,
                'opening_import_id'    => $openingImportId,
                'reference'            => $reference,
                'notes'                => $notes,
            ]);
        } catch (UniqueConstraintViolationException | QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                $existing = GroupCostMovement::query()
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->first();
                if ($existing) {
                    throw new DuplicateGroupCostMovementException(
                        sourceType:         $sourceType,
                        sourceId:           $sourceId,
                        existingMovementId: (int) $existing->id,
                        previous:           $e,
                    );
                }
            }
            throw $e;
        }

        // Update pool snapshot.
        $update = [
            'on_hand_pig_count'    => $after['count_after'],
            'total_carrying_value' => $after['value_after'],
            'total_weight_kg'      => $after['weight_after'],
            'last_movement_id'     => $movement->id,
            'last_movement_at'     => $occurredAt,
        ];

        if ($pool->first_movement_at === null) {
            $update['first_movement_at'] = $occurredAt;
        }

        // Auto-close empty pools (count=0, value=0, weight=0).
        if ((int) $after['count_after'] === 0
            && bccomp((string) $after['value_after'],  '0', self::SCALE_QTY) === 0
            && bccomp((string) $after['weight_after'], '0', self::SCALE_QTY) === 0
            && $pool->closed_at === null) {
            $update['closed_at'] = $occurredAt;
        }

        $pool->update($update);

        return $movement->fresh();
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * @return string  bcmath string at scale 6, or '0' if weight is zero.
     */
    protected function perKgBasis(string $value, string $weight): string
    {
        if (bccomp($weight, '0', self::SCALE_QTY) <= 0) {
            return '0';
        }
        return bcdiv($value, $weight, self::SCALE_RATIO);
    }

    protected function assertNonNegativeString(string $field, string $value): void
    {
        if (bccomp($value, '0', self::SCALE_QTY) < 0) {
            throw new \InvalidArgumentException("{$field} must be non-negative, got {$value}");
        }
    }

    protected function isCloseToZero(string $value): bool
    {
        // |value| < CLAMP_THRESHOLD ?
        $absLeq = bccomp(bcsub('0', $value, self::SCALE_QTY), self::CLAMP_THRESHOLD, self::SCALE_QTY) <= 0;
        $valLeq = bccomp($value, self::CLAMP_THRESHOLD, self::SCALE_QTY) <= 0;
        return $absLeq && $valLeq;
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }
        $sqlState = (string) $e->getCode();
        if ($sqlState === '23000' || $sqlState === '23505') {
            return true;
        }
        return str_contains($e->getMessage(), 'UNIQUE');
    }
}
