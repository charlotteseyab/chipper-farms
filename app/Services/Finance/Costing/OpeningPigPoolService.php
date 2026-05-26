<?php

namespace App\Services\Finance\Costing;

use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Group;
use App\Models\User;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 5 — opening pig pool ingestion.
 *
 * Mirrors Phase 4's OpeningBalanceService EXACTLY. Same idempotency
 * contract, same status machine, same hash-based dedup, same
 * audit-row-survives-rollback pattern.
 *
 * Inputs per row:
 *   group_id        : existing group
 *   pig_count       : integer count of pigs in the group at cutover
 *   total_weight_kg : cumulative weight
 *   total_value     : cumulative invested cost (best estimate)
 *
 * For each row, recordOpening writes:
 *   - one group_cost_movements row (event_type=opening)
 *   - upserts the group_cost_pools row with the declared values
 *   - stamps opened_at on the pool
 *
 * GL posting at opening is the responsibility of a subscriber listening
 * to a domain event we'll dispatch on each opening row. Phase 5 dispatches
 * `pig_group_opening.recorded` for each successful row; subscriber posts
 * Dr 1230 / Cr 3010 Retained Earnings (or 3020 Opening Balance Equity).
 *
 * REFUSAL CASES
 *
 *   - Pool already has movements → recordOpening throws DomainException.
 *     OpeningPigPoolService catches and flips the audit row to FAILED.
 *     Operator must use manual adjustment for corrections to a pool
 *     that has already started accumulating.
 *
 *   - Resubmission of an identical payload after success → returns the
 *     existing completed import row, no new movements.
 *
 *   - Resubmission while pending or failed → DomainException refusal.
 *     Operator must archive the stuck row before retrying.
 */
class OpeningPigPoolService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected GroupCostPoolService $poolService,
    ) {
    }

    /**
     * @param  array<int, array{group_id: int, pig_count: int, total_weight_kg: float|string, total_value: float|string}>  $rows
     */
    public function ingest(
        array $rows,
        CarbonImmutable $cutoverDate,
        User $actor,
        string $importMode = GroupOpeningPoolImport::MODE_MANUAL,
        ?string $originalFilename = null,
        ?string $sourcePayload = null,
        ?string $notes = null,
    ): GroupOpeningPoolImport {
        $orgId    = $this->currentOrganization->requireId();
        $hash     = $this->computeHash($rows, $sourcePayload);
        $eod      = $cutoverDate->endOfDay();

        // Short-circuit on existing completed import.
        $existing = GroupOpeningPoolImport::query()
            ->where('organization_id', $orgId)
            ->where('import_hash', $hash)
            ->first();

        if ($existing) {
            if ($existing->isCompleted()) {
                return $existing;
            }
            if ($existing->isPending()) {
                throw new \DomainException(
                    "An earlier opening pool import with hash {$hash} is still pending. "
                    . "Archive it before retrying (admin only)."
                );
            }
            if ($existing->isFailed()) {
                throw new \DomainException(
                    "An earlier opening pool import with hash {$hash} failed: "
                    . "{$existing->error_message}. Archive it before retrying."
                );
            }
            // Archived: fall through and create a fresh attempt.
        }

        $validated = $this->validate($rows);

        $import = $this->createPendingImport(
            organizationId:   $orgId,
            hash:             $hash,
            importMode:       $importMode,
            cutoverDate:      $cutoverDate,
            originalFilename: $originalFilename,
            rowCount:         count($validated),
            actor:            $actor,
            notes:            $notes,
        );

        try {
            DB::transaction(function () use ($validated, $import, $eod, $actor) {
                foreach ($validated as $idx => $row) {
                    $group = Group::find($row['group_id']);
                    if (! $group) {
                        throw new \DomainException(
                            "Row {$idx}: group #{$row['group_id']} not found."
                        );
                    }

                    $this->poolService->recordOpening(
                        groupId:        $group->id,
                        pigCount:       (int) $row['pig_count'],
                        totalWeight:    (string) $row['total_weight_kg'],
                        totalValue:     (string) $row['total_value'],
                        import:         $import,
                        importRowIndex: $idx + 1,
                        occurredAt:     $eod,
                        actorUserId:    $actor->id,
                    );
                }
            });
        } catch (\Throwable $e) {
            $import->update([
                'status'        => GroupOpeningPoolImport::STATUS_FAILED,
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
            throw $e;
        }

        $import->update(['status' => GroupOpeningPoolImport::STATUS_COMPLETED]);
        return $import->fresh();
    }

    // ================================================================
    // Internals
    // ================================================================

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{group_id: int, pig_count: int, total_weight_kg: float, total_value: float}>
     */
    protected function validate(array $rows): array
    {
        if (empty($rows)) {
            throw new \InvalidArgumentException('No rows to ingest.');
        }

        $seen = [];
        $out  = [];

        foreach ($rows as $i => $row) {
            $id    = isset($row['group_id'])      ? (int) $row['group_id']        : 0;
            $count = isset($row['pig_count'])     ? (int) $row['pig_count']       : null;
            $w     = isset($row['total_weight_kg']) ? (float) $row['total_weight_kg'] : null;
            $v     = isset($row['total_value'])   ? (float) $row['total_value']   : null;

            if ($id <= 0) {
                throw new \InvalidArgumentException("Row {$i}: group_id missing or invalid.");
            }
            if ($count === null || $count < 0) {
                throw new \InvalidArgumentException("Row {$i}: pig_count must be non-negative.");
            }
            if ($w === null || $w < 0) {
                throw new \InvalidArgumentException("Row {$i}: total_weight_kg must be non-negative.");
            }
            if ($v === null || $v < 0) {
                throw new \InvalidArgumentException("Row {$i}: total_value must be non-negative.");
            }
            if (isset($seen[$id])) {
                throw new \InvalidArgumentException("Row {$i}: duplicate group_id {$id} in import.");
            }
            $seen[$id] = true;

            $out[] = [
                'group_id'        => $id,
                'pig_count'       => $count,
                'total_weight_kg' => $w,
                'total_value'     => $v,
            ];
        }
        return $out;
    }

    protected function createPendingImport(
        int $organizationId,
        string $hash,
        string $importMode,
        CarbonImmutable $cutoverDate,
        ?string $originalFilename,
        int $rowCount,
        User $actor,
        ?string $notes,
    ): GroupOpeningPoolImport {
        try {
            return GroupOpeningPoolImport::create([
                'organization_id'      => $organizationId,
                'import_mode'          => $importMode,
                'import_hash'          => $hash,
                'original_filename'    => $originalFilename,
                'cutover_date'         => $cutoverDate->toDateString(),
                'row_count'            => $rowCount,
                'status'               => GroupOpeningPoolImport::STATUS_PENDING,
                'performed_by_user_id' => $actor->id,
                'notes'                => $notes,
            ]);
        } catch (UniqueConstraintViolationException | QueryException $e) {
            $existing = GroupOpeningPoolImport::query()
                ->where('organization_id', $organizationId)
                ->where('import_hash', $hash)
                ->first();
            if ($existing) {
                throw new DuplicateOpeningPoolImportException(
                    importHash: $hash,
                    existingId: (int) $existing->id,
                    previous:   $e,
                );
            }
            throw $e;
        }
    }

    protected function computeHash(array $rows, ?string $sourcePayload): string
    {
        if ($sourcePayload !== null) {
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', $sourcePayload);
            $clean = str_replace(["\r\n", "\r"], "\n", $clean);
            $lines = array_map('rtrim', explode("\n", $clean));
            while (! empty($lines) && end($lines) === '') {
                array_pop($lines);
            }
            return hash('sha256', implode("\n", $lines));
        }

        // Manual: deterministic key ordering.
        $normalised = array_map(function ($row) {
            ksort($row);
            return $row;
        }, $rows);

        return hash(
            'sha256',
            json_encode($normalised, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
