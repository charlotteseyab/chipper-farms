<?php

namespace App\Services\Finance\BankStatements\Parsers;

/**
 * Phase 3e — Bank statement CSV parser contract.
 *
 * Each implementation handles ONE bank's CSV layout. The registry
 * (BankStatementImportService) picks the right one based on the
 * source_format hint or by trial-detection.
 *
 * Implementations must be:
 *   - Stateless (a fresh instance per parse, or safe to re-use).
 *   - Tolerant of UTF-8 BOMs, quoted fields, and trailing blank rows.
 *   - Strict about column presence — if a required column is missing,
 *     throw with a clear message naming the column. Don't silently
 *     produce garbage rows.
 *   - Numeric-safe — parse "1,234.56", "1.234,56" (some banks),
 *     "(1,234.56)" (parenthesis = negative), "GHS 1,234.56" all into
 *     a normalised PHP string (no thousands separator, period decimal).
 *   - Date-safe — parse the bank's date format into ISO 8601 ('Y-m-d').
 *
 * Output is an array of normalised rows + a header summary.
 */
interface BankStatementParserInterface
{
    /**
     * Stable identifier — matches `bank_accounts.default_csv_format`
     * and `bank_statements.source_format`. Lower-snake-case, e.g.
     * 'gcb_csv', 'stanbic_csv', 'generic_csv'.
     */
    public function code(): string;

    /**
     * Human-readable label for the picker UI.
     */
    public function label(): string;

    /**
     * Cheap detect: given the first ~10 KB of file content, would this
     * parser likely succeed? Used to auto-detect format. Conservative:
     * return false on doubt — better to ask the user than parse wrong.
     */
    public function detect(string $sample): bool;

    /**
     * Parse the file and return:
     *   [
     *     'header' => [
     *       'period_from'              => 'YYYY-MM-DD' | null,
     *       'period_to'                => 'YYYY-MM-DD' | null,
     *       'opening_balance_per_bank' => string | null,
     *       'closing_balance_per_bank' => string | null,
     *       'currency_code'            => 'GHS' | null,
     *       'account_number'           => string | null,
     *     ],
     *     'lines' => [
     *       [
     *         'value_date'             => 'YYYY-MM-DD',
     *         'transaction_date'       => 'YYYY-MM-DD' | null,
     *         'description'            => string,
     *         'bank_reference'         => string | null,
     *         'counterparty_reference' => string | null,
     *         'debit_amount'           => string,  // '0.00' if not a debit
     *         'credit_amount'          => string,  // '0.00' if not a credit
     *         'running_balance'        => string | null,
     *         'raw_row'                => array,   // original CSV row as-is
     *       ],
     *       ...
     *     ],
     *   ]
     *
     * Header values are best-effort — if the format doesn't carry them
     * inside the file (most generic CSVs don't), return null and let
     * the user supply them at upload time.
     *
     * Throws \InvalidArgumentException with a descriptive message on
     * malformed input. Never returns partial garbage.
     *
     * @return array{header: array, lines: array<int, array>}
     */
    public function parse(string $fileContents): array;
}
