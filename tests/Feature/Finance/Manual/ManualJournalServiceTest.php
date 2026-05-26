<?php

namespace Tests\Feature\Finance\Manual;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Exceptions\InvalidAccountException;
use App\Models\Finance\JournalBatch;
use App\Models\FiscalPeriod;
use App\Services\Finance\ManualJournalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class ManualJournalServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_propose_creates_draft_batch_with_journals_and_lines(): void
    {
        $draft = $this->basicManualDraft();

        $batch = app(ManualJournalService::class)->propose($draft, $this->user);

        $this->assertSame(JournalBatch::STATUS_DRAFT, $batch->status);
        $this->assertSame($this->user->id, $batch->proposed_by_user_id);
        $this->assertNotNull($batch->proposed_at);
        $this->assertNull($batch->posted_at);
        $this->assertSame(1, $batch->journals()->count());
        $this->assertSame(2, $batch->journals->first()->lines()->count());
    }

    public function test_propose_rejects_auto_drafts(): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'auto_pig_sale',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'sales_revenue',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '100.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
            idempotencyKey: 'auto-key',
            postingPurpose: 'revenue',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('idempotency_key set');
        app(ManualJournalService::class)->propose($draft, $this->user);
    }

    public function test_propose_rejects_control_account_lines(): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'manual_journal',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'manual',
                    lines: [
                        JournalLineDraft::debit($this->arAccount->id, '100.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
        );

        $this->expectException(InvalidAccountException::class);
        app(ManualJournalService::class)->propose($draft, $this->user);
    }

    public function test_propose_succeeds_against_closed_period(): void
    {
        // Per design: propose does NOT check period status. Approve does.
        $this->period->update(['status' => FiscalPeriod::STATUS_CLOSED]);

        $batch = app(ManualJournalService::class)->propose($this->basicManualDraft(), $this->user);

        $this->assertSame(JournalBatch::STATUS_DRAFT, $batch->status);
    }

    public function test_propose_and_approve_in_one_step_posts(): void
    {
        $draft = $this->basicManualDraft();

        $batch = app(ManualJournalService::class)->proposeAndApprove($draft, $this->user);

        $this->assertSame(JournalBatch::STATUS_POSTED, $batch->status);
        $this->assertSame($this->user->id, $batch->posted_by_user_id);
        $this->assertNotNull($batch->posted_at);
    }

    public function test_propose_and_approve_rejects_auto_drafts(): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'auto_pig_sale',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'sales_revenue',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '100.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
            idempotencyKey: 'auto-key',
            postingPurpose: 'revenue',
        );

        $this->expectException(\InvalidArgumentException::class);
        app(ManualJournalService::class)->proposeAndApprove($draft, $this->user);
    }

    private function basicManualDraft(): JournalBatchDraft
    {
        return new JournalBatchDraft(
            batchType: 'manual_journal',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'manual',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '100.00', 'Manual debit'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00', 'Manual credit'),
                    ],
                    description: 'Manual entry test',
                ),
            ],
            description: 'Test manual batch',
        );
    }
}
