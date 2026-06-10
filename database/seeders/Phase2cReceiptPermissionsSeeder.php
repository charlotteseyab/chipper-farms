<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 2c — receipt permissions.
 *
 * Adds:
 *   - create_receipt : create + edit + allocate + reverse-allocation
 *
 * Reuses for receipt reversal:
 *   - reverse_journal : already exists; receipt full reversal cascades
 *                        through journal-batch reversal
 *
 * Why no separate post_receipt:
 *   Receipts auto-post on creation (Decision 16). There's no draft state
 *   in the standard flow, so post is implicit in create.
 *
 * Idempotent — safe to re-run.
 */
class Phase2cReceiptPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perm = Permission::firstOrCreate(
            ['name' => 'create_receipt'],
            [
                'description' => 'Record customer receipts, allocate to invoices, reverse allocations',
                'module'      => 'finance',
            ],
        );

        $rolesGetPerm = ['admin', 'accountant', 'superuser'];

        foreach ($rolesGetPerm as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) continue;

            if (! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
                $role->permissions()->attach($perm->id);
            }
        }

        $this->command?->info("Phase 2c: granted 'create_receipt' to: " . implode(', ', $rolesGetPerm));
    }
}
