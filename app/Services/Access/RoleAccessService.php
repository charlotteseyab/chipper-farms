<?php

namespace App\Services\Access;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Support\Access\RoleAccessCatalog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Saves what a role may do on one farm (Settings → Roles & access).
 *
 * Only the permissions of the modules the farm has on are changed; every other
 * permission the role holds is kept. A role's legacy alias (manager for
 * farmmanager) is saved with it, so users on either row get the same access.
 */
class RoleAccessService
{
    public function __construct(
        protected RolePermissionResolver $resolver,
        protected AuditTrailService $audit,
    ) {
    }

    /**
     * @param  list<string>  $submitted  the permissions ticked for the farm's modules
     * @return array{added: list<string>, removed: list<string>}
     */
    public function save(Organization $organization, Role $role, array $submitted, User $actor): array
    {
        $this->assertEditable($role);

        $offered = array_merge(...array_column(RoleAccessCatalog::availableFor($organization), 'permissions'));
        $submitted = array_values(array_unique(array_intersect($submitted, $offered)));

        $current = array_keys($this->resolver->names($organization->id, $role->id));
        $kept = array_diff($current, $offered);
        $next = array_values(array_unique([...$kept, ...$submitted]));

        $added = array_values(array_diff($next, $current));
        $removed = array_values(array_diff($current, $next));

        if ($added === [] && $removed === []) {
            return ['added' => [], 'removed' => []];
        }

        $ids = Permission::query()->whereIn('name', $next)->pluck('id')->all();

        DB::transaction(function () use ($organization, $role, $ids, $actor) {
            foreach ($this->rolesSharingName($role) as $roleId) {
                DB::table('organization_role_settings')->updateOrInsert(
                    ['organization_id' => $organization->id, 'role_id' => $roleId],
                    ['updated_by' => $actor->id, 'updated_at' => now(), 'created_at' => now()],
                );
                DB::table('organization_role_permissions')
                    ->where('organization_id', $organization->id)->where('role_id', $roleId)->delete();
                DB::table('organization_role_permissions')->insert(array_map(fn ($id) => [
                    'organization_id' => $organization->id,
                    'role_id' => $roleId,
                    'permission_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $ids));
            }
        });

        $this->audit->log('roles_access', 'role_access_updated', [
            'user' => $actor,
            'organization_id' => $organization->id,
            'auditable' => $role,
            'record_label' => $this->roleLabel($role),
            'description' => 'Changed what the '.$this->roleLabel($role).' role can do on this farm.',
            'metadata' => ['added' => $added, 'removed' => $removed],
        ]);

        return ['added' => $added, 'removed' => $removed];
    }

    public function reset(Organization $organization, Role $role, User $actor): bool
    {
        $this->assertEditable($role);

        $roleIds = $this->rolesSharingName($role);

        $deleted = DB::transaction(function () use ($organization, $roleIds) {
            DB::table('organization_role_permissions')
                ->where('organization_id', $organization->id)->whereIn('role_id', $roleIds)->delete();

            return DB::table('organization_role_settings')
                ->where('organization_id', $organization->id)->whereIn('role_id', $roleIds)->delete();
        });

        if ($deleted > 0) {
            $this->audit->log('roles_access', 'role_access_reset', [
                'user' => $actor,
                'organization_id' => $organization->id,
                'auditable' => $role,
                'record_label' => $this->roleLabel($role),
                'description' => 'Put the '.$this->roleLabel($role).' role back to its default access on this farm.',
            ]);
        }

        return $deleted > 0;
    }

    public function roleLabel(Role $role): string
    {
        return match (User::normalizeRoleName($role->name)) {
            'farmmanager' => 'Farm manager',
            'dataentry' => 'Data entry',
            'mill_operator' => 'Mill operator',
            default => ucfirst((string) User::normalizeRoleName($role->name)),
        };
    }

    private function assertEditable(Role $role): void
    {
        if (! RoleAccessCatalog::isEditableRole($role->name)) {
            throw new InvalidArgumentException("The {$role->name} role cannot be changed by a farm.");
        }
    }

    /** @return list<int> */
    private function rolesSharingName(Role $role): array
    {
        $name = User::normalizeRoleName($role->name);

        return Role::query()->get(['id', 'name'])
            ->filter(fn (Role $other) => User::normalizeRoleName($other->name) === $name)
            ->pluck('id')->all();
    }
}
