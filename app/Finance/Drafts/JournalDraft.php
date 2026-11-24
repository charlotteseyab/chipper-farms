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
 * Lines are kept to whole cents. Subscribers may hand in amounts with more
 * decimals (a feed mix costs 70.7850 split across eight ingredients); the
 * database stores 2 decimals, rounding half up. So each line is rounded here
 * the same way, and the cent or so that rounding can open between the two
 * sides is settled on the side split into more lines (the ingredients, not
 * the mix's one total), on its largest line, so a single total stays exact. Without this the stored
 * lines, the journal's totals and the balance check each rounded differently:
 * totals a cent off their own lines, and balanced mixes refused as
 * "unbalanced". A draft whose exact amounts differ by a cent or more is
 * still refused: that is a real imbalance, not rounding.
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
    /** @var list<JournalLineDraft> rounded to whole cents and balanced */
    public readonly array $lines;

    public function __construct(
        public readonly string $journalType,
        array $lines,
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

        // Balance check on the exact amounts: a cent or more apart is a
        // real imbalance.
        $exactDebit = '0';
        $exactCredit = '0';
        foreach ($lines as $line) {
            $line->isDebit() ? $exactDebit = bcadd($exactDebit, $line->amount(), 8) : $exactCredit = bcadd($exactCredit, $line->amount(), 8);
        }
        $gap = bcsub($exactDebit, $exactCredit, 8);
        if (bccomp(ltrim($gap, '-'), '0.01', 8) >= 0) {
            throw new UnbalancedJournalException(
                totalDebit:  $this->cents($exactDebit),
                totalCredit: $this->cents($exactCredit),
            );
        }

        $this->lines = $this->toCents(array_values($lines));

        if (count($this->lines) < 2) {
            throw new EmptyJournalException(count($this->lines));
        }
    }

    /**
     * Each line rounded half up to whole cents (a line that rounds to
     * nothing carries no money and is left out), then any rounding gap
     * between the sides settled on the largest line of the side split into
     * more lines: one total (the mix's cost) stays exactly as given.
     *
     * @param  list<JournalLineDraft>  $lines
     * @return list<JournalLineDraft>
     */
    private function toCents(array $lines): array
    {
        $rounded = [];
        foreach ($lines as $line) {
            $cents = $this->cents($line->amount());
            if (bccomp($cents, '0', 2) > 0) {
                $rounded[] = $cents === $line->amount() ? $line : $line->withAmount($cents);
            }
        }

        $debit = '0';
        $credit = '0';
        foreach ($rounded as $line) {
            $line->isDebit() ? $debit = bcadd($debit, $line->amount(), 2) : $credit = bcadd($credit, $line->amount(), 2);
        }
        $gap = bcsub($debit, $credit, 2);
        if (bccomp($gap, '0', 2) === 0) {
            return $rounded;
        }

        // Settle on the side with more lines (credits on a tie): raise it when
        // it is the short side, lower it when it is the long one.
        $debitLines = count(array_filter($rounded, fn ($l) => $l->isDebit()));
        $adjustDebits = $debitLines > count($rounded) - $debitLines;
        $debitsLong = bccomp($gap, '0', 2) > 0;
        $delta = ($adjustDebits === $debitsLong) ? '-'.ltrim($gap, '-') : ltrim($gap, '-');

        $largest = null;
        foreach ($rounded as $i => $line) {
            if ($line->isDebit() === $adjustDebits
                && ($largest === null || bccomp($line->amount(), $rounded[$largest]->amount(), 2) > 0)) {
                $largest = $i;
            }
        }
        if ($largest !== null) {
            $rounded[$largest] = $rounded[$largest]->withAmount(bcadd($rounded[$largest]->amount(), $delta, 2));
        }

        return $rounded;
    }

    /** Half up to 2 decimals, the way the database stores money. */
    private function cents(string $amount): string
    {
        return bcadd($amount, bccomp($amount, '0', 8) >= 0 ? '0.005' : '-0.005', 2);
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
