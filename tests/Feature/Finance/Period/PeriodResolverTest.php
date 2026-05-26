<?php

namespace Tests\Feature\Finance\Period;

use App\Finance\Exceptions\PeriodLockedException;
use App\Finance\Exceptions\PeriodNotOpenException;
use App\Finance\Posting\PeriodResolver;
use App\Models\Finance\PeriodLockDate;
use App\Models\FiscalPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PeriodResolverTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_resolve_returns_covering_period(): void
    {
        $period = app(PeriodResolver::class)->resolveForPosting(
            CarbonImmutable::parse('2026-05-15')
        );

        $this->assertSame($this->period->id, $period->id);
    }

    public function test_resolve_throws_for_date_outside_any_period(): void
    {
        $this->expectException(PeriodNotOpenException::class);
        app(PeriodResolver::class)->resolveForPosting(
            CarbonImmutable::parse('2030-12-31')
        );
    }

    public function test_resolve_throws_for_closed_period(): void
    {
        $this->period->update(['status' => FiscalPeriod::STATUS_CLOSED]);

        $this->expectException(PeriodNotOpenException::class);
        app(PeriodResolver::class)->resolveForPosting(
            CarbonImmutable::parse('2026-05-15')
        );
    }

    public function test_resolve_only_succeeds_for_closed_period(): void
    {
        // resolveOnly skips the status check.
        $this->period->update(['status' => FiscalPeriod::STATUS_CLOSED]);

        $period = app(PeriodResolver::class)->resolveOnly(
            CarbonImmutable::parse('2026-05-15')
        );

        $this->assertSame($this->period->id, $period->id);
    }

    public function test_hard_lock_blocks_posting_before_lock_date(): void
    {
        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-05-10',
            'is_hard'           => true,
            'is_active'         => true,
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now(),
        ]);

        $this->expectException(PeriodLockedException::class);
        app(PeriodResolver::class)->resolveForPosting(
            CarbonImmutable::parse('2026-05-05')
        );
    }

    public function test_hard_lock_does_not_block_posting_on_lock_date(): void
    {
        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-05-10',
            'is_hard'           => true,
            'is_active'         => true,
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now(),
        ]);

        $period = app(PeriodResolver::class)->resolveForPosting(
            CarbonImmutable::parse('2026-05-10')
        );

        $this->assertSame($this->period->id, $period->id);
    }

    public function test_hard_lock_not_bypassed_even_with_bypass_flag(): void
    {
        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-05-10',
            'is_hard'           => true,
            'is_active'         => true,
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now(),
        ]);

        $this->expectException(PeriodLockedException::class);

        app(PeriodResolver::class)->resolveForPosting(
            CarbonImmutable::parse('2026-05-05'),
            bypassSoftLock: true,  // Even with bypass.
        );
    }

    public function test_soft_lock_bypassed_with_flag(): void
    {
        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-05-10',
            'is_hard'           => false,
            'is_active'         => true,
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now(),
        ]);

        $period = app(PeriodResolver::class)->resolveForPosting(
            CarbonImmutable::parse('2026-05-05'),
            bypassSoftLock: true,
        );

        $this->assertSame($this->period->id, $period->id);
    }

    public function test_inactive_lock_not_enforced(): void
    {
        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-05-10',
            'is_hard'           => true,
            'is_active'         => false,  // INACTIVE
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now(),
        ]);

        $period = app(PeriodResolver::class)->resolveForPosting(
            CarbonImmutable::parse('2026-05-05')
        );

        $this->assertSame($this->period->id, $period->id);
    }

    public function test_most_recent_active_lock_takes_precedence(): void
    {
        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-04-30',  // Earlier
            'is_hard'           => true,
            'is_active'         => true,
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now()->subDays(60),
        ]);

        PeriodLockDate::create([
            'organization_id'   => $this->org->id,
            'lock_date'         => '2026-05-10',  // Most recent
            'is_hard'           => true,
            'is_active'         => true,
            'locked_by_user_id' => $this->user->id,
            'locked_at'         => now(),
        ]);

        // Posting on 2026-05-05 — between the two locks.
        // The MORE RECENT lock (2026-05-10) is enforced.
        $this->expectException(PeriodLockedException::class);
        app(PeriodResolver::class)->resolveForPosting(
            CarbonImmutable::parse('2026-05-05')
        );
    }
}
