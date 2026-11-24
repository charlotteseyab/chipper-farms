<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\RoleAccessService;
use App\Services\Access\RolePermissionResolver;
use App\Support\Access\RoleAccessCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Settings → Roles & access: a farm's admin decides which modules each of the
 * farm's other roles may use, and how far (view only or full).
 *
 * An admin changes their own farm. The platform superuser has no farm, so
 * names one (`farm=<id>`), as on Modules & Features.
 */
class RoleAccessController extends Controller
{
    public function __construct(
        protected RolePermissionResolver $resolver,
        protected RoleAccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        $this->abortUnlessRole('superuser', 'admin');

        $user = $request->user();
        $farms = $user->isSuperuser() ? Organization::active()->orderBy('name')->get(['id', 'name']) : collect();
        $organization = $this->organization($request);

        if (! $organization) {
            return view('settings.roles-access', ['farms' => $farms, 'organization' => null]);
        }

        $modules = RoleAccessCatalog::availableFor($organization);
        $settings = DB::table('organization_role_settings')
            ->where('organization_role_settings.organization_id', $organization->id)
            ->leftJoin('users', 'users.id', '=', 'organization_role_settings.updated_by')
            ->get(['organization_role_settings.role_id', 'organization_role_settings.updated_at', 'users.name as updated_by_name'])
            ->keyBy('role_id');

        $roles = $this->editableRoles()->map(function (Role $role) use ($organization, $modules, $settings) {
            $granted = array_keys($this->resolver->names($organization->id, $role->id));
            $setting = $settings->get($role->id);

            return [
                'id' => $role->id,
                'label' => $this->access->roleLabel($role),
                'granted' => array_values(array_intersect(array_merge(...array_column($modules, 'permissions')), $granted)),
                'levels' => array_map(fn ($module) => RoleAccessCatalog::level($module, $granted), $modules),
                'customised' => $setting !== null,
                'updated_by' => $setting?->updated_by_name,
                'updated_at' => $setting?->updated_at,
                'users' => User::query()->where('organization_id', $organization->id)
                    ->whereIn('role_id', Role::query()->get(['id', 'name'])
                        ->filter(fn (Role $other) => User::normalizeRoleName($other->name) === User::normalizeRoleName($role->name))->pluck('id'))
                    ->count(),
            ];
        })->values();

        $descriptions = \App\Models\Permission::query()
            ->whereIn('name', array_merge(...array_column($modules, 'permissions')))
            ->pluck('description', 'name');

        $selected = $roles->firstWhere('id', (int) $request->query('role')) ?? $roles->first();

        return view('settings.roles-access', compact('farms', 'organization', 'modules', 'roles', 'descriptions', 'selected'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->abortUnlessRole('superuser', 'admin');
        abort_unless(RoleAccessCatalog::isEditableRole($role->name), 403, 'This role cannot be changed.');

        $organization = $this->organization($request);
        abort_unless($organization, 404);

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'max:80'],
        ]);

        $changes = $this->access->save($organization, $role, $validated['permissions'] ?? [], $request->user());

        $message = $changes['added'] === [] && $changes['removed'] === []
            ? 'Nothing changed for '.$this->access->roleLabel($role).'.'
            : $this->access->roleLabel($role).' access saved. It applies the next time those users open a page.';

        return redirect()->route('settings.roles-access', $this->query($request, $organization, $role))->with('success', $message);
    }

    public function reset(Request $request, Role $role): RedirectResponse
    {
        $this->abortUnlessRole('superuser', 'admin');
        abort_unless(RoleAccessCatalog::isEditableRole($role->name), 403, 'This role cannot be changed.');

        $organization = $this->organization($request);
        abort_unless($organization, 404);

        $this->access->reset($organization, $role, $request->user());

        return redirect()->route('settings.roles-access', $this->query($request, $organization, $role))
            ->with('success', $this->access->roleLabel($role).' is back to its default access.');
    }

    private function organization(Request $request): ?Organization
    {
        $user = $request->user();

        if (! $user->isSuperuser()) {
            return $user->organization_id ? Organization::find($user->organization_id) : null;
        }

        $farm = (string) $request->input('farm', '');

        return ctype_digit($farm) ? Organization::active()->find((int) $farm) : null;
    }

    /** One row per role, the canonical name preferred over a legacy alias (farmmanager over manager). */
    private function editableRoles(): Collection
    {
        return Role::query()->orderBy('id')->get()
            ->filter(fn (Role $role) => RoleAccessCatalog::isEditableRole($role->name))
            ->sortBy(fn (Role $role) => [
                array_search(User::normalizeRoleName($role->name), RoleAccessCatalog::EDITABLE_ROLES, true),
                $role->name === User::normalizeRoleName($role->name) ? 0 : 1,
            ])
            ->unique(fn (Role $role) => User::normalizeRoleName($role->name))
            ->values();
    }

    private function query(Request $request, Organization $organization, Role $role): array
    {
        return array_filter([
            'farm' => $request->user()->isSuperuser() ? $organization->id : null,
            'role' => $role->id,
        ]);
    }
}
