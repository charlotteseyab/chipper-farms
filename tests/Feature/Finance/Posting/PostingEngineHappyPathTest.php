<?php

namespace Tests\Feature\Finance\Posting;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Finance\JournalPosting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PostingEngineHappyPathTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_post_simple_balanced_journal_succeeds(): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'auto_pig_sale',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'sales_revenue',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '500.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '500.00'),
                    ],
                    description: 'Sale of pig #42',
                ),
            ],
            sourceModule: 'sales',
            sourceType: 'App\\Models\\PigSale',
            sourceId: 42,
            idempotencyKey: 'pig_sale.recorded:PigSale:42:revenue',
            postingPurpose: 'revenue',
        );

        $batch = app(PostingEngine::class)->post($draft, $this->user);

        $this->assertSame(JournalBatch::STATUS_POSTED, $batch->status);
        $this->assertNotNull($batch->posted_at);
        $this->assertSame($this->user->id, $batch->posted_by_user_id);
        $this->assertSame(1, $batch->journals()->count());

        $journal = $batch->journals->first();
        $this->assertSame('500.00', (string) $journal->total_debit);
        $this->assertSame('500.00', (string) $journal->total_credit);
        $this->assertSame(2, $journal->lines()->count());
    }

    public function test_post_writes_idempotency_record(): void
    {
        $draft = $this->makeRevenueDraft('pig_sale.recorded:PigSale:1:revenue');

        $batch = app(PostingEngine::class)->post($draft, $this->user);

        $posting = JournalPosting::where('idempotency_key', 'pig_sale.recorded:PigSale:1:revenue')->first();
        $this->assertNotNull($posting);
        $this->assertSame($batch->id, $posting->journal_batch_id);
        $this->assertSame('revenue', $posting->posting_purpose);
    }

    public function test_post_updates_period_audit_counters(): void
    {
        $this->assertNull($this->period->first_journal_posted_at);
        $this->assertSame(0, $this->period->posting_count);

        app(PostingEngine::class)->post($this->makeRevenueDraft('key1'), $this->user);

        $period = $this->period->fresh();
        $this->assertNotNull($period->first_journal_posted_at);
        $this->assertNotNull($period->last_journal_posted_at);
        $this->assertSame(1, $period->posting_count);

        // Second post bumps the count but not first_posted.
        app(PostingEngine::class)->post($this->makeRevenueDraft('key2'), $this->user);
        $period = $this->period->fresh();
        $this->assertSame(2, $period->posting_count);
    }

    public function test_post_persists_lines_with_correct_dimensions(): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'manual_journal',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'manual',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '100.00', 'Test debit', [
                            'reference' => 'REF-001',
                        ]),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00', 'Test credit'),
                    ],
                ),
            ],
        );

        $batch = app(PostingEngine::class)->post($draft, $this->user);

        $line = $batch->journals->first()->lines->first();
        $this->assertSame('REF-001', $line->reference);
        $this->assertSame('Test debit', $line->description);
    }

    public function test_post_assigns_sequential_journal_numbers(): void
    {
        $b1 = app(PostingEngine::class)->post($this->makeRevenueDraft('k1'), $this->user);
        $b2 = app(PostingEngine::class)->post($this->makeRevenueDraft('k2'), $this->user);

        $j1 = $b1->journals->first()->journal_number;
        $j2 = $b2->journals->first()->journal_number;

        $this->assertNotSame($j1, $j2);
        // Numbers follow JV-2026-NNNNNN format.
        $this->assertMatchesRegularExpression('/^JV-2026-\d{6}$/', $j1);
        $this->assertMatchesRegularExpression('/^JV-2026-\d{6}$/', $j2);
    }

    public function test_post_returns_fresh_batch_with_journals_loaded(): void
    {
        $draft = $this->makeRevenueDraft('key-fresh');
        $batch = app(PostingEngine::class)->post($draft, $this->user);

        // The relations should be eager-loaded on return.
        $this->assertTrue($batch->relationLoaded('journals'));
        $this->assertSame(1, $batch->journals->count());
    }

    private function makeRevenueDraft(string $key): JournalBatchDraft
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
