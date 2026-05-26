<?php

namespace App\Services\Finance\Inventory;

/**
 * Phase 4 — thrown when a service attempts to record an inventory
 * movement whose (source_type, source_id) tuple already exists.
 *
 * UNIQUE(source_type, source_id) on inventory_movements is the DB-level
 * race-safety mechanism. The service catches the UniqueConstraintViolation,
 * looks up the existing row, and throws this typed exception with the
 * existing movement id so callers can decide whether to treat it as
 * "already done" (typically yes) or as a real error.
 */
class DuplicateMovementException extends \RuntimeException
{
    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly int $existingMovementId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Inventory movement for ({$sourceType}, {$sourceId}) already exists "
            . "(existing movement id #{$existingMovementId}). Refusing duplicate.",
            0,
            $previous,
        );
    }
}
