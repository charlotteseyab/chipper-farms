<?php

namespace Database\Seeders;

use App\Models\Finance\Account;
use Illuminate\Database\Seeder;

/**
 * Phase 1a — Chart of Accounts seed for Chipper Farms.
 *
 * Decision 9: 4-digit numeric block scheme.
 * Decision 7: Cost model + IAS 41 — biological asset accounts at cost.
 * Decision 4: Hybrid investor framework — separate equity vs liability blocks.
 * Decision 6: Effective-interest loans — Finance Costs block.
 *
 * Idempotent via updateOrCreate on (organization_id, code).
 *
 * Re-running is safe — existing accounts are updated with the latest names
 * and metadata; their ids and historical journal references are preserved.
 *
 * Account code summary:
 *   1000-1999 ASSETS       (cash, AR, inventory, biological, prepayments, PPE)
 *   2000-2999 LIABILITIES  (AP, loans, taxes, prepayments-received, accruals, investor-debt)
 *   3000-3999 EQUITY       (capital, investor-equity, retained earnings)
 *   4000-4999 REVENUE      (pig sales, feed sales, other income)
 *   5000-5999 COGS         (cost of pigs sold, feed COGS, inventory adjustments)
 *   6000-7999 OPERATING    (vet, labour, utilities, depreciation, etc.)
 *   8000-8999 OTHER        (FX gains/losses, gains/losses on disposal)
 *   9000-9999 reserved
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = null;
        // Phase 1 ships single-tenant; null org_id is the default Chipper Farms org.
        // For multi-tenant setups, this seeder would loop per organisation.

        $accounts = $this->definition();

        // First pass: create/update all accounts WITHOUT parent_id (avoids
        // ordering dependency on parent codes existing first).
        foreach ($accounts as $a) {
            Account::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'code'            => $a['code'],
                ],
                [
                    'name'                 => $a['name'],
                    'description'          => $a['description'] ?? null,
                    'account_type'         => $a['account_type'],
                    'account_subtype'      => $a['account_subtype'] ?? null,
                    'currency_code'        => $a['currency_code'] ?? 'GHS',
                    'is_control'           => $a['is_control'] ?? false,
                    'is_active'            => $a['is_active'] ?? true,
                    'is_system'            => $a['is_system'] ?? false,
                    'allow_manual_journal' => $a['allow_manual_journal'] ?? true,
                    'display_order'        => $a['display_order'] ?? (int) $a['code'],
                ]
            );
        }

        // Second pass: wire up parent_id by looking up parent codes.
        foreach ($accounts as $a) {
            if (! isset($a['parent_code'])) {
                continue;
            }

            $parent = Account::where('organization_id', $organizationId)
                ->where('code', $a['parent_code'])
                ->first();

            if (! $parent) {
                continue; // Parent not found; skip without erroring.
            }

            Account::where('organization_id', $organizationId)
                ->where('code', $a['code'])
                ->update(['parent_id' => $parent->id]);
        }
    }

    /**
     * The full account definition. Each entry is one account.
     * Parent codes are referenced by string code, not id, for readability.
     */
    private function definition(): array
    {
        return [
            // ============================================
            // 1000-1999 ASSETS
            // ============================================

            // 1000-1099 Cash and Bank (parent groups)
            ['code' => '1000', 'name' => 'Cash and Bank',                    'account_type' => 'asset', 'account_subtype' => 'current_asset',     'is_system' => true,  'allow_manual_journal' => false],
            ['code' => '1010', 'name' => 'Cash on Hand',                     'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1000'],
            ['code' => '1020', 'name' => 'Petty Cash',                       'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1000'],
            ['code' => '1030', 'name' => 'Bank — Main Operating',            'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1000'],
            ['code' => '1040', 'name' => 'Bank — Savings',                   'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1000'],
            ['code' => '1050', 'name' => 'Bank — Foreign Currency',          'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1000'],
            ['code' => '1060', 'name' => 'Mobile Money — MTN',               'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1000'],
            ['code' => '1061', 'name' => 'Mobile Money — AirtelTigo',        'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1000'],
            ['code' => '1062', 'name' => 'Mobile Money — Vodafone',          'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1000'],

            // 1100-1199 Accounts Receivable
            ['code' => '1100', 'name' => 'Accounts Receivable',              'account_type' => 'asset', 'account_subtype' => 'current_asset',     'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '1110', 'name' => 'Trade Receivables — Pig Sales',    'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1100', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1120', 'name' => 'Trade Receivables — Feed Sales',   'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1100', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1130', 'name' => 'Trade Receivables — Other',        'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1100', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1190', 'name' => 'Allowance for Doubtful Accounts',  'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1100', 'description' => 'Contra-asset; credit balance offsets AR'],

            // 1200-1299 Inventory
            ['code' => '1200', 'name' => 'Inventory',                        'account_type' => 'asset', 'account_subtype' => 'current_asset',     'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '1210', 'name' => 'Raw Materials Inventory',          'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1200', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1220', 'name' => 'Finished Feed Inventory',          'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1200', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1230', 'name' => 'Live Pig Inventory',               'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1200', 'is_system' => true, 'is_control' => true, 'allow_manual_journal' => false, 'description' => 'Per-group cost pools roll up here (Phase 5). Subscribers are the only sanctioned writers.'],
            ['code' => '1240', 'name' => 'Packaging Materials Inventory',    'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1200'],
            ['code' => '1250', 'name' => 'Veterinary Supplies Inventory',    'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1200', 'is_system' => true, 'description' => 'Relocated from 1230 in Phase 6. Reserved for vet-supply capitalisation; not yet wired.'],

            // 1300-1399 Biological Assets (Decision 7: cost model)
            ['code' => '1300', 'name' => 'Biological Assets',                'account_type' => 'asset', 'account_subtype' => 'current_asset',     'is_system' => true, 'allow_manual_journal' => false, 'description' => 'IAS 41 — measured at cost; fair value disclosure in notes'],
            ['code' => '1310', 'name' => 'Biological Assets — Breeding Stock','account_type' => 'asset', 'account_subtype' => 'non_current_asset','parent_code' => '1300', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1320', 'name' => 'Biological Assets — Sows in Production','account_type' => 'asset', 'account_subtype' => 'non_current_asset','parent_code' => '1300', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1330', 'name' => 'Biological Assets — Boars',        'account_type' => 'asset', 'account_subtype' => 'non_current_asset','parent_code' => '1300', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1340', 'name' => 'Biological Assets — Piglets',     'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1300', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1350', 'name' => 'Biological Assets — Weaners',      'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1300', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1360', 'name' => 'Biological Assets — Growers',      'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1300', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '1370', 'name' => 'Biological Assets — Finishers',   'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1300', 'is_control' => true, 'allow_manual_journal' => false],

            // 1400-1499 Prepayments and Advances
            ['code' => '1400', 'name' => 'Prepayments and Advances',         'account_type' => 'asset', 'account_subtype' => 'current_asset',     'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '1410', 'name' => 'Prepaid Insurance',                'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1400'],
            ['code' => '1420', 'name' => 'Prepaid Rent',                     'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1400'],
            ['code' => '1430', 'name' => 'Supplier Advances',                'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1400', 'is_control' => true],
            ['code' => '1440', 'name' => 'Staff Advances',                   'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1400'],
            ['code' => '1490', 'name' => 'Other Prepayments',                'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1400'],

            // 1500-1599 Property, Plant & Equipment
            ['code' => '1500', 'name' => 'Property, Plant & Equipment',      'account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '1510', 'name' => 'Land',                             'account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'parent_code' => '1500'],
            ['code' => '1520', 'name' => 'Buildings — Pens',                 'account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'parent_code' => '1500'],
            ['code' => '1521', 'name' => 'Accumulated Depreciation — Buildings','account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'parent_code' => '1500', 'description' => 'Contra-asset'],
            ['code' => '1530', 'name' => 'Equipment — Feed Mill',            'account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'parent_code' => '1500'],
            ['code' => '1531', 'name' => 'Accumulated Depreciation — Feed Mill','account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'parent_code' => '1500', 'description' => 'Contra-asset'],
            ['code' => '1540', 'name' => 'Vehicles',                         'account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'parent_code' => '1500'],
            ['code' => '1541', 'name' => 'Accumulated Depreciation — Vehicles','account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'parent_code' => '1500', 'description' => 'Contra-asset'],
            ['code' => '1550', 'name' => 'Office Equipment',                 'account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'parent_code' => '1500'],
            ['code' => '1551', 'name' => 'Accumulated Depreciation — Office Equipment','account_type' => 'asset', 'account_subtype' => 'non_current_asset', 'parent_code' => '1500', 'description' => 'Contra-asset'],

            // 1900-1999 Other Assets
            ['code' => '1900', 'name' => 'Other Assets',                     'account_type' => 'asset', 'account_subtype' => 'current_asset',     'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '1910', 'name' => 'Suspense — Receipts',              'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1900', 'is_system' => true, 'description' => 'Holding account for unallocated receipts'],
            ['code' => '1990', 'name' => 'Other Receivables',                'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1900'],

            // ============================================
            // 2000-2999 LIABILITIES
            // ============================================

            // 2000-2099 Accounts Payable
            ['code' => '2000', 'name' => 'Accounts Payable',                 'account_type' => 'liability', 'account_subtype' => 'current_liability', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '2010', 'name' => 'Trade Payables — Suppliers',       'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2000', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '2020', 'name' => 'Trade Payables — Feed Suppliers',  'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2000', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '2030', 'name' => 'Trade Payables — Veterinary',      'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2000', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '2090', 'name' => 'Trade Payables — Other',           'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2000', 'is_control' => true, 'allow_manual_journal' => false],

            // 2100-2199 Loans Payable — current portion (Decision 6)
            ['code' => '2100', 'name' => 'Loans Payable — Current',          'account_type' => 'liability', 'account_subtype' => 'current_liability', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '2110', 'name' => 'Bank Loans — Current Portion',     'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2100', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '2120', 'name' => 'Equipment Loans — Current Portion','account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2100', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '2130', 'name' => 'Accrued Loan Interest',            'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2100'],

            // 2200-2299 Tax Payable (Ghana stack)
            ['code' => '2200', 'name' => 'Taxes Payable',                    'account_type' => 'liability', 'account_subtype' => 'current_liability', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '2210', 'name' => 'VAT Payable (Output)',             'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2200', 'is_control' => true],
            ['code' => '2211', 'name' => 'VAT Receivable (Input)',           'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2200', 'description' => 'Contra-liability; debit balance offsets VAT Payable'],
            ['code' => '2220', 'name' => 'NHIL Payable',                     'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2200'],
            ['code' => '2230', 'name' => 'GETFund Payable',                  'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2200'],
            ['code' => '2240', 'name' => 'COVID-19 Health Recovery Levy Payable', 'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2200'],
            ['code' => '2250', 'name' => 'Withholding Tax Payable',          'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2200'],
            ['code' => '2260', 'name' => 'PAYE Payable',                     'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2200'],
            ['code' => '2270', 'name' => 'SSNIT Payable',                    'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2200'],

            // 2300-2399 Customer Prepayments (Decision 5: two-step storefront)
            ['code' => '2300', 'name' => 'Customer Prepayments',             'account_type' => 'liability', 'account_subtype' => 'current_liability', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'Cash received in advance of fulfilment (IFRS 15)'],
            ['code' => '2310', 'name' => 'Customer Prepayments — Pig Orders','account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2300', 'is_control' => true],
            ['code' => '2320', 'name' => 'Customer Prepayments — Feed Orders','account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2300', 'is_control' => true],
            ['code' => '2390', 'name' => 'Customer Prepayments — Other',     'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2300', 'is_control' => true],

            // 2400-2499 Accruals
            ['code' => '2400', 'name' => 'Accrued Liabilities',              'account_type' => 'liability', 'account_subtype' => 'current_liability', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '2410', 'name' => 'Accrued Salaries and Wages',       'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2400'],
            ['code' => '2420', 'name' => 'Accrued Utilities',                'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2400'],
            ['code' => '2430', 'name' => 'Accrued Professional Fees',        'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2400'],
            ['code' => '2490', 'name' => 'Other Accruals',                   'account_type' => 'liability', 'account_subtype' => 'current_liability', 'parent_code' => '2400'],

            // 2500-2599 Long-term Loans Payable
            ['code' => '2500', 'name' => 'Long-term Loans Payable',          'account_type' => 'liability', 'account_subtype' => 'non_current_liability', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '2510', 'name' => 'Bank Loans — Long-term Portion',   'account_type' => 'liability', 'account_subtype' => 'non_current_liability', 'parent_code' => '2500', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '2520', 'name' => 'Equipment Loans — Long-term Portion','account_type' => 'liability', 'account_subtype' => 'non_current_liability', 'parent_code' => '2500', 'is_control' => true, 'allow_manual_journal' => false],

            // 2600-2699 Investor Liabilities (Decision 4: debt and preferred treatment)
            ['code' => '2600', 'name' => 'Investor Liabilities',             'account_type' => 'liability', 'account_subtype' => 'non_current_liability', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'For investors with debt or preferred-shares treatment'],
            ['code' => '2610', 'name' => 'Investor Loans Payable',           'account_type' => 'liability', 'account_subtype' => 'non_current_liability', 'parent_code' => '2600', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '2620', 'name' => 'Investor Preferred Shares',        'account_type' => 'liability', 'account_subtype' => 'non_current_liability', 'parent_code' => '2600', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '2630', 'name' => 'Accrued Investor Returns',         'account_type' => 'liability', 'account_subtype' => 'current_liability',     'parent_code' => '2600'],
            ['code' => '2640', 'name' => 'Investor Returns Payable',         'account_type' => 'liability', 'account_subtype' => 'current_liability',     'parent_code' => '2600'],

            // 2900-2999 Other Liabilities
            ['code' => '2900', 'name' => 'Other Liabilities',                'account_type' => 'liability', 'account_subtype' => 'current_liability',     'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '2910', 'name' => 'Suspense — Payments',              'account_type' => 'liability', 'account_subtype' => 'current_liability',     'parent_code' => '2900', 'is_system' => true, 'description' => 'Holding account for unallocated payments'],

            // ============================================
            // 3000-3999 EQUITY
            // ============================================

            ['code' => '3000', 'name' => 'Owner\'s Equity',                  'account_type' => 'equity', 'account_subtype' => 'equity', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '3010', 'name' => 'Owner\'s Capital — Founders',      'account_type' => 'equity', 'account_subtype' => 'equity', 'parent_code' => '3000'],
            ['code' => '3020', 'name' => 'Owner\'s Drawings',                'account_type' => 'equity', 'account_subtype' => 'equity', 'parent_code' => '3000', 'description' => 'Contra-equity; debit balance reduces equity'],

            // Investor Equity (Decision 4: equity treatment)
            ['code' => '3100', 'name' => 'Investor Capital Accounts',        'account_type' => 'equity', 'account_subtype' => 'equity', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '3110', 'name' => 'Investor Equity — Contributions',  'account_type' => 'equity', 'account_subtype' => 'equity', 'parent_code' => '3100', 'is_control' => true, 'allow_manual_journal' => false],
            ['code' => '3120', 'name' => 'Investor Equity — Distributions',  'account_type' => 'equity', 'account_subtype' => 'equity', 'parent_code' => '3100', 'is_control' => true, 'allow_manual_journal' => false, 'description' => 'Contra-equity; reduces investor balances'],
            ['code' => '3130', 'name' => 'Investor Equity — Profit Allocation', 'account_type' => 'equity', 'account_subtype' => 'equity', 'parent_code' => '3100', 'is_control' => true, 'allow_manual_journal' => false],

            // Retained Earnings + Current Year (clearing)
            ['code' => '3200', 'name' => 'Retained Earnings',                'account_type' => 'equity', 'account_subtype' => 'equity', 'is_system' => true],
            ['code' => '3300', 'name' => 'Current Year Earnings (Clearing)', 'account_type' => 'equity', 'account_subtype' => 'equity', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'Closing entries land here; period-end roll-forward sweeps to Retained Earnings'],

            // ============================================
            // 4000-4999 REVENUE
            // ============================================

            ['code' => '4000', 'name' => 'Pig Sales Revenue',                'account_type' => 'revenue', 'account_subtype' => 'revenue', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '4010', 'name' => 'Revenue — Live Pigs (Standard)',   'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4000'],
            ['code' => '4020', 'name' => 'Revenue — Live Pigs (Breeding)',   'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4000'],
            ['code' => '4030', 'name' => 'Revenue — Carcass Pigs',           'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4000'],
            ['code' => '4040', 'name' => 'Revenue — Cull Pigs',              'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4000'],
            ['code' => '4090', 'name' => 'Revenue — Pig Sales (Other)',      'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4000'],

            ['code' => '4100', 'name' => 'Feed Sales Revenue',               'account_type' => 'revenue', 'account_subtype' => 'revenue', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '4110', 'name' => 'Revenue — Feed (Direct)',          'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4100'],
            ['code' => '4120', 'name' => 'Revenue — Feed (Storefront)',      'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4100'],

            ['code' => '4200', 'name' => 'By-Product Revenue',               'account_type' => 'revenue', 'account_subtype' => 'revenue', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '4210', 'name' => 'Revenue — Manure',                 'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4200'],
            ['code' => '4290', 'name' => 'Revenue — Other By-products',      'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4200'],

            ['code' => '4900', 'name' => 'Other Income',                     'account_type' => 'revenue', 'account_subtype' => 'revenue', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '4910', 'name' => 'Interest Income',                  'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4900'],
            ['code' => '4920', 'name' => 'FX Gains',                         'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4900'],
            ['code' => '4930', 'name' => 'Insurance Recoveries',             'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4900'],
            ['code' => '4990', 'name' => 'Miscellaneous Income',             'account_type' => 'revenue', 'account_subtype' => 'revenue', 'parent_code' => '4900'],

            // ============================================
            // 5000-5999 COST OF GOODS SOLD
            // ============================================

            ['code' => '5000', 'name' => 'Cost of Sales',                    'account_type' => 'expense', 'account_subtype' => 'cogs', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'Cost of sales parent. Children: 5100 feed, 5110 pigs, 5200 by-products, 5300 adjustments, 5320 mortality.'],
            ['code' => '5100', 'name' => 'Cost of Feed Sold',                'account_type' => 'expense', 'account_subtype' => 'cogs', 'parent_code' => '5000', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '5110', 'name' => 'Cost of Pigs Sold',                'account_type' => 'expense', 'account_subtype' => 'cogs', 'parent_code' => '5000', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'COGS at pig sale: weight-proportional share of source group pool. Posted by PigSaleCogsSubscriber.'],
            ['code' => '5200', 'name' => 'Cost of By-products Sold',         'account_type' => 'expense', 'account_subtype' => 'cogs', 'parent_code' => '5000', 'is_system' => true, 'allow_manual_journal' => false],
            ['code' => '5300', 'name' => 'Inventory Adjustments and Shrinkage','account_type' => 'expense', 'account_subtype' => 'cogs', 'parent_code' => '5000', 'is_system' => true, 'description' => 'Stock-take discrepancies, write-offs'],
            ['code' => '5320', 'name' => 'Mortality Loss',                   'account_type' => 'expense', 'account_subtype' => 'cogs', 'parent_code' => '5000', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'Cost basis written off when a pig dies (Phase 5). Posted by PigDeathRecordedSubscriber.'],
            ['code' => '5400', 'name' => 'Biological Asset Mortality (Adult)','account_type' => 'expense', 'account_subtype' => 'cogs', 'parent_code' => '5000', 'is_system' => true, 'description' => 'Adult pig deaths; carrying cost write-off'],
            ['code' => '5410', 'name' => 'Biological Asset Mortality (Piglet)','account_type' => 'expense', 'account_subtype' => 'cogs', 'parent_code' => '5000', 'is_system' => true, 'description' => 'Piglet deaths; carrying cost write-off'],

            // ============================================
            // 6000-7999 OPERATING EXPENSES
            // ============================================

            ['code' => '6000', 'name' => 'Veterinary and Health',            'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6010', 'name' => 'Veterinary Medicine and Vaccines', 'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6000'],
            ['code' => '6020', 'name' => 'Veterinary Services',              'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6000'],
            ['code' => '6030', 'name' => 'Diagnostic Tests',                 'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6000'],

            ['code' => '6100', 'name' => 'Feed Production Operating Costs',  'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6110', 'name' => 'Feed Mill Energy',                 'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6100'],
            ['code' => '6120', 'name' => 'Feed Mill Maintenance',            'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6100'],

            ['code' => '6200', 'name' => 'Farm Labour',                      'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6210', 'name' => 'Salaries and Wages',               'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6200'],
            ['code' => '6220', 'name' => 'Casual Labour',                    'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6200'],
            ['code' => '6230', 'name' => 'Staff Welfare',                    'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6200'],
            ['code' => '6240', 'name' => 'SSNIT Employer Contribution',      'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6200'],

            ['code' => '6300', 'name' => 'Utilities',                        'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6310', 'name' => 'Electricity (ECG/NEDCo)',          'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6300'],
            ['code' => '6320', 'name' => 'Water (GWCL / Borehole)',          'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6300'],
            ['code' => '6330', 'name' => 'Gas / Fuel',                       'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6300'],
            ['code' => '6340', 'name' => 'Internet and Communications',      'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6300'],

            ['code' => '6400', 'name' => 'Repairs and Maintenance',          'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6410', 'name' => 'Building Repairs',                 'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6400'],
            ['code' => '6420', 'name' => 'Equipment Maintenance',            'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6400'],
            ['code' => '6430', 'name' => 'Vehicle Maintenance',              'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6400'],

            ['code' => '6500', 'name' => 'Depreciation',                     'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6510', 'name' => 'Depreciation — Buildings',         'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6500'],
            ['code' => '6520', 'name' => 'Depreciation — Feed Mill',         'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6500'],
            ['code' => '6530', 'name' => 'Depreciation — Vehicles',          'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6500'],
            ['code' => '6540', 'name' => 'Depreciation — Office Equipment',  'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6500'],

            ['code' => '6600', 'name' => 'Office and Administration',        'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6610', 'name' => 'Office Supplies',                  'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6600'],
            ['code' => '6620', 'name' => 'Software Subscriptions',           'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6600'],
            ['code' => '6630', 'name' => 'Insurance',                        'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6600'],
            ['code' => '6640', 'name' => 'Travel and Accommodation',         'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6600'],
            ['code' => '6650', 'name' => 'Training and Development',         'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6600'],

            ['code' => '6700', 'name' => 'Marketing and Sales',              'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6710', 'name' => 'Marketing and Advertising',        'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6700'],
            ['code' => '6720', 'name' => 'Sales Commissions',                'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6700'],
            ['code' => '6730', 'name' => 'Delivery and Transport (Outbound)','account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6700'],

            ['code' => '6800', 'name' => 'Professional Fees',                'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6810', 'name' => 'Audit and Accounting Fees',        'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6800'],
            ['code' => '6820', 'name' => 'Legal Fees',                       'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6800'],
            ['code' => '6830', 'name' => 'Consulting Fees',                  'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6800'],

            ['code' => '6900', 'name' => 'Bank Charges and Levies',          'account_type' => 'expense', 'account_subtype' => 'opex', 'is_system' => true],
            ['code' => '6910', 'name' => 'Bank Charges',                     'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6900'],
            ['code' => '6920', 'name' => 'Mobile Money Charges',             'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6900'],
            ['code' => '6930', 'name' => 'Card Processing Fees',             'account_type' => 'expense', 'account_subtype' => 'opex', 'parent_code' => '6900'],

            // 7000-7099 Finance Costs (Decision 6: effective-interest)
            ['code' => '7000', 'name' => 'Finance Costs',                    'account_type' => 'expense', 'account_subtype' => 'finance_cost', 'is_system' => true],
            ['code' => '7010', 'name' => 'Loan Interest Expense',            'account_type' => 'expense', 'account_subtype' => 'finance_cost', 'parent_code' => '7000'],
            ['code' => '7020', 'name' => 'Loan Fees and Charges',            'account_type' => 'expense', 'account_subtype' => 'finance_cost', 'parent_code' => '7000'],
            ['code' => '7030', 'name' => 'Loan Penalties',                   'account_type' => 'expense', 'account_subtype' => 'finance_cost', 'parent_code' => '7000'],

            // 7100-7199 Investor Returns (Decision 4)
            ['code' => '7100', 'name' => 'Investor Returns Expense',         'account_type' => 'expense', 'account_subtype' => 'finance_cost', 'is_system' => true, 'description' => 'Interest on debt-treatment investors'],
            ['code' => '7110', 'name' => 'Investor Interest Expense',        'account_type' => 'expense', 'account_subtype' => 'finance_cost', 'parent_code' => '7100'],
            ['code' => '7120', 'name' => 'Preferred Share Returns',          'account_type' => 'expense', 'account_subtype' => 'finance_cost', 'parent_code' => '7100'],

            // ============================================
            // 8000-8999 OTHER GAINS / LOSSES
            // ============================================

            ['code' => '8000', 'name' => 'Other Gains and Losses',           'account_type' => 'expense', 'account_subtype' => 'other_expense', 'is_system' => true],
            ['code' => '8010', 'name' => 'FX Losses',                        'account_type' => 'expense', 'account_subtype' => 'other_expense', 'parent_code' => '8000'],
            ['code' => '8020', 'name' => 'Loss on Disposal of PPE',          'account_type' => 'expense', 'account_subtype' => 'other_expense', 'parent_code' => '8000'],
            ['code' => '8030', 'name' => 'Bad Debt Expense',                 'account_type' => 'expense', 'account_subtype' => 'other_expense', 'parent_code' => '8000'],
            ['code' => '8040', 'name' => 'Penalties and Fines',              'account_type' => 'expense', 'account_subtype' => 'other_expense', 'parent_code' => '8000'],
            ['code' => '8090', 'name' => 'Other Losses',                     'account_type' => 'expense', 'account_subtype' => 'other_expense', 'parent_code' => '8000'],
        ];
    }
}
