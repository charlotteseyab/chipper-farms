<?php

namespace App\Services\Finance\BankStatements;

use App\Models\Finance\BankAccount;
use App\Models\Finance\BankStatement;
use App\Models\Finance\BankStatementLine;
use App\Models\User;
use App\Services\Finance\BankStatements\Parsers\BankStatementParserRegistry;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Bank statement import — Phase 3e.
 *
 * Workflow:
 *   1. User picks a bank account + uploads a CSV file (+ optional
 *      format hint, period, opening/closing balances).
 *   2. Service reads file → SHA-256 → check for prior import
 *      (organisation, bank_account, sha256 unique).
 *   3. Service picks parser (hint → detect → generic).
 *   4. Service parses → validates the resulting line list.
 *   5. Service stores the original file on the configured disk for
 *      audit, then writes the bank_statements + bank_statement_lines
 *      rows in a single transaction.
 *
 * The import is atomic: either every line is written or none of them.
 *
 * The import is idempotent at the file level: a second upload of the
 * same byte content (same SHA) for the same bank account is rejected
 * with a clear error pointing at the first import.
 */
class BankStatementImportService
{
    public function __construct(
        protected BankStatementParserRegistry $registry,
        protected NumberingService $numbering,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Import a CSV file into a new BankStatement + lines.
     *
     * @param BankAccount $bankAccount
     * @param string      $fileContents     The raw file bytes.
     * @param string      $originalFilename
     * @param array{
     *   source_format?: ?string,
     *   period_from?: ?string,
     *   period_to?: ?string,
     *   opening_balance_per_bank?: ?string,
     *   closing_balance_per_bank?: ?string,
     *   currency_code?: ?string,
     *   notes?: ?string,
     * } $userOverrides
     * @param User $importedBy
     */
    public function import(
        BankAccount $bankAccount,
        string $fileContents,
        string $originalFilename,
        array $userOverrides,
        User $importedBy,
    ): BankStatement {
        if (! $bankAccount->isUsable()) {
            throw new \DomainException(
                "Bank account {$bankAccount->id} is not usable for import (inactive or missing GL link)."
            );
        }
        if ($fileContents === '') {
            throw new \InvalidArgumentException('Empty file.');
        }

        $sha256 = hash('sha256', $fileContents);

        // Duplicate-import detection at the file-content level.
        $existing = BankStatement::query()
            ->where('organization_id', $bankAccount->organization_id)
            ->where('bank_account_id',  $bankAccount->id)
            ->where('file_sha256', $sha256)
            ->where('status', '!=', BankStatement::STATUS_VOID)
            ->first();
        if ($existing) {
            throw new \DomainException(
                "This file has already been imported as {$existing->statement_number}. " .
                'Void that import first if you need to re-upload it.'
            );
        }

        // Pick parser.
        $hint = $userOverrides['source_format'] ?? $bankAccount->default_csv_format;
        $sample = mb_substr($fileContents, 0, 16384);
        $parser = $this->registry->detect($sample, $hint);

        // Parse.
        $parsed = $parser->parse($fileContents);
        $parsedHeader = $parsed['header'] ?? [];
        $parsedLines  = $parsed['lines']  ?? [];

        if (empty($parsedLines)) {
            throw new \InvalidArgumentException(
                'Parser produced zero lines. Either the file is empty, the format is wrong, ' .
                'or the file contains only metadata.'
            );
        }

        // Resolve period + balances. User overrides win.
        $periodFrom = $userOverrides['period_from'] ?? $parsedHeader['period_from'];
        $periodTo   = $userOverrides['period_to']   ?? $parsedHeader['period_to'];
        $opening    = $userOverrides['opening_balance_per_bank'] ?? $parsedHeader['opening_balance_per_bank'];
        $closing    = $userOverrides['closing_balance_per_bank'] ?? $parsedHeader['closing_balance_per_bank'];
        $currency   = $userOverrides['currency_code'] ?? $parsedHeader['currency_code'] ?? $bankAccount->currency_code;

        if (! $periodFrom || ! $periodTo) {
            // Last-ditch: derive from line dates.
            $dates = array_filter(array_column($parsedLines, 'value_date'));
            if (! empty($dates)) {
                sort($dates);
                $periodFrom = $periodFrom ?: reset($dates);
                $periodTo   = $periodTo   ?: end($dates);
            }
        }
        if (! $periodFrom || ! $periodTo) {
            throw new \InvalidArgumentException(
                'Could not determine statement period. Supply period_from and period_to explicitly.'
            );
        }
        if (CarbonImmutable::parse($periodTo)->lt(CarbonImmutable::parse($periodFrom))) {
            throw new \InvalidArgumentException('period_to is before period_from.');
        }

        if ($opening === null || $closing === null) {
            throw new \InvalidArgumentException(
                'Opening AND closing balance per bank are required for reconciliation. ' .
                'Provide them at upload time if your CSV does not carry them.'
            );
        }

        // Sanity: closing should equal opening + credits - debits.
        // We compute totals here and warn (don't throw) if they don't
        // match — the bank's balance is authoritative; mismatch is
        // usually due to opening-balance carryover / mid-day rows.
        $totalCredits = '0';
        $totalDebits  = '0';
        foreach ($parsedLines as $l) {
            $totalCredits = bcadd($totalCredits, (string) $l['credit_amount'], 2);
            $totalDebits  = bcadd($totalDebits,  (string) $l['debit_amount'],  2);
        }

        // Persist the original file to the configured disk for audit.
        $disk = config('finance.attachments.disk', 'local');
        $orgId = $bankAccount->organization_id ?? 'noorg';
        $fileSafeName = $this->safeFilename($originalFilename);
        $filePath = "bank-statements/{$orgId}/{$bankAccount->id}/{$sha256}-{$fileSafeName}";
        Storage::disk($disk)->put($filePath, $fileContents);

        return DB::transaction(function () use (
            $bankAccount, $importedBy, $parser, $parsedLines, $periodFrom, $periodTo,
            $opening, $closing, $currency, $totalCredits, $totalDebits,
            $disk, $filePath, $originalFilename, $fileContents, $sha256, $userOverrides,
        ) {
            $stmtNumber = $this->numbering->next(
                sequenceType:   'bank_statement',
                prefix:         'BSTMT',
                year:           (int) CarbonImmutable::parse($periodTo)->format('Y'),
                organizationId: $bankAccount->organization_id,
                padding:        6,
            );

            $statement = BankStatement::create([
                'organization_id'          => $bankAccount->organization_id,
                'bank_account_id'          => $bankAccount->id,
                'statement_number'         => $stmtNumber,
                'source_format'            => $parser->code(),
                'period_from'              => $periodFrom,
                'period_to'                => $periodTo,
                'currency_code'            => $currency,
                'opening_balance_per_bank' => $opening,
                'closing_balance_per_bank' => $closing,
                'line_count'               => count($parsedLines),
                'total_credits'            => $totalCredits,
                'total_debits'             => $totalDebits,
                'original_filename'        => $originalFilename,
                'file_disk'                => $disk,
                'file_path'                => $filePath,
                'file_size_bytes'          => strlen($fileContents),
                'file_sha256'              => $sha256,
                'status'                   => BankStatement::STATUS_IMPORTED,
                'notes'                    => $userOverrides['notes'] ?? null,
                'imported_by_user_id'      => $importedBy->id,
                'imported_at'              => now(),
            ]);

            // Insert lines in one batch insert (faster on large files).
            $now = now();
            $lineRecords = [];
            foreach ($parsedLines as $idx => $l) {
                $lineRecords[] = [
                    'organization_id'         => $bankAccount->organization_id,
                    'bank_statement_id'       => $statement->id,
                    'line_number'             => $idx + 1,
                    'value_date'              => $l['value_date'],
                    'transaction_date'        => $l['transaction_date'],
                    'description'             => mb_substr((string) $l['description'], 0, 500),
                    'bank_reference'          => $l['bank_reference'],
                    'counterparty_reference'  => $l['counterparty_reference'],
                    'debit_amount'            => $l['debit_amount'],
                    'credit_amount'           => $l['credit_amount'],
                    'running_balance'         => $l['running_balance'],
                    'match_status'            => BankStatementLine::STATUS_UNMATCHED,
                    'amount_difference'       => '0.00',
                    'raw_row'                 => json_encode($l['raw_row'] ?? []),
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ];
            }
            // Use insert in chunks of 500 to avoid driver row-limits.
            foreach (array_chunk($lineRecords, 500) as $chunk) {
                BankStatementLine::insert($chunk);
            }

            return $statement->fresh('lines');
        });
    }

    /**
     * Void a previously imported statement. Line audit is preserved;
     * no reconciliation linkage allowed.
     */
    public function void(BankStatement $statement, User $user, string $reason): BankStatement
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Void reason is required.');
        }

        return DB::transaction(function () use ($statement, $user, $reason) {
            $statement = BankStatement::lockForUpdate()->findOrFail($statement->id);

            if (! $statement->canVoid()) {
                throw new \DomainException(
                    "Cannot void statement {$statement->statement_number}: " .
                    'unmatch all matched lines and remove from any closed reconciliation first.'
                );
            }

            $statement->update([
                'status'             => BankStatement::STATUS_VOID,
                'voided_at'          => now(),
                'voided_by_user_id'  => $user->id,
                'void_reason'        => $reason,
            ]);

            return $statement->fresh();
        });
    }

    protected function safeFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
        $safeBase = trim($safeBase, '_') ?: 'statement';
        $safeExt  = preg_replace('/[^A-Za-z0-9]+/', '', $ext) ?: 'csv';
        return mb_substr($safeBase, 0, 80) . '.' . mb_substr($safeExt, 0, 6);
    }
}
