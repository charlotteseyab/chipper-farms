<?php

namespace App\Services\Finance\SupplierStatements;

use App\Models\Counterparty;
use App\Models\Finance\SupplierStatement;
use App\Models\Finance\SupplierStatementLine;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Manages the lifecycle of supplier statement headers and lines.
 *
 * Handles:
 *   - createDraft           Allocate SSTMT-YYYY-NNNNNN, create header row.
 *   - addLine               Manual single-line entry.
 *   - addLinesFromCsv       Bulk line entry (one CSV row per supplier statement line).
 *   - removeLine            Remove an unmatched/manually-resolved line; refuse if matched.
 *   - markFlagged           Status transition with reasoning.
 *   - markReconciled        Status transition; refuses if unresolved items remain.
 *   - archive               Status transition with reason.
 *
 * Reconciliation matching is a separate service:
 *   {@see StatementReconciliationService}
 *
 * Why split:
 *   - SupplierStatementService = data lifecycle, status transitions
 *   - StatementReconciliationService = pure matching engine, no I/O state
 *   This makes the matcher unit-testable without touching headers and
 *   keeps services single-responsibility.
 */
class SupplierStatementService
{
    public function __construct(
        protected NumberingService $numbering,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function createDraft(
        Counterparty $supplier,
        \DateTimeInterface $statementDate,
        \DateTimeInterface $periodFrom,
        \DateTimeInterface $periodTo,
        string $supplierBalance,
        User $createdBy,
        array $extra = [],
    ): SupplierStatement {
        if (! $supplier->isSupplier()) {
            throw new \DomainException(
                "Counterparty #{$supplier->id} is not a supplier (type: {$supplier->type})"
            );
        }

        $periodFromImm = CarbonImmutable::instance($periodFrom);
        $periodToImm   = CarbonImmutable::instance($periodTo);
        $stmtDateImm   = CarbonImmutable::instance($statementDate);

        if ($periodToImm->lt($periodFromImm)) {
            throw new \InvalidArgumentException('period_to must be on or after period_from');
        }
        if ($stmtDateImm->lt($periodFromImm)) {
            throw new \InvalidArgumentException('statement_date must be on or after period_from');
        }

        $organizationId = $this->currentOrganization->resolve()?->id;

        return DB::transaction(function () use (
            $supplier, $stmtDateImm, $periodFromImm, $periodToImm, $supplierBalance,
            $createdBy, $extra, $organizationId
        ) {
            $number = $this->numbering->next(
                sequenceType:   'supplier_statement',
                prefix:         'SSTMT',
                year:           (int) $stmtDateImm->format('Y'),
                organizationId: $organizationId,
                padding:        6,
            );

            return SupplierStatement::create([
                'organization_id'    => $organizationId,
                'counterparty_id'    => $supplier->id,
                'statement_number'   => $number,
                'statement_date'     => $stmtDateImm->toDateString(),
                'period_from'        => $periodFromImm->toDateString(),
                'period_to'          => $periodToImm->toDateString(),
                'currency_code'      => $extra['currency_code'] ?? $supplier->currency_code ?? 'GHS',
                'supplier_balance'   => $supplierBalance,
                'our_balance'        => '0',
                'balance_difference' => $supplierBalance,
                'status'             => SupplierStatement::STATUS_DRAFT,
                'supplier_reference' => $extra['supplier_reference'] ?? null,
                'notes'              => $extra['notes'] ?? null,
                'created_by_user_id' => $createdBy->id,
            ]);
        });
    }

    /**
     * Add one line to a draft/in-progress statement.
     *
     * @param  array  $data  Keys: document_date, document_kind, document_reference, amount, description?
     */
    public function addLine(SupplierStatement $statement, array $data): SupplierStatementLine
    {
        if (! $statement->canEdit()) {
            throw new \DomainException(
                "Cannot add lines to statement {$statement->statement_number} in status '{$statement->status}'"
            );
        }

        $this->validateLineInput($data);

        return DB::transaction(function () use ($statement, $data) {
            $maxLineNumber = (int) SupplierStatementLine::query()
                ->where('supplier_statement_id', $statement->id)
                ->max('line_number');

            return SupplierStatementLine::create([
                'supplier_statement_id' => $statement->id,
                'line_number'           => $maxLineNumber + 1,
                'document_date'         => CarbonImmutable::parse($data['document_date'])->toDateString(),
                'document_kind'         => $data['document_kind'],
                'document_reference'    => trim((string) $data['document_reference']),
                'amount'                => (string) $data['amount'],
                'description'           => $data['description'] ?? null,
                'match_status'          => SupplierStatementLine::STATUS_UNMATCHED,
            ]);
        });
    }

    /**
     * Bulk-add lines from CSV. Expected columns:
     *
     *   document_date, document_kind, document_reference, amount, description (optional)
     *
     * Rejects the entire batch on any malformed row — atomic.
     *
     * @param  array<array<string, mixed>>  $rows
     * @return array{added: int, skipped: array<int, string>}
     */
    public function addLinesFromCsv(SupplierStatement $statement, array $rows): array
    {
        if (! $statement->canEdit()) {
            throw new \DomainException(
                "Cannot import lines into statement {$statement->statement_number} in status '{$statement->status}'"
            );
        }

        if (empty($rows)) {
            return ['added' => 0, 'skipped' => []];
        }

        // Validate everything first.
        $errors = [];
        foreach ($rows as $i => $row) {
            try {
                $this->validateLineInput($row);
            } catch (\Throwable $e) {
                $errors[$i + 1] = $e->getMessage();
            }
        }

        if (! empty($errors)) {
            $msg = "CSV import failed: " . count($errors) . " malformed row(s). First error on row "
                . array_key_first($errors) . ': ' . reset($errors);
            throw new \InvalidArgumentException($msg);
        }

        $added = 0;
        DB::transaction(function () use ($statement, $rows, &$added) {
            $maxLineNumber = (int) SupplierStatementLine::query()
                ->where('supplier_statement_id', $statement->id)
                ->max('line_number');

            foreach ($rows as $row) {
                $maxLineNumber++;
                SupplierStatementLine::create([
                    'supplier_statement_id' => $statement->id,
                    'line_number'           => $maxLineNumber,
                    'document_date'         => CarbonImmutable::parse($row['document_date'])->toDateString(),
                    'document_kind'         => $row['document_kind'],
                    'document_reference'    => trim((string) $row['document_reference']),
                    'amount'                => (string) $row['amount'],
                    'description'           => $row['description'] ?? null,
                    'match_status'          => SupplierStatementLine::STATUS_UNMATCHED,
                ]);
                $added++;
            }
        });

        return ['added' => $added, 'skipped' => []];
    }

    public function removeLine(SupplierStatementLine $line): void
    {
        $statement = $line->statement;
        if (! $statement || ! $statement->canEdit()) {
            throw new \DomainException(
                "Cannot remove line from statement in status '{$statement?->status}'"
            );
        }
        if ($line->isMatched()) {
            throw new \DomainException(
                'Cannot remove a matched line. Re-match or manually resolve first.'
            );
        }
        $line->delete();
    }

    public function markFlagged(SupplierStatement $statement, User $user, string $reason): SupplierStatement
    {
        if ($statement->isArchived()) {
            throw new \DomainException('Archived statements cannot change status.');
        }
        $statement->update([
            'status'                     => SupplierStatement::STATUS_FLAGGED,
            'reconciliation_notes'       => trim($reason),
            'last_reconciled_at'         => now(),
            'last_reconciled_by_user_id' => $user->id,
        ]);
        return $statement->fresh();
    }

    public function markReconciled(SupplierStatement $statement, User $user): SupplierStatement
    {
        if ($statement->isArchived()) {
            throw new \DomainException('Archived statements cannot change status.');
        }
        $statement->loadMissing('lines');
        if ($statement->hasUnresolvedItems()) {
            throw new \DomainException(
                'Cannot mark reconciled while unresolved items remain. ' .
                'Resolve or use Mark Flagged with a reason.'
            );
        }
        if (! $statement->balancesAgree()) {
            throw new \DomainException(
                "Cannot mark reconciled while balance_difference is non-zero ({$statement->balance_difference}). " .
                'Refresh the reconciliation, or use Mark Flagged with a reason.'
            );
        }

        $statement->update([
            'status'                     => SupplierStatement::STATUS_RECONCILED,
            'last_reconciled_at'         => now(),
            'last_reconciled_by_user_id' => $user->id,
        ]);
        return $statement->fresh();
    }

    public function archive(SupplierStatement $statement, User $user, string $reason): SupplierStatement
    {
        if ($statement->isArchived()) {
            throw new \DomainException('Statement is already archived.');
        }
        $statement->update([
            'status'              => SupplierStatement::STATUS_ARCHIVED,
            'archived_by_user_id' => $user->id,
            'archived_at'         => now(),
            'archive_reason'      => trim($reason),
        ]);
        return $statement->fresh();
    }

    /* internals */

    protected function validateLineInput(array $data): void
    {
        foreach (['document_date', 'document_kind', 'document_reference', 'amount'] as $req) {
            if (! array_key_exists($req, $data) || $data[$req] === null || $data[$req] === '') {
                throw new \InvalidArgumentException("Missing required field: {$req}");
            }
        }
        if (! in_array($data['document_kind'], SupplierStatementLine::ALL_KINDS, true)) {
            throw new \InvalidArgumentException(
                "Invalid document_kind '{$data['document_kind']}'. Allowed: " .
                implode(', ', SupplierStatementLine::ALL_KINDS)
            );
        }
        if (! is_numeric($data['amount'])) {
            throw new \InvalidArgumentException("amount must be numeric, got '{$data['amount']}'");
        }
        // Amount may be negative for credits/refunds — don't gt:0.
        if (bccomp((string) $data['amount'], '0', 2) === 0) {
            throw new \InvalidArgumentException('amount must be non-zero');
        }
        try {
            CarbonImmutable::parse($data['document_date']);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Invalid document_date: '{$data['document_date']}'");
        }
        if (mb_strlen((string) $data['document_reference']) > 100) {
            throw new \InvalidArgumentException('document_reference exceeds 100 characters');
        }
    }
}
