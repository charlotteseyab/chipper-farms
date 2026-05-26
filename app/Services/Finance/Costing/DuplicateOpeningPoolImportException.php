<?php

namespace App\Services\Finance\Costing;

/**
 * Thrown when an opening pool import attempt collides with an existing
 * audit row (UNIQUE on org + hash).
 *
 * Mirrors Phase 4's DuplicateImportException.
 */
class DuplicateOpeningPoolImportException extends \RuntimeException
{
    public function __construct(
        public readonly string $importHash,
        public readonly int $existingId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Group opening pool import with hash {$importHash} already exists "
            . "(existing id #{$existingId}). Refusing duplicate.",
            0,
            $previous,
        );
    }
}
