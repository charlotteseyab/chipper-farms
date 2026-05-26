<?php

namespace App\Services\Finance\VendorInvoicing;

use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Counterparty;
use App\Models\Finance\SupplierCategory;
use App\Models\RawMaterial;

/**
 * Walks the account-resolution hierarchy for vendor invoice lines.
 *
 * Order (Decision 27):
 *   1. Caller-provided account_id (line override)
 *   2. raw_material.inventory_account_id  (if rawMaterialId given AND set)
 *   3. raw_material.expense_account_id    (if rawMaterialId given AND set)
 *   4. counterparty.default_expense_account_id  (supplier-level default)
 *   5. supplier_category.default_expense_account_id  (category default)
 *   6. COA fallback: '6990' Sundry Expense
 *
 * Returns the resolved account_id. Throws if no fallback can be found.
 *
 * PHASE 4 BUG FIX
 *
 *   Previous version fell back to COA code '6010' and labelled it
 *   "Sundry Expense" in the doc comment. '6010' is actually
 *   "Veterinary Medicine and Vaccines" per the COA seeder. Every
 *   non-vet expense without category override was silently routed
 *   to vet medicine.
 *
 *   Phase 4 adds '6990 Sundry Expense' as a real top-level expense
 *   account and points the fallback at it. The migration that creates
 *   the account is at
 *   2027_03_01_120000_extend_coa_for_phase_4_inventory.
 *
 *   No remediation script: the previous chipper_v11 codebase was
 *   pre-production, so there is no historic '6010' misroute data to
 *   reclassify. If 3b ever does ship to production before 4 lands,
 *   the resolver fix should be deployed first with a follow-up
 *   reclassification journal.
 */
class ExpenseAccountResolver
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
    ) {
    }

    /**
     * Resolve the GL account for an invoice line.
     */
    public function resolve(
        Counterparty $supplier,
        ?int $explicitAccountId = null,
        ?int $rawMaterialId = null,
    ): int {
        // Step 1: caller override.
        if ($explicitAccountId) {
            return $explicitAccountId;
        }

        // Step 2 + 3: item-level (inventory wins over expense).
        if ($rawMaterialId) {
            $item = RawMaterial::select('id', 'inventory_account_id', 'expense_account_id')
                ->find($rawMaterialId);

            if ($item) {
                if ($item->inventory_account_id) {
                    return (int) $item->inventory_account_id;
                }
                if ($item->expense_account_id) {
                    return (int) $item->expense_account_id;
                }
            }
        }

        // Step 4: supplier-level.
        if ($supplier->default_expense_account_id) {
            return (int) $supplier->default_expense_account_id;
        }

        // Step 5: category-level.
        if ($supplier->supplier_category_id) {
            $categoryAccountId = SupplierCategory::query()
                ->where('id', $supplier->supplier_category_id)
                ->value('default_expense_account_id');

            if ($categoryAccountId) {
                return (int) $categoryAccountId;
            }
        }

        // Step 6: COA fallback (Phase 4 fix — points at 6990 not 6010).
        return $this->resolveSundryExpenseFallback();
    }

    /**
     * The COA-code fallback. Configurable via the inventory section of
     * config/finance.php so deployments can choose a different sundry
     * account if their COA has been customised.
     */
    protected function resolveSundryExpenseFallback(): int
    {
        $code = (string) config(
            'finance.inventory.fallback_account_codes.sundry_expense',
            '6990',
        );

        try {
            return $this->accountResolver->byCode($code)->id;
        } catch (\Throwable $e) {
            throw new \DomainException(
                "Cannot resolve GL account for vendor invoice line: "
                . "COA fallback code '{$code}' (Sundry Expense) is missing or inactive. "
                . "Run Phase 4's COA extension migration to seed '6990 Sundry Expense'.",
                previous: $e,
            );
        }
    }
}
