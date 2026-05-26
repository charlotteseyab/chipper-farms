<?php

namespace App\Services\Finance\Costing;

/**
 * Thrown when an outflow (sale_cogs, mortality_writeoff, transfer_out,
 * adjustment_deduct) would drive a group pool below zero AND the caller
 * hasn't supplied the override flag.
 *
 * In practice this should not happen if the upstream lifecycle is
 * consistent (a pig can only be sold/died/transferred while it's in a
 * group, and the pool was loaded with at least that pig's cost basis).
 * If it does happen, it's a strong signal that some prior cost movement
 * was missed — investigation required, not a silent override.
 */
class InsufficientPoolException extends \DomainException
{
    public function __construct(
        public readonly int $groupId,
        public readonly string $dimension,   // 'value', 'count', or 'weight'
        public readonly string $requested,
        public readonly string $available,
    ) {
        parent::__construct(sprintf(
            'Insufficient pool for group #%d: requested %s %s, available %s.',
            $groupId,
            $requested,
            $dimension,
            $available,
        ));
    }
}
