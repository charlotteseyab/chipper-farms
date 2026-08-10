<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permission to record a sales return (IMP-098, blueprint §43).
 *
 * WHY ITS OWN PERMISSION RATHER THAN REUSING AN EXISTING ONE
 * -----------------------------------------------------------
 * Recording a return reverses posted revenue, restores inventory and credits a
 * customer. That is nearer to a refund than to data entry, and the storefront
 * refund route is already commented "treasurer-grade, NOT salesperson".
 * `refund_storefront_orders` was the closest fit and is the wrong one: this
 * covers direct farm-gate feed and pig sales that never touched the storefront,
 * so borrowing that permission would have tied a farm's ability to record a
 * return to whether it sells online.
 *
 * Granted to admin, superuser and accountant. Deliberately NOT salesperson —
 * the person who made the sale should not be the only one who can reverse it,
 * and a return is where a mistake becomes hard to see.
 */
class SalesReturnPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['superuser', 'admin', 'accountant'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $permission = Permission::firstOrCreate(['name' => 'record_sales_returns'], [
            'name'        => 'record_sales_returns',
            'description' => 'Record goods a customer brought back. Reverses the sale, '
                . 'restores stock and raises a credit note.',
            'module'      => 'finance',
        ]);

        foreach (['superuser', 'admin', 'accountant'] as $roleName) {
            Role::where('name', $roleName)->first()?->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $this->command?->info("Returns: granted 'record_sales_returns' to admin, superuser, accountant.");
    }
}
