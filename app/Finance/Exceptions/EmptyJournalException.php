<?php

namespace App\Finance\Exceptions;

/**
 * Thrown when a JournalDraft has fewer than 2 lines. A valid double-entry
 * journal must have at least one debit and one credit. Even a 1-debit-1-credit
 * journal (the minimum) needs both sides.
 */
class EmptyJournalException extends PostingException
{
    public function __construct(public readonly int $lineCount)
    {
        parent::__construct(
            "Journal must contain at least 2 lines, got {$lineCount}"
        );
    }
}
