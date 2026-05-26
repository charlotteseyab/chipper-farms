<?php

namespace App\Services\Finance\BankStatements\Parsers;

/**
 * Generic CSV parser. Handles the common column shape exported by
 * most online banking portals when the user picks "CSV":
 *
 *   date, description, debit, credit, balance
 *
 * Also handles variants:
 *   - reference / ref / transaction_id column
 *   - separate value_date / transaction_date columns
 *   - single 'amount' column with sign (negative = debit)
 *
 * No header metadata is parsed — you must supply opening/closing
 * balance and period at upload time. (Most plain CSV exports don't
 * include this.)
 *
 * Detection heuristic:
 *   - First non-empty row contains 'date' AND ('debit' OR 'credit' OR 'amount')
 *   - At least 4 columns
 */
class GenericCsvParser extends AbstractCsvParser
{
    public function code(): string  { return 'generic_csv'; }
    public function label(): string { return 'Generic CSV (date, description, debit, credit, balance)'; }

    public function detect(string $sample): bool
    {
        $rows = $this->readRows($sample);
        if (count($rows) < 2) return false;

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
        if (count($headers) < 3) return false;

        $hasDate   = (bool) array_filter($headers, fn ($h) => str_contains($h, 'date'));
        $hasDebit  = (bool) array_filter($headers, fn ($h) => str_contains($h, 'debit') || str_contains($h, 'withdrawal'));
        $hasCredit = (bool) array_filter($headers, fn ($h) => str_contains($h, 'credit') || str_contains($h, 'deposit'));
        $hasAmount = (bool) array_filter($headers, fn ($h) => $h === 'amount');

        return $hasDate && ($hasDebit || $hasCredit || $hasAmount);
    }

    public function parse(string $fileContents): array
    {
        $rows = $this->readRows($fileContents);
        if (count($rows) < 2) {
            throw new \InvalidArgumentException(
                'CSV has fewer than 2 rows (header + 1 data row required).'
            );
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($rows));

        $valueDateIdx = $this->resolveColumn($headers,
            ['value date', 'transaction date', 'posting date', 'date'],
            true, 'Value/transaction date');
        $transactionDateIdx = $this->resolveColumn($headers,
            ['transaction date', 'txn date'],
            false);
        $descIdx = $this->resolveColumn($headers,
            ['description', 'narrative', 'narration', 'details', 'particulars', 'memo'],
            true, 'Description');
        $refIdx = $this->resolveColumn($headers,
            ['reference', 'ref', 'transaction id', 'transaction reference', 'txn ref', 'txn id'],
            false);
        $cpRefIdx = $this->resolveColumn($headers,
            ['counterparty reference', 'sender reference', 'their ref', 'cust ref'],
            false);
        $debitIdx = $this->resolveColumn($headers,
            ['debit', 'withdrawal', 'money out', 'paid out', 'dr'],
            false);
        $creditIdx = $this->resolveColumn($headers,
            ['credit', 'deposit', 'money in', 'paid in', 'cr'],
            false);
        $amountIdx = $this->resolveColumn($headers,
            ['amount'],
            false);
        $balanceIdx = $this->resolveColumn($headers,
            ['balance', 'running balance', 'closing balance'],
            false);

        if ($debitIdx === null && $creditIdx === null && $amountIdx === null) {
            throw new \InvalidArgumentException(
                'CSV must have either debit/credit columns or a single amount column.'
            );
        }

        $lines = [];
        foreach ($rows as $rowIdx => $row) {
            // Skip blank rows.
            if (count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }

            try {
                [$debit, $credit] = $this->extractDebitCredit(
                    $row, $debitIdx, $creditIdx, $amountIdx
                );

                $valueDate = $this->parseDate(
                    $row[$valueDateIdx] ?? null, 'value_date'
                );
                $transactionDate = $transactionDateIdx !== null && isset($row[$transactionDateIdx])
                    ? $this->parseDate($row[$transactionDateIdx], 'transaction_date')
                    : null;

                $balance = null;
                if ($balanceIdx !== null && isset($row[$balanceIdx])) {
                    $bal = trim((string) $row[$balanceIdx]);
                    if ($bal !== '') {
                        $balance = $this->parseAmount($bal, 'running_balance');
                    }
                }

                $lines[] = [
                    'value_date'             => $valueDate,
                    'transaction_date'       => $transactionDate,
                    'description'            => trim((string) ($row[$descIdx] ?? '')),
                    'bank_reference'         => $refIdx !== null
                        ? (trim((string) ($row[$refIdx] ?? '')) ?: null) : null,
                    'counterparty_reference' => $cpRefIdx !== null
                        ? (trim((string) ($row[$cpRefIdx] ?? '')) ?: null) : null,
                    'debit_amount'           => $debit,
                    'credit_amount'          => $credit,
                    'running_balance'        => $balance,
                    'raw_row'                => $row,
                ];
            } catch (\InvalidArgumentException $e) {
                // Annotate with row context so the user can fix it.
                throw new \InvalidArgumentException(
                    "Row " . ($rowIdx + 2) . ": " . $e->getMessage()
                );
            }
        }

        if (empty($lines)) {
            throw new \InvalidArgumentException(
                'No valid data rows found in CSV.'
            );
        }

        return [
            'header' => [
                'period_from'              => null,  // generic CSV doesn't carry this
                'period_to'                => null,
                'opening_balance_per_bank' => null,
                'closing_balance_per_bank' => null,
                'currency_code'            => null,
                'account_number'           => null,
            ],
            'lines'  => $lines,
        ];
    }

    /**
     * Returns [debit, credit] strings, both at scale 2.
     */
    protected function extractDebitCredit(
        array $row,
        ?int $debitIdx,
        ?int $creditIdx,
        ?int $amountIdx,
    ): array {
        if ($debitIdx !== null || $creditIdx !== null) {
            $debit  = $debitIdx !== null && isset($row[$debitIdx])
                ? $this->parseAmount($row[$debitIdx], 'debit')
                : '0.00';
            $credit = $creditIdx !== null && isset($row[$creditIdx])
                ? $this->parseAmount($row[$creditIdx], 'credit')
                : '0.00';

            // If both are non-zero, that's a CSV bug — refuse.
            if (bccomp($debit, '0', 2) > 0 && bccomp($credit, '0', 2) > 0) {
                throw new \InvalidArgumentException(
                    'Both debit and credit columns are non-zero; this is not a valid bank line.'
                );
            }
            // Make absolute (some banks export debit as negative number).
            $debit  = ltrim($debit,  '-');
            $credit = ltrim($credit, '-');
            return [$debit, $credit];
        }

        // Single amount column.
        $amt = $this->parseAmount($row[$amountIdx] ?? null, 'amount');
        if (str_starts_with($amt, '-')) {
            return [substr($amt, 1), '0.00'];     // debit
        }
        if (bccomp($amt, '0', 2) > 0) {
            return ['0.00', $amt];                // credit
        }
        return ['0.00', '0.00'];                  // zero amount (rare; balance-only row)
    }
}
