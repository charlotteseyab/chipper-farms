<?php

namespace App\Services\Finance\BankStatements\Parsers;

use Carbon\CarbonImmutable;

/**
 * Shared parsing helpers used by every concrete parser.
 */
abstract class AbstractCsvParser implements BankStatementParserInterface
{
    /**
     * Strip a UTF-8 BOM if present, normalise line endings, trim
     * trailing blank lines.
     */
    protected function preprocess(string $contents): string
    {
        // Strip BOM.
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }
        // Normalise line endings.
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        // Trim trailing blank lines.
        $contents = rtrim($contents, "\n");
        return $contents;
    }

    /**
     * Read CSV string into rows, respecting quoted fields. Returns
     * arrays of strings (no trim — let callers decide per-column).
     *
     * @return array<int, array<int, string>>
     */
    protected function readRows(string $contents, string $delimiter = ','): array
    {
        $contents = $this->preprocess($contents);
        if ($contents === '') return [];

        // Use SplFileObject in memory via php://temp.
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Could not allocate in-memory CSV buffer.');
        }
        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            // fgetcsv returns [null] for blank lines.
            if ($row === [null] || $row === [false]) continue;
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    /**
     * Parse a numeric string that may have:
     *   - thousands separators (commas)
     *   - parenthesis-negative notation
     *   - a leading/trailing currency code
     *   - whitespace
     *
     * Returns a normalised string at scale 2: '1234.56', '-1234.56'.
     * Returns '0.00' for empty or genuinely zero. Throws on non-numeric.
     */
    protected function parseAmount(?string $raw, string $field = 'amount'): string
    {
        $s = trim((string) $raw);
        if ($s === '' || $s === '-' || $s === '—') {
            return '0.00';
        }

        $negative = false;
        // Parenthesis = negative.
        if (preg_match('/^\((.*)\)$/', $s, $m)) {
            $negative = true;
            $s = $m[1];
        }
        // Strip currency codes / symbols.
        $s = preg_replace('/(?:GHS|USD|EUR|GBP|₵|\$|€|£)/i', '', $s);
        // Strip spaces.
        $s = preg_replace('/\s+/', '', $s);
        // Leading minus.
        if (str_starts_with($s, '-')) {
            $negative = ! $negative;  // allow both () and - (rare)
            $s = substr($s, 1);
        }
        // Now it should be either:
        //   1234.56, 1,234.56  -> easy
        //   1.234,56, 1234,56  -> European; only if NO period AND comma
        if (str_contains($s, '.') && str_contains($s, ',')) {
            // Both present: treat comma as thousands.
            $s = str_replace(',', '', $s);
        } elseif (! str_contains($s, '.') && substr_count($s, ',') === 1) {
            // Only one comma, no period: treat as decimal.
            $s = str_replace(',', '.', $s);
        } else {
            // Only commas: treat them as thousands separators.
            $s = str_replace(',', '', $s);
        }

        if (! is_numeric($s)) {
            throw new \InvalidArgumentException(
                "Could not parse {$field} from value '{$raw}'."
            );
        }

        if ($negative) {
            $s = '-' . ltrim($s, '-+');
        }

        // Normalise to 2dp.
        return number_format((float) $s, 2, '.', '');
    }

    /**
     * Parse a date string into 'Y-m-d'. Tries a list of common formats
     * before falling back to strtotime. Throws on completely
     * unparseable input.
     */
    protected function parseDate(?string $raw, string $field = 'date'): string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            throw new \InvalidArgumentException("Empty {$field}.");
        }

        $formats = [
            'Y-m-d', 'd/m/Y', 'd-m-Y', 'd-M-Y', 'd M Y',
            'd/m/y', 'd-m-y', 'm/d/Y', 'Y/m/d',
            'd-M-y', 'd M y', 'M d, Y',
        ];
        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $fmt, $s);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }
        // Last resort.
        $ts = strtotime($s);
        if ($ts !== false) {
            return CarbonImmutable::createFromTimestamp($ts)->format('Y-m-d');
        }
        throw new \InvalidArgumentException("Could not parse {$field} from '{$raw}'.");
    }

    /**
     * Resolve a column index by candidate header name. Looks for
     * exact match first, then case-insensitive partial. Throws if no
     * candidate is found AND $required is true.
     */
    protected function resolveColumn(array $headers, array $candidates, bool $required = true, string $label = ''): ?int
    {
        $normalised = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            $headers,
        );
        // Pass 1: exact lower-case.
        foreach ($candidates as $cand) {
            $idx = array_search(strtolower($cand), $normalised, true);
            if ($idx !== false) return (int) $idx;
        }
        // Pass 2: substring contains.
        foreach ($candidates as $cand) {
            foreach ($normalised as $i => $h) {
                if ($h !== '' && str_contains($h, strtolower($cand))) {
                    return (int) $i;
                }
            }
        }
        if ($required) {
            $list = implode("' / '", $candidates);
            $shown = implode(', ', $headers);
            throw new \InvalidArgumentException(
                ($label ?: 'Column') . " not found. Looked for '{$list}'. Headers seen: {$shown}"
            );
        }
        return null;
    }
}
