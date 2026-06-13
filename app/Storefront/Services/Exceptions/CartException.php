<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/** Thrown for invalid cart operations (unorderable listing, over-quantity, etc.). */
class CartException extends RuntimeException
{
}
