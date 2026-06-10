<?php

/**
 * Phase 7 — Biological Assets (IAS 41, cost model) configuration.
 *
 * This file is the single source of truth for:
 *   - how a pig/group maps to a biological-asset stage,
 *   - which GL account each stage carries its cost in,
 *   - the fair-value price table used by the IAS 41 disclosure report.
 *
 * It is merged into the application config via mergeConfigFrom in the
 * service provider, so a fresh install has working defaults even without
 * publishing this file. Operators override per environment by publishing
 * config/biological_assets.php and editing the values.
 *
 * ACCOUNTING POLICY: cost model. Biological assets are carried at
 * accumulated cost (opening + feed + allocated costs, less mortality and
 * cost of sales). Fair value is DISCLOSED (see the disclosure report), not
 * posted. This matches the chart-of-accounts policy statement on the 1300
 * account ("measured at cost; fair value disclosure in notes").
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Stage definitions
    |--------------------------------------------------------------------------
    | Each stage maps to a GL account (under the 1300 Biological Assets
    | parent) and a balance-sheet classification. The "fattening" path is
    | age-driven; the "breeding" path is driven by stock_type = 'breeding'.
    |
    | Order matters for age-based resolution: the resolver picks the LAST
    | fattening stage whose min_age_days <= pig age. Keep ascending.
    */
    'stages' => [
        'piglet' => [
            'label'         => 'Piglets',
            'account_code'  => '1340',
            'classification'=> 'current',          // current asset
            'path'          => 'fattening',
            'min_age_days'  => 0,
        ],
        'weaner' => [
            'label'         => 'Weaners',
            'account_code'  => '1350',
            'classification'=> 'current',
            'path'          => 'fattening',
            'min_age_days'  => 45,   // birth_to_weaning_days default
        ],
        'grower' => [
            'label'         => 'Growers',
            'account_code'  => '1360',
            'classification'=> 'current',
            'path'          => 'fattening',
            'min_age_days'  => 90,
        ],
        'finisher' => [
            'label'         => 'Finishers',
            'account_code'  => '1370',
            'classification'=> 'current',
            'path'          => 'fattening',
            'min_age_days'  => 150,
        ],

        // Breeding path (non-current). Resolved by stock_type = 'breeding'
        // plus gender, NOT by age.
        'breeding_stock' => [
            'label'         => 'Breeding Stock',
            'account_code'  => '1310',
            'classification'=> 'non_current',
            'path'          => 'breeding',
            'min_age_days'  => null,
        ],
        'sow' => [
            'label'         => 'Sows in Production',
            'account_code'  => '1320',
            'classification'=> 'non_current',
            'path'          => 'breeding',
            'min_age_days'  => null,
        ],
        'boar' => [
            'label'         => 'Boars',
            'account_code'  => '1330',
            'classification'=> 'non_current',
            'path'          => 'breeding',
            'min_age_days'  => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transitional clearing account
    |--------------------------------------------------------------------------
    | Phase 5 posted all live-pig cost to 1230 Live Pig Inventory. Phase 7
    | repoints those subscribers to the stage accounts above. 1230 is kept
    | as a transitional clearing account; it should normally net to zero.
    | The period-close reconciliation (Phase 6) watches it.
    */
    'clearing_account_code' => '1230',

    /*
    |--------------------------------------------------------------------------
    | Acquisition offset account
    |--------------------------------------------------------------------------
    | When an animal is registered with an explicit acquisition_cost, the
    | finance subscriber capitalises it to the resolved biological-asset stage
    | account. Without supplier/payment context, the conservative offset is
    | owner capital/opening livestock funding. Sites that want purchases to
    | hit AP should record the supplier invoice/payment through finance.
    */
    'acquisition_offset_account_code' => '3010',

    /*
    |--------------------------------------------------------------------------
    | Default stage when context is missing
    |--------------------------------------------------------------------------
    | If a group/pig has no resolvable age or stock_type (bad legacy data),
    | the resolver falls back to this stage rather than throwing, so posting
    | never hard-fails on a single dirty record. The reconciliation report
    | surfaces anything parked here.
    */
    'fallback_stage' => 'grower',

    /*
    |--------------------------------------------------------------------------
    | Fair value table (IAS 41 disclosure ONLY — never posted)
    |--------------------------------------------------------------------------
    | Fair-value-less-costs-to-sell per kg of live weight, by stage, in GHS.
    | Used by the disclosure report to compute the fair-value column beside
    | the carrying (cost) column. Operators MUST review these against the
    | local market; defaults are conservative placeholders.
    |
    | costs_to_sell_pct is deducted from gross fair value to arrive at
    | fair-value-less-costs-to-sell (transport, commission, levies).
    */
    'fair_value' => [
        'currency'           => 'GHS',
        'costs_to_sell_pct'  => '0.05',   // 5% deduction
        'price_per_kg' => [
            'piglet'         => '18.00',
            'weaner'         => '16.00',
            'grower'         => '15.00',
            'finisher'       => '14.00',
            'breeding_stock' => '15.00',
            'sow'            => '15.00',
            'boar'           => '15.00',
        ],
    ],

];
