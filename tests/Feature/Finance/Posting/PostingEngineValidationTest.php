<?php

namespace Tests\Feature\Finance\Posting;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Exceptions\CurrencyMismatchException;
use App\Finance\Exceptions\DuplicatePostingException;
use App\Finance\Exceptions\InvalidAccountException;
use App\Finance\Exceptions\PeriodLockedException;
use App\Finance\Exceptions\PeriodNotOpenException;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Models\Finance\PeriodLockDate;
use App\Models\FiscalPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PostingEngineValidationTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_posting_to_closed_period_rejected(): void
    {
        $this->period->update(['status' => FiscalPeriod::STATUS_CLOSED]);

        $this->expectException(PeriodNotOpenException::class);
        app(PostingEngine::class)->post($this->basicDraft('k1'), $this->user);
    }

    public function test_posting_to_locked_period_rejected(): void
    {
        $this->period->update(['status' => FiscalPeriod::STATUS_LOCKED]);

        $this->expectException(PeriodNotOpenException::class);
        app(PostingEngine::class)->post($this->basicDraft('k1'), $this->user);
    }

    public function test_posting_with_no_covering_period_rejected(): void
    {
        // Posting date 2027-01-01 — no period covers this date.
        $draft = new JournalBatchDraft(
            batchType: 'auto_pig_sale',
            postingDate: CarbonImmutable::parse('2027-01-01'),
            journals: [
                new JournalDraft(
                    journalType: 'sales_revenue',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '100.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
            idempotencyKey: 'no-period',
            postingPurpose: 'revenue',
        );

        $this->expectException(PeriodNotOpenException::class);
        app(PostingEngine::class)->post($draft, $this->user);
    }

    public function test_posting_before_hard_lock_rejected(): void
    {
        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-05-10',
            'is_hard'           => true,
            'is_active'         => true,
            'reason'            => 'Year-end audit',
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now(),
        ]);

        // Try to post on 2026-05-05 — before the lock.
        $draft = new JournalBatchDraft(
            batchType: 'auto_pig_sale',
            postingDate: CarbonImmutable::parse('2026-05-05'),
            journals: [
                new JournalDraft(
                    journalType: 'sales_revenue',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '100.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
            idempotencyKey: 'before-hard-lock',
            postingPurpose: 'revenue',
        );

        $this->expectException(PeriodLockedException::class);
        app(PostingEngine::class)->post($draft, $this->user);
    }

    public function test_posting_before_soft_lock_rejected_without_bypass(): void
    {
        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-05-10',
            'is_hard'           => false,  // Soft
            'is_active'         => true,
            'reason'            => 'Soft preview',
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now(),
        ]);

        $draft = new JournalBatchDraft(
            batchType: 'auto_pig_sale',
            postingDate: CarbonImmutable::parse('2026-05-05'),
            journals: [
                new JournalDraft(
                    journalType: 'sales_revenue',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '100.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
            idempotencyKey: 'before-soft-lock-no-bypass',
            postingPurpose: 'revenue',
        );

        $this->expectException(PeriodLockedException::class);
        app(PostingEngine::class)->post($draft, $this->user, false);
    }

    public function test_posting_before_soft_lock_succeeds_with_bypass(): void
    {
        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-05-10',
            'is_hard'           => false,
            'is_active'         => true,
            'reason'            => 'Soft preview',
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now(),
        ]);

        $draft = new JournalBatchDraft(
            batchType: 'auto_pig_sale',
            postingDate: CarbonImmutable::parse('2026-05-05'),
            journals: [
                new JournalDraft(
                    journalType: 'sales_revenue',
                    lines: [
                        JournalLineDraft::debit($this->cashAccount->id, '100.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
            idempotencyKey: 'before-soft-lock-with-bypass',
            postingPurpose: 'revenue',
        );

        $batch = app(PostingEngine::class)->post($draft, $this->user, true);

        $this->assertSame('posted', $batch->status);
    }

    public function test_posting_against_nonexistent_account_rejected(): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'auto_pig_sale',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'sales_revenue',
                    lines: [
                        JournalLineDraft::debit(99999, '100.00'),
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
            idempotencyKey: 'no-account',
            postingPurpose: 'revenue',
        );

        $this->expectException(InvalidAccountException::class);
        $this->expectExceptionMessage('does not exist');
        app(PostingEngine::class)->post($draft, $this->user);
    }

    public function test_posting_against_inactive_account_rejected(): void
    {
        $this->cashAccount->update(['is_active' => false]);

        $this->expectException(InvalidAccountException::class);
        $this->expectExceptionMessage('inactive');
        app(PostingEngine::class)->post($this->basicDraft('inactive-acc'), $this->user);
    }

    public function test_posting_against_currency_mismatched_account_rejected(): void
    {
        $this->cashAccount->update(['currency_code' => 'USD']);

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
                    currencyCode: 'GHS',  // Journal in GHS but cash account is USD now.
                ),
            ],
            idempotencyKey: 'currency-mismatch',
            postingPurpose: 'revenue',
        );

        $this->expectException(CurrencyMismatchException::class);
        app(PostingEngine::class)->post($draft, $this->user);
    }

    public function test_manual_journal_against_control_account_rejected(): void
    {
        // Manual journal = no idempotency_key. AR account has allow_manual_journal=false.
        $draft = new JournalBatchDraft(
            batchType: 'manual_journal',
            postingDate: CarbonImmutable::parse('2026-05-15'),
            journals: [
                new JournalDraft(
                    journalType: 'manual',
                    lines: [
                        JournalLineDraft::debit($this->arAccount->id, '100.00'),  // Control account
                        JournalLineDraft::credit($this->revenueAccount->id, '100.00'),
                    ],
                ),
            ],
            // idempotency_key NULL = manual.
        );

        $this->expectException(InvalidAccountException::class);
        $this->expectExceptionMessage('control account');
        app(PostingEngine::class)->post($draft, $this->user);
    }

    public function test_duplicate_idempotency_key_rejected(): void
    {
        $draft = $this->basicDraft('dup-key-1');

        $first = app(PostingEngine::class)->post($draft, $this->user);
        $this->assertSame('posted', $first->status);

        // Second attempt with same key.
        try {
            app(PostingEngine::class)->post($draft, $this->user);
            $this->fail('Expected DuplicatePostingException');
        } catch (DuplicatePostingException $e) {
            $this->assertSame('dup-key-1', $e->idempotencyKey);
            $this->assertSame($first->id, $e->existingBatchId);
        }
    }

    private function basicDraft(string $key): JournalBatchDraft
    {
        return new JournalBatchDraft(
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
            idempotencyKey: $key,
            postingPurpose: 'revenue',
        );
    }
}
