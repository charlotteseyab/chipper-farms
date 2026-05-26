<?php

use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\VendorInvoiceLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3c — COA remediation for Phase 3a / 3b.
 *
 * Phase 3a's SupplierCategoriesSeeder mapped categories to several wrong
 * COA codes. Phase 3b's posting service used several wrong fallback codes.
 * This migration corrects the live data BEFORE 3c's payments table goes
 * in, because the payment posting service inherits the same hierarchy
 * and we don't want to double-down on bad codes.
 *
 * --------------------------------------------------------------------
 * What was wrong
 * --------------------------------------------------------------------
 *
 *   Phase 3b posting service references:
 *     - resolveApAccountId default '2110'   → '2110' is "Bank Loans
 *                                              — Current Portion".
 *                                              Correct: '2010' Trade
 *                                              Payables — Suppliers.
 *     - resolveInputVatAccountId default '1410' → '1410' is "Prepaid
 *                                                  Insurance". Correct:
 *                                                  '2211' VAT Receivable
 *                                                  (Input).
 *
 *   Phase 3a SupplierCategoriesSeeder mapped:
 *     - FEED_INGREDIENTS → '1310'           → '1310' is "Biological
 *                                              Assets — Breeding Stock".
 *                                              Correct: '1210' Raw
 *                                              Materials Inventory.
 *     - GENERAL          → '6010'           → '6010' is "Veterinary
 *                                              Medicine and Vaccines".
 *                                              Need a true sundry
 *                                              account; one doesn't
 *                                              exist in the seeded COA.
 *                                              We CREATE '6999' Other
 *                                              Operating Expenses for
 *                                              this purpose.
 *     - UTILITIES        → '6030'           → '6030' is "Diagnostic
 *                                              Tests". Correct: '6310'
 *                                              Electricity (or '6300'
 *                                              parent — but parents
 *                                              aren't postable).
 *     - PROFESSIONAL     → '6040'           → '6040' doesn't exist.
 *                                              Correct: '6830' Consulting
 *                                              Fees.
 *     - TRANSPORT        → '6050'           → '6050' doesn't exist.
 *                                              Correct: '6730' Delivery
 *                                              and Transport (Outbound).
 *     - VETERINARY       → '6020'           → '6020' is "Veterinary
 *                                              Services". Correct or
 *                                              acceptable; can also
 *                                              point to '6010' Vet
 *                                              Medicine. Leave as 6020.
 *     - CAPEX            → '1500'           → '1500' is the "Property,
 *                                              Plant & Equipment" parent,
 *                                              which has allow_manual_
 *                                              journal=false. Posting to
 *                                              it would fail. Keep
 *                                              category default at NULL
 *                                              and require operations
 *                                              to choose specific PPE
 *                                              code per bill (1530 Feed
 *                                              Mill, 1540 Vehicles, etc.)
 *
 *   Categories were also missing ap_account_id seeding entirely. The
 *   posting service falls back to a default AP code, which until this
 *   migration was '2110' (Bank Loans!). After this migration the
 *   fallback is '2010' AND the per-category ap_account_id is properly
 *   seeded for FEED_INGREDIENTS (→ '2020'), VETERINARY (→ '2030'), and
 *   the rest (→ '2010').
 *
 * --------------------------------------------------------------------
 * What this migration does
 * --------------------------------------------------------------------
 *
 *   1. Creates '6999' Other Operating Expenses per organisation
 *      (skipped if already present).
 *   2. Repoints supplier_categories.default_expense_account_id and
 *      .ap_account_id to correct COA codes.
 *   3. Repoints vendor_invoice_lines.account_id ON DRAFT AND
 *      PENDING_APPROVAL invoices ONLY. Posted vendor invoices are
 *      IMMUTABLE HISTORICAL RECORDS — they keep their (wrong) account_id
 *      and the journal_batch_id they posted. Reversing those journals
 *      to repost is out of scope; if any have already posted, finance
 *      can journal-correct manually.
 *
 *   The migration logs counts of repointed rows.
 *
 * --------------------------------------------------------------------
 * What this migration does NOT do
 * --------------------------------------------------------------------
 *
 *   - Does NOT touch posted journal_lines. Those are immutable.
 *   - Does NOT auto-reverse VIs that posted with wrong codes.
 *     Operations decides whether to reverse-and-repost or leave history.
 *   - Does NOT correct the Phase 3b posting service's fallback constants.
 *     That's done in 3c's PaymentService and a corresponding patch to
 *     the existing VendorInvoicePostingService (PATCH file in this batch).
 *
 * --------------------------------------------------------------------
 * Idempotent. Safe to run multiple times.
 * --------------------------------------------------------------------
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        // ---- 1. Create 6999 Other Operating Expenses per org ----

        $orgIds = DB::table('chart_of_accounts')
            ->select('organization_id')
            ->distinct()
            ->pluck('organization_id');

        // If table is empty (fresh install), seed for NULL org so test
        // setups have it. Real orgs get seeded as 6999 codes are needed.
        if ($orgIds->isEmpty()) {
            $orgIds = collect([null]);
        }

        $createdCount = 0;
        foreach ($orgIds as $orgId) {
            $exists = DB::table('chart_of_accounts')
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->when(! $orgId, fn ($q) => $q->whereNull('organization_id'))
                ->where('code', '6999')
                ->exists();

            if ($exists) {
                continue;
            }

            // Find the parent if 6900 exists; otherwise create as top-level.
            $parentId = DB::table('chart_of_accounts')
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->when(! $orgId, fn ($q) => $q->whereNull('organization_id'))
                ->where('code', '6000')  // Use top expense bucket as parent
                ->value('id');

            DB::table('chart_of_accounts')->insert([
                'organization_id'      => $orgId,
                'code'                 => '6999',
                'name'                 => 'Other Operating Expenses',
                'account_type'         => 'expense',
                'account_subtype'      => 'opex',
                'parent_id'            => $parentId,
                'is_system'            => false,
                'is_control'           => false,
                'allow_manual_journal' => true,
                'is_active'            => true,
                'currency_code'        => 'GHS',
                'description'          => 'Catch-all sundry expense bucket — created by Phase 3c remediation. Use only when no specific operating expense account fits.',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
            $createdCount++;
        }

        \Log::info("Phase 3c COA remediation: created '6999' Other Operating Expenses for {$createdCount} organisation(s)");

        // ---- 2. Repoint supplier_categories ----

        if (Schema::hasTable('supplier_categories') && Schema::hasColumn('supplier_categories', 'default_expense_account_id')) {

            $remediations = [
                // code           => [expense_target_code, ap_target_code, notes]
                'FEED_INGREDIENTS' => ['1210', '2020', 'Feed ingredients are Raw Materials Inventory'],
                'VETERINARY'       => ['6020', '2030', 'Veterinary Services (or 6010 Vet Medicine)'],
                'UTILITIES'        => ['6310', '2010', 'Electricity is the most-used utility'],
                'PROFESSIONAL'     => ['6830', '2010', 'Consulting Fees as default; finance overrides per bill'],
                'CAPEX'            => [null,   '2010', 'CAPEX requires per-bill GL coding — leave NULL'],
                'TRANSPORT'        => ['6730', '2010', 'Outbound delivery cost'],
                'GENERAL'          => ['6999', '2010', 'New sundry account created above'],
            ];

            $repointedCount = 0;
            foreach ($remediations as $catCode => [$expenseCode, $apCode, $note]) {
                $categories = DB::table('supplier_categories')
                    ->where('code', $catCode)
                    ->get();

                foreach ($categories as $cat) {
                    $expenseAcctId = null;
                    if ($expenseCode) {
                        $expenseAcctId = DB::table('chart_of_accounts')
                            ->where('code', $expenseCode)
                            ->when($cat->organization_id, fn ($q) => $q->where('organization_id', $cat->organization_id))
                            ->when(! $cat->organization_id, fn ($q) => $q->whereNull('organization_id'))
                            ->where('is_active', true)
                            ->value('id');
                    }

                    $apAcctId = DB::table('chart_of_accounts')
                        ->where('code', $apCode)
                        ->when($cat->organization_id, fn ($q) => $q->where('organization_id', $cat->organization_id))
                        ->when(! $cat->organization_id, fn ($q) => $q->whereNull('organization_id'))
                        ->where('is_active', true)
                        ->value('id');

                    $update = [];
                    // Only update if non-null target was found (don't blank out a working value with NULL by accident).
                    if ($expenseAcctId) {
                        $update['default_expense_account_id'] = $expenseAcctId;
                    } elseif ($expenseCode === null) {
                        // Explicit NULL request (CAPEX): set to NULL.
                        $update['default_expense_account_id'] = null;
                    }
                    if ($apAcctId && Schema::hasColumn('supplier_categories', 'ap_account_id')) {
                        $update['ap_account_id'] = $apAcctId;
                    }

                    if (! empty($update)) {
                        $update['updated_at'] = now();
                        DB::table('supplier_categories')
                            ->where('id', $cat->id)
                            ->update($update);
                        $repointedCount++;
                    }
                }
            }

            \Log::info("Phase 3c COA remediation: repointed {$repointedCount} supplier_categories rows");
        }

        // ---- 3. Repoint vendor_invoice_lines.account_id on DRAFTS ONLY ----

        if (Schema::hasTable('vendor_invoice_lines') && Schema::hasTable('vendor_invoices')) {

            // Map old wrong codes to correct codes for line repointing.
            $lineCodeMap = [
                '1310' => '1210',  // Biological Assets → Raw Materials Inventory
                '6010' => '6999',  // Vet Medicine catch-all → Other Operating Expenses
                '6030' => '6310',  // Diagnostic Tests → Electricity
                '6040' => '6830',  // doesn't exist → Consulting Fees
                '6050' => '6730',  // doesn't exist → Outbound Delivery
            ];

            $linesRepointedCount = 0;
            foreach ($lineCodeMap as $oldCode => $newCode) {
                // Find all draft/pending VI lines pointing at the wrong code.
                $linesToFix = DB::table('vendor_invoice_lines as vil')
                    ->join('vendor_invoices as vi', 'vi.id', '=', 'vil.vendor_invoice_id')
                    ->join('chart_of_accounts as old_acct', 'old_acct.id', '=', 'vil.account_id')
                    ->whereIn('vi.status', ['draft', 'pending_approval'])
                    ->where('old_acct.code', $oldCode)
                    ->select(['vil.id', 'vil.organization_id'])
                    ->get();

                foreach ($linesToFix as $line) {
                    $newAcctId = DB::table('chart_of_accounts')
                        ->where('code', $newCode)
                        ->when($line->organization_id, fn ($q) => $q->where('organization_id', $line->organization_id))
                        ->when(! $line->organization_id, fn ($q) => $q->whereNull('organization_id'))
                        ->where('is_active', true)
                        ->value('id');

                    if ($newAcctId) {
                        DB::table('vendor_invoice_lines')
                            ->where('id', $line->id)
                            ->update([
                                'account_id' => $newAcctId,
                                'updated_at' => now(),
                            ]);
                        $linesRepointedCount++;
                    }
                }
            }

            \Log::info("Phase 3c COA remediation: repointed {$linesRepointedCount} vendor_invoice_lines.account_id on draft/pending VIs");
        }
    }

    public function down(): void
    {
        // No-op rollback. The remediation FIXES bugs; rolling back would
        // re-introduce them. If a deployment must roll back, restore from
        // backup. The newly-created 6999 account remains — leaving it
        // doesn't harm anything.

        \Log::warning("Phase 3c COA remediation: down() is a no-op — remediation fixes bugs and is not auto-reverted");
    }
};
