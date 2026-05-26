<?php

namespace App\Finance\Resolvers;

use App\Finance\Exceptions\PostingException;

/**
 * Thrown by FinanceAccountResolver when an account it was asked to resolve
 * doesn't exist (or exists but is inactive).
 *
 * Extends PostingException so it propagates through Subscriber's catch
 * handler the same way other posting failures do — i.e. the DomainEvent
 * is marked failed, the queue job fails, and a human gets to look at it.
 *
 * This is permanent — retrying won't fix a missing COA entry.
 */
class MissingAccountException extends PostingException
{
    public function __construct(
        public readonly string $codeOrHint,
        public readonly bool $isInactive = false,
    ) {
        $reason = $isInactive ? 'is inactive' : 'does not exist';
        parent::__construct(
            "FinanceAccountResolver: account '{$codeOrHint}' {$reason}. "
            . 'Check that ChartOfAccountsSeeder has been run and the account is active.'
        );
    }
}
