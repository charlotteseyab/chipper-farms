<?php

namespace App\Services\Finance\Inventory;

use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\InventoryOpeningBalanceImport;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — Unified weighted-average-cost (WAC) engine for inventory.
 *
 * Single source of truth for all inventory movements regardless of
 * item kind. Replaces the Phase 0.7 RawMaterialValuationService and
 * the Phase 4a sibling FeedInventoryService design.
 *
 * RESPONSIBILITIES
 *
 *   1. Persist exactly one inventory_movements row per business event.
 *   2. Update the inventory_balances snapshot atomically with the
 *      movement.
 *   3. Enforce the WAC math invariants (see WAC RULES below).
 *   4. Be idempotent on (source_type, source_id) via DB UNIQUE +
 *      typed DuplicateMovementException.
 *   5. Block negative inventory by default; respect override flag.
 *
 * NOT RESPONSIBILITIES
 *
 *   - Posting to GL. Subscribers do that, reading the movement row.
 *   - Validating that source_type and source_id refer to real records.
 *     Callers are trusted; the FK on inventory_movements.balance_id
 *     and the application-layer service contracts cover this.
 *
 * WAC RULES (IAS 2 weighted average)
 *
 *   RECEIVE (qty Q, unit_cost P):
 *     new_qty   = old_qty + Q
 *     new_value = old_value + (Q × P)
 *     new_WAC   = new_value / new_qty   (if new_qty > 0, else 0)
 *
 *   USAGE (qty Q):
 *     new_qty   = old_qty - Q
 *     new_value = old_value - (Q × old_WAC)
 *     new_WAC   = old_WAC  (consumption does not move WAC)
 *
 *   ADJUSTMENT_ADD (qty Q):  identical to RECEIVE but at current WAC
 *                            (count gain — no new price signal)
 *
 *   ADJUSTMENT_DEDUCT (qty Q): identical to USAGE (count loss / shrinkage)
 *
 *   ADJUSTMENT_SET (target qty Q):
 *     new_qty   = Q                     (the new total)
 *     new_value = Q × old_WAC           (value follows quantity at WAC)
 *     new_WAC   = old_WAC               (unchanged)
 *
 *   FEED_PRODUCE (qty Q, total_cost C):
 *     Like RECEIVE but the unit cost is C / Q (the effective cost of
 *     the produced batch as a whole).
 *
 *   FEED_CONSUME_FOR_PIGS (qty Q): identical to USAGE.
 *
 *   OPENING (qty Q, unit_cost P): like RECEIVE but on an empty ledger.
 *
 *   TRANSFER_OUT/IN: paired events that net to zero org-wide; each is
 *     locally identical to a USAGE / RECEIVE at the source / destination.
 *
 * EDGE CASES
 *
 *   - Receipt into zero stock: new_WAC = receipt price.
 *   - Usage when on-hand is zero: rejected (InsufficientInventoryException).
 *   - Tiny floating-point residual when on-hand drains to ~0: clamped
 *     to exactly 0.0000 (residual threshold: 0.0001).
 *
 * CONCURRENCY
 *
 *   Every write acquires SELECT ... FOR UPDATE on the inventory_balances
 *   row for (org, kind, item_id). Two concurrent receipts of the same
 *   item serialise on this lock; receipts for different items proceed
 *   in parallel. The balances row is the lock target rather than the
 *   movements table to avoid lock escalation.
 *
 *   The DB-level UNIQUE(source_type, source_id) on inventory_movements
 *   is defence-in-depth — if two queue workers race to record the same
 *   business event (e.g. a stock log subscriber retry), exactly one
 *   wins; the other catches DuplicateMovementException cleanly.
 *
 * PRECISION
 *
 *   All math performed with PHP's bcmath at scale 6 for WAC intermediates,
 *   then stored as decimal(18,4) for quantity/value and decimal(18,6)
 *   for WAC. The conversion from bcmath strings to decimals goes through
 *   bcadd/bcsub/bcmul/bcdiv — no float arithmetic anywhere in the math
 *   path.
 *
 *   Caller-supplied quantity/unit_cost are floats (legacy API) but
 *   immediately stringified for bcmath. The legacy float API is preserved
 *   for compatibility with the existing controller callers; new callers
 *   may pass strings if they want full precision.
 *
 * TRANSACTIONS
 *
 *   The service does NOT open its own transaction. Callers are expected
 *   to wrap their full business logic (e.g. "receive stock + log + value")
 *   in a single transaction so that the inventory write commits or rolls
 *   back together with the source row. This matches the Phase 0.7
 *   RawMaterialValuationService pattern and is critical for source-event
 *   consistency.
 */
class InventoryService
{
    /** bcmath scale for WAC intermediate computations. */
    public const SCALE_WAC = 6;

    /** Scale for quantity / value stored values. */
    public const SCALE_QTY_VALUE = 4;

    /** Tiny residual clamp threshold (under this, on-hand snaps to 0). */
    public const CLAMP_THRESHOLD = '0.0001';

    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    // ================================================================
    // Public API — typed methods per event class
    // ================================================================

    /**
     * Record a RECEIVE event.
     *
     * Used by ReceiveRawMaterialService (and future receipt flows).
     * Source: stock log id (raw material kind).
     */
    public function recordReceive(
        string $itemKind,
        int $itemId,
        float $quantity,
        float $unitCost,
        string $sourceType,
        int $sourceId,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null,
        ?string $itemNameCache = null,
        ?string $itemUnitCache = null,
    ): InventoryMovement {
        $this->assertItemKind($itemKind);
        $this->assertPositive('quantity', $quantity);
        $this->assertNonNegative('unit_cost', $unitCost);

        return $this->commit(
            itemKind:      $itemKind,
            itemId:        $itemId,
            eventType:     InventoryMovement::EVENT_RECEIVE,
            sourceType:    $sourceType,
            sourceId:      $sourceId,
            occurredAt:    $occurredAt ?? CarbonImmutable::now(),
            actorUserId:   $actorUserId,
            reference:     $reference,
            notes:         $notes,
            itemNameCache: $itemNameCache,
            itemUnitCache: $itemUnitCache,
            compute: function (array $before) use ($quantity, $unitCost) {
                // bcmath strings
                $q = $this->str($quantity);
                $p = $this->str($unitCost);

                $newQty   = bcadd($before['qty'],   $q,                 self::SCALE_QTY_VALUE);
                $valIn    = bcmul($q,               $p,                 self::SCALE_QTY_VALUE);
                $newValue = bcadd($before['value'], $valIn,             self::SCALE_QTY_VALUE);
                $newWac   = bccomp($newQty, '0', self::SCALE_QTY_VALUE) > 0
                    ? bcdiv($newValue, $newQty, self::SCALE_WAC)
                    : '0';

                return [
                    'quantity_delta'  => $q,
                    'value_delta'     => $valIn,
                    'qty_after'       => $newQty,
                    'value_after'     => $newValue,
                    'wac_after'       => $newWac,
                    'event_unit_cost' => $p,
                ];
            },
        );
    }

    /**
     * Record a USAGE event (raw material consumed into feed production).
     */
    public function recordUsage(
        string $itemKind,
        int $itemId,
        float $quantity,
        string $sourceType,
        int $sourceId,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null,
        bool $allowNegative = false,
    ): InventoryMovement {
        return $this->recordOutflow(
            itemKind:      $itemKind,
            itemId:        $itemId,
            eventType:     InventoryMovement::EVENT_USAGE,
            quantity:      $quantity,
            sourceType:    $sourceType,
            sourceId:      $sourceId,
            occurredAt:    $occurredAt,
            actorUserId:   $actorUserId,
            reference:     $reference,
            notes:         $notes,
            allowNegative: $allowNegative,
        );
    }

    /**
     * Record an ADJUSTMENT — three semantics depending on `$type`:
     *   - 'add'    : $quantity = positive magnitude to add
     *   - 'deduct' : $quantity = positive magnitude to remove
     *   - 'set'    : $quantity = absolute new on-hand
     */
    public function recordAdjustment(
        string $itemKind,
        int $itemId,
        string $type,
        float $quantity,
        string $sourceType,
        int $sourceId,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null,
        bool $allowNegative = false,
    ): InventoryMovement {
        if (! in_array($type, ['add', 'deduct', 'set'], true)) {
            throw new \InvalidArgumentException(
                "Unknown adjustment type '{$type}'. Expected 'add' | 'deduct' | 'set'."
            );
        }

        return match ($type) {
            'add'    => $this->recordAdjustmentAdd(   $itemKind, $itemId, $quantity, $sourceType, $sourceId, $occurredAt, $actorUserId, $reference, $notes),
            'deduct' => $this->recordOutflow(
                itemKind:      $itemKind,
                itemId:        $itemId,
                eventType:     InventoryMovement::EVENT_ADJUSTMENT_DEDUCT,
                quantity:      $quantity,
                sourceType:    $sourceType,
                sourceId:      $sourceId,
                occurredAt:    $occurredAt,
                actorUserId:   $actorUserId,
                reference:     $reference,
                notes:         $notes,
                allowNegative: $allowNegative,
            ),
            'set'    => $this->recordAdjustmentSet(   $itemKind, $itemId, $quantity, $sourceType, $sourceId, $occurredAt, $actorUserId, $reference, $notes),
        };
    }

    /**
     * Record a FEED_PRODUCE event — finished feed inventory gain when
     * a feed batch is mixed. Unit cost = total_cost / quantity.
     */
    public function recordFeedProduction(
        int $feedTypeId,
        float $quantity,
        float $totalCost,
        int $feedBatchId,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null,
        ?string $itemNameCache = null,
    ): InventoryMovement {
        $this->assertPositive('quantity', $quantity);
        $this->assertNonNegative('total_cost', $totalCost);

        // Use SOURCE_FEED_BATCH so the unique constraint dedupes per batch.
        return $this->commit(
            itemKind:      InventoryMovement::ITEM_KIND_FINISHED_FEED,
            itemId:        $feedTypeId,
            eventType:     InventoryMovement::EVENT_FEED_PRODUCE,
            sourceType:    InventoryMovement::SOURCE_FEED_BATCH,
            sourceId:      $feedBatchId,
            occurredAt:    $occurredAt ?? CarbonImmutable::now(),
            actorUserId:   $actorUserId,
            reference:     $reference,
            notes:         $notes,
            itemNameCache: $itemNameCache,
            itemUnitCache: 'kg',
            compute: function (array $before) use ($quantity, $totalCost) {
                $q = $this->str($quantity);
                $valIn = $this->str($totalCost);
                $unitCost = bccomp($q, '0', self::SCALE_QTY_VALUE) > 0
                    ? bcdiv($valIn, $q, self::SCALE_WAC)
                    : '0';

                $newQty   = bcadd($before['qty'],   $q,     self::SCALE_QTY_VALUE);
                $newValue = bcadd($before['value'], $valIn, self::SCALE_QTY_VALUE);
                $newWac   = bccomp($newQty, '0', self::SCALE_QTY_VALUE) > 0
                    ? bcdiv($newValue, $newQty, self::SCALE_WAC)
                    : '0';

                return [
                    'quantity_delta'  => $q,
                    'value_delta'     => $valIn,
                    'qty_after'       => $newQty,
                    'value_after'     => $newValue,
                    'wac_after'       => $newWac,
                    'event_unit_cost' => $unitCost,
                ];
            },
        );
    }

    /**
     * Record an OPENING balance event. Used only by OpeningBalanceService.
     */
    public function recordOpening(
        string $itemKind,
        int $itemId,
        float $quantity,
        float $unitCost,
        InventoryOpeningBalanceImport $import,
        int $importRowIndex,
        ?CarbonImmutable $occurredAt = null,
        ?int $actorUserId = null,
        ?string $itemNameCache = null,
        ?string $itemUnitCache = null,
    ): InventoryMovement {
        $this->assertItemKind($itemKind);
        $this->assertNonNegative('quantity', $quantity);
        $this->assertNonNegative('unit_cost', $unitCost);

        // Refuse opening when ledger is not empty for this item.
        $existingBalance = InventoryBalance::query()
            ->where('organization_id', $this->currentOrganization->requireId())
            ->where('item_kind', $itemKind)
            ->where('item_id',   $itemId)
            ->first();

        if ($existingBalance !== null) {
            throw new \DomainException(sprintf(
                'Refusing opening balance for %s #%d: ledger already contains movements. '
                . 'Use stock adjustment instead.',
                $itemKind,
                $itemId,
            ));
        }

        // Source dedup: (opening_import, importId × 100000 + rowIndex).
        // The composite gives a unique source_id per row of the import.
        // Supports up to ~21000 imports × 99999 rows each, well within
        // the unsignedBigInteger range.
        $sourceId = $import->id * 100000 + $importRowIndex;

        $movement = $this->commit(
            itemKind:      $itemKind,
            itemId:        $itemId,
            eventType:     InventoryMovement::EVENT_OPENING,
            sourceType:    InventoryMovement::SOURCE_OPENING_IMPORT,
            sourceId:      $sourceId,
            occurredAt:    $occurredAt ?? CarbonImmutable::parse($import->cutover_date)->endOfDay(),
            actorUserId:   $actorUserId ?? $import->performed_by_user_id,
            reference:     'Opening balance import #' . $import->id,
            notes:         null,
            itemNameCache: $itemNameCache,
            itemUnitCache: $itemUnitCache,
            compute: function (array $before) use ($quantity, $unitCost) {
                $q = $this->str($quantity);
                $p = $this->str($unitCost);
                $valIn = bcmul($q, $p, self::SCALE_QTY_VALUE);
                $wac   = bccomp($q, '0', self::SCALE_QTY_VALUE) > 0
                    ? bcdiv($valIn, $q, self::SCALE_WAC)
                    : '0';

                return [
                    'quantity_delta'  => $q,
                    'value_delta'     => $valIn,
                    'qty_after'       => $q,
                    'value_after'     => $valIn,
                    'wac_after'       => $wac,
                    'event_unit_cost' => $p,
                ];
            },
        );

        // Link movement to import for audit (separate update because
        // commit() doesn't know about imports).
        $movement->update(['opening_balance_import_id' => $import->id]);

        return $movement;
    }

    // ================================================================
    // Internal — outflow + adjustment-add + adjustment-set helpers
    // ================================================================

    protected function recordOutflow(
        string $itemKind,
        int $itemId,
        string $eventType,
        float $quantity,
        string $sourceType,
        int $sourceId,
        ?CarbonImmutable $occurredAt,
        ?int $actorUserId,
        ?string $reference,
        ?string $notes,
        bool $allowNegative,
    ): InventoryMovement {
        $this->assertItemKind($itemKind);
        $this->assertPositive('quantity', $quantity);

        return $this->commit(
            itemKind:      $itemKind,
            itemId:        $itemId,
            eventType:     $eventType,
            sourceType:    $sourceType,
            sourceId:      $sourceId,
            occurredAt:    $occurredAt ?? CarbonImmutable::now(),
            actorUserId:   $actorUserId,
            reference:     $reference,
            notes:         $notes,
            itemNameCache: null,
            itemUnitCache: null,
            compute: function (array $before) use ($quantity, $itemKind, $itemId, $allowNegative) {
                $q = $this->str($quantity);

                if (! $allowNegative && bccomp($q, $before['qty'], self::SCALE_QTY_VALUE) > 0) {
                    throw new InsufficientInventoryException(
                        itemKind:  $itemKind,
                        itemId:    $itemId,
                        requested: (float) $q,
                        available: (float) $before['qty'],
                    );
                }

                $valueOut = bcmul($q, $before['wac'], self::SCALE_QTY_VALUE);
                $newQty   = bcsub($before['qty'],   $q,         self::SCALE_QTY_VALUE);
                $newValue = bcsub($before['value'], $valueOut,  self::SCALE_QTY_VALUE);

                // Tiny-residual clamp: if newQty is below the threshold,
                // treat as exact zero. Protects against floating-point
                // legacy callers passing slightly-off magnitudes that
                // would leave the ledger with 0.0001 dust.
                if (bccomp(bcsub('0', $newQty, self::SCALE_QTY_VALUE), self::CLAMP_THRESHOLD, self::SCALE_QTY_VALUE) <= 0
                    && bccomp($newQty, self::CLAMP_THRESHOLD, self::SCALE_QTY_VALUE) <= 0) {
                    $newQty   = '0';
                    $newValue = '0';
                }

                return [
                    'quantity_delta'  => bcsub('0', $q, self::SCALE_QTY_VALUE),  // negative
                    'value_delta'     => bcsub('0', $valueOut, self::SCALE_QTY_VALUE),
                    'qty_after'       => $newQty,
                    'value_after'     => $newValue,
                    'wac_after'       => $before['wac'],   // unchanged on outflow
                    'event_unit_cost' => null,
                ];
            },
        );
    }

    protected function recordAdjustmentAdd(
        string $itemKind,
        int $itemId,
        float $quantity,
        string $sourceType,
        int $sourceId,
        ?CarbonImmutable $occurredAt,
        ?int $actorUserId,
        ?string $reference,
        ?string $notes,
    ): InventoryMovement {
        $this->assertItemKind($itemKind);
        $this->assertPositive('quantity', $quantity);

        return $this->commit(
            itemKind:      $itemKind,
            itemId:        $itemId,
            eventType:     InventoryMovement::EVENT_ADJUSTMENT_ADD,
            sourceType:    $sourceType,
            sourceId:      $sourceId,
            occurredAt:    $occurredAt ?? CarbonImmutable::now(),
            actorUserId:   $actorUserId,
            reference:     $reference,
            notes:         $notes,
            itemNameCache: null,
            itemUnitCache: null,
            compute: function (array $before) use ($quantity) {
                $q = $this->str($quantity);
                $valIn = bcmul($q, $before['wac'], self::SCALE_QTY_VALUE);

                $newQty   = bcadd($before['qty'],   $q,     self::SCALE_QTY_VALUE);
                $newValue = bcadd($before['value'], $valIn, self::SCALE_QTY_VALUE);

                return [
                    'quantity_delta'  => $q,
                    'value_delta'     => $valIn,
                    'qty_after'       => $newQty,
                    'value_after'     => $newValue,
                    'wac_after'       => $before['wac'],
                    'event_unit_cost' => null,
                ];
            },
        );
    }

    protected function recordAdjustmentSet(
        string $itemKind,
        int $itemId,
        float $targetQuantity,
        string $sourceType,
        int $sourceId,
        ?CarbonImmutable $occurredAt,
        ?int $actorUserId,
        ?string $reference,
        ?string $notes,
    ): InventoryMovement {
        $this->assertItemKind($itemKind);
        $this->assertNonNegative('target_quantity', $targetQuantity);

        return $this->commit(
            itemKind:      $itemKind,
            itemId:        $itemId,
            eventType:     InventoryMovement::EVENT_ADJUSTMENT_SET,
            sourceType:    $sourceType,
            sourceId:      $sourceId,
            occurredAt:    $occurredAt ?? CarbonImmutable::now(),
            actorUserId:   $actorUserId,
            reference:     $reference,
            notes:         $notes,
            itemNameCache: null,
            itemUnitCache: null,
            compute: function (array $before) use ($targetQuantity) {
                $newQ = $this->str($targetQuantity);
                $delta = bcsub($newQ, $before['qty'], self::SCALE_QTY_VALUE);

                $newValue = bcmul($newQ, $before['wac'], self::SCALE_QTY_VALUE);
                $valueDelta = bcsub($newValue, $before['value'], self::SCALE_QTY_VALUE);

                return [
                    'quantity_delta'  => $delta,        // signed
                    'value_delta'     => $valueDelta,   // signed
                    'qty_after'       => $newQ,
                    'value_after'     => $newValue,
                    'wac_after'       => $before['wac'],
                    'event_unit_cost' => null,
                ];
            },
        );
    }

    // ================================================================
    // The core commit path — shared by every event method.
    // ================================================================

    /**
     * Compute the new balance, write the movement row, update the
     * balances snapshot, all under row-level lock.
     *
     * @param  \Closure(array{qty:string,value:string,wac:string}): array  $compute
     *         Computes the after-state given the before-state. Returns
     *         {quantity_delta, value_delta, qty_after, value_after,
     *          wac_after, event_unit_cost}.
     */
    protected function commit(
        string $itemKind,
        int $itemId,
        string $eventType,
        string $sourceType,
        int $sourceId,
        CarbonImmutable $occurredAt,
        ?int $actorUserId,
        ?string $reference,
        ?string $notes,
        ?string $itemNameCache,
        ?string $itemUnitCache,
        \Closure $compute,
    ): InventoryMovement {
        $orgId = $this->currentOrganization->requireId();

        // Race-safe balance acquisition.
        //
        // Worst case at fresh-item: two workers see no balance row and both
        // try to INSERT after computing $before = 0. UNIQUE(org, kind, item_id)
        // prevents the double-insert, but the loser would still have written
        // a movement row based on a stale $before snapshot of zero.
        //
        // Mitigation: eagerly upsert a zero balance row OUTSIDE the lock,
        // then lock-and-read it inside the lock. The UNIQUE guarantees
        // exactly one row exists by the time we enter the locked region;
        // the lock then serialises concurrent updates to that single row.
        InventoryBalance::query()
            ->where('organization_id', $orgId)
            ->where('item_kind', $itemKind)
            ->where('item_id',   $itemId)
            ->firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'item_kind'       => $itemKind,
                    'item_id'         => $itemId,
                ],
                [
                    'item_name_cache'   => $itemNameCache,
                    'item_unit_cache'   => $itemUnitCache,
                    'on_hand_qty'       => '0',
                    'on_hand_value'     => '0',
                    'weighted_avg_cost' => '0',
                ],
            );

        // Now acquire the row-level lock and read the authoritative state.
        $balance = InventoryBalance::query()
            ->where('organization_id', $orgId)
            ->where('item_kind', $itemKind)
            ->where('item_id',   $itemId)
            ->lockForUpdate()
            ->first();

        // Defensive: should always exist after firstOrCreate, but check
        // anyway in case some other process deleted the row between
        // upsert and lock.
        if ($balance === null) {
            throw new \RuntimeException(sprintf(
                'Balance row for (%s, %d) disappeared between upsert and lock — '
                . 'unexpected database state.',
                $itemKind,
                $itemId,
            ));
        }

        $beforeQty   = $this->str($balance->on_hand_qty);
        $beforeValue = $this->str($balance->on_hand_value);
        $beforeWac   = $this->str($balance->weighted_avg_cost);

        $after = $compute([
            'qty'   => $beforeQty,
            'value' => $beforeValue,
            'wac'   => $beforeWac,
        ]);

        // Persist the movement row. UNIQUE(source_type, source_id) is
        // the race-safety net; catch the violation and translate.
        try {
            $movement = InventoryMovement::create([
                'organization_id'           => $orgId,
                'item_kind'                 => $itemKind,
                'item_id'                   => $itemId,
                'balance_id'                => $balance?->id,
                'event_type'                => $eventType,
                'source_type'               => $sourceType,
                'source_id'                 => $sourceId,
                'quantity_delta'            => $after['quantity_delta'],
                'value_delta'               => $after['value_delta'],
                'quantity_on_hand_before'   => $beforeQty,
                'value_on_hand_before'      => $beforeValue,
                'weighted_avg_cost_before'  => $beforeWac,
                'quantity_on_hand_after'    => $after['qty_after'],
                'value_on_hand_after'       => $after['value_after'],
                'weighted_avg_cost_after'   => $after['wac_after'],
                'event_unit_cost'           => $after['event_unit_cost'],
                'occurred_at'               => $occurredAt,
                'performed_by_user_id'      => $actorUserId,
                'reference'                 => $reference,
                'notes'                     => $notes,
            ]);
        } catch (UniqueConstraintViolationException | QueryException $e) {
            // (source_type, source_id) collision OR generic unique violation.
            if ($this->isUniqueViolation($e)) {
                $existing = InventoryMovement::query()
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->first();

                if ($existing) {
                    throw new DuplicateMovementException(
                        sourceType:           $sourceType,
                        sourceId:             $sourceId,
                        existingMovementId:   (int) $existing->id,
                        previous:             $e,
                    );
                }
            }
            throw $e;
        }

        // Update the balance snapshot. The row is guaranteed to exist
        // from the firstOrCreate at the top of commit().
        $balance->update([
            'on_hand_qty'       => $after['qty_after'],
            'on_hand_value'     => $after['value_after'],
            'weighted_avg_cost' => $after['wac_after'],
            'last_movement_id'  => $movement->id,
            'last_movement_at'  => $occurredAt,
            'first_movement_at' => $balance->first_movement_at ?? $occurredAt,
            'item_name_cache'   => $itemNameCache ?? $balance->item_name_cache,
            'item_unit_cache'   => $itemUnitCache ?? $balance->item_unit_cache,
        ]);

        $movement->update(['balance_id' => $balance->id]);

        return $movement->fresh();
    }

    // ================================================================
    // Helpers
    // ================================================================

    protected function assertItemKind(string $itemKind): void
    {
        if (! in_array($itemKind, InventoryMovement::ALLOWED_ITEM_KINDS, true)) {
            throw new \InvalidArgumentException(
                "Unknown inventory item_kind '{$itemKind}'. Allowed: "
                . implode(', ', InventoryMovement::ALLOWED_ITEM_KINDS)
            );
        }
    }

    protected function assertPositive(string $field, float $value): void
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException("{$field} must be positive, got {$value}");
        }
    }

    protected function assertNonNegative(string $field, float $value): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException("{$field} must be non-negative, got {$value}");
        }
    }

    /**
     * Normalise a numeric input (float or string) to a bcmath-safe string
     * at the WAC scale. Prevents PHP's float-to-string locale drift
     * (e.g. ',' as decimal separator in some locales).
     */
    protected function str(float|string $value): string
    {
        if (is_string($value)) {
            // Already a string — trust it (callers using bcmath-native).
            return $value;
        }
        // Use number_format with '.' decimal and no thousands separator.
        return number_format($value, self::SCALE_WAC, '.', '');
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
