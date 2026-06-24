<?php

namespace Database\Seeders;

use App\Models\CustomerCategory;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Phase 2a — sensible default customer categories for a Ghana pig farm.
 *
 * Idempotent — uses updateOrCreate keyed on (organization_id, code).
 *
 * The categories are illustrative starting points. Operations can rename,
 * deactivate, or add new ones via the Settings UI.
 *
 * No revenue_account_id or ar_account_id is wired here — the seeder runs
 * before the COA is necessarily seeded for a given organisation. The
 * Settings UI lets the admin pick the override accounts after both seeders
 * have run.
 */
class CustomerCategorySeeder extends Seeder
{
    public function run(): void
    {
        $organizationIds = Organization::query()->pluck('id');

        if ($organizationIds->isEmpty()) {
            $organizationIds = collect([null]);
        }

        foreach ($organizationIds as $organizationId) {
            $this->seedForOrganization($organizationId);
        }
    }

    private function seedForOrganization(?int $organizationId): void
    {
        $categories = [
            [
                'code'        => 'WHOLESALE',
                'name'        => 'Wholesale',
                'description' => 'Large butchers, restaurants, food processors. Net-30 default credit.',
                'default_payment_terms_days' => 30,
                'default_credit_limit'       => 50000.00,
                'default_discount_percent'   => 5.00,
                'display_order'              => 10,
            ],
            [
                'code'        => 'RETAIL',
                'name'        => 'Retail',
                'description' => 'Walk-in customers, market stall sellers. Cash on delivery.',
                'default_payment_terms_days' => 0,
                'default_credit_limit'       => 0.00,
                'default_discount_percent'   => 0.00,
                'display_order'              => 20,
            ],
            [
                'code'        => 'INSTITUTIONAL',
                'name'        => 'Institutional / Government',
                'description' => 'Schools, hospitals, government agencies. Long credit terms typical.',
                'default_payment_terms_days' => 60,
                'default_credit_limit'       => 100000.00,
                'default_discount_percent'   => 2.00,
                'display_order'              => 30,
            ],
            [
                'code'        => 'EXPORT',
                'name'        => 'Export',
                'description' => 'Cross-border buyers. Foreign currency invoicing typical.',
                'default_payment_terms_days' => 14,
                'default_credit_limit'       => 25000.00,
                'default_discount_percent'   => 0.00,
                'display_order'              => 40,
            ],
            [
                'code'        => 'INTERNAL',
                'name'        => 'Internal / Staff',
                'description' => 'Sales to staff or related parties. Tracked separately for transparency.',
                'default_payment_terms_days' => 30,
                'default_credit_limit'       => 5000.00,
                'default_discount_percent'   => 10.00,
                'display_order'              => 50,
            ],
        ];

        foreach ($categories as $cat) {
            CustomerCategory::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'code'            => $cat['code'],
                ],
                $cat + ['is_active' => true],
            );
        }
    }
}
