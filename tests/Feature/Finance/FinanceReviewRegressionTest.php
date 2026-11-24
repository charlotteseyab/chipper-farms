<?php

namespace Tests\Feature\Finance;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Exceptions\CurrencyMismatchException;
use App\Finance\Exceptions\InvalidAccountException;
use App\Finance\Exceptions\PeriodLockedException;
use App\Finance\Exceptions\PeriodNotOpenException;
use App\Finance\Posting\PeriodResolver;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\PeriodLockDate;
use App\Services\Finance\JournalApprovalService;
use App\Services\Finance\JournalReversalService;
use App\Services\Finance\ManualJournalService;
use App\Services\Finance\Reporting\LedgerBalanceQuery;
use App\Services\Finance\Reporting\TrialBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanceReviewRegressionTest extends TestCase
{
    use FinanceTestSetup;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->travelTo(Carbon::parse('2026-05-20'));
    }

    private function draft(): JournalBatchDraft
    {
        return new JournalBatchDraft(batchType: 'manual_journal', postingDate: CarbonImmutable::parse('2026-05-15'), journals: [
            new JournalDraft(journalType: 'manual', lines: [
                JournalLineDraft::debit($this->cashAccount->id, '100'),
                JournalLineDraft::credit($this->revenueAccount->id, '100'),
            ]),
        ]);
    }

    public function test_reversed_original_remains_in_historical_and_current_ledger(): void
    {
        $batch = app(PostingEngine::class)->post($this->draft(), $this->user);
        app(JournalReversalService::class)->reverse($batch, $this->user, 'Correct entry', CarbonImmutable::parse('2026-05-20'));
        foreach (['2026-05-16' => 100, '2026-05-21' => 0] as $date => $expected) {
            $tb = app(TrialBalanceService::class)->generate(null, Carbon::parse($date));
            $this->assertEquals($expected, $tb['rows']->firstWhere('account_id', $this->cashAccount->id)->net);
            $this->assertTrue($tb['totals']['balanced']);
            $row = app(LedgerBalanceQuery::class)->aggregate(null, Carbon::parse($date))->get($this->cashAccount->id);
            $this->assertEquals($expected, (float) $row->total_debit - (float) $row->total_credit);
        }
    }

    public function test_rejected_draft_never_affects_ledger(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->draft(), $this->user);
        app(JournalApprovalService::class)->reject($batch, $this->user, 'Wrong entry');
        $tb = app(TrialBalanceService::class)->generate(null, Carbon::parse('2026-05-21'));
        $this->assertEquals(0, $tb['totals']['debit']);
        $this->assertTrue(app(LedgerBalanceQuery::class)->aggregate(null, Carbon::parse('2026-05-21'))->isEmpty());
    }

    public function test_stale_model_cannot_reverse_manual_batch_twice(): void
    {
        $batch = app(PostingEngine::class)->post($this->draft(), $this->user);
        $stale = $batch->fresh();
        $one = app(JournalReversalService::class)->reverse($batch, $this->user, 'One reversal');
        try {
            app(JournalReversalService::class)->reverse($stale, $this->user, 'Duplicate');
            $this->fail('The duplicate reversal must be refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('already reversed', $e->getMessage());
        }
        $this->assertSame(1, JournalBatch::where('reverses_batch_id', $batch->id)->count());
        $this->assertSame($one->id, $batch->fresh()->reversed_by_batch_id);
    }

    public function test_approval_rechecks_account_activity(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->draft(), $this->user);
        $this->cashAccount->update(['is_active' => false]);
        $this->expectException(InvalidAccountException::class);
        app(JournalApprovalService::class)->approve($batch, $this->user);
    }

    public function test_approval_rechecks_control_account_restriction(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->draft(), $this->user);
        $this->cashAccount->update(['allow_manual_journal' => false]);
        $this->expectException(InvalidAccountException::class);
        app(JournalApprovalService::class)->approve($batch, $this->user);
    }

    public function test_approval_rechecks_account_currency(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->draft(), $this->user);
        $this->cashAccount->update(['currency_code' => 'USD']);
        $this->expectException(CurrencyMismatchException::class);
        app(JournalApprovalService::class)->approve($batch, $this->user);
    }

    public function test_stale_draft_cannot_be_rejected_after_approval(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->draft(), $this->user);
        app(JournalApprovalService::class)->approve($batch, $this->user);
        $this->expectException(\DomainException::class);
        app(JournalApprovalService::class)->reject($batch, $this->user, 'Stale request');
    }

    public function test_soft_lock_bypass_does_not_mask_an_earlier_hard_lock(): void
    {
        foreach (['2026-05-10' => true, '2026-05-20' => false] as $date => $hard) {
            PeriodLockDate::create(['organization_id' => $this->org->id, 'lock_date' => $date,
                'is_hard' => $hard, 'is_active' => true, 'locked_by_user_id' => $this->user->id, 'locked_at' => now()]);
        }
        $this->assertSame($this->period->id, app(PeriodResolver::class)->resolveForPosting(CarbonImmutable::parse('2026-05-15'), true)->id);
        $this->expectException(PeriodLockedException::class);
        app(PeriodResolver::class)->resolveForPosting(CarbonImmutable::parse('2026-05-01'), true);
    }

    public function test_closed_fiscal_year_blocks_posting_into_open_period(): void
    {
        $this->year->update(['status' => 'closed']);
        $this->expectException(PeriodNotOpenException::class);
        app(PostingEngine::class)->post($this->draft(), $this->user);
    }

    public function test_locked_fiscal_year_blocks_approval_into_open_period(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->draft(), $this->user);
        $this->year->update(['status' => 'locked']);
        $this->expectException(PeriodNotOpenException::class);
        app(JournalApprovalService::class)->approve($batch, $this->user);
    }
}
