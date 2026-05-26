<?php

/**
 * Phase 4 — additions to config/finance.php.
 *
 * Deployment: merge this 'inventory' key into the array returned from
 * config/finance.php. The existing 'vendor_invoices' section already
 * carries the match tolerance keys that Phase 4 uses.
 *
 * The deployment notes (deployment/PHASE_4_DEPLOY.md) include the
 * exact patch text. The file below is a self-contained example of
 * the new inventory section for reference / testing.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Inventory (Phase 4)
    |--------------------------------------------------------------------------
    |
    | Fallback GL account codes per (kind, role). The
    | InventoryAccountResolver walks these AFTER the inventory_account_mappings
    | table; they're the safety net when the mapping is missing or partial.
    |
    | Per-deployment customisation: change a value here to swing the
    | fallback for the whole org without writing a mapping row. For
    | per-category control, use the inventory_account_mappings table.
    */
    'inventory' => [

        'fallback_account_codes' => [

            'raw_material' => [
                'inventory'   => '1210',  // Raw Materials Inventory
                'gr_ir'       => '2050',  // Goods Received Not Invoiced
                'suspense'    => '2055',  // Inventory Suspense
                'adjustment'  => '5300',  // Inventory Adjustments and Shrinkage
                'cogs'        => null,    // raw materials are consumed not sold
                'ppv'         => '5310',  // Purchase Price Variance
            ],

            'finished_feed' => [
                'inventory'   => '1220',  // Finished Feed Inventory
                'gr_ir'       => '2050',  // (only relevant if FF is ever directly procured)
                'suspense'    => '2055',
                'adjustment'  => '5300',
                'cogs'        => '5100',  // Cost of Feed Sold
                'ppv'         => '5310',
            ],

            // Tax routing fallback — used by VendorInvoicePostingService.
            'input_vat' => '2211',  // VAT Receivable (Input)

            // Sundry expense fallback — used by ExpenseAccountResolver.
            // Phase 4 corrects the 3b bug that misrouted to 6010.
            'sundry_expense' => '6990',
        ],

        /*
        | Policy for outflows that would drive on-hand below zero.
        |
        |   'block' : default. InventoryService refuses the write.
        |   'warn'  : reserved for future use — logs but allows.
        |   'allow' : reserved — bypasses the check. Effectively the
        |             same as passing allow_negative=true on every call.
        |
        | The InventoryService respects an explicit allow_negative flag
        | from the caller regardless of this config; the config sets the
        | default for callers that don't pass the flag.
        */
        'negative_inventory_policy' => env('FINANCE_INVENTORY_NEG_POLICY', 'block'),

        'opening_balance' => [
            // Maximum rows per CSV upload — protects against accidental
            // 100k-row uploads that would hold a transaction for minutes.
            'max_csv_rows' => env('FINANCE_OPENING_BALANCE_MAX_ROWS', 500),

            // Allow rows with quantity=0 AND unit_cost=0. Default: reject
            // as likely-template-mistake. Enable when ops genuinely needs
            // to record a zero-on-hand item with no historic cost.
            'allow_zero_quantity_opening' => env('FINANCE_OPENING_BALANCE_ALLOW_ZERO', false),
        ],

        'reporting' => [
            // Items with no movement in this many days are flagged in
            // inventory value reports as potentially stale (recommend
            // a stock count). 0 disables the warning.
            'stale_movement_warn_days' => env('FINANCE_INVENTORY_STALE_DAYS', 60),
        ],
    ],
];
