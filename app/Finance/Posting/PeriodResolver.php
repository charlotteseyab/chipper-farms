<?php

namespace App\Finance\Posting;

use App\Finance\Exceptions\PeriodLockedException;
use App\Finance\Exceptions\PeriodNotOpenException;
use App\Models\Finance\PeriodLockDate;
use App\Models\FiscalPeriod;
use App\Support\CurrentOrganization;

/**
 * Resolves a posting_date into the fiscal_period that should own the
 * journal, applying both:
 *   - fiscal_periods.status (open/closed/locked) — per-period control
 *   - period_lock_dates              — org-wide cutoff
 *
 * Throws typed exceptions for each rejection reason. Returns the
 * FiscalPeriod model on success.
 *
 * The $bypassSoftLock flag is for the `bypass_period_lock` permission
 * holder — they can override soft (is_hard=false) locks. Hard locks are
 * never bypassable. The PostingEngine performs the permission check
 * before passing this flag in.
 */
class PeriodResolver
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    /**
     * Resolve and validate. Returns the FiscalPeriod or throws.
     */
    public function resolveForPosting(\DateTimeInterface $postingDate, bool $bypassSoftLock = false): FiscalPeriod
    {
        $organizationId = $this->currentOrganization->id();

        $this->checkLockDates($postingDate, $organizationId, $bypassSoftLock);

        $period = $this->findCoveringPeriod($postingDate, $organizationId);

        if (! $period->acceptsPostings()) {
            throw new PeriodNotOpenException(
                postingDate:  $postingDate->format('Y-m-d'),
                periodName:   $period->name,
                periodStatus: $period->status,
            );
        }

        return $period;
    }

    /**
     * Resolve without validation — used by reports that just need to bucket
     * a journal into a period regardless of status.
     */
    public function resolveOnly(\DateTimeInterface $postingDate): FiscalPeriod
    {
        $organizationId = $this->currentOrganization->id();
        return $this->findCoveringPeriod($postingDate, $organizationId);
    }

    protected function findCoveringPeriod(\DateTimeInterface $postingDate, ?int $organizationId): FiscalPeriod
    {
        $period = FiscalPeriod::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->coveringDate($postingDate)
            ->first();

        if (! $period) {
            throw new PeriodNotOpenException(
                postingDate:  $postingDate->format('Y-m-d'),
                periodName:   null,
                periodStatus: 'no_period_defined',
            );
        }

        return $period;
    }

    protected function checkLockDates(\DateTimeInterface $postingDate, ?int $organizationId, bool $bypassSoftLock): void
    {
        $activeLock = PeriodLockDate::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->active()
            ->orderByDesc('lock_date')
            ->first();

        if (! $activeLock) {
            return;
        }

        $postingDateString = $postingDate->format('Y-m-d');
        $lockDateString    = $activeLock->lock_date->format('Y-m-d');

        // Posting on or after the lock date is fine.
        if ($postingDateString >= $lockDateString) {
            return;
        }

        // Posting BEFORE the lock date — apply rules.
        if ($activeLock->isHard()) {
            throw new PeriodLockedException(
                postingDate: $postingDateString,
                lockDate:    $lockDateString,
                isHard:      true,
                reason:      $activeLock->reason,
            );
        }

        // Soft lock: bypass permission lets it through.
        if (! $bypassSoftLock) {
            throw new PeriodLockedException(
                postingDate: $postingDateString,
                lockDate:    $lockDateString,
                isHard:      false,
                reason:      $activeLock->reason,
            );
        }
        // Bypass granted — posting allowed.
    }
}
