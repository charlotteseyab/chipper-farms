<?php

namespace App\Services\Finance\Costing;

/**
 * Thrown when GroupCostPoolService attempts to record a movement whose
 * (source_type, source_id) tuple already exists. The DB-level
 * UNIQUE(source_type, source_id) is the race-safety net; the service
 * translates the constraint violation into this typed exception so
 * callers can decide whether to treat it as "already done" (typically
 * yes) or as a real error.
 */
class DuplicateGroupCostMovementException extends \RuntimeException
{
    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly int $existingMovementId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Group cost movement for ({$sourceType}, {$sourceId}) already exists "
            . "(existing movement id #{$existingMovementId}). Refusing duplicate.",
            0,
            $previous,
        );
    }
}
