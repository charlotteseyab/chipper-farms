<?php

namespace App\Finance\Exceptions;

/**
 * Thrown when a subscriber attempts to post a batch with an idempotency_key
 * that already exists. This is the SUCCESS case from a duplicate-event
 * perspective — the work was already done.
 *
 * Subscribers should catch this specifically and treat it as "already
 * processed, skip silently". The existing batch is exposed via $existingBatch
 * so the caller can return the canonical reference if needed.
 */
class DuplicatePostingException extends PostingException
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly int $existingBatchId,
    ) {
        parent::__construct(
            "Posting with idempotency_key '{$idempotencyKey}' already exists as batch #{$existingBatchId}"
        );
    }
}
