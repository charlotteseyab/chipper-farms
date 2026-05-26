<?php

use App\Models\Finance\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — biological-asset COA finalisation.
 *
 * The 1300–1370 biological-asset tree already exists in the seeder (created
 * as IAS-41 cost-model stubs). Phase 7 wires live posting into it. This
 * migration makes the database authoritative and consistent with the
 * Phase 7 code:
 *
 *   1. Ensures the 1300 parent + 1310..1370 stage accounts exist with the
 *      correct flags (control, no manual journals) and parent wiring.
 *   2. Re-classifies 1230 Live Pig Inventory as a TRANSITIONAL CLEARING
 *      account: it keeps its name-ish role but its description and intent
 *      change — Phase 5 subscribers are being repointed to the stage
 *      accounts, and 1230 should normally net to zero. We do NOT drop it
 *      (Phase 5 opening-pool history and the Phase 6 reconciliation watch
 *      it), and we keep it postable by the system (no manual journals).
 *
 * Pre-production safe: refuses to run if posted journal lines already exist
 * on 1230 with a non-zero net balance (which would mean real Phase 5
 * history that needs a reclass migration instead of a clean cutover).
 *
 * Idempotent + reversible (best-effort: down() restores 1230's prior
 * description; it cannot un-create the stage accounts because the seeder
 * owns them).
 */
return new class extends Migration {
    /** @var array<string, array{name:string, subtype:string}> */
    private array $stageAccounts = [
        '1310' => ['name' => 'Biological Assets — Breeding Stock',     'subtype' => 'non_current_asset'],
        '1320' => ['name' => 'Biological Assets — Sows in Production', 'subtype' => 'non_current_asset'],
        '1330' => ['name' => 'Biological Assets — Boars',             'subtype' => 'non_current_asset'],
        '1340' => ['name' => 'Biological Assets — Piglets',           'subtype' => 'current_asset'],
        '1350' => ['name' => 'Biological Assets — Weaners',           'subtype' => 'current_asset'],
        '1360' => ['name' => 'Biological Assets — Growers',           'subtype' => 'current_asset'],
        '1370' => ['name' => 'Biological Assets — Finishers',         'subtype' => 'current_asset'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            throw new \RuntimeException('chart_of_accounts missing — apply earlier finance migrations first.');
        }

        DB::transaction(function () {
            $this->guardClearingHasNoNetBalance();

            // 1. Parent: 1300 Biological Assets.
            $parent = Account::updateOrCreate(
                ['organization_id' => null, 'code' => '1300'],
                [
                    'name'                 => 'Biological Assets',
                    'account_type'         => 'asset',
                    'account_subtype'      => 'current_asset',
                    'currency_code'        => 'GHS',
                    'is_active'            => true,
                    'is_system'            => true,
                    'is_control'           => false,
                    'allow_manual_journal' => false,
                    'description'          => 'IAS 41 — measured at cost; fair value disclosed in notes. Parent of stage accounts 1310–1370.',
                ],
            );

            // 2. Stage accounts.
            foreach ($this->stageAccounts as $code => $def) {
                Account::updateOrCreate(
                    ['organization_id' => null, 'code' => $code],
                    [
                        'name'                 => $def['name'],
                        'account_type'         => 'asset',
                        'account_subtype'      => $def['subtype'],
                        'currency_code'        => 'GHS',
                        'is_active'            => true,
                        'is_system'            => true,
                        'is_control'           => true,
                        'allow_manual_journal' => false,
                        'parent_id'            => $parent->id,
                    ],
                );
            }

            // 3. Re-purpose 1230 as the transitional clearing account.
            $clearing = Account::query()->whereNull('organization_id')->where('code', '1230')->first();
            if ($clearing) {
                $clearing->update([
                    'name'                 => 'Live Pig Inventory (Clearing)',
                    'is_control'           => false,        // it is a clearing account, not a subledger control
                    'allow_manual_journal' => false,
                    'description'          => 'Phase 7 transitional clearing account. Phase 5 subscribers now post '
                        . 'directly to stage accounts 1340–1370 / 1310–1330. This account should normally net to zero; '
                        . 'the period-close reconciliation watches it.',
                ]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        DB::transaction(function () {
            $clearing = Account::query()->whereNull('organization_id')->where('code', '1230')->first();
            if ($clearing) {
                $clearing->update([
                    'name'                 => 'Live Pig Inventory',
                    'is_control'           => true,
                    'allow_manual_journal' => false,
                    'description'          => 'Per-group cost pools roll up here (Phase 5).',
                ]);
            }
            // Stage accounts are owned by the seeder; leave them in place.
        });
    }

    private function guardClearingHasNoNetBalance(): void
    {
        $clearing = Account::query()->whereNull('organization_id')->where('code', '1230')->first();
        if (! $clearing) {
            return;
        }

        $row = DB::table('journal_lines')
            ->where('account_id', $clearing->id)
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        $net = (float) ($row->d ?? 0) - (float) ($row->c ?? 0);
        if (abs($net) > 0.005) {
            throw new \RuntimeException(
                'Phase 7 aborted: account 1230 has a non-zero posted balance (' . number_format($net, 2) . '). '
                . 'This indicates real Phase 5 history. A reclass migration is required to move that balance into the '
                . 'stage accounts before 1230 becomes a clearing account — do NOT force this migration.'
            );
        }
    }
};
