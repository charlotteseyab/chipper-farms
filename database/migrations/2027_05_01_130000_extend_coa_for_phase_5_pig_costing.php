<?php

use App\Models\Finance\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Chart of Accounts extension for pig cost accounting.
 *
 * Adds the accounts needed to track live pigs as inventory and to
 * recognise mortality + COGS at the right points.
 *
 * Accounts added:
 *
 *   1230  Live Pig Inventory                  asset       (CONTROL; per-group
 *                                                          cost pools roll up here.
 *                                                          Pigs enter at cumulative
 *                                                          allocated cost; leave at
 *                                                          sale-time COGS.)
 *
 *   5110  Cost of Pigs Sold                   expense     (COGS recognised at sale,
 *                                                          weight-proportional share
 *                                                          of source group's pool.)
 *
 *   5320  Mortality Loss                      expense     (Cost basis of pigs that
 *                                                          died — pro-rata write-off
 *                                                          from group pool to expense.
 *                                                          Tracked separately from
 *                                                          5300 Inventory Adjustments
 *                                                          so mortality is its own KPI.)
 *
 * Why 5110 separate from existing 5100 Cost of Feed Sold?
 *   Feed COGS recognises COGS when finished feed is sold to outside
 *   customers (e.g. selling bulk feed). Pig COGS recognises COGS when
 *   a pig (which contains accumulated feed + other inputs) is sold.
 *   The two flows hit different accounts so the P&L reflects the
 *   business structure: revenue from feed sales vs revenue from pig
 *   sales, each matched to their own cost line.
 *
 * Why 5320 separate from 5300 Inventory Adjustments?
 *   Mortality is a recurring operational cost; 5300 is for one-off
 *   inventory corrections (count mismatches). Lumping them together
 *   would mask the mortality rate, which farms manage actively.
 *
 * Why 1230 is a CONTROL account?
 *   Per-group balances sum to the 1230 balance. Manual journals to
 *   1230 would desync from the group_cost_pools subledger. The only
 *   sanctioned writers are the Phase 5 subscribers, which always pair
 *   GL movements with subledger updates inside one transaction.
 *
 *   `allow_manual_journal = false` enforces this at the journal posting
 *   level.
 *
 * No new parent accounts needed — 1200 Inventory parent already exists
 * (Phase 0.5 COA), 5000 COGS parent already exists.
 *
 * Idempotency: updateOrCreate on (organization_id, code). Re-running
 * is safe.
 */
return new class extends Migration {
    /**
     * Account definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accounts(): array
    {
        return [
            [
                'code'                 => '1230',
                'name'                 => 'Live Pig Inventory',
                'account_type'         => 'asset',
                'account_subtype'      => 'current_asset',
                'parent_code'          => '1200',
                'is_system'            => true,
                'is_control'           => true,
                'allow_manual_journal' => false,
                'is_active'            => true,
                'description'          => 'Per-group cost pools roll up here. Carries the accumulated cost basis of live pigs from issuance/birth through sale. Phase 5 subscribers are the only sanctioned writers.',
            ],

            [
                'code'                 => '5110',
                'name'                 => 'Cost of Pigs Sold',
                'account_type'         => 'expense',
                'account_subtype'      => 'cogs',
                'parent_code'          => null,
                'is_system'            => true,
                'is_control'           => false,
                'allow_manual_journal' => false,
                'is_active'            => true,
                'description'          => 'COGS at pig sale time. Weight-proportional share of the source group pool. Posted by PigSaleCogsSubscriber.',
            ],

            [
                'code'                 => '5320',
                'name'                 => 'Mortality Loss',
                'account_type'         => 'expense',
                'account_subtype'      => 'opex',
                'parent_code'          => null,
                'is_system'            => true,
                'is_control'           => false,
                'allow_manual_journal' => false,
                'is_active'            => true,
                'description'          => 'Cost basis written off when a pig dies. Tracked separately from inventory adjustments so mortality rate is visible in the P&L. Posted by PigDeathRecordedSubscriber.',
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            throw new \RuntimeException(
                'chart_of_accounts table is missing — Phase 5 COA extension cannot run without it. '
                . 'Apply earlier finance migrations first.'
            );
        }

        $organizationId = null;

        DB::transaction(function () use ($organizationId) {
            foreach ($this->accounts() as $a) {
                Account::updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'code'            => $a['code'],
                    ],
                    [
                        'name'                 => $a['name'],
                        'account_type'         => $a['account_type'],
                        'account_subtype'      => $a['account_subtype'],
                        'is_system'            => (bool) $a['is_system'],
                        'is_control'           => (bool) $a['is_control'],
                        'allow_manual_journal' => (bool) $a['allow_manual_journal'],
                        'is_active'            => (bool) $a['is_active'],
                        'description'          => $a['description'] ?? null,
                        'currency_code'        => 'GHS',
                    ]
                );
            }

            // Pass 2: wire parent_id where applicable.
            foreach ($this->accounts() as $a) {
                if (empty($a['parent_code'])) {
                    continue;
                }

                $parent = Account::query()
                    ->when(
                        $organizationId === null,
                        fn ($q) => $q->whereNull('organization_id'),
                        fn ($q) => $q->where('organization_id', $organizationId),
                    )
                    ->where('code', $a['parent_code'])
                    ->first();

                if (! $parent) {
                    throw new \RuntimeException(
                        "Phase 5 COA extension: parent account '{$a['parent_code']}' for child "
                        . "'{$a['code']}' is missing. Run ChartOfAccountsSeeder first."
                    );
                }

                Account::query()
                    ->when(
                        $organizationId === null,
                        fn ($q) => $q->whereNull('organization_id'),
                        fn ($q) => $q->where('organization_id', $organizationId),
                    )
                    ->where('code', $a['code'])
                    ->update(['parent_id' => $parent->id]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        $codes = array_column($this->accounts(), 'code');

        // Refuse to drop accounts with journal history.
        $orphansToDrop = Account::query()
            ->whereNull('organization_id')
            ->whereIn('code', $codes)
            ->whereDoesntHave('lines')
            ->pluck('id', 'code')
            ->all();

        $protected = array_diff($codes, array_keys($orphansToDrop));
        if (! empty($protected)) {
            logger()->warning(
                'Phase 5 COA extension rollback: refusing to drop accounts with journal history: '
                . implode(', ', $protected)
                . '. Inactivate them via COA admin if they must be hidden.'
            );
        }

        if (! empty($orphansToDrop)) {
            Account::query()->whereIn('id', array_values($orphansToDrop))->delete();
        }
    }
};
