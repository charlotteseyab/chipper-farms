<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/**
 * Thrown when a quote is asked to make a transition its status machine does
 * not permit.
 */
class InvalidQuoteTransitionException extends RuntimeException
{
}
