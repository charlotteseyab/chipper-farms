<?php

use App\Models\Finance\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Chart of Accounts extension for inventory cost accounting.
 *
 * Adds the accounts required by the inventory ledger and the GR/IR
 * (Goods Received / Invoice Received) accrual mechanism that bridges
 * physical receipt and AP invoicing.
 *
 * Accounts added:
 *
 *   1215  Raw Materials In Transit            asset       (currently informational; future
 *                                                          phases may use for landed-cost)
 *   1225  Feed Work-in-Progress               asset       (transient holding during batch
 *                                                          mixing; ProduceFeedBatchService
 *                                                          posts straight through but the
 *                                                          account is present for future
 *                                                          intermediate-mixing workflows)
 *   2050  Goods Received Not Invoiced (GR/IR) liability   (CONTROL; accrues at receipt,
 *                                                          clears at invoice — the heart
 *                                                          of the 2-step AP/inventory model)
 *   2055  Inventory Suspense                  liability   (CONTROL; holds non-PR receipts
 *                                                          pending supplier attribution)
 *   5310  Purchase Price Variance             expense     (PPV — captures the difference
 *                                                          between receipt-time WAC and
 *                                                          invoice-time price within tol)
 *   6990  Sundry Expense                      expense     (resolver fallback — fixes the
 *                                                          Phase 3b bug where the fallback
 *                                                          was misrouted to 6010 Vet Med)
 *
 * Why all liabilities/assets are CONTROL accounts:
 *   GR/IR and Inventory Suspense aggregate per-supplier balances. Like
 *   the AP Trade Payables accounts, they cannot be posted to manually —
 *   only the receipt/invoice flows write to them, so the subledger view
 *   (gr_ir_entries) and GL stay consistent. Marked is_control=true and
 *   allow_manual_journal=false.
 *
 * Idempotency:
 *   updateOrCreate on (organization_id, code). Re-running the migration
 *   is safe; the only mutation is updates to name/description if the
 *   migration is re-run after a name change.
 *
 * Multi-tenant note:
 *   This migration writes a single set of accounts with organization_id = NULL,
 *   matching the existing ChartOfAccountsSeeder posture. Multi-tenant
 *   deployments that need per-org chart entries should run the COA seeder
 *   per-org separately; this migration covers the default org only.
 */
return new class extends Migration {
    /**
     * Account definitions (in dependency order — parents first).
     *
     * @return array<int, array<string, mixed>>
     */
    private function accounts(): array
    {
        return [
            // Asset block — Inventory parent already exists (1200) and
            // Inventory child 1210/1220 are seeded by ChartOfAccountsSeeder.
            [
                'code'                 => '1215',
                'name'                 => 'Raw Materials In Transit',
                'account_type'         => 'asset',
                'account_subtype'      => 'current_asset',
                'parent_code'          => '1200',
                'is_system'            => false,
                'is_control'           => false,
                'allow_manual_journal' => true,
                'is_active'            => true,
                'description'          => 'Raw materials shipped but not yet received. Phase 4 reserves this for future landed-cost workflows.',
            ],
            [
                'code'                 => '1225',
                'name'                 => 'Feed Work-in-Progress',
                'account_type'         => 'asset',
                'account_subtype'      => 'current_asset',
                'parent_code'          => '1200',
                'is_system'            => false,
                'is_control'           => false,
                'allow_manual_journal' => true,
                'is_active'            => true,
                'description'          => 'Transient holding for feed batches in-progress. Phase 4 routes batch production direct from RM → FG; this account is reserved for future multi-step mixing.',
            ],

            // Liability block — Accounts Payable parent (2000) hosts trade
            // payables. We attach GR/IR and Inventory Suspense as siblings
            // under the same parent because they're accruals that PRECEDE
            // the AP entry (received-not-billed) rather than alongside it.
            [
                'code'                 => '2050',
                'name'                 => 'Goods Received Not Invoiced (GR/IR)',
                'account_type'         => 'liability',
                'account_subtype'      => 'current_liability',
                'parent_code'          => '2000',
                'is_system'            => true,
                'is_control'           => true,
                'allow_manual_journal' => false,
                'is_active'            => true,
                'description'          => 'Accrued payable for goods physically received from a PR-linked supplier where the AP invoice has not yet been posted. Clears 1:1 against the VI posting that matches the receipt. Never posted to manually.',
            ],
            [
                'code'                 => '2055',
                'name'                 => 'Inventory Suspense',
                'account_type'         => 'liability',
                'account_subtype'      => 'current_liability',
                'parent_code'          => '2000',
                'is_system'            => true,
                'is_control'           => true,
                'allow_manual_journal' => false,
                'is_active'            => true,
                'description'          => 'Holding account for inventory receipts with no PR linkage (free samples, donations, returns). Cleared when ops attribute the receipt to a supplier/PR retroactively, or written off to Sundry.',
            ],

            // COGS block — PPV slots in 5300-range (Inventory Adjustments
            // is 5300). PPV is a sibling: it captures price-side variance
            // explicitly so adjustments stay quantity-side only.
            [
                'code'                 => '5310',
                'name'                 => 'Purchase Price Variance',
                'account_type'         => 'expense',
                'account_subtype'      => 'cogs',
                'parent_code'          => null,
                'is_system'            => true,
                'is_control'           => false,
                'allow_manual_journal' => false,
                'is_active'            => true,
                'description'          => 'Difference between receipt-time WAC (debited to inventory at receipt) and invoice-time price (when AP bill arrives). Within tolerance → auto-post; outside tolerance → block invoice. Debit = unfavourable variance, credit = favourable.',
            ],

            // Expense block — Sundry Expense fixes the resolver fallback.
            // It is a top-level expense (parent_id = null) because the
            // COA's 6900 is already used for 'Bank Charges and Levies'
            // and there is no existing 'Other Operating Expenses' parent.
            // Marking is_system=false because operations may add child
            // accounts under it for finer attribution (e.g. 6991 Cleaning,
            // 6992 Stationery) as their needs evolve.
            [
                'code'                 => '6990',
                'name'                 => 'Sundry Expense',
                'account_type'         => 'expense',
                'account_subtype'      => 'opex',
                'parent_code'          => null,
                'is_system'            => false,
                'is_control'           => false,
                'allow_manual_journal' => true,
                'is_active'            => true,
                'description'          => 'Catch-all expense for vendor invoice lines with no item-level, supplier-level, or category-level account override. Phase 4 corrects the Phase 3b resolver bug where 6010 (Vet Medicine) was silently used as the fallback. Operations may add child accounts here for finer attribution.',
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            throw new \RuntimeException(
                'chart_of_accounts table is missing — Phase 4 COA extension cannot run without it. '
                . 'Apply earlier finance migrations first.'
            );
        }

        // We write organization_id = NULL to match the existing
        // ChartOfAccountsSeeder posture (single-tenant by default).
        $organizationId = null;

        DB::transaction(function () use ($organizationId) {
            // Pass 1: upsert each row without parent_id wiring. This
            // avoids ordering dependency on parent rows existing first,
            // and is robust against re-running the migration after parent
            // accounts have been renamed.
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
                        // parent_id resolved in pass 2.
                    ]
                );
            }

            // Pass 2: wire parent_id by resolving parent_code → id.
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

                // Parent missing is a hard error — the COA seeder is the
                // source of truth; if a parent is absent it means the COA
                // wasn't seeded properly.
                if (! $parent) {
                    throw new \RuntimeException(
                        "Phase 4 COA extension: parent account '{$a['parent_code']}' for child "
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

        // Refuse to drop accounts that have journal lines posted against
        // them — that's destructive and would orphan history. The accounts
        // can be inactivated via the COA admin if they truly need to be
        // hidden, but the rows themselves must stay for audit integrity.
        $orphansToDrop = Account::query()
            ->whereNull('organization_id')
            ->whereIn('code', $codes)
            ->whereDoesntHave('lines')
            ->pluck('id', 'code')
            ->all();

        $protected = array_diff($codes, array_keys($orphansToDrop));
        if (! empty($protected)) {
            logger()->warning(
                'Phase 4 COA extension rollback: refusing to drop accounts with journal history: '
                . implode(', ', $protected)
                . '. Inactivate them via COA admin if they must be hidden.'
            );
        }

        if (! empty($orphansToDrop)) {
            Account::query()
                ->whereIn('id', array_values($orphansToDrop))
                ->delete();
        }
    }
};
