<?php

namespace App\Finance\Exceptions;

/**
 * Thrown when posting_date < an active period_lock_dates entry.
 *
 * Distinct from PeriodNotOpenException because the rejection comes from
 * the org-wide lock_date, not from the fiscal_period status. A user with
 * `bypass_period_lock` permission can override SOFT (is_hard=false) locks
 * — that override path is checked in PostingEngine before throwing.
 */
class PeriodLockedException extends PostingException
{
    public function __construct(
        public readonly string $postingDate,
        public readonly string $lockDate,
        public readonly bool $isHard,
        public readonly ?string $reason = null,
    ) {
        $hardness = $isHard ? 'HARD' : 'soft';
        $reasonText = $reason ? " (reason: {$reason})" : '';
        parent::__construct(
            "Posting date {$postingDate} is before {$hardness} period lock at {$lockDate}{$reasonText}"
        );
    }
}
