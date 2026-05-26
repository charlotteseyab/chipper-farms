<?php

namespace Tests\Feature\Finance\Reporting;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Services\Finance\Reporting\CashFlowService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class CashFlowServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Account $cashParent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // The cash flow service identifies cash accounts as children of the
        // '1000 Cash and Bank' parent. Wire that up.
        $this->cashParent = Account::create([
            'organization_id' => $this->org->id, 'code' => '1000', 'name' => 'Cash and Bank',
            'account_type' => Account::TYPE_ASSET, 'account_subtype' => 'current_asset',
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
        ]);
        $this->cashAccount->update(['account_subtype' => 'current_asset', 'parent_id' => $this->cashParent->id]);
        $this->arAccount->update(['account_subtype' => 'current_asset']);
        $this->revenueAccount->update(['account_subtype' => 'revenue']);
    }

    public function test_cash_sale_reconciles(): void
    {
        // Cash sale: Dr Cash 600 / Cr Revenue 600.
        $this->postJournal('2026-05-10', [
            JournalLineDraft::debit($this->cashAccount->id, '600.00'),
            JournalLineDraft::credit($this->revenueAccount->id, '600.00'),
        ]);

        $cf = app(CashFlowService::class)->generate(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'));

        // Net profit 600, no working capital movement (cash is excluded),
        // so operating = 600, net change = 600, actual cash movement = 600.
        $this->assertSame('600.00', $cf['net_profit']);
        $this->assertSame('600.00', $cf['net_change_in_cash']);
        $this->assertSame('600.00', $cf['actual_cash_movement']);
        $this->assertTrue($cf['reconciled'], 'Variance: ' . $cf['variance']);
    }

    public function test_credit_sale_then_collection_reconciles(): void
    {
        // Credit sale in May: Dr AR 1000 / Cr Revenue 1000 (no cash yet).
        $this->postJournal('2026-05-05', [
            JournalLineDraft::debit($this->arAccount->id, '1000.00'),
            JournalLineDraft::credit($this->revenueAccount->id, '1000.00'),
        ]);
        // Collect 400 of it: Dr Cash 400 / Cr AR 400.
        $this->postJournal('2026-05-20', [
            JournalLineDraft::debit($this->cashAccount->id, '400.00'),
            JournalLineDraft::credit($this->arAccount->id, '400.00'),
        ]);

        $cf = app(CashFlowService::class)->generate(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'));

        // Net profit 1000; AR increased by 600 net (1000 - 400 collected) =>
        // working capital uses 600 of cash; operating = 1000 - 600 = 400.
        // Actual cash movement = +400.
        $this->assertSame('1000.00', $cf['net_profit']);
        $this->assertSame('400.00', $cf['actual_cash_movement']);
        $this->assertTrue($cf['reconciled'], 'Variance: ' . $cf['variance']);
    }

    /** @param array<int,JournalLineDraft> $lines */
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
