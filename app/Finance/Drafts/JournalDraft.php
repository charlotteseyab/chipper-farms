<?php

namespace App\Finance\Drafts;

use App\Finance\Exceptions\EmptyJournalException;
use App\Finance\Exceptions\UnbalancedJournalException;

/**
 * Immutable collection of JournalLineDraft + journal-level metadata.
 *
 * Construction validates the LOCAL invariants that don't require database
 * access:
 *   - At least 2 lines.
 *   - sum(debits) == sum(credits) (balance).
 *
 * It does NOT validate:
 *   - Account existence (database)
 *   - Account-organization match (database)
 *   - Period status (database)
 *   - Currency consistency vs accounts (database)
 *
 * Those happen in PostingEngine.
 *
 * Why split LOCAL vs DATABASE validation?
 *   - Tests of subscriber logic don't need a DB to verify subscriber
 *     output is well-formed.
 *   - Drafts can be inspected/serialised before commit (e.g. logging the
 *     draft for manual journal preview UI).
 *   - PostingEngine's job is narrowed to "the bits that need DB" — easier
 *     to reason about.
 */
final class JournalDraft
{
    /**
     * @param  list<JournalLineDraft>  $lines
     */
    public function __construct(
        public readonly string $journalType,
        public readonly array $lines,
        public readonly ?string $reference = null,
        public readonly ?string $description = null,
        public readonly string $currencyCode = 'GHS',
        public readonly string $exchangeRate = '1',
        public readonly ?string $sourceType = null,
        public readonly ?int $sourceId = null,
        public readonly ?int $reversesJournalId = null,
    ) {
        if (count($lines) < 2) {
            throw new EmptyJournalException(count($lines));
        }

        // Verify every element is a JournalLineDraft.
        foreach ($lines as $i => $line) {
            if (! $line instanceof JournalLineDraft) {
                throw new \InvalidArgumentException(
                    "JournalDraft line {$i} is not a JournalLineDraft instance"
                );
            }
        }

        // Balance check.
        $totalDebit  = $this->totalDebit();
        $totalCredit = $this->totalCredit();

        if ($this->decComp($totalDebit, $totalCredit, 2) !== 0) {
            throw new UnbalancedJournalException(
                totalDebit:  $totalDebit,
                totalCredit: $totalCredit,
            );
        }
    }

    public function totalDebit(): string
    {
        $sum = '0';
        foreach ($this->lines as $line) {
            if ($line->isDebit()) {
                $sum = $this->decAdd($sum, $line->debit ?: '0', 2);
            }
        }
        return $sum;
    }

    public function totalCredit(): string
    {
        $sum = '0';
        foreach ($this->lines as $line) {
            if ($line->isCredit()) {
                $sum = $this->decAdd($sum, $line->credit ?: '0', 2);
            }
        }
        return $sum;
    }

    public function totalDebitTc(): string
    {
        $sum = '0';
        foreach ($this->lines as $line) {
            if ($line->isDebit()) {
                $sum = $this->decAdd($sum, $line->debitTc ?? $line->debit ?: '0', 2);
            }
        }
        return $sum;
    }

    public function totalCreditTc(): string
    {
        $sum = '0';
        foreach ($this->lines as $line) {
            if ($line->isCredit()) {
                $sum = $this->decAdd($sum, $line->creditTc ?? $line->credit ?: '0', 2);
            }
        }
        return $sum;
    }

    public function lineCount(): int
    {
        return count($this->lines);
    }

    private function decAdd(string $left, string $right, int $scale = 2): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, $scale);
        }

        return number_format((float) $left + (float) $right, $scale, '.', '');
    }

    private function decComp(string $left, string $right, int $scale = 2): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, $scale);
        }

        $a = round((float) $left, $scale);
        $b = round((float) $right, $scale);

        return $a <=> $b;
    }
}
