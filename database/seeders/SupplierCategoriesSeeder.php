<?php

namespace Database\Seeders;

use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Phase 3a — seed standard supplier categories.
 *
 * Categories link to default GL accounts where possible. If the COA
 * doesn't have a particular account, the category is created with
 * default_expense_account_id = NULL and operations sets it later.
 *
 * Standard set (Ghana pig farming context):
 *
 *   FEED_INGREDIENTS  → 1310 (Inventory: Feed Ingredients) — assets, not expense.
 *                        Posts to inventory; consumption produces a separate
 *                        expense journal in Phase 4 inventory accounting.
 *   VETERINARY        → 6020 (Veterinary Expense)
 *   UTILITIES         → 6030 (Utilities)
 *   PROFESSIONAL      → 6040 (Professional Fees)
 *   CAPEX             → 1500 (Fixed Assets — In Progress) — placeholder; capex
 *                        bills then route through fixed-asset module in
 *                        Phase 6.
 *   GENERAL           → 6010 (Sundry Expense) — catch-all.
 *   TRANSPORT         → 6050 (Transport)
 *
 * Idempotent. Per-org seeding: runs across all organisations (or
 * organization_id NULL for shared categories).
 */
class SupplierCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();
        if ($organizations->isEmpty()) {
            // Single-tenant or test scenario — use NULL org_id
            $this->seedForOrg(null);
            return;
        }

        foreach ($organizations as $org) {
            $this->seedForOrg($org->id);
        }
    }

    protected function seedForOrg(?int $orgId): void
    {
        $categories = [
            'FEED_INGREDIENTS' => [
                'name'                       => 'Feed Ingredients',
                'description'                => 'Raw materials for feed production — corn, soybean, wheat bran, premixes',
                'expense_account_code'       => '1310',  // Asset, NOT expense
                'payment_terms_days'         => 30,
                'default_withholding_percent'=> 0.00,
            ],
            'VETERINARY' => [
                'name'                       => 'Veterinary',
                'description'                => 'Vaccines, medicines, vet services',
                'expense_account_code'       => '6020',
                'payment_terms_days'         => 30,
                'default_withholding_percent'=> 7.5,  // Services in Ghana
            ],
            'UTILITIES' => [
                'name'                       => 'Utilities',
                'description'                => 'Electricity, water, fuel, gas',
                'expense_account_code'       => '6030',
                'payment_terms_days'         => 30,
                'default_withholding_percent'=> 0.00,
            ],
            'PROFESSIONAL' => [
                'name'                       => 'Professional Services',
                'description'                => 'Audit, legal, consulting, accounting',
                'expense_account_code'       => '6040',
                'payment_terms_days'         => 30,
                'default_withholding_percent'=> 7.5,
            ],
            'CAPEX' => [
                'name'                       => 'Capital Expenditure',
                'description'                => 'Equipment, buildings, vehicles — fixed asset purchases',
                'expense_account_code'       => '1500',
                'payment_terms_days'         => 30,
                'default_withholding_percent'=> 0.00,
            ],
            'TRANSPORT' => [
                'name'                       => 'Transport',
                'description'                => 'Logistics, fuel, vehicle maintenance',
                'expense_account_code'       => '6050',
                'payment_terms_days'         => 30,
                'default_withholding_percent'=> 7.5,
            ],
            'GENERAL' => [
                'name'                       => 'General / Sundry',
                'description'                => 'Catch-all for misc expenses',
                'expense_account_code'       => '6010',
                'payment_terms_days'         => 30,
                'default_withholding_percent'=> 0.00,
            ],
        ];

        foreach ($categories as $code => $data) {
            // Look up the expense account by code in this org's COA.
            $accountId = null;
            if (! empty($data['expense_account_code'])) {
                $accountId = Account::query()
                    ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                    ->where('code', $data['expense_account_code'])
                    ->where('is_active', true)
                    ->value('id');
            }

            SupplierCategory::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'code'            => $code,
                ],
                [
                    'name'                          => $data['name'],
                    'description'                   => $data['description'],
                    'default_expense_account_id'    => $accountId,
                    'payment_terms_days'            => $data['payment_terms_days'],
                    'default_withholding_percent'   => $data['default_withholding_percent'],
                    'is_active'                     => true,
                ],
            );
        }

        $this->command?->info("Seeded supplier categories for org #" . ($orgId ?? 'NULL'));
    }
}
