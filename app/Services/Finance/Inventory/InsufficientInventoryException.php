<?php

namespace App\Services\Finance\Inventory;

/**
 * Thrown when an issue/usage/deduct/transfer-out would drive on-hand
 * below zero AND the caller hasn't supplied the override flag.
 *
 * Default policy is to BLOCK such movements. Operators with the
 * override_negative_inventory permission can bypass via the service's
 * $allowNegative=true flag, which the controller layer passes only
 * after the permission check.
 */
class InsufficientInventoryException extends \DomainException
{
    public function __construct(
        public readonly string $itemKind,
        public readonly int $itemId,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(sprintf(
            'Insufficient inventory for %s #%d: requested %s, available %s.',
            $itemKind,
            $itemId,
            number_format($requested, 4),
            number_format($available, 4),
        ));
    }
}
