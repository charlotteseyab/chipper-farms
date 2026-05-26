<?php

namespace App\Services\Finance\Inventory;

/**
 * Thrown when an opening balance import attempt collides with an
 * existing import audit row (UNIQUE on (org, item_kind, import_hash)).
 *
 * Carries the existing import id so the caller can redirect to it.
 */
class DuplicateImportException extends \RuntimeException
{
    public function __construct(
        public readonly string $importHash,
        public readonly string $itemKind,
        public readonly int $existingId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Opening balance import with hash {$importHash} already exists "
            . "(existing id #{$existingId}). Refusing duplicate.",
            0,
            $previous,
        );
    }
}
