<?php

namespace App\Services\Access;

use Illuminate\Support\Facades\DB;

/**
 * What a role may do on a farm.
 *
 * A farm's admin can customise a role for that farm (Settings → Roles &
 * access); a customised role uses that farm's own permissions, and any other
 * role the default ones. Not cached here: callers keep the answer as long as
 * they should (a user keeps it for its own lifetime, see HasPermissions).
 */
class RolePermissionResolver
{
    public function has(?int $organizationId, int $roleId, string $permission): bool
    {
        return isset($this->names($organizationId, $roleId)[$permission]);
    }

    /** @return array<string, true> */
    public function names(?int $organizationId, int $roleId): array
    {
        return $this->load($organizationId, $roleId);
    }

    public function isCustomised(?int $organizationId, int $roleId): bool
    {
        return $organizationId !== null && DB::table('organization_role_settings')
            ->where('organization_id', $organizationId)->where('role_id', $roleId)->exists();
    }

    /** @return array<string, true> */
    private function load(?int $organizationId, int $roleId): array
    {
        if ($this->isCustomised($organizationId, $roleId)) {
            $names = DB::table('organization_role_permissions as granted')
                ->where('granted.organization_id', $organizationId)->where('granted.role_id', $roleId)
                ->join('permissions', 'permissions.id', '=', 'granted.permission_id')->pluck('permissions.name')->all();
        } else {
            $names = $this->defaults($roleId);
        }

        return array_fill_keys($names, true);
    }

    /**
     * A role's default permissions: those stored for it, plus the livestock
     * permissions that replaced role-name checks, which follow the role's
     * name (see LivestockAccessPermissionsSeeder).
     *
     * @return list<string>
     */
    public function defaults(int $roleId): array
    {
        $stored = DB::table('role_permissions')->where('role_id', $roleId)
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')->pluck('permissions.name')->all();
        $roleName = DB::table('roles')->where('id', $roleId)->value('name');

        return array_values(array_unique([...$stored, ...\Database\Seeders\LivestockAccessPermissionsSeeder::defaultsFor($roleName)]));
    }
}
