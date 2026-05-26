<?php

namespace App\Services\Finance\CreditControl;

use App\Models\Counterparty;

/**
 * Result of a credit-control evaluation.
 *
 * The two methods that callers care about:
 *   - shouldWarn() — show a yellow banner in the form
 *   - isBlocked() — refuse the submission
 *
 * Hard rule:
 *   isBlocked() returns true ONLY when the customer is BOTH over limit
 *   AND has hold_mode='block'. 'warn' never blocks; 'none' never warns
 *   or blocks regardless of how far over limit the customer is.
 */
final class CheckResult
{
    public function __construct(
        public readonly Counterparty $customer,
        public readonly string $outstandingBalance,
        public readonly string $projectedBalance,
        public readonly ?string $limit,
        public readonly string $holdMode,            // 'none' | 'warn' | 'block'
        public readonly bool $isOverLimit,
        public readonly ?string $message,
    ) {
    }

    /**
     * Should the operations UI show a warning banner?
     * True when over-limit AND mode is 'warn' or 'block'.
     */
    public function shouldWarn(): bool
    {
        if (! $this->isOverLimit) {
            return false;
        }
        return in_array($this->holdMode, ['warn', 'block'], true);
    }

    /**
     * Should the action be hard-blocked?
     * True ONLY when over-limit AND mode is 'block'.
     */
    public function isBlocked(): bool
    {
        return $this->isOverLimit && $this->holdMode === 'block';
    }

    /**
     * Color hint for the UI banner.
     */
    public function severityColor(): string
    {
        if ($this->isBlocked())  return 'red';
        if ($this->shouldWarn()) return 'amber';
        return 'emerald';
    }
}
