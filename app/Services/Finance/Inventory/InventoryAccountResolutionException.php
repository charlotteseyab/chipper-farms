<?php

namespace App\Services\Finance\Inventory;

/**
 * Thrown when InventoryAccountResolver cannot resolve a required GL
 * account for an inventory movement. Indicates a configuration problem:
 *   - Missing chart_of_accounts row for the COA fallback code
 *   - Inventory_account_mappings row references an inactive account
 *   - No fallback configured for an item_kind × role combination
 *
 * Different from FinanceAccountResolver's MissingAccountException because
 * the resolution path is multi-step (mappings → COA fallback); this
 * exception type captures the inventory-specific context.
 */
class InventoryAccountResolutionException extends \DomainException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
