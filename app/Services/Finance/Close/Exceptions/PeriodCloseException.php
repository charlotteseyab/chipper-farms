<?php

namespace App\Services\Finance\Close\Exceptions;

/**
 * Thrown when a period close (or reopen) is rejected — blocking checks,
 * invalid state transitions, or missing override reasons.
 */
class PeriodCloseException extends \DomainException
{
}
