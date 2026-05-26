<?php

/**
 * Phase 8 — Loans & Treasury configuration.
 *
 * Maps each loan type to its GL accounts and sets accrual defaults. Merged
 * via mergeConfigFrom in the service provider so a fresh install has working
 * defaults; publish config/loans.php to override per environment.
 *
 * The COA already defines these accounts (Phase 0 seeder):
 *   2110 Bank Loans — Current Portion        (current liability, control)
 *   2120 Equipment Loans — Current Portion   (current liability, control)
 *   2510 Bank Loans — Long-term Portion      (non-current liability, control)
 *   2520 Equipment Loans — Long-term Portion (non-current liability, control)
 *   2610 Investor Loans Payable              (non-current liability, control)
 *   2130 Accrued Loan Interest               (current liability)
 *   2630 Accrued Investor Returns            (current liability)
 *   7010 Loan Interest Expense               (finance cost)
 *   7020 Loan Fees and Charges               (finance cost)
 *   7030 Loan Penalties                      (finance cost)
 *   7110 Investor Interest Expense           (finance cost)
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Loan type → GL account mapping
    |--------------------------------------------------------------------------
    | Per loan type:
    |   principal_current      where outstanding principal is posted
    |   accrued_interest       where accrued (unpaid) interest sits
    |   interest_expense       the P&L finance-cost account
    |   fees_expense           loan fees/charges
    |   penalty_expense        late-payment penalties
    |
    | We post principal to the CURRENT account on disbursement; the
    | current/long-term split is a reporting reclassification (see the
    | reclassification report), not an automatic per-period posting.
    */
    'types' => [
        'bank' => [
            'label'            => 'Bank Loan',
            'principal_current'=> '2110',
            'principal_longterm'=> '2510',
            'accrued_interest' => '2130',
            'interest_expense' => '7010',
            'fees_expense'     => '7020',
            'penalty_expense'  => '7030',
        ],
        'equipment' => [
            'label'            => 'Equipment Loan',
            'principal_current'=> '2120',
            'principal_longterm'=> '2520',
            'accrued_interest' => '2130',
            'interest_expense' => '7010',
            'fees_expense'     => '7020',
            'penalty_expense'  => '7030',
        ],
        'investor' => [
            'label'            => 'Investor Loan',
            'principal_current'=> '2610',   // investor loans payable (no separate current split)
            'principal_longterm'=> '2610',
            'accrued_interest' => '2630',   // accrued investor returns
            'interest_expense' => '7110',   // investor interest expense
            'fees_expense'     => '7020',
            'penalty_expense'  => '7030',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Amortisation
    |--------------------------------------------------------------------------
    | Supported methods:
    |   reducing_balance  annuity — equal total instalment, interest on the
    |                     declining balance, principal = instalment − interest
    |   straight_line     equal principal each period + interest on the
    |                     declining balance (declining total instalment)
    |
    | interest_basis: how the periodic rate is derived from the annual rate.
    |   'monthly' => annual / 12 (the common SME convention)
    */
    'amortisation' => [
        'default_method' => 'reducing_balance',
        'interest_basis' => 'monthly',
        'rounding_scale' => 2,   // schedule amounts rounded to 2 dp; final period absorbs rounding
    ],

    /*
    |--------------------------------------------------------------------------
    | Default cash account for disbursement/repayment fallback
    |--------------------------------------------------------------------------
    | When a loan disbursement/repayment does not specify a bank account, the
    | engine falls back to this GL code (main operating bank).
    */
    'default_cash_account_code' => '1030',

];
