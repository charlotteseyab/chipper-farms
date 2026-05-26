<?php

namespace App\Services\Finance\BankStatements\Parsers;

/**
 * GCB Bank Ghana CSV parser.
 *
 * GCB online banking exports look like:
 *
 *   Statement of Account
 *   Account Number: 1234567890
 *   Period: 01/12/2026 to 31/12/2026
 *   Opening Balance: 12,500.00
 *   Closing Balance: 18,250.00
 *
 *   Date,Value Date,Description,Reference,Debit,Credit,Balance
 *   01/12/2026,01/12/2026,DEPOSIT,DEP123,,5000.00,17500.00
 *   ...
 *
 * The metadata block is parsed into the `header` portion of the
 * return value so the import service can pre-fill period dates and
 * opening/closing balances without asking the user.
 *
 * IMPORTANT: This implementation is hand-tuned to the format observed
 * at the time of writing. Banks change their exports without warning.
 * If a parse fails on a real-world GCB file, fall back to
 * GenericCsvParser by changing source_format at upload time.
 */
class GcbCsvParser extends AbstractCsvParser
{
    public function code(): string  { return 'gcb_csv'; }
    public function label(): string { return 'GCB Bank Ghana CSV'; }

    public function detect(string $sample): bool
    {
        $lower = strtolower($sample);
        // Distinguishing signal: GCB-specific header phrasing.
        $hasGcbHeader = str_contains($lower, 'gcb')
            || str_contains($lower, 'ghana commercial bank')
            || (str_contains($lower, 'statement of account')
                && str_contains($lower, 'value date'));
        return $hasGcbHeader;
    }

    public function parse(string $fileContents): array
    {
        $contents = $this->preprocess($fileContents);
        if ($contents === '') {
            throw new \InvalidArgumentException('GCB CSV is empty.');
        }

        // Split into pre-table metadata and table.
        // Strategy: find the first row that contains "Date" + "Description"
        // + ("Debit" OR "Credit"). Everything before is metadata.
        $allRows = $this->readRows($contents);
        $tableStart = null;
        foreach ($allRows as $i => $row) {
            $low = array_map(fn ($v) => strtolower(trim((string) $v)), $row);
            $hasDate = (bool) array_filter($low, fn ($h) => str_contains($h, 'date'));
            $hasDesc = (bool) array_filter($low, fn ($h) => str_contains($h, 'descript') || str_contains($h, 'narration'));
            $hasMoney = (bool) array_filter($low, fn ($h) => $h === 'debit' || $h === 'credit' || $h === 'dr' || $h === 'cr');
            if ($hasDate && $hasDesc && $hasMoney) {
                $tableStart = $i;
                break;
            }
        }
        if ($tableStart === null) {
            throw new \InvalidArgumentException(
                'Could not locate the transaction table header in the GCB CSV.'
            );
        }

        // Metadata header (rows before the table).
        $metaRows = array_slice($allRows, 0, $tableStart);
        $header = $this->extractMetadata($metaRows);

        // Table rows.
        $tableRows = array_slice($allRows, $tableStart);
        $headers = array_map(fn ($h) => trim((string) $h), array_shift($tableRows));

        $valueDateIdx = $this->resolveColumn($headers,
            ['value date', 'date'], true, 'Value date');
        $transactionDateIdx = $this->resolveColumn($headers,
            ['date', 'transaction date'], false);
        if ($transactionDateIdx === $valueDateIdx) $transactionDateIdx = null;

        $descIdx = $this->resolveColumn($headers,
            ['description', 'narration', 'narrative', 'particulars'],
            true, 'Description');
        $refIdx = $this->resolveColumn($headers,
            ['reference', 'ref', 'transaction id'], false);
        $debitIdx = $this->resolveColumn($headers,
            ['debit', 'dr', 'withdrawal'], true, 'Debit');
        $creditIdx = $this->resolveColumn($headers,
            ['credit', 'cr', 'deposit'], true, 'Credit');
        $balanceIdx = $this->resolveColumn($headers,
            ['balance', 'running balance'], false);

        $lines = [];
        foreach ($tableRows as $rowIdx => $row) {
            if (count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }
            try {
                $debit  = $this->parseAmount($row[$debitIdx]  ?? null, 'debit');
                $credit = $this->parseAmount($row[$creditIdx] ?? null, 'credit');
                if (bccomp($debit, '0', 2) > 0 && bccomp($credit, '0', 2) > 0) {
                    throw new \InvalidArgumentException(
                        'Both debit and credit are non-zero; check the source file.'
                    );
                }
                $valueDate = $this->parseDate($row[$valueDateIdx] ?? null, 'value_date');
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
                    'counterparty_reference' => null,
                    'debit_amount'           => $debit,
                    'credit_amount'          => $credit,
                    'running_balance'        => $balance,
                    'raw_row'                => $row,
                ];
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(
                    "Row " . ($tableStart + 2 + $rowIdx) . ": " . $e->getMessage()
                );
            }
        }

        if (empty($lines)) {
            throw new \InvalidArgumentException('No data rows found after the GCB header block.');
        }

        return ['header' => $header, 'lines' => $lines];
    }

    /**
     * Extract metadata from the pre-table rows. Best-effort.
     *
     * @param array<int, array<int, string>> $rows
     */
    protected function extractMetadata(array $rows): array
    {
        $header = [
            'period_from'              => null,
            'period_to'                => null,
            'opening_balance_per_bank' => null,
            'closing_balance_per_bank' => null,
            'currency_code'            => 'GHS',
            'account_number'           => null,
        ];

        foreach ($rows as $row) {
            $joined = strtolower(trim(implode(' ', array_filter(array_map(
                fn ($v) => (string) $v, $row,
            )))));
            if ($joined === '') continue;

            if (str_contains($joined, 'account number')
                && preg_match('/account number[:\s]+([\d\-]+)/i', $joined, $m)) {
                $header['account_number'] = trim($m[1]);
            }
            if (str_contains($joined, 'period')
                && preg_match('/period[:\s]+([\d\/\-\.]+)\s+to\s+([\d\/\-\.]+)/i', $joined, $m)) {
                try {
                    $header['period_from'] = $this->parseDate($m[1], 'period_from');
                    $header['period_to']   = $this->parseDate($m[2], 'period_to');
                } catch (\Throwable $e) {
                    // Leave nulls.
                }
            }
            if (str_contains($joined, 'opening balance')
                && preg_match('/opening balance[:\s]+([\(\)\d\.,\-\s]+)/i', $joined, $m)) {
                try {
                    $header['opening_balance_per_bank'] = $this->parseAmount(
                        $m[1], 'opening_balance_per_bank'
                    );
                } catch (\Throwable $e) {}
            }
            if (str_contains($joined, 'closing balance')
                && preg_match('/closing balance[:\s]+([\(\)\d\.,\-\s]+)/i', $joined, $m)) {
                try {
                    $header['closing_balance_per_bank'] = $this->parseAmount(
                        $m[1], 'closing_balance_per_bank'
                    );
                } catch (\Throwable $e) {}
            }
        }
        return $header;
    }
}
