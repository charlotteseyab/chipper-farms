<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/** Thrown when an order is asked to make a transition its status machine forbids. */
class InvalidOrderTransitionException extends RuntimeException
{
}
