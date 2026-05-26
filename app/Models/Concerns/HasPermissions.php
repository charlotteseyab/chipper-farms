<?php

namespace App\Models\Concerns;

/**
 * Phase 1d — adds permission-checking to the User model.
 *
 * The User model was already large; this trait keeps the additions
 * minimal and isolated.
 *
 * Usage in the User model:
 *
 *   use \App\Models\Concerns\HasPermissions;
 *
 * Usage in code:
 *
 *   if ($user->hasPermission('approve_journal')) { ... }
 *
 * Superuser bypass:
 *   Superusers are treated as having every permission. This matches the
 *   Gate::before bypass already in place (Phase 0.3) and means a
 *   superuser can never be locked out of an action by a missing permission
 *   row.
 *
 * Caching:
 *   In a single request, repeated $user->hasPermission() calls share a
 *   cache to avoid repeating the JOIN. Cache is per-request and per-user
 *   instance — no cross-request leakage.
 */
trait HasPermissions
{
    /** @var array<string, bool>|null */
    protected ?array $permissionCache = null;

    public function hasPermission(string $name): bool
    {
        // Superuser bypass — matches Gate::before behaviour.
        if (method_exists($this, 'isSuperuser') && $this->isSuperuser()) {
            return true;
        }

        if (! $this->role_id) {
            return false;
        }

        if ($this->permissionCache === null) {
            $this->permissionCache = [];
        }

        if (isset($this->permissionCache[$name])) {
            return $this->permissionCache[$name];
        }

        // Look up via the role relationship. Loading once and caching is
        // O(1) on subsequent checks within the request.
        $role = \App\Models\Role::find($this->role_id);
        $this->permissionCache[$name] = $role
            ? $role->hasPermission($name)
            : false;

        return $this->permissionCache[$name];
    }

    /**
     * Check if user has ANY of the given permissions.
     *
     * @param  string|list<string>  $permissions
     */
    public function hasAnyPermission(string|array $permissions): bool
    {
        $list = is_array($permissions) ? $permissions : [$permissions];
        foreach ($list as $name) {
            if ($this->hasPermission($name)) {
                return true;
            }
        }
        return false;
    }

    public function clearPermissionCache(): void
    {
        $this->permissionCache = null;
    }
}
