<?php

namespace App\Validation;

use App\Support\CurrentOrganization;
use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Makes ordinary exists/unique validation respect the active farm.
 *
 * Eloquent scopes do not participate in Laravel's database presence rules.
 * Centralising this boundary prevents a newly added form from accidentally
 * accepting an id or duplicate name owned by another organization.
 */
class OrganizationPresenceVerifier extends DatabasePresenceVerifier
{
    private const PLATFORM_TABLES = [
        'organizations',
        'users',
        'roles',
        'permissions',
        'email_settings',
        'system_configs',
        'password_reset_tokens',
    ];

    protected function table($table)
    {
        $query = parent::table($table);
        $plainTable = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

        if (in_array($plainTable, self::PLATFORM_TABLES, true)) {
            return $query;
        }

        try {
            $organizationId = app(CurrentOrganization::class)->id();
            if ($organizationId !== null
                && $query->getConnection()->getSchemaBuilder()->hasColumn($plainTable, 'organization_id')) {
                $query->where($plainTable.'.organization_id', $organizationId);
            }
        } catch (\Throwable) {
            // Installer/console validation can run before either the tenant
            // resolver or the table exists. In that context retain Laravel's
            // normal presence behaviour.
        }

        return $query;
    }
}
