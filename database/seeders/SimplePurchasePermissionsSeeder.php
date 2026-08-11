<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 6b — permission to record farm purchases.
 *
 * NARROWER THAN record_expenses, AND WHY
 * --------------------------------------
 * Quick Expense is granted to dataentry because recording that money left the
 * farm is operational. A purchase is not the same act: a stock line RECEIVES
 * GOODS INTO INVENTORY, which changes stock on hand, feeds valuation, and flows
 * into COGS on every subsequent sale.
 *
 * Someone who can record a purchase can therefore inflate stock. That belongs
 * with the roles who already answer for inventory — farmmanager and above — not
 * with every data-entry user.
 *
 * Blueprint §14 supports this: the Data Entry profile lists "record expenses,
 * purchases, receipts", but §49's matrix puts approving and posting purchase
 * structure above it. Where the two readings differ, the one that limits who can
 * move stock is the safer default, and it can be widened later by an
 * administrator far more easily than an inflated inventory can be unwound.
 */
class SimplePurchasePermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        foreach (['superuser', 'admin', 'accountant', 'treasurer', 'farmmanager'] as $roleName) {
            $this->seededRoleOrCreate($roleName);
        }

        $perm = Permission::firstOrCreate(['name' => 'record_purchases'], [
            'name'        => 'record_purchases',
            'description' => 'Record farm purchases. Stock lines receive goods into inventory, '
                . 'so this changes stock on hand and downstream valuation.',
            'module'      => 'finance',
        ]);

        foreach (['superuser', 'admin', 'accountant', 'treasurer', 'farmmanager'] as $roleName) {
            $this->seededRole($roleName)?->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->command?->info(
            "Phase 6b: granted 'record_purchases' to admin, superuser, accountant, treasurer "
            . 'and farmmanager (NOT dataentry — a stock line moves inventory).'
        );
    }
}
