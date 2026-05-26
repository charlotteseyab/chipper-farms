<?php

namespace App\Services\Sales\Exceptions;

/**
 * Thrown on invalid feed-sale operations (bad quantity/price, etc.).
 */
class FeedSaleException extends \DomainException
{
}
