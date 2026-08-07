<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 4 — permission to record farm expenses.
 *
 * DELIBERATELY WIDE, AND WHY THAT IS SAFE
 * ---------------------------------------
 * Granted to data entry and farm manager as well as admin, accountant and
 * treasurer. That is broader than most finance permissions in this codebase,
 * and it is the point: blueprint §14 puts "record expenses, purchases,
 * receipts" in the Data Entry profile, and §49's permission matrix marks Quick
 * Expense as available to every operational role.
 *
 * Recording that money left the farm is an operational act, not an accounting
 * one. A store officer who buys diesel needs to record it from their phone at
 * the fuel station. Routing that through an accountant guarantees the expense
 * is entered late, from a memory, or not at all — which costs more accuracy
 * than the narrower permission would buy.
 *
 * The controls that matter are elsewhere and remain intact: the posting goes
 * through PostingEngine with its period locks and account validation, the
 * amount is idempotent against double submission, and every expense records who
 * entered it. Nobody can post to an arbitrary account — they choose a farm
 * category and the mapping decides the ledger account.
 *
 * NOT granted to salesperson, whose profile in blueprint §14 is listings,
 * quotes and orders rather than farm spend.
 */
class QuickExpensePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['superuser', 'admin', 'accountant', 'treasurer', 'farmmanager', 'dataentry'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $perm = Permission::firstOrCreate(['name' => 'record_expenses'], [
            'name'        => 'record_expenses',
            'description' => 'Record farm expenses through the Record Expense workflow. '
                . 'Posts through the posting engine; no direct account selection.',
            'module'      => 'finance',
        ]);

        foreach (['superuser', 'admin', 'accountant', 'treasurer', 'farmmanager', 'dataentry'] as $roleName) {
            Role::where('name', $roleName)->first()?->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->command?->info(
            "Phase 4: granted 'record_expenses' to admin, superuser, accountant, treasurer, "
            . 'farmmanager and dataentry (NOT salesperson).'
        );
    }
}
