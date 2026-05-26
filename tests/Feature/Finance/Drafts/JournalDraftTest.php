<?php

namespace Tests\Feature\Finance\Drafts;

use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Exceptions\EmptyJournalException;
use App\Finance\Exceptions\UnbalancedJournalException;
use Tests\TestCase;

class JournalDraftTest extends TestCase
{
    public function test_balanced_two_line_journal_constructs(): void
    {
        $draft = new JournalDraft(
            journalType: 'sales_revenue',
            lines: [
                JournalLineDraft::debit(1, '100.00'),
                JournalLineDraft::credit(2, '100.00'),
            ],
        );

        $this->assertSame(2, $draft->lineCount());
        $this->assertSame('100.00', $draft->totalDebit());
        $this->assertSame('100.00', $draft->totalCredit());
    }

    public function test_balanced_multi_line_journal_constructs(): void
    {
        $draft = new JournalDraft(
            journalType: 'sales_revenue',
            lines: [
                JournalLineDraft::debit(1, '100.00'),
                JournalLineDraft::debit(2, '50.00'),
                JournalLineDraft::credit(3, '120.00'),
                JournalLineDraft::credit(4, '30.00'),
            ],
        );

        $this->assertSame(4, $draft->lineCount());
        $this->assertSame('150.00', $draft->totalDebit());
        $this->assertSame('150.00', $draft->totalCredit());
    }

    public function test_zero_lines_rejected(): void
    {
        $this->expectException(EmptyJournalException::class);
        new JournalDraft(journalType: 'sales_revenue', lines: []);
    }

    public function test_one_line_rejected(): void
    {
        $this->expectException(EmptyJournalException::class);
        new JournalDraft(
            journalType: 'sales_revenue',
            lines: [JournalLineDraft::debit(1, '100.00')],
        );
    }

    public function test_unbalanced_journal_rejected(): void
    {
        $this->expectException(UnbalancedJournalException::class);

        new JournalDraft(
            journalType: 'sales_revenue',
            lines: [
                JournalLineDraft::debit(1, '100.00'),
                JournalLineDraft::credit(2, '50.00'),
            ],
        );
    }

    public function test_unbalanced_journal_includes_totals_in_exception(): void
    {
        try {
            new JournalDraft(
                journalType: 'sales_revenue',
                lines: [
                    JournalLineDraft::debit(1, '100.00'),
                    JournalLineDraft::credit(2, '99.99'),
                ],
            );
            $this->fail('Expected UnbalancedJournalException');
        } catch (UnbalancedJournalException $e) {
            $this->assertSame('100.00', $e->totalDebit);
            $this->assertSame('99.99', $e->totalCredit);
        }
    }

    public function test_non_line_draft_element_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new JournalDraft(
            journalType: 'sales_revenue',
            lines: [
                JournalLineDraft::debit(1, '100.00'),
                ['not a draft'],
            ],
        );
    }

    public function test_decimal_precision_preserved(): void
    {
        $draft = new JournalDraft(
            journalType: 'sales_revenue',
            lines: [
                JournalLineDraft::debit(1, '0.10'),
                JournalLineDraft::debit(2, '0.20'),
                JournalLineDraft::credit(3, '0.30'),
            ],
        );

        // Float arithmetic: 0.10 + 0.20 == 0.30 but in floats, 0.1+0.2 = 0.30000000000000004
        // bcadd with scale 2 gives '0.30' exactly.
        $this->assertSame('0.30', $draft->totalDebit());
        $this->assertSame('0.30', $draft->totalCredit());
    }
}
