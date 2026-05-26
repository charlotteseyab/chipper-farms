<?php

namespace Tests\Feature\Finance\Reversal;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalBatch;
use App\Services\Finance\JournalReversalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class JournalReversalServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_reverse_creates_reversal_batch_with_swapped_lines(): void
    {
        $original = app(PostingEngine::class)->post($this->makeDraft('orig-1'), $this->user);
        $origJournal = $original->journals->first();

        $origDebit  = $origJournal->lines->first(fn ($l) => $l->isDebit());
        $origCredit = $origJournal->lines->first(fn ($l) => $l->isCredit());

        $reversal = app(JournalReversalService::class)
            ->reverse($original, $this->user, 'Test reversal');

        $reversalJournal = $reversal->journals->first();
        $reversalDebit  = $reversalJournal->lines->first(fn ($l) => $l->isDebit());
        $reversalCredit = $reversalJournal->lines->first(fn ($l) => $l->isCredit());

        // The reversal's debit account == the original's credit account
        // and vice versa. Amounts equal but mirrored.
        $this->assertSame($origCredit->account_id, $reversalDebit->account_id);
        $this->assertSame($origDebit->account_id,  $reversalCredit->account_id);
        $this->assertSame((string) $origCredit->credit, (string) $reversalDebit->debit);
        $this->assertSame((string) $origDebit->debit,   (string) $reversalCredit->credit);
    }

    public function test_reverse_marks_original_as_reversed(): void
    {
        $original = app(PostingEngine::class)->post($this->makeDraft('orig-2'), $this->user);

        $reversal = app(JournalReversalService::class)
            ->reverse($original, $this->user, 'oops');

        $original->refresh();
        $this->assertSame(JournalBatch::STATUS_REVERSED, $original->status);
        $this->assertSame($reversal->id, $original->reversed_by_batch_id);
        $this->assertSame($this->user->id, $original->reversed_by_user_id);
        $this->assertSame('oops', $original->reversal_reason);
        $this->assertNotNull($original->reversed_at);

        $this->assertSame(Journal::STATUS_REVERSED, $original->journals->first()->fresh()->status);
    }

    public function test_reverse_links_back_via_reverses_batch_id(): void
    {
        $original = app(PostingEngine::class)->post($this->makeDraft('orig-3'), $this->user);

        $reversal = app(JournalReversalService::class)
            ->reverse($original, $this->user, 'fix typo');

        $this->assertSame($original->id, $reversal->reverses_batch_id);
    }

    public function test_reverse_uses_today_when_no_date_specified(): void
    {
        $original = app(PostingEngine::class)->post($this->makeDraft('orig-4'), $this->user);

        $reversal = app(JournalReversalService::class)
            ->reverse($original, $this->user, 'today reversal');

        $this->assertSame(now()->toDateString(), $reversal->posting_date->toDateString());
    }

    public function test_reverse_with_custom_date(): void
    {
        $original = app(PostingEngine::class)->post($this->makeDraft('orig-5'), $this->user);

        $customDate = CarbonImmutable::parse('2026-05-20');
        $reversal = app(JournalReversalService::class)
            ->reverse($original, $this->user, 'custom date', $customDate);

        $this->assertSame('2026-05-20', $reversal->posting_date->toDateString());
    }

    public function test_reverse_rejects_already_reversed_batch(): void
    {
        $original = app(PostingEngine::class)->post($this->makeDraft('orig-6'), $this->user);
        app(JournalReversalService::class)->reverse($original, $this->user, 'first');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already reversed');
        app(JournalReversalService::class)->reverse($original->fresh(), $this->user, 'second');
    }

    public function test_reverse_rejects_draft_batch(): void
    {
        $batch = JournalBatch::create([
            'organization_id'  => $this->org->id,
            'batch_number'     => 'JB-DRAFT-001',
            'batch_type'       => 'manual_journal',
            'posting_date'     => '2026-05-15',
            'fiscal_period_id' => $this->period->id,
            'status'           => JournalBatch::STATUS_DRAFT,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('expected \'posted\'');
        app(JournalReversalService::class)->reverse($batch, $this->user, 'cant reverse draft');
    }

    public function test_reverse_creates_idempotency_record_with_reversal_suffix(): void
    {
        $original = app(PostingEngine::class)->post($this->makeDraft('orig-7'), $this->user);

        $reversal = app(JournalReversalService::class)
            ->reverse($original, $this->user, 'reverse me');

        $reversalPosting = $reversal->postings->first();
        $this->assertNotNull($reversalPosting);
        $this->assertStringEndsWith(':reversal', $reversalPosting->idempotency_key);
        $this->assertSame('orig-7:reversal', $reversalPosting->idempotency_key);
    }

    public function test_double_reverse_via_idempotency_key_rejected(): void
    {
        // If you somehow attempt to reverse the same batch twice via two
        // queue retries hitting at the same time, the idempotency UNIQUE
        // catches the second.
        $original = app(PostingEngine::class)->post($this->makeDraft('orig-8'), $this->user);

        app(JournalReversalService::class)->reverse($original, $this->user, 'first reverse');

        // Now manually try to invoke a second reverse — original is now status='reversed'
        // so it should be caught by the application-layer guard before idempotency.
        $this->expectException(\DomainException::class);
        app(JournalReversalService::class)->reverse($original->fresh(), $this->user, 'second reverse');
    }

    private function makeDraft(string $key): JournalBatchDraft
    {
        return new JournalBatchDraft(
            batchType: 'auto_pig_sale',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'sales_revenue',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '500.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '500.00'),
                    ],
                ),
            ],
            idempotencyKey: $key,
            postingPurpose: 'revenue',
        );
    }
}
