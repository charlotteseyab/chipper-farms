<?php

namespace Tests\Feature\Finance\Reporting;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Services\Finance\Reporting\IncomeStatementService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class IncomeStatementServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_computes_gross_operating_and_net_profit(): void
    {
        // Need a COGS account + an opex account (expenseAccount=6010 is opex).
        $cogs = Account::create([
            'organization_id' => $this->org->id, 'code' => '5110', 'name' => 'Cost of Pigs Sold',
            'account_type' => Account::TYPE_EXPENSE, 'account_subtype' => 'cogs',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
        // Tag the seeded expense account as opex explicitly.
        $this->expenseAccount->update(['account_subtype' => 'opex']);
        $this->revenueAccount->update(['account_subtype' => 'revenue']);

        // Revenue 1000 (Cr revenue / Dr cash)
        $this->postJournal('2026-05-10', [
            JournalLineDraft::debit($this->cashAccount->id, '1000.00'),
            JournalLineDraft::credit($this->revenueAccount->id, '1000.00'),
        ]);
        // COGS 400 (Dr cogs / Cr cash)
        $this->postJournal('2026-05-11', [
            JournalLineDraft::debit($cogs->id, '400.00'),
            JournalLineDraft::credit($this->cashAccount->id, '400.00'),
        ]);
        // Opex 150 (Dr opex / Cr cash)
        $this->postJournal('2026-05-12', [
            JournalLineDraft::debit($this->expenseAccount->id, '150.00'),
            JournalLineDraft::credit($this->cashAccount->id, '150.00'),
        ]);

        $svc = app(IncomeStatementService::class);
        $stmt = $svc->generate(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'));

        $this->assertSame('1000.00', $stmt['totals']['revenue']);
        $this->assertSame('400.00', $stmt['totals']['cost_of_sales']);
        $this->assertSame('600.00', $stmt['gross_profit']);
        $this->assertSame('150.00', $stmt['totals']['opex']);
        $this->assertSame('450.00', $stmt['operating_profit']);
        $this->assertSame('450.00', $stmt['net_profit']);
    }

    public function test_comparison_window_populated(): void
    {
        $this->revenueAccount->update(['account_subtype' => 'revenue']);

        // April open period so April postings resolve.
        \App\Models\FiscalPeriod::create([
            'organization_id' => $this->org->id,
            'fiscal_year_id'  => $this->year->id,
            'period_number'   => 4,
            'name'            => 'April 2026',
            'start_date'      => '2026-04-01',
            'end_date'        => '2026-04-30',
            'status'          => \App\Models\FiscalPeriod::STATUS_OPEN,
        ]);
        // April revenue 500, May revenue 800.
        $this->postJournal('2026-04-15', [
            JournalLineDraft::debit($this->cashAccount->id, '500.00'),
            JournalLineDraft::credit($this->revenueAccount->id, '500.00'),
        ]);
        $this->postJournal('2026-05-15', [
            JournalLineDraft::debit($this->cashAccount->id, '800.00'),
            JournalLineDraft::credit($this->revenueAccount->id, '800.00'),
        ]);

        $svc = app(IncomeStatementService::class);
        $stmt = $svc->generate(
            Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'),
            Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'),
        );

        $this->assertSame('800.00', $stmt['totals']['revenue']);
        $this->assertNotNull($stmt['comparison']);
        $this->assertSame('500.00', $stmt['comparison']['revenue']);
    }

    /**
     * @param  array<int, JournalLineDraft>  $lines
     */
    protected function postJournal(string $date, array $lines): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'manual_journal',
            postingDate: CarbonImmutable::parse($date),
            journals: [new JournalDraft(journalType: 'manual', lines: $lines)],
        );
        app(PostingEngine::class)->post($draft, $this->user);
    }
}
