<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/** Thrown for payment/gateway failures (verification mismatch, gateway error). */
class PaymentException extends RuntimeException
{
}
