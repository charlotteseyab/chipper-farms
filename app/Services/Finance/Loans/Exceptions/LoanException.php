<?php

namespace App\Services\Finance\Loans\Exceptions;

/**
 * Thrown on invalid loan operations (bad state transitions, invalid amounts).
 */
class LoanException extends \DomainException
{
}
