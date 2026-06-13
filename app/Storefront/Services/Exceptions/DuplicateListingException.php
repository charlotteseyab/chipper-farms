<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/**
 * Thrown when "Push to Storefront" is attempted for an operational source that
 * already has an active listing, preventing a pig (or product) from being
 * double-listed and double-sold.
 */
class DuplicateListingException extends RuntimeException
{
}
