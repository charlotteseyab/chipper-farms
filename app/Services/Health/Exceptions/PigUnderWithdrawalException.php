<?php

namespace App\Services\Health\Exceptions;

/**
 * A sale was refused because the pig is inside a medicine withdrawal period
 * (decision D8: blocked, no override). The message is shown to the user.
 */
class PigUnderWithdrawalException extends \DomainException
{
}
