<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/**
 * Thrown when a listing is asked to make a transition the lifecycle state
 * machine does not permit, or when a publish is attempted against an
 * unavailable source record.
 */
class InvalidListingTransitionException extends RuntimeException
{
}
