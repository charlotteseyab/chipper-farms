<?php

use App\Models\Finance\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — Chart of Accounts normalization (Option A, pre-production).
 *
 * BACKGROUND
 *
 * Two charts of accounts diverged before Phase 6:
 *
 *   - The hand-built ChartOfAccountsSeeder defined:
 *       1230 = Veterinary Supplies Inventory   (label-only; referenced
 *              nowhere in code)
 *       5000 = Cost of Pigs Sold               (a postable leaf)
 *       5400 = Biological Asset Mortality (Adult)
 *       5410 = Biological Asset Mortality (Piglet)
 *
 *   - The Phase 5 migrations created and WIRED LIVE CODE to:
 *       1230 = Live Pig Inventory   (control; FeedIssuedSubscriber,
 *              PigSaleCogsSubscriber, OpeningPigPoolService all post here)
 *       5110 = Cost of Pigs Sold    (PigSaleCogsSubscriber)
 *       5320 = Mortality Loss       (PigDeathRecordedSubscriber)
 *     and assumed 5000 is a COGS *parent*.
 *
 * Because both the seeder and the Phase 5 migration upsert on
 * (organization_id = NULL, code), they collide on 1230 and on the meaning
 * of 5000. Whichever ran last won — an unstable, label-corrupting state.
 *
 * RESOLUTION (Option A — Phase 5 codes are the system of record)
 *
 * We are pre-production: there are no posted journal lines yet, so this is
 * a pure structural normalization with no reclass/backfill required. This
 * migration makes the live database authoritative and consistent with the
 * code that actually posts:
 *
 *   1. 1230 -> "Live Pig Inventory" (asset, current_asset, CONTROL,
 *      no manual journals), parented under 1200 Inventory.
 *   2. Relocate the unused "Veterinary Supplies Inventory" label to the
 *      free code 1250 (kept for when vet capitalisation lands; not a
 *      control account, not wired to anything yet).
 *   3. 5000 -> COGS PARENT ("Cost of Sales"), no manual journals, no
 *      subtype change needed (stays expense/cogs). It stops being a
 *      postable leaf.
 *   4. Ensure 5110 "Cost of Pigs Sold" and 5100 "Cost of Feed Sold" are
 *      parented under 5000.
 *   5. Re-parent 5200/5300/5320/5400/5410 under 5000 so the whole
 *      cost-of-sales family rolls up cleanly for the income statement.
 *      (5320 Mortality Loss is the Phase 5 live account; 5400/5410 are the
 *      seeder's biological-mortality accounts — kept as siblings, unused
 *      pre-prod, available if the farm wants adult/piglet split later.)
 *
 * This migration is:
 *   - Pre-production safe: refuses to touch any account that already has
 *     posted journal lines (defence-in-depth even though we expect none).
 *   - Idempotent: re-running converges to the same state.
 *   - Reversible (best-effort): down() restores the seeder's prior labels
 *     where it can, but does not recreate posting history (there is none).
 *
 * The companion seeder change (ChartOfAccountsSeeder corrected to match)
 * ships in this phase so fresh installs are born consistent. This
 * migration handles already-migrated environments.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            throw new \RuntimeException(
                'chart_of_accounts is missing — Phase 6 COA normalization cannot run. '
                . 'Apply earlier finance migrations first.'
            );
        }

        DB::transaction(function () {
            $this->guardNoPostings(['1230', '5000']);

            // 1. Relocate the unused vet-supplies label OUT of 1230 first,
            //    so we don't lose it when 1230 becomes Live Pig Inventory.
            //    Only do this if 1230 currently carries the vet label
            //    (i.e. the seeder won the race). If 1230 is already Live
            //    Pig Inventory, this is a no-op.
            $current1230 = $this->acct('1230');

            if ($current1230 && $this->looksLikeVetSupplies($current1230)) {
                // Move the vet-supplies concept to 1250 (free code) unless
                // 1250 already exists.
                if (! $this->acct('1250')) {
                    Account::create([
                        'organization_id'      => null,
                        'code'                 => '1250',
                        'name'                 => 'Veterinary Supplies Inventory',
                        'account_type'         => 'asset',
                        'account_subtype'      => 'current_asset',
                        'is_system'            => true,
                        'is_control'           => false,
                        'allow_manual_journal' => true,
                        'is_active'            => true,
                        'currency_code'        => 'GHS',
                        'description'           => 'Relocated from 1230 in Phase 6 (1230 is now Live Pig Inventory). '
                            . 'Reserved for vet-supply capitalisation when that lands; not yet wired.',
                        'parent_id'            => optional($this->acct('1200'))->id,
                    ]);
                }
            }

            // 2. Force 1230 to be Live Pig Inventory (control, no manual).
            $this->upsertAccount('1230', [
                'name'                 => 'Live Pig Inventory',
                'account_type'         => 'asset',
                'account_subtype'      => 'current_asset',
                'is_system'            => true,
                'is_control'           => true,
                'allow_manual_journal' => false,
                'is_active'            => true,
                'description'           => 'Per-group cost pools roll up here. Carries accumulated cost basis of '
                    . 'live pigs from issuance/birth through sale. Phase 5 subscribers are the only sanctioned writers.',
                'parent_code'          => '1200',
            ]);

            // 3. 5000 becomes the Cost of Sales PARENT.
            $this->upsertAccount('5000', [
                'name'                 => 'Cost of Sales',
                'account_type'         => 'expense',
                'account_subtype'      => 'cogs',
                'is_system'            => true,
                'is_control'           => false,
                'allow_manual_journal' => false,   // parent — never post here
                'is_active'            => true,
                'description'           => 'Cost of sales parent. Children: 5100 feed, 5110 pigs, 5200 by-products, '
                    . '5300 inventory adjustments, 5320 mortality. Roll-up for the income statement.',
                'parent_code'          => null,
            ]);

            // 4. Ensure 5110 Cost of Pigs Sold exists and is parented to 5000.
            //    (Phase 5 migration created it; this re-asserts the parent in
            //    case ordering left it null.)
            $this->upsertAccount('5110', [
                'name'                 => 'Cost of Pigs Sold',
                'account_type'         => 'expense',
                'account_subtype'      => 'cogs',
                'is_system'            => true,
                'is_control'           => false,
                'allow_manual_journal' => false,
                'is_active'            => true,
                'description'           => 'COGS at pig sale: weight-proportional share of source group pool. '
                    . 'Posted by PigSaleCogsSubscriber.',
                'parent_code'          => '5000',
            ]);

            // 5. Re-parent the rest of the cost-of-sales family under 5000.
            //    These already exist from the seeder; we only set parent_id.
            foreach (['5100', '5200', '5300', '5320', '5400', '5410'] as $childCode) {
                $child = $this->acct($childCode);
                if (! $child) {
                    continue; // seeder may not have created all of them
                }
                $parent = $this->acct('5000');
                if ($parent) {
                    Account::query()
                        ->whereNull('organization_id')
                        ->where('code', $childCode)
                        ->update(['parent_id' => $parent->id]);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        DB::transaction(function () {
            // Best-effort restore of the seeder's prior labels. Pre-prod, so
            // no posting history to preserve. We do NOT delete 1250 here
            // because operators may have started using it.
            $this->guardNoPostings(['1230', '5000']);

            // 5000 back to a postable leaf "Cost of Pigs Sold".
            Account::query()->whereNull('organization_id')->where('code', '5000')->update([
                'name'                 => 'Cost of Pigs Sold',
                'allow_manual_journal' => false,
            ]);

            // 1230 back to vet supplies label (its pre-Phase-5 seeder meaning).
            Account::query()->whereNull('organization_id')->where('code', '1230')->update([
                'name'                 => 'Veterinary Supplies Inventory',
                'is_control'           => false,
                'allow_manual_journal' => true,
            ]);
        });
    }

    // ----------------------------------------------------------------

    private function acct(string $code): ?Account
    {
        return Account::query()
            ->whereNull('organization_id')
            ->where('code', $code)
            ->first();
    }

    private function looksLikeVetSupplies(Account $a): bool
    {
        return stripos((string) $a->name, 'veterinary') !== false
            || stripos((string) $a->name, 'vet supplies') !== false;
    }

    /**
     * Upsert an account by code, wiring parent_id from parent_code.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function upsertAccount(string $code, array $attrs): void
    {
        $parentId = null;
        if (! empty($attrs['parent_code'])) {
            $parentId = optional($this->acct($attrs['parent_code']))->id;
        }
        unset($attrs['parent_code']);

        $payload = array_merge($attrs, [
            'currency_code' => $attrs['currency_code'] ?? 'GHS',
            'parent_id'     => $parentId,
        ]);

        Account::updateOrCreate(
            ['organization_id' => null, 'code' => $code],
            $payload,
        );
    }

    /**
     * Defence-in-depth: refuse to mutate accounts that already carry posted
     * journal lines. Pre-production we expect zero; if this fires, STOP and
     * switch to a reclass strategy (Option A with backfill).
     *
     * @param  array<int, string>  $codes
     */
    private function guardNoPostings(array $codes): void
    {
        $offenders = [];
        foreach ($codes as $code) {
            $acct = $this->acct($code);
            if (! $acct) {
                continue;
            }
            $hasLines = DB::table('journal_lines')
                ->where('account_id', $acct->id)
                ->exists();
            if ($hasLines) {
                $offenders[] = $code;
            }
        }

        if (! empty($offenders)) {
            throw new \RuntimeException(
                'Phase 6 COA normalization aborted: accounts ' . implode(', ', $offenders)
                . ' already have posted journal lines. This migration is for the pre-production '
                . 'state only. A reclass/backfill migration is required instead — do NOT force this.'
            );
        }
    }
};
