<?php

/**
 * Phase 9 — Investor / Fund tracking configuration.
 *
 * Maps investor postings to GL accounts and sets behaviour flags. Merged via
 * mergeConfigFrom in the service provider; publish config/investors.php to
 * override per environment.
 *
 * The COA already defines these (Phase 0 seeder):
 *   3100 Investor Capital Accounts          (equity, control — the subledger control)
 *   3110 Investor Equity — Contributions    (equity)
 *   3120 Investor Equity — Distributions    (equity, contra)
 *   3130 Investor Equity — Profit Allocation (equity, tracking)
 *   3200 Retained Earnings                  (equity)
 *   2620 Investor Preferred Shares          (liability)
 *   2630 Accrued Investor Returns           (liability)
 *   2640 Investor Returns Payable           (liability)
 *   7120 Preferred Share Returns            (finance cost)
 *
 * ACCOUNTING MODEL (locked decisions):
 *   - Contribution:  Dr cash/asset           / Cr 3110 (and the investor's
 *                    capital balance rises). 3100 is the control; 3110 is the
 *                    contributions movement account.
 *   - Profit alloc:  Dr 3200 Retained Earnings / Cr 3100 Investor Capital.
 *                    This MOVES profit already swept into retained earnings
 *                    (by the Phase 6 year-end close) into investor capital.
 *                    RULE: run allocation AFTER the year-end close. 3130 is a
 *                    disclosure/tracking view, not the posting target, to
 *                    avoid inflating total equity.
 *   - Distribution:  Dr 3120 Distributions   / Cr cash. Reduces capital.
 *   - Preferred ret: Dr 7120 / Cr 2630 (accrue); Dr 2630 / Cr 2640 or bank (pay).
 */

return [

    /*
    |--------------------------------------------------------------------------
    | GL account mapping
    |--------------------------------------------------------------------------
    */
    'accounts' => [
        'capital_control'      => '3100', // investor capital subledger control
        'contributions'        => '3110',
        'distributions'        => '3120',
        'profit_allocation'    => '3130', // tracking/disclosure sub-account
        'retained_earnings'    => '3200', // source of allocated profit
        'preferred_shares'     => '2620',
        'accrued_returns'      => '2630',
        'returns_payable'      => '2640',
        'preferred_return_exp' => '7120',
    ],

    /*
    |--------------------------------------------------------------------------
    | Profit allocation
    |--------------------------------------------------------------------------
    | method:
    |   'ownership_pct'  allocate by each investor's ownership_percentage
    |   'fixed_schedule' allocate by per-investor fixed amounts supplied to
    |                    the run (caller passes the schedule)
    |
    | post_debit_account: where the allocation debit lands. Default 3200
    | (retained earnings) — moves real profit into investor capital without
    | inflating total equity. Set to 3130 only if your policy treats profit
    | allocation as an equity-internal reclassification (rare).
    */
    'allocation' => [
        'method'             => 'ownership_pct',
        'post_debit_account' => '3200',
    ],

    /*
    |--------------------------------------------------------------------------
    | Preferred shares
    |--------------------------------------------------------------------------
    | enabled: gate the preferred-share return accrual/payment features. Many
    | farms issue only ordinary equity; leave false unless preferred shares
    | are actually on issue.
    */
    'preferred_shares' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default cash account for contributions in / distributions out
    |--------------------------------------------------------------------------
    */
    'default_cash_account_code' => '1030',

];
