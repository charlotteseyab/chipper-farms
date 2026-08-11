<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Storefront Phase 6 — refund permission.
 *
 * Refunding a posted/completed order reverses customer-facing GL legs (an
 * audited financial reversal). It is therefore TREASURER-GRADE and is granted
 * only to admin/superuser — deliberately NOT to salesperson, mirroring the
 * project's principle that financially-binding reversals sit above the
 * operational sales role.
 */
class StorefrontRefundPermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        foreach (['superuser', 'admin', 'salesperson'] as $roleName) {
            $this->seededRoleOrCreate($roleName);
        }

        $perm = Permission::firstOrCreate(['name' => 'refund_storefront_orders'], [
            'name'        => 'refund_storefront_orders',
            'description' => 'Refund storefront orders (reverses customer-facing financial legs). Treasurer-grade.',
            'module'      => 'storefront',
        ]);

        foreach (['admin', 'superuser'] as $roleName) {
            $this->seededRole($roleName)?->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->command?->info("Storefront P6: granted 'refund_storefront_orders' to: admin, superuser (NOT salesperson).");
    }
}
