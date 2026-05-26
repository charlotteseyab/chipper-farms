<?php

namespace Tests\Feature\Finance\Close;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Models\Finance\YearEndClosing;
use App\Models\FiscalYear;
use App\Services\Finance\Close\Exceptions\PeriodCloseException;
use App\Services\Finance\Close\YearEndCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class YearEndCloseServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Account $retainedEarnings;
    protected Account $clearing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->revenueAccount->update(['account_subtype' => 'revenue']);
        $this->expenseAccount->update(['account_subtype' => 'opex']);

        $this->retainedEarnings = Account::create([
            'organization_id' => $this->org->id, 'code' => '3200', 'name' => 'Retained Earnings',
            'account_type' => Account::TYPE_EQUITY, 'account_subtype' => 'equity',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
        $this->clearing = Account::create([
            'organization_id' => $this->org->id, 'code' => '3300', 'name' => 'Current Year Earnings (Clearing)',
            'account_type' => Account::TYPE_EQUITY, 'account_subtype' => 'equity',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
        ]);
    }

    public function test_year_end_close_profit_credits_retained_earnings(): void
    {
        // Revenue 1000, expense 300 => net profit 700.
        $this->postBalanced('2026-05-10', $this->cashAccount->id, $this->revenueAccount->id, '1000.00');
        $this->postBalanced('2026-05-11', $this->expenseAccount->id, $this->cashAccount->id, '300.00');

        $closing = app(YearEndCloseService::class)->close($this->year, $this->user);

        $this->assertSame(YearEndClosing::STATUS_COMPLETED, $closing->status);
        $this->assertSame('1000.0000', $closing->total_revenue);
        $this->assertSame('300.0000', $closing->total_expense);
        $this->assertSame('700.0000', $closing->net_profit);
        $this->assertNotNull($closing->closing_journal_batch_id);

        // RE should now carry a 700 credit balance from the closing entry.
        $reLines = JournalLine::where('account_id', $this->retainedEarnings->id)->get();
        $credit = $reLines->sum(fn ($l) => (float) $l->credit);
        $debit  = $reLines->sum(fn ($l) => (float) $l->debit);
        $this->assertEqualsWithDelta(700.0, $credit - $debit, 0.001);

        // Year marked closed.
        $this->assertSame(FiscalYear::STATUS_CLOSED, $this->year->fresh()->status);
    }

    public function test_year_end_close_loss_debits_retained_earnings(): void
    {
        // Revenue 200, expense 500 => net loss 300.
        $this->postBalanced('2026-05-10', $this->cashAccount->id, $this->revenueAccount->id, '200.00');
        $this->postBalanced('2026-05-11', $this->expenseAccount->id, $this->cashAccount->id, '500.00');

        $closing = app(YearEndCloseService::class)->close($this->year, $this->user);

        $this->assertSame('-300.0000', $closing->net_profit);

        $reLines = JournalLine::where('account_id', $this->retainedEarnings->id)->get();
        $credit = $reLines->sum(fn ($l) => (float) $l->credit);
        $debit  = $reLines->sum(fn ($l) => (float) $l->debit);
        // Loss => RE debited 300.
        $this->assertEqualsWithDelta(300.0, $debit - $credit, 0.001);
    }

    public function test_closing_journal_is_balanced(): void
    {
        $this->postBalanced('2026-05-10', $this->cashAccount->id, $this->revenueAccount->id, '1000.00');
        $this->postBalanced('2026-05-11', $this->expenseAccount->id, $this->cashAccount->id, '300.00');

        $closing = app(YearEndCloseService::class)->close($this->year, $this->user);

        $lines = JournalLine::whereIn('journal_id',
            Journal::whereIn('journal_batch_id', [$closing->closing_journal_batch_id])->pluck('id')
        )->get();

        $debits  = $lines->sum(fn ($l) => (float) $l->debit);
        $credits = $lines->sum(fn ($l) => (float) $l->credit);
        $this->assertEqualsWithDelta($debits, $credits, 0.001, 'Closing journal must balance.');
    }

    public function test_reverse_reopens_year(): void
    {
        $this->postBalanced('2026-05-10', $this->cashAccount->id, $this->revenueAccount->id, '1000.00');
        $svc = app(YearEndCloseService::class);
        $svc->close($this->year, $this->user);

        $closing = $svc->reverse($this->year, $this->user, 'Audit adjustment needed.');

        $this->assertSame(YearEndClosing::STATUS_REVERSED, $closing->status);
        $this->assertNotNull($closing->reversal_journal_batch_id);
        $this->assertSame(FiscalYear::STATUS_OPEN, $this->year->fresh()->status);
    }

    public function test_double_close_refused(): void
    {
        $this->postBalanced('2026-05-10', $this->cashAccount->id, $this->revenueAccount->id, '1000.00');
        $svc = app(YearEndCloseService::class);
        $svc->close($this->year, $this->user);

        $this->expectException(PeriodCloseException::class);
        $svc->close($this->year, $this->user);
    }

    protected function postBalanced(string $date, int $debitAccount, int $creditAccount, string $amount): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'manual_journal',
            postingDate: CarbonImmutable::parse($date),
            journals: [new JournalDraft(journalType: 'manual', lines: [
                JournalLineDraft::debit($debitAccount, $amount),
                JournalLineDraft::credit($creditAccount, $amount),
            ])],
        );
        app(PostingEngine::class)->post($draft, $this->user);
    }
}
