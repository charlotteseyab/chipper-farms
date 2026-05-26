<?php

namespace App\Finance\Exceptions;

class PeriodNotOpenException extends PostingException
{
    public function __construct(
        public readonly string $postingDate,
        public readonly ?string $periodName = null,
        public readonly ?string $periodStatus = null,
    ) {
        $period = $periodName ? " '{$periodName}'" : '';
        $status = $periodStatus ? " (status: {$periodStatus})" : '';
        parent::__construct(
            "Cannot post to period{$period}{$status} for date {$postingDate}: period is not open"
        );
    }
}
