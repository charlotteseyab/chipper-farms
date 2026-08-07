<?php

use App\Support\Features\FeatureCatalog;

/**
 * Farm-language expense categories, mapped to the chart of accounts
 * (Phase 4, blueprint §23, prompt §33).
 *
 * WHY THIS IS THE WHOLE POINT OF QUICK EXPENSE
 * --------------------------------------------
 * A farm manager paying the electricity bill should choose "Electricity", not
 * account 6310. The mapping below is the entire translation layer between farm
 * language and double-entry: everything else in the workflow is plumbing.
 *
 * Every code here was checked against ChartOfAccountsSeeder. A category
 * pointing at an account that does not exist would fail at posting time, after
 * the user has filled in the form and attached a receipt — which is the worst
 * possible moment to discover a configuration error. There is a test that walks
 * this file and asserts every account resolves.
 *
 * CONFIGURABLE, NOT HARDCODED
 * ---------------------------
 * This is config rather than a database table on purpose. The categories are a
 * product decision that ships with the application, and an organisation that
 * needs a different mapping overrides the account code via the
 * `finance.expense_category_overrides` setting rather than by editing rows.
 * Making it a table would mean every new farm needs nineteen seeded rows before
 * it can record a single expense.
 *
 * THE SUSPENSE FALLBACK
 * ---------------------
 * Blueprint §23 requires an unknown category to route to a suspense expense and
 * raise a needs-attention item, rather than blocking the user or silently
 * picking something plausible. Account 2910 (Suspense — Payments) already
 * exists for this. A farm manager should never be stopped from recording that
 * money left the farm; getting the classification right is an accountant's job
 * and can happen afterwards.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default cash/bank/MoMo accounts by payment method
    |--------------------------------------------------------------------------
    |
    | The credit side of a paid-now expense. Mobile money providers are kept
    | separate because their settlement and reference formats differ, which
    | blueprint §25 calls out explicitly.
    */
    'payment_accounts' => [
        'cash'        => ['label' => 'Cash', 'account' => '1010'],
        'petty_cash'  => ['label' => 'Petty Cash', 'account' => '1020'],
        'bank'        => ['label' => 'Bank', 'account' => '1030'],
        'momo_mtn'    => ['label' => 'MTN Mobile Money', 'account' => '1060'],
        'momo_at'     => ['label' => 'AT Money', 'account' => '1061'],
        'momo_telecel'=> ['label' => 'Telecel Cash', 'account' => '1062'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment methods that require a reference
    |--------------------------------------------------------------------------
    |
    | Blueprint §23 item 7: reference optional, but required for MoMo and bank.
    | A MoMo payment with no transaction id cannot be reconciled against the
    | provider statement later, and reconciliation is where errors are caught.
    */
    'reference_required_for' => ['bank', 'momo_mtn', 'momo_at', 'momo_telecel'],

    /*
    |--------------------------------------------------------------------------
    | Accounts payable — the credit side of a pay-later expense
    |--------------------------------------------------------------------------
    */
    'payable_account' => '2010',

    /*
    |--------------------------------------------------------------------------
    | Suspense — used when a category cannot be resolved
    |--------------------------------------------------------------------------
    */
    'suspense_account' => '2910',

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    |
    | `feature` optionally hides a category when the module it belongs to is
    | switched off. Most are unconditional — a farm always has electricity.
    */
    'categories' => [
        'veterinary' => [
            'label'   => 'Veterinary & Medicine',
            'account' => '6010',
            'hint'    => 'Vaccines, drugs, vet visits and diagnostics.',
        ],
        'vet_services' => [
            'label'   => 'Veterinary Services',
            'account' => '6020',
            'hint'    => 'Call-out fees and professional veterinary work.',
        ],
        'feed_consumables' => [
            'label'   => 'Feed & Farm Consumables',
            'account' => '6100',
            'hint'    => 'Operating costs of running the feed mill.',
        ],
        'labour' => [
            'label'   => 'Farm Labour',
            'account' => '6220',
            'hint'    => 'Casual and day labour.',
        ],
        'salaries' => [
            'label'   => 'Salaries & Wages',
            'account' => '6210',
            'hint'    => 'Regular staff pay.',
        ],
        'electricity' => [
            'label'   => 'Electricity',
            'account' => '6310',
            'hint'    => 'ECG, NEDCo and generator power.',
        ],
        'water' => [
            'label'   => 'Water',
            'account' => '6320',
            'hint'    => 'GWCL and borehole costs.',
        ],
        'fuel' => [
            'label'   => 'Fuel & Gas',
            'account' => '6330',
            'hint'    => 'Diesel, petrol and LPG.',
        ],
        'communications' => [
            'label'   => 'Airtime & Internet',
            'account' => '6340',
            'hint'    => 'Phone credit, data and internet.',
        ],
        'repairs' => [
            'label'   => 'Repairs & Maintenance',
            'account' => '6400',
            'hint'    => 'Buildings, pens and general upkeep.',
        ],
        'equipment_maintenance' => [
            'label'   => 'Equipment Maintenance',
            'account' => '6420',
            'hint'    => 'Servicing and spare parts.',
        ],
        'vehicle' => [
            'label'   => 'Vehicle Running Costs',
            'account' => '6430',
            'hint'    => 'Servicing, tyres and repairs.',
        ],
        'transport' => [
            'label'   => 'Transport & Delivery',
            'account' => '6730',
            'hint'    => 'Moving stock, feed or produce.',
        ],
        'office' => [
            'label'   => 'Office & Admin',
            'account' => '6610',
            'hint'    => 'Stationery, printing and sundries.',
        ],
        'insurance' => [
            'label'   => 'Insurance',
            'account' => '6630',
            'hint'    => 'Farm, vehicle and liability cover.',
        ],
        'professional_fees' => [
            'label'   => 'Professional Fees',
            'account' => '6800',
            'hint'    => 'Accountants, lawyers and consultants.',
        ],
        'marketing' => [
            'label'   => 'Marketing',
            'account' => '6710',
            'hint'    => 'Advertising and promotion.',
        ],
        'bank_charges' => [
            'label'   => 'Bank Charges',
            'account' => '6910',
            'hint'    => 'Account fees and transfer charges.',
        ],
        'momo_charges' => [
            'label'   => 'MoMo Charges',
            'account' => '6920',
            'hint'    => 'Mobile money transaction fees.',
        ],
        'training' => [
            'label'   => 'Training',
            'account' => '6650',
            'hint'    => 'Courses and staff development.',
        ],
        'other' => [
            'label'   => 'Something else',
            'account' => '6600',
            'hint'    => 'Anything that does not fit above. An accountant can '
                . 'reclassify it later.',
        ],
    ],
];
