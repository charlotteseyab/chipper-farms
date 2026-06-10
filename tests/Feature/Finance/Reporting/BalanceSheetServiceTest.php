<?php

namespace Tests\Feature\Finance\Reporting;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Services\Finance\Reporting\BalanceSheetService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class BalanceSheetServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->cashAccount->update(['account_subtype' => 'current_asset']);
        $this->arAccount->update(['account_subtype' => 'current_asset']);
        $this->revenueAccount->update(['account_subtype' => 'revenue']);
    }

    public function test_balance_sheet_balances_after_a_sale(): void
    {
        // A credit sale: Dr AR 1000 / Cr Revenue 1000.
        // Assets +1000 (AR), Equity +1000 (current-year earnings via revenue).
        $this->postJournal('2026-05-10', [
            JournalLineDraft::debit($this->arAccount->id, '1000.00'),
            JournalLineDraft::credit($this->revenueAccount->id, '1000.00'),
        ], 'report-credit-sale');

        $bs = app(BalanceSheetService::class)->generate(Carbon::parse('2026-05-31'));

        $this->assertTrue($bs['balanced'], 'Balance sheet should balance. Variance: ' . $bs['variance']);
        $this->assertSame('1000.00', $bs['assets']['total']);
        $this->assertSame('1000.00', $bs['equity']['current_year_earnings']);
        $this->assertSame('0.00', $bs['liabilities']['total']);
        $this->assertSame('1000.00', $bs['equity']['total']);
    }

    public function test_balance_sheet_balances_with_liability(): void
    {
        // Buy inventory on credit: Dr Inventory asset / Cr AP liability.
        $inv = Account::firstOrCreate([
            'organization_id' => $this->org->id, 'code' => '1210',
        ], [
            'name' => 'Raw Materials Inventory',
            'account_type' => Account::TYPE_ASSET, 'account_subtype' => 'current_asset',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
        $inv->update(['account_subtype' => 'current_asset']);

        $ap = Account::firstOrCreate([
            'organization_id' => $this->org->id, 'code' => '2010',
        ], [
            'name' => 'Trade Payables',
            'account_type' => Account::TYPE_LIABILITY, 'account_subtype' => 'current_liability',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);
        $ap->update(['account_subtype' => 'current_liability']);

        $this->postJournal('2026-05-10', [
            JournalLineDraft::debit($inv->id, '750.00'),
            JournalLineDraft::credit($ap->id, '750.00'),
        ], 'report-credit-purchase');

        $bs = app(BalanceSheetService::class)->generate(Carbon::parse('2026-05-31'));

        $this->assertTrue($bs['balanced'], 'Variance: ' . $bs['variance']);
        $this->assertSame('750.00', $bs['assets']['total']);
        $this->assertSame('750.00', $bs['liabilities']['total']);
        $this->assertSame('0.00', $bs['equity']['total']);
    }

    /** @param array<int,JournalLineDraft> $lines */
    protected function postJournal(string $date, array $lines, ?string $idempotencyKey = null): void
    {
        $draft = new JournalBatchDraft(
            batchType: $idempotencyKey ? 'report_fixture' : 'manual_journal',
            postingDate: CarbonImmutable::parse($date),
            journals: [new JournalDraft(journalType: 'manual', lines: $lines)],
            idempotencyKey: $idempotencyKey,
            postingPurpose: $idempotencyKey ? 'report_fixture' : null,
        );
        app(PostingEngine::class)->post($draft, $this->user);
    }
}
