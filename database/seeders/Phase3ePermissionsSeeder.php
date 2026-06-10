<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 3e permissions.
 *
 * Adds:
 *   - view_bank_accounts         : list and view bank account configs
 *   - manage_bank_accounts       : create / edit / activate / deactivate
 *   - import_bank_statement      : upload CSV statements + manual matching
 *   - reconcile_bank_statement   : create / refresh / close / flag / archive
 *                                  reconciliations. The privileged op.
 *
 * Why split import_bank_statement from reconcile_bank_statement:
 *   Importing is a clerical activity (file in, lines parsed). Manual
 *   matching is also clerical — it's just bookkeeping at the line
 *   level.
 *   RECONCILING is the period-close act. It locks payments and
 *   receipts from reversal (Phase 3e period-freeze invariant) and
 *   stamps the cleared status onto every contributing document.
 *   That's a treasurer-grade responsibility, not a clerical one.
 *
 * Why view_bank_accounts is separate from view_finance:
 *   view_finance is broad (anyone in finance can see invoices,
 *   payments, AR ageing). Bank account configuration exposes
 *   account numbers, swift codes, and opening-balance values that
 *   are operationally sensitive. Default is narrower; sites that
 *   want it broad can grant explicitly.
 *
 * Idempotent. Safe to re-run.
 */
class Phase3ePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'view_bank_accounts' => [
                'name'        => 'View Bank Accounts',
                'description' => 'List bank accounts and view configuration / balances.',
                'category'    => 'finance',
            ],
            'manage_bank_accounts' => [
                'name'        => 'Manage Bank Accounts',
                'description' => 'Create, edit, activate, and deactivate bank account records.',
                'category'    => 'finance',
            ],
            'import_bank_statement' => [
                'name'        => 'Import Bank Statements',
                'description' => 'Upload CSV bank statements and perform line-level matching.',
                'category'    => 'finance',
            ],
            'reconcile_bank_statement' => [
                'name'        => 'Reconcile Bank Statements',
                'description' => 'Create, refresh, close (reconcile), flag, reopen, and archive bank reconciliations. Reconciling LOCKS contributing payments/receipts from reversal.',
                'category'    => 'finance',
            ],
        ];

        foreach ($perms as $code => $attrs) {
            Permission::firstOrCreate(
                ['name' => $code],
                [
                    'name'        => $code,
                    'description' => $attrs['description'],
                    'module'      => $attrs['category'] ?? 'finance',
                ]
            );
        }

        // Role assignments. Adjust role names to match your installation.
        $assignments = [
            'view_bank_accounts'        => ['admin', 'accountant', 'treasurer', 'superuser'],
            'manage_bank_accounts'      => ['admin', 'treasurer', 'superuser'],
            'import_bank_statement'     => ['admin', 'accountant', 'treasurer', 'superuser'],
            'reconcile_bank_statement'  => ['admin', 'treasurer', 'superuser'],
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) continue;

            foreach ($roleNames as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if (! $role) continue;
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }

            $this->command?->info("Phase 3e: granted '{$code}' to: " . implode(', ', $roleNames));
        }

        $this->command?->info(
            "Phase 3e: 'reconcile_bank_statement' deliberately NOT granted to 'accountant'. "
            . "Reconciling locks contributing payments/receipts from reversal — a treasurer-grade act."
        );
    }
}
