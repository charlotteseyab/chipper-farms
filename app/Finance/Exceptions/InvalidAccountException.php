<?php

namespace App\Finance\Exceptions;

/**
 * Thrown when a journal line targets an account that:
 *   - doesn't exist
 *   - is inactive
 *   - is in a different organization
 *   - (manual journals only) is_control=true and allow_manual_journal=false
 *
 * The reasoning is exposed via the $reason string for clear UI feedback.
 */
class InvalidAccountException extends PostingException
{
    public function __construct(
        public readonly ?int $accountId,
        public readonly ?string $accountCode,
        public readonly string $reason,
    ) {
        $ref = $accountCode ?? ($accountId ? "id={$accountId}" : 'unknown');
        parent::__construct("Invalid account ({$ref}): {$reason}");
    }
}
