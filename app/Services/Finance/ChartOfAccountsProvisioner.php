<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Gives a farm its own copy of the template chart of accounts.
 *
 * The template is the set of chart_of_accounts rows with no organization_id,
 * seeded and extended by the finance migrations. Posting only ever looks at
 * a farm's own accounts, so a farm without them cannot post anything, and a
 * farm missing an account added by a later migration (e.g. 2055 Inventory
 * Suspense) fails only the postings that need it.
 *
 * sync() adds every template account whose code the farm does not have yet
 * and links each new account to the farm's copy of its template parent. It
 * never changes or re-adds an account the farm already has, renamed or
 * deleted ones included, so running it again is always safe.
 */
class ChartOfAccountsProvisioner
{
    /**
     * Template accounts this farm lacks, by code.
     *
     * @return array<int, object> template rows
     */
    public function missing(int $organizationId): array
    {
        $held = DB::table('chart_of_accounts')->where('organization_id', $organizationId)->pluck('code')->all();

        return DB::table('chart_of_accounts')
            ->whereNull('organization_id')->whereNull('deleted_at')
            ->whereNotIn('code', $held)
            ->orderBy('code')->get()->all();
    }

    /** Copy the missing template accounts to the farm. Returns how many were added. */
    public function sync(int $organizationId): int
    {
        return DB::transaction(function () use ($organizationId) {
            $missing = $this->missing($organizationId);
            if ($missing === []) {
                return 0;
            }

            $now = now();
            foreach ($missing as $template) {
                DB::table('chart_of_accounts')->insert([
                    'organization_id' => $organizationId,
                    'code' => $template->code,
                    'name' => $template->name,
                    'description' => $template->description,
                    'account_type' => $template->account_type,
                    'account_subtype' => $template->account_subtype,
                    'parent_id' => null,
                    'currency_code' => $template->currency_code,
                    'is_control' => $template->is_control,
                    'is_active' => $template->is_active,
                    'is_system' => $template->is_system,
                    'allow_manual_journal' => $template->allow_manual_journal,
                    'display_order' => $template->display_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Parents: the farm's account with the template parent's code.
            $templateCodes = DB::table('chart_of_accounts')->whereNull('organization_id')->pluck('code', 'id');
            $farmIds = DB::table('chart_of_accounts')->where('organization_id', $organizationId)->pluck('id', 'code');
            foreach ($missing as $template) {
                $parentCode = $template->parent_id ? ($templateCodes[$template->parent_id] ?? null) : null;
                if ($parentCode !== null && isset($farmIds[$parentCode])) {
                    DB::table('chart_of_accounts')->where('id', $farmIds[$template->code])
                        ->update(['parent_id' => $farmIds[$parentCode]]);
                }
            }

            return count($missing);
        });
    }
}
