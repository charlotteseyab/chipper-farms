<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/** Thrown when an order cannot be created from a cart (empty cart, bad address, etc.). */
class CheckoutException extends RuntimeException
{
}
