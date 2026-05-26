<?php

namespace App\Finance\Exceptions;

class UnbalancedJournalException extends PostingException
{
    public function __construct(
        public readonly string $totalDebit,
        public readonly string $totalCredit,
        public readonly ?string $journalNumber = null,
    ) {
        $journal = $journalNumber ? " {$journalNumber}" : '';
        parent::__construct(
            "Journal{$journal} unbalanced: debit={$totalDebit}, credit={$totalCredit}"
        );
    }
}
