<?php

namespace App\Services\Finance\Investors\Exceptions;

/**
 * Thrown on invalid investor operations (bad state, double allocation,
 * insufficient capital for distribution, etc.).
 */
class InvestorException extends \DomainException
{
}
