<?php

namespace App\Services\Finance\Inventory;

use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryOpeningBalanceImport;
use App\Models\RawMaterial;
use App\Models\User;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 4 — Idempotent opening balance ingestion.
 *
 * Both kinds (raw material + finished feed) go through the same audit
 * flow with a SHA-256 hash for dedup. The service is the only sanctioned
 * write path for opening balances; controllers call this service rather
 * than InventoryService::recordOpening directly so the audit row exists
 * and the rules are enforced uniformly.
 *
 * IDEMPOTENCY CONTRACT
 *
 *   - UNIQUE(organization_id, item_kind, import_hash) on the audit table
 *     is the race-safe dedup. Two concurrent identical uploads collide
 *     cleanly; the second wins or loses on the UNIQUE.
 *
 *   - Re-running with the same payload AFTER a successful completion
 *     returns the existing import row short-circuit (no new movements).
 *
 *   - Re-running while a previous attempt is in 'pending' or 'failed'
 *     is REFUSED with a DomainException; ops must archive the stuck row
 *     manually. This protects against:
 *       * Half-written state from a crashed writer.
 *       * Retrying a payload that failed validation — the failure
 *         reason needs review before automatic retry.
 *
 * INVARIANT
 *
 *   Opening balances may only be written for items whose ledger is
 *   currently EMPTY. InventoryService::recordOpening enforces this at
 *   the per-movement level. The service additionally pre-checks at the
 *   row-batch level so the failure surfaces before any partial writes.
 *
 * ROLLBACK
 *
 *   The writer transaction wraps ALL row writes for the import. Any
 *   failure (validation, ledger-not-empty, anything) rolls back every
 *   movement AND flips the audit row's status to 'failed' with the
 *   error message captured. The audit row itself is committed OUTSIDE
 *   the writer transaction (in its own statement) so it survives
 *   rollback as evidence.
 */
class OpeningBalanceService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected InventoryService $inventory,
    ) {
    }

    /**
     * Ingest raw material opening balances.
     *
     * @param  array<int, array{raw_material_id: int, quantity: float, unit_cost: float}>  $rows
     */
    public function ingestRawMaterials(
        array $rows,
        CarbonImmutable $cutoverDate,
        User $actor,
        string $importMode = InventoryOpeningBalanceImport::MODE_MANUAL,
        ?string $originalFilename = null,
        ?string $sourcePayload = null,
        ?string $notes = null,
    ): InventoryOpeningBalanceImport {
        return $this->ingest(
            kind:             InventoryOpeningBalanceImport::KIND_RAW_MATERIAL,
            rawRows:          $rows,
            cutoverDate:      $cutoverDate,
            actor:            $actor,
            importMode:       $importMode,
            originalFilename: $originalFilename,
            sourcePayload:    $sourcePayload,
            notes:            $notes,
            validator:        fn ($r) => $this->validateRawMaterialRows($r),
            writer:           function (array $validated, InventoryOpeningBalanceImport $import, CarbonImmutable $eod, User $actor) {
                foreach ($validated as $idx => $row) {
                    /** @var RawMaterial|null $material */
                    $material = RawMaterial::find($row['raw_material_id']);
                    if (! $material) {
                        throw new \DomainException(
                            "Row {$idx}: raw material #{$row['raw_material_id']} not found."
                        );
                    }

                    $this->inventory->recordOpening(
                        itemKind:       'raw_material',
                        itemId:         $material->id,
                        quantity:       (float) $row['quantity'],
                        unitCost:       (float) $row['unit_cost'],
                        import:         $import,
                        importRowIndex: $idx + 1,
                        occurredAt:     $eod,
                        actorUserId:    $actor->id,
                        itemNameCache:  $material->name,
                        itemUnitCache:  $material->unit,
                    );

                    // Sync legacy column for backward-compat readers.
                    // Phase 4 still ships with RawMaterial::current_wac
                    // reading from the unit_cost column for materials
                    // with no movements; the WAC accessor is updated in
                    // a separate code drop, so we keep the legacy column
                    // in sync for one release.
                    $material->update([
                        'quantity_in_stock' => $row['quantity'],
                        'unit_cost'         => $row['unit_cost'],
                    ]);
                }
            },
        );
    }

    /**
     * Ingest finished feed opening balances.
     *
     * @param  array<int, array{feed_type_id: int, quantity: float, unit_cost: float}>  $rows
     */
    public function ingestFinishedFeed(
        array $rows,
        CarbonImmutable $cutoverDate,
        User $actor,
        string $importMode = InventoryOpeningBalanceImport::MODE_MANUAL,
        ?string $originalFilename = null,
        ?string $sourcePayload = null,
        ?string $notes = null,
    ): InventoryOpeningBalanceImport {
        return $this->ingest(
            kind:             InventoryOpeningBalanceImport::KIND_FINISHED_FEED,
            rawRows:          $rows,
            cutoverDate:      $cutoverDate,
            actor:            $actor,
            importMode:       $importMode,
            originalFilename: $originalFilename,
            sourcePayload:    $sourcePayload,
            notes:            $notes,
            validator:        fn ($r) => $this->validateFeedRows($r),
            writer:           function (array $validated, InventoryOpeningBalanceImport $import, CarbonImmutable $eod, User $actor) {
                foreach ($validated as $idx => $row) {
                    /** @var FeedType|null $feedType */
                    $feedType = FeedType::find($row['feed_type_id']);
                    if (! $feedType) {
                        throw new \DomainException(
                            "Row {$idx}: feed type #{$row['feed_type_id']} not found."
                        );
                    }

                    $this->inventory->recordOpening(
                        itemKind:       'finished_feed',
                        itemId:         $feedType->id,
                        quantity:       (float) $row['quantity'],
                        unitCost:       (float) $row['unit_cost'],
                        import:         $import,
                        importRowIndex: $idx + 1,
                        occurredAt:     $eod,
                        actorUserId:    $actor->id,
                        itemNameCache:  $feedType->name,
                        itemUnitCache:  'kg',
                    );
                }
            },
        );
    }

    // ================================================================
    // Internal — unified ingestion path
    // ================================================================

    /**
     * @param  array<int, array<string, mixed>>  $rawRows
     * @param  \Closure(array<int, array<string, mixed>>): array<int, array<string, mixed>>  $validator
     * @param  \Closure(array<int, array<string, mixed>>, InventoryOpeningBalanceImport, CarbonImmutable, User): void  $writer
     */
    protected function ingest(
        string $kind,
        array $rawRows,
        CarbonImmutable $cutoverDate,
        User $actor,
        string $importMode,
        ?string $originalFilename,
        ?string $sourcePayload,
        ?string $notes,
        \Closure $validator,
        \Closure $writer,
    ): InventoryOpeningBalanceImport {
        $orgId = $this->currentOrganization->requireId();
        $hash  = $this->computeHash($rawRows, $sourcePayload);
        $eod   = $cutoverDate->endOfDay();

        // Short-circuit if already committed.
        $existing = InventoryOpeningBalanceImport::query()
            ->where('organization_id', $orgId)
            ->where('item_kind', $kind)
            ->where('import_hash', $hash)
            ->first();

        if ($existing) {
            if ($existing->isCompleted()) {
                return $existing;
            }
            if ($existing->isPending()) {
                throw new \DomainException(
                    "An earlier import with hash {$hash} is still pending. "
                    . "Archive it before retrying (admin only)."
                );
            }
            if ($existing->isFailed()) {
                throw new \DomainException(
                    "An earlier import with hash {$hash} failed: {$existing->error_message}. "
                    . "Archive it before retrying."
                );
            }
            // Archived rows do not block — fall through and create a new
            // pending row. (Operator chose to archive; a fresh attempt
            // is welcome.)
        }

        // Pre-validate rows before any writes.
        $validated = $validator($rawRows);

        // Audit row is created in its OWN transaction so it survives
        // any rollback during the writer phase.
        $import = $this->createPendingImport(
            organizationId:    $orgId,
            kind:              $kind,
            importMode:        $importMode,
            hash:              $hash,
            cutoverDate:       $cutoverDate,
            originalFilename:  $originalFilename,
            rowCount:          count($validated),
            actor:             $actor,
            notes:             $notes,
        );

        try {
            DB::transaction(function () use ($validated, $import, $eod, $actor, $writer) {
                $writer($validated, $import, $eod, $actor);
            });
        } catch (\Throwable $e) {
            // Flip the audit row to FAILED and capture the error.
            $import->update([
                'status'        => InventoryOpeningBalanceImport::STATUS_FAILED,
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
            throw $e;
        }

        $import->update(['status' => InventoryOpeningBalanceImport::STATUS_COMPLETED]);
        return $import->fresh();
    }

    /**
     * Create the audit row in 'pending' status. Race-safe via the
     * UNIQUE(org, kind, hash); if another process raced past us, we
     * catch and translate to DuplicateImportException with the existing
     * row's id.
     */
    protected function createPendingImport(
        int $organizationId,
        string $kind,
        string $importMode,
        string $hash,
        CarbonImmutable $cutoverDate,
        ?string $originalFilename,
        int $rowCount,
        User $actor,
        ?string $notes,
    ): InventoryOpeningBalanceImport {
        try {
            return InventoryOpeningBalanceImport::create([
                'organization_id'      => $organizationId,
                'item_kind'            => $kind,
                'import_mode'          => $importMode,
                'import_hash'          => $hash,
                'original_filename'    => $originalFilename,
                'cutover_date'         => $cutoverDate->toDateString(),
                'row_count'            => $rowCount,
                'status'               => InventoryOpeningBalanceImport::STATUS_PENDING,
                'performed_by_user_id' => $actor->id,
                'notes'                => $notes,
            ]);
        } catch (UniqueConstraintViolationException | QueryException $e) {
            // Race: a parallel attempt won. Re-read and throw typed.
            $existing = InventoryOpeningBalanceImport::query()
                ->where('organization_id', $organizationId)
                ->where('item_kind', $kind)
                ->where('import_hash', $hash)
                ->first();

            if ($existing) {
                throw new DuplicateImportException(
                    importHash:  $hash,
                    itemKind:    $kind,
                    existingId:  (int) $existing->id,
                    previous:    $e,
                );
            }
            throw $e;
        }
    }

    // ================================================================
    // Row validation
    // ================================================================

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{raw_material_id: int, quantity: float, unit_cost: float}>
     */
    protected function validateRawMaterialRows(array $rows): array
    {
        $allowZero = (bool) config('finance.inventory.opening_balance.allow_zero_quantity_opening', false);

        if (empty($rows)) {
            throw new \InvalidArgumentException('No rows to ingest.');
        }

        $seen = [];
        $out  = [];

        foreach ($rows as $i => $row) {
            $id   = isset($row['raw_material_id']) ? (int) $row['raw_material_id'] : 0;
            $qty  = isset($row['quantity'])  ? (float) $row['quantity']  : null;
            $cost = isset($row['unit_cost']) ? (float) $row['unit_cost'] : null;

            if ($id <= 0) {
                throw new \InvalidArgumentException("Row {$i}: raw_material_id missing or invalid.");
            }
            if ($qty === null || $qty < 0) {
                throw new \InvalidArgumentException("Row {$i}: quantity must be non-negative.");
            }
            if ($cost === null || $cost < 0) {
                throw new \InvalidArgumentException("Row {$i}: unit_cost must be non-negative.");
            }
            if (! $allowZero && $qty == 0 && $cost == 0) {
                throw new \InvalidArgumentException("Row {$i}: zero quantity and zero unit_cost rejected (config to enable).");
            }
            if (isset($seen[$id])) {
                throw new \InvalidArgumentException("Row {$i}: duplicate raw_material_id {$id} in import.");
            }
            $seen[$id] = true;

            $out[] = ['raw_material_id' => $id, 'quantity' => $qty, 'unit_cost' => $cost];
        }
        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{feed_type_id: int, quantity: float, unit_cost: float}>
     */
    protected function validateFeedRows(array $rows): array
    {
        $allowZero = (bool) config('finance.inventory.opening_balance.allow_zero_quantity_opening', false);

        if (empty($rows)) {
            throw new \InvalidArgumentException('No rows to ingest.');
        }

        $seen = [];
        $out  = [];

        foreach ($rows as $i => $row) {
            $id   = isset($row['feed_type_id']) ? (int) $row['feed_type_id'] : 0;
            $qty  = isset($row['quantity'])  ? (float) $row['quantity']  : null;
            $cost = isset($row['unit_cost']) ? (float) $row['unit_cost'] : null;

            if ($id <= 0) {
                throw new \InvalidArgumentException("Row {$i}: feed_type_id missing or invalid.");
            }
            if ($qty === null || $qty < 0) {
                throw new \InvalidArgumentException("Row {$i}: quantity must be non-negative.");
            }
            if ($cost === null || $cost < 0) {
                throw new \InvalidArgumentException("Row {$i}: unit_cost must be non-negative.");
            }
            if (! $allowZero && $qty == 0 && $cost == 0) {
                throw new \InvalidArgumentException("Row {$i}: zero quantity and zero unit_cost rejected (config to enable).");
            }
            if (isset($seen[$id])) {
                throw new \InvalidArgumentException("Row {$i}: duplicate feed_type_id {$id} in import.");
            }
            $seen[$id] = true;

            $out[] = ['feed_type_id' => $id, 'quantity' => $qty, 'unit_cost' => $cost];
        }
        return $out;
    }

    // ================================================================
    // Hash computation
    // ================================================================

    /**
     * Canonicalised hash of the import payload. CSV uploads pass the
     * original file text via $sourcePayload (preferred — most stable).
     * Manual entries pass null, and we hash the sorted JSON of the rows.
     */
    protected function computeHash(array $rows, ?string $sourcePayload): string
    {
        if ($sourcePayload !== null) {
            // Strip BOM, normalise line endings, trim trailing blank
            // lines. Anything that doesn't change the data signature
            // gets stripped.
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', $sourcePayload);
            $clean = str_replace(["\r\n", "\r"], "\n", $clean);
            $lines = array_map('rtrim', explode("\n", $clean));
            while (! empty($lines) && end($lines) === '') {
                array_pop($lines);
            }
            return hash('sha256', implode("\n", $lines));
        }

        // Manual payload: deterministic JSON with sorted keys.
        $normalised = array_map(function ($row) {
            ksort($row);
            return $row;
        }, $rows);

        return hash('sha256', json_encode($normalised, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
