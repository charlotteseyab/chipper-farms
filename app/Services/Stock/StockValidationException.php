<?php

namespace App\Services\Stock;

use RuntimeException;

/**
 * Thrown by PigStockService when a business-rule validation (inbreeding,
 * room capacity, etc.) fails. The controller catches this and translates
 * into a back()->withErrors() call.
 *
 * Kept as a lightweight exception rather than a result object because the
 * happy-path return is a PigManagement model and encoding either-or in the
 * return type would add complexity for marginal benefit.
 */
class StockValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}