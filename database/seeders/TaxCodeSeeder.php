<?php

namespace Database\Seeders;

use App\Models\TaxCode;
use Illuminate\Database\Seeder;

/**
 * Seeds the Ghana tax stack as of 2026.
 *
 * The Ghanaian VAT stack consists of FOUR levies applied on the VAT-exclusive
 * base (VAT 15%, NHIL 2.5%, GETFund 2.5%, COVID-19 Health Recovery Levy 1%).
 * Total effective burden on a standard-rated sale: ~21.9% (due to NHIL/GETFund/
 * COVID compounding behaviour — they're technically levies on VAT's tax base,
 * not VAT-on-VAT). For simplicity we seed each code separately and expect
 * Phase 1 to apply the combined rate at posting time via a tax group.
 *
 * Withholding tax rates in Ghana vary by payment type:
 *   - Goods: 3%
 *   - Services: 7.5%
 *   - Rent: 8%
 * We seed the most common.
 *
 * All tax rates are subject to change by Ghana Revenue Authority. Check
 * effective_from / effective_to before relying on historical records.
 *
 * Idempotent via updateOrCreate on (organization_id, code).
 */
class TaxCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            // VAT — standard rate, recoverable (input vs output offset applies).
            [
                'code' => 'VAT_15', 'name' => 'VAT — Standard Rate (15%)',
                'rate_type' => 'percent', 'rate' => 15.0000,
                'applies_to' => 'both', 'is_recoverable' => true,
                'gl_account_hint' => 'VAT_CONTROL', 'display_order' => 10,
            ],

            // VAT zero-rated and exempt supplies.
            [
                'code' => 'VAT_ZERO', 'name' => 'VAT — Zero Rated',
                'rate_type' => 'percent', 'rate' => 0,
                'applies_to' => 'both', 'is_recoverable' => true,
                'display_order' => 11,
            ],
            [
                'code' => 'VAT_EXEMPT', 'name' => 'VAT — Exempt',
                'rate_type' => 'percent', 'rate' => 0,
                'applies_to' => 'both', 'is_recoverable' => false,
                'display_order' => 12,
            ],

            // Health levies — NOT recoverable.
            [
                'code' => 'NHIL', 'name' => 'NHIL (National Health Insurance Levy 2.5%)',
                'rate_type' => 'percent', 'rate' => 2.5000,
                'applies_to' => 'both', 'is_recoverable' => false, 'is_compound' => true,
                'gl_account_hint' => 'NHIL_PAYABLE', 'display_order' => 20,
            ],
            [
                'code' => 'GETFUND', 'name' => 'GETFund Levy (2.5%)',
                'rate_type' => 'percent', 'rate' => 2.5000,
                'applies_to' => 'both', 'is_recoverable' => false, 'is_compound' => true,
                'gl_account_hint' => 'GETFUND_PAYABLE', 'display_order' => 21,
            ],
            [
                'code' => 'COVID_LEVY', 'name' => 'COVID-19 Health Recovery Levy (1%)',
                'rate_type' => 'percent', 'rate' => 1.0000,
                'applies_to' => 'both', 'is_recoverable' => false, 'is_compound' => true,
                'gl_account_hint' => 'COVID_LEVY_PAYABLE', 'display_order' => 22,
            ],

            // Withholding taxes (supplier deductions)
            [
                'code' => 'WHT_GOODS_3', 'name' => 'Withholding Tax — Goods (3%)',
                'rate_type' => 'percent', 'rate' => 3.0000,
                'applies_to' => 'purchase', 'is_withholding' => true,
                'gl_account_hint' => 'WHT_PAYABLE', 'display_order' => 30,
            ],
            [
                'code' => 'WHT_SERVICES_75', 'name' => 'Withholding Tax — Services (7.5%)',
                'rate_type' => 'percent', 'rate' => 7.5000,
                'applies_to' => 'purchase', 'is_withholding' => true,
                'gl_account_hint' => 'WHT_PAYABLE', 'display_order' => 31,
            ],
            [
                'code' => 'WHT_RENT_8', 'name' => 'Withholding Tax — Rent (8%)',
                'rate_type' => 'percent', 'rate' => 8.0000,
                'applies_to' => 'purchase', 'is_withholding' => true,
                'gl_account_hint' => 'WHT_PAYABLE', 'display_order' => 32,
            ],

            // Non-taxable marker — use when no tax applies.
            [
                'code' => 'NONE', 'name' => 'No Tax',
                'rate_type' => 'percent', 'rate' => 0,
                'applies_to' => 'both', 'is_recoverable' => false,
                'display_order' => 99,
            ],
        ];

        foreach ($codes as $code) {
            TaxCode::updateOrCreate(
                [
                    'organization_id' => $code['organization_id'] ?? null,
                    'code'            => $code['code'],
                ],
                array_merge([
                    'is_recoverable' => false,
                    'is_withholding' => false,
                    'is_compound'    => false,
                    'is_active'      => true,
                    'effective_from' => '2020-01-01',
                ], $code)
            );
        }
    }
}