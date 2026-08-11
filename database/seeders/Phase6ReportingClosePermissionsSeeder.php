<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 6 — financial reports + period close permissions.
 *
 *   view_financial_reports  Income statement, balance sheet, cash flow.
 *   view_period_close       See the close dashboard, checklists, close packs.
 *   manage_period_close     Run a (soft) period close.
 *   override_period_close   Override soft checks, run a HARD close, reopen
 *                           a closed/locked period, reverse a year-end close.
 *   manage_year_end_close   Run the year-end RE closing journal.
 *
 * Idempotent (firstOrCreate + syncWithoutDetaching).
 */
class Phase6ReportingClosePermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        $perms = [
            'view_financial_reports' => [
                'description' => 'View the income statement, balance sheet, and cash flow statement.',
            ],
            'view_period_close' => [
                'description' => 'View the period close dashboard, checklists, and close packs.',
            ],
            'manage_period_close' => [
                'description' => 'Run a soft period close (period -> closed).',
            ],
            'override_period_close' => [
                'description' => 'Override soft close checks, run a hard close (period -> locked), reopen a closed/locked period, and reverse a year-end close.',
            ],
            'manage_year_end_close' => [
                'description' => 'Run the year-end closing journal (sweep P&L to retained earnings).',
            ],
        ];

        foreach ($perms as $code => $attrs) {
            Permission::firstOrCreate(
                ['name' => $code],
                [
                    'name'        => $code,
                    'description' => $attrs['description'],
                    'module'      => 'finance',
                ]
            );
        }

        $assignments = [
            'view_financial_reports' => ['admin', 'accountant', 'manager', 'farmmanager', 'superuser'],
            'view_period_close'      => ['admin', 'accountant', 'manager', 'farmmanager', 'superuser'],
            'manage_period_close'    => ['admin', 'accountant', 'superuser'],
            'override_period_close'  => ['admin', 'superuser'],
            'manage_year_end_close'  => ['admin', 'superuser'],
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) {
                continue;
            }

            foreach ($roleNames as $roleName) {
                $role = $this->seededRole($roleName);
                if (! $role) {
                    continue;
                }
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }

            $this->command?->info("Phase 6: granted '{$code}' to: " . implode(', ', $roleNames));
        }

        $this->command?->info(
            'Phase 6: override_period_close and manage_year_end_close are restricted to '
            . 'admin/superuser — hard close, reopen, and year-end are privileged, audited actions.'
        );
    }
}
