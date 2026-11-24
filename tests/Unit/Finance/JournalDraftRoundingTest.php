<?php

namespace Tests\Unit\Finance;

use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Exceptions\UnbalancedJournalException;
use PHPUnit\Framework\TestCase;

/**
 * Journal lines are stored to whole cents, rounding half up. A draft must
 * round the same way, so its totals match its lines and a balanced posting
 * built from finer amounts is never refused as "unbalanced".
 */
class JournalDraftRoundingTest extends TestCase
{
    public function test_a_feed_mix_split_across_ingredients_balances_in_whole_cents(): void
    {
        // The failing mix: 70.79 in total; the ingredients round up to 70.80.
        $draft = new JournalDraft('feed_production', [
            JournalLineDraft::debit(1, '70.7900'),
            JournalLineDraft::credit(2, '35.3950'),
            JournalLineDraft::credit(3, '20.1975'),
            JournalLineDraft::credit(4, '15.1975'),
        ]);

        $this->assertSame('70.79', $draft->totalDebit());
        $this->assertSame('70.79', $draft->totalCredit());
        // The mix's total stays exact; the cent comes off the largest ingredient.
        $this->assertSame(['70.79', '35.39', '20.20', '15.20'], array_map(fn ($l) => $l->amount(), $draft->lines));
    }

    public function test_a_single_credit_against_split_debits_stays_exact(): void
    {
        $draft = new JournalDraft('manual', [
            JournalLineDraft::debit(1, '3.3350'),
            JournalLineDraft::debit(2, '3.3350'),
            JournalLineDraft::debit(3, '3.3300'),
            JournalLineDraft::credit(4, '10.00'),
        ]);

        $this->assertSame(['3.33', '3.34', '3.33', '10.00'], array_map(fn ($l) => $l->amount(), $draft->lines));
    }

    public function test_totals_equal_the_lines_after_half_up_rounding(): void
    {
        // Truncation would make this 12.54 while the stored line is 12.55.
        $draft = new JournalDraft('feed_issue', [
            JournalLineDraft::debit(1, '12.5450'),
            JournalLineDraft::credit(2, '12.5450'),
        ]);

        $this->assertSame('12.55', $draft->totalDebit());
        $this->assertSame('12.55', $draft->lines[0]->amount());
        $this->assertSame('12.55', $draft->lines[1]->amount());
    }

    public function test_a_real_imbalance_of_a_cent_or_more_is_still_refused(): void
    {
        $this->expectException(UnbalancedJournalException::class);

        new JournalDraft('manual', [
            JournalLineDraft::debit(1, '100.00'),
            JournalLineDraft::credit(2, '99.99'),
        ]);
    }

    public function test_amounts_already_in_cents_are_left_alone(): void
    {
        $lines = [JournalLineDraft::debit(1, '250.00'), JournalLineDraft::credit(2, '200.00'), JournalLineDraft::credit(3, '50.00')];

        $draft = new JournalDraft('manual', $lines);

        $this->assertSame($lines, $draft->lines, 'Nothing rounded, nothing moved.');
    }

    public function test_a_line_that_rounds_to_nothing_is_dropped(): void
    {
        $draft = new JournalDraft('feed_production', [
            JournalLineDraft::debit(1, '10.0040'),
            JournalLineDraft::credit(2, '10.0000'),
            JournalLineDraft::credit(3, '0.0040'),
        ]);

        $this->assertCount(2, $draft->lines);
        $this->assertSame('10.00', $draft->totalDebit());
        $this->assertSame('10.00', $draft->totalCredit());
    }
}
