<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/**
 * Raised when an order cannot be confirmed because the stock it needs is no
 * longer available (CF-P0-006).
 *
 * Distinct from InvalidOrderTransitionException on purpose. That means "this
 * status change is not legal" — a programming or workflow error. This means
 * "the transition was legal, but somebody got there first": a pig already
 * reserved by another order, or product stock consumed between checkout and
 * staff confirmation.
 *
 * The distinction matters operationally. A transition error is a bug to fix; an
 * inventory conflict is a normal race with a business resolution (tell the
 * customer, offer an alternative, refund a prepaid order). Staff need to be able
 * to tell them apart, and so does any retry logic — retrying a conflict is
 * pointless, whereas the previous behaviour of silently succeeding made the
 * conflict invisible to everyone including the second customer.
 */
class InventoryConflictException extends RuntimeException
{
}
