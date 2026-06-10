<?php

namespace Tests\Feature\Finance\Close;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\DomainEvent;
use App\Models\Finance\PeriodCloseRun;
use App\Models\FiscalPeriod;
use App\Services\Finance\Close\Exceptions\PeriodCloseException;
use App\Services\Finance\Close\PeriodCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PeriodCloseServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->cashAccount->update(['account_subtype' => 'current_asset']);
        $this->revenueAccount->update(['account_subtype' => 'revenue']);
    }

    public function test_close_happy_path_flips_period_and_snapshots(): void
    {
        // A clean, balanced cash sale within the period.
        $this->postBalanced('2026-05-10', '500.00');

        $svc = app(PeriodCloseService::class);
        $run = $svc->close($this->period, $this->user);

        $this->assertSame(PeriodCloseRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(PeriodCloseRun::LEVEL_SOFT, $run->close_level);

        // Period flipped to closed.
        $this->assertSame(FiscalPeriod::STATUS_CLOSED, $this->period->fresh()->status);

        // Statements snapshotted (4: TB, IS, BS, CF).
        $this->assertCount(4, $run->statements);

        // TB snapshot present + balanced.
        $tb = $run->decodedTrialBalance();
        $this->assertNotNull($tb);
        $this->assertTrue($tb['totals']['balanced']);
    }

    public function test_hard_close_locks_period(): void
    {
        $this->postBalanced('2026-05-10', '500.00');

        $run = app(PeriodCloseService::class)->close(
            $this->period, $this->user, PeriodCloseRun::LEVEL_HARD,
        );

        $this->assertSame(PeriodCloseRun::LEVEL_HARD, $run->close_level);
        $this->assertSame(FiscalPeriod::STATUS_LOCKED, $this->period->fresh()->status);
    }

    public function test_close_blocked_by_failing_soft_check_without_override(): void
    {
        $this->postBalanced('2026-05-10', '500.00');

        // Create a stuck (pending) domain event in the period -> outbox_drained fails.
        DomainEvent::create([
            'organization_id' => $this->org->id,
            'event_type'      => 'pig_sale.recorded',
            'idempotency_key' => 'period-close-test-pending-1',
            'source_module'   => 'sales',
            'source_type'     => 'App\\Models\\PigSale',
            'source_id'       => 1,
            'status'          => DomainEvent::STATUS_PENDING,
            'occurred_at'     => '2026-05-15 09:00:00',
            'payload'         => json_encode(['x' => 1]),
        ]);

        $this->expectException(PeriodCloseException::class);
        app(PeriodCloseService::class)->close($this->period, $this->user);
    }

    public function test_close_proceeds_when_soft_check_overridden(): void
    {
        $this->postBalanced('2026-05-10', '500.00');

        DomainEvent::create([
            'organization_id' => $this->org->id,
            'event_type'      => 'pig_sale.recorded',
            'idempotency_key' => 'period-close-test-pending-2',
            'source_module'   => 'sales',
            'source_type'     => 'App\\Models\\PigSale',
            'source_id'       => 1,
            'status'          => DomainEvent::STATUS_PENDING,
            'occurred_at'     => '2026-05-15 09:00:00',
            'payload'         => json_encode(['x' => 1]),
        ]);

        $run = app(PeriodCloseService::class)->close(
            $this->period, $this->user, PeriodCloseRun::LEVEL_SOFT,
            ['outbox_drained' => 'Known stuck event, will replay next cycle.'],
        );

        $this->assertSame(PeriodCloseRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(1, $run->override_count);

        $overridden = $run->checks->firstWhere('check_key', 'outbox_drained');
        $this->assertSame('overridden', $overridden->status);
        $this->assertNotNull($overridden->override_reason);
    }

    public function test_reopen_restores_open_status(): void
    {
        $this->postBalanced('2026-05-10', '500.00');
        $svc = app(PeriodCloseService::class);
        $svc->close($this->period, $this->user);

        $reopened = $svc->reopen($this->period, $this->user, 'Found a missing accrual.');

        $this->assertSame(FiscalPeriod::STATUS_OPEN, $reopened->status);
        $this->assertSame(PeriodCloseRun::STATUS_REOPENED,
            PeriodCloseRun::where('fiscal_period_id', $this->period->id)->first()->status);
    }

    public function test_reopen_requires_reason(): void
    {
        $this->postBalanced('2026-05-10', '500.00');
        $svc = app(PeriodCloseService::class);
        $svc->close($this->period, $this->user);

        $this->expectException(PeriodCloseException::class);
        $svc->reopen($this->period, $this->user, '   ');
    }

    protected function postBalanced(string $date, string $amount): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'manual_journal',
            postingDate: CarbonImmutable::parse($date),
            journals: [new JournalDraft(journalType: 'manual', lines: [
                JournalLineDraft::debit($this->cashAccount->id, $amount),
                JournalLineDraft::credit($this->revenueAccount->id, $amount),
            ])],
        );
        app(PostingEngine::class)->post($draft, $this->user);
    }
}
