<?php

namespace App\Finance\Exceptions;

/**
 * Base for every PostingEngine validation/permission failure.
 *
 * Subclasses split into two retry-classes:
 *   - permanent (caller should NOT retry: balance broken, account inactive, etc.)
 *   - transient (caller MAY retry: lock wait timeout, deadlock — extremely rare)
 *
 * Most subclasses are permanent. The `isTransient()` predicate lets queue
 * subscribers (Phase 1c) decide whether to release the job back to the queue
 * vs failing it permanently.
 */
abstract class PostingException extends \RuntimeException
{
    public function isTransient(): bool
    {
        return false;
    }
}
