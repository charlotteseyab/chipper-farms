<?php

namespace Tests\Feature\Finance\Manual;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalBatch;
use App\Models\FiscalPeriod;
use App\Services\Finance\JournalApprovalService;
use App\Services\Finance\ManualJournalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class JournalApprovalServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_approve_flips_draft_to_posted(): void
    {
        $draft = $this->makeDraft();
        $batch = app(ManualJournalService::class)->propose($draft, $this->user);
        $this->assertSame(JournalBatch::STATUS_DRAFT, $batch->status);

        $approved = app(JournalApprovalService::class)->approve($batch, $this->user);

        $this->assertSame(JournalBatch::STATUS_POSTED, $approved->status);
        $this->assertSame($this->user->id, $approved->posted_by_user_id);
        $this->assertNotNull($approved->posted_at);
    }

    public function test_approve_flips_journals_to_posted(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->makeDraft(), $this->user);
        $this->assertSame(Journal::STATUS_DRAFT, $batch->journals->first()->status);

        $approved = app(JournalApprovalService::class)->approve($batch, $this->user);

        $this->assertSame(Journal::STATUS_POSTED, $approved->journals->first()->status);
    }

    public function test_approve_rejects_already_posted_batch(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->makeDraft(), $this->user);
        app(JournalApprovalService::class)->approve($batch, $this->user);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('expected \'draft\'');
        app(JournalApprovalService::class)->approve($batch->fresh(), $this->user);
    }

    public function test_approve_fails_if_period_closed_at_approval_time(): void
    {
        // Propose while period is open.
        $batch = app(ManualJournalService::class)->propose($this->makeDraft(), $this->user);

        // Period closes between propose and approve.
        $this->period->update(['status' => FiscalPeriod::STATUS_CLOSED]);

        $this->expectException(\App\Finance\Exceptions\PeriodNotOpenException::class);
        app(JournalApprovalService::class)->approve($batch->fresh(), $this->user);
    }

    public function test_reject_marks_draft_as_reversed(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->makeDraft(), $this->user);

        $rejected = app(JournalApprovalService::class)
            ->reject($batch, $this->user, 'Wrong account selected');

        $this->assertSame(JournalBatch::STATUS_REVERSED, $rejected->status);
        $this->assertStringContainsString('Wrong account selected', $rejected->reversal_reason);
        $this->assertNotNull($rejected->reversed_at);
    }

    public function test_reject_cannot_be_called_on_posted_batch(): void
    {
        $batch = app(ManualJournalService::class)->propose($this->makeDraft(), $this->user);
        app(JournalApprovalService::class)->approve($batch, $this->user);

        $this->expectException(\DomainException::class);
        app(JournalApprovalService::class)->reject($batch->fresh(), $this->user, 'too late');
    }

    private function makeDraft(): JournalBatchDraft
    {
        return new JournalBatchDraft(
            batchType: 'manual_journal',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'manual',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '100.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
        );
    }
}
