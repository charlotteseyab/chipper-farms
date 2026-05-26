<?php

namespace App\Finance\Exceptions;

/**
 * Thrown when a journal line's account currency_code differs from the
 * journal's currency_code. Phase 1 forbids cross-currency lines; multi-
 * currency journals (Phase 5+) will use a separate posting model.
 */
class CurrencyMismatchException extends PostingException
{
    public function __construct(
        public readonly string $journalCurrency,
        public readonly string $accountCurrency,
        public readonly string $accountCode,
    ) {
        parent::__construct(
            "Account {$accountCode} is in {$accountCurrency}, journal is in {$journalCurrency}"
        );
    }
}
