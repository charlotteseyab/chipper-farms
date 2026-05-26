<?php

/**
 * Feed-sale finance path configuration.
 *
 * Maps the feed-sale channel to its revenue account and pins the AR account.
 * Merged via mergeConfigFrom in the service provider; publish
 * config/feed_sales.php to override per environment.
 *
 * All accounts already exist in the COA (Phase 0 seeder):
 *   1120 Trade Receivables — Feed Sales   (AR control for feed)
 *   1220 Finished Feed Inventory          (credited on COGS)
 *   4110 Revenue — Feed (Direct)          (internally-recorded sales)
 *   4120 Revenue — Feed (Storefront)      (storefront-originated sales)
 *   5100 Cost of Feed Sold                (COGS)
 *
 * The COGS subscriber resolves the inventory + COGS accounts per feed type via
 * InventoryAccountResolver (honouring per-kind mappings); the codes here are
 * the config-visible fallbacks.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Channel → revenue account
    |--------------------------------------------------------------------------
    | The feed sale's channel selects the revenue account. 'direct' is the
    | default for internally-recorded sales; 'storefront' is set by the
    | storefront module when it lands.
    */
    'revenue_accounts' => [
        'direct'     => '4110',
        'storefront' => '4120',
    ],
    'default_channel' => 'direct',

    /*
    |--------------------------------------------------------------------------
    | AR control account for feed (credit sales)
    |--------------------------------------------------------------------------
    */
    'ar_account_code' => '1120',

    /*
    |--------------------------------------------------------------------------
    | COGS + inventory fallbacks (resolver-preferred)
    |--------------------------------------------------------------------------
    */
    'cogs_account_code'      => '5100',
    'inventory_account_code' => '1220',

    /*
    |--------------------------------------------------------------------------
    | Default cash account for cash sales
    |--------------------------------------------------------------------------
    */
    'default_cash_account_code' => '1030',

];
