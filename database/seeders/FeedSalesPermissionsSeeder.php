<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Feed-sale permissions. Idempotent.
 *
 *   view_feed_sales    view the feed-sales list
 *   record_feed_sales  record a feed sale (posts revenue + COGS via pipeline)
 */
class FeedSalesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'view_feed_sales'   => 'View feed sales.',
            'record_feed_sales' => 'Record feed sales (recognises revenue, AR/cash, and COGS).',
        ];

        foreach ($perms as $code => $description) {
            Permission::firstOrCreate(['name' => $code], [
                'name' => $code, 'description' => $description, 'module' => 'sales',
            ]);
        }

        $assignments = [
            'view_feed_sales'   => ['admin', 'accountant', 'manager', 'farmmanager', 'superuser'],
            'record_feed_sales' => ['admin', 'accountant', 'manager', 'farmmanager', 'superuser'],
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) {
                continue;
            }
            foreach ($roleNames as $roleName) {
                $role = Role::where('name', $roleName)->first();
                $role?->permissions()->syncWithoutDetaching([$perm->id]);
            }
            $this->command?->info("Feed sales: granted '{$code}' to: " . implode(', ', $roleNames));
        }
    }
}
