<?php

namespace App\Services\Users;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The single authoritative path for creating a user (CF-P0-001).
 *
 * Why this exists
 * ---------------
 * Before remediation there were two independent user-creation paths that
 * disagreed on the two fields that decide whether the account actually works:
 *
 *                        organization_id        active_status
 *   SettingsController   never set (NULL)       defaults to FALSE
 *   Fortify CreateNewUser  inherited from actor  defaults to TRUE
 *
 * A settings-created user was therefore *doubly* unusable:
 * EnsureOrganizationContext aborts 403 on an empty organization_id, and
 * EnsureUserIsActive rejects an inactive account. A superuser could create an
 * account through the UI that could not make a single authenticated request,
 * with no error at creation time to indicate it.
 *
 * Every entry point — settings, Fortify registration, invitations, admin
 * creation, seeders, imports and tests — must go through provision() so the two
 * paths can never diverge again.
 *
 * Organisation resolution policy
 * ------------------------------
 * Inherit only when the actor is unambiguously bound to one organisation.
 * Require an explicit choice when the actor could mean more than one. Never
 * guess: an account attached to the wrong tenant is worse than a failed
 * creation, because it silently grants cross-tenant reach.
 */
class UserProvisioningService
{
    /**
     * Create a user, or fail without creating anything.
     *
     * @param  array<string,mixed>  $attributes  Validated input. Recognised keys:
     *                                          first_name, last_name, email, phone,
     *                                          role, password, active_status,
     *                                          organization_id, email_verified.
     * @param  User|null  $actor  The user performing the creation. Null is only
     *                            valid for system contexts (seeders, imports),
     *                            which must pass organization_id explicitly.
     *
     * @throws ValidationException
     */
    public function provision(array $attributes, ?User $actor = null): User
    {
        $organizationId = $this->resolveOrganizationId($attributes, $actor);

        $email = strtolower(trim((string) ($attributes['email'] ?? '')));

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'An email address is required to create a user.',
            ]);
        }

        // Guard duplicates explicitly. Callers normally validate `unique:users`
        // first, but this service is also reachable from seeders and imports
        // where that validation may not have run.
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A user with this email address already exists.',
            ]);
        }

        $role = (string) ($attributes['role'] ?? '');

        if ($role === '') {
            throw ValidationException::withMessages([
                'role' => 'A role is required to create a user.',
            ]);
        }

        $password = $attributes['password'] ?? null;

        if (! is_string($password) || $password === '') {
            throw ValidationException::withMessages([
                'password' => 'A password is required to create a user.',
            ]);
        }

        $firstName = trim((string) ($attributes['first_name'] ?? ''));
        $lastName  = trim((string) ($attributes['last_name'] ?? ''));

        // active_status is set DELIBERATELY. The default is active, matching the
        // User model's own attribute default and the Fortify path. A caller that
        // wants a dormant account must ask for it explicitly — silently creating
        // inactive accounts was half of CF-P0-001.
        $activeStatus = array_key_exists('active_status', $attributes)
            ? (bool) $attributes['active_status']
            : true;

        // Role assignment and persistence are one unit. syncRoleFields() throws
        // InvalidArgumentException on an unsupported role; inside the
        // transaction that means nothing is written at all, rather than leaving
        // a roleless user behind.
        return DB::transaction(function () use (
            $attributes, $organizationId, $email, $role, $password,
            $firstName, $lastName, $activeStatus, $actor
        ) {
            $user = new User([
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'name'            => trim($firstName . ' ' . $lastName),
                'email'           => $email,
                'phone'           => $attributes['phone'] ?? null,
                'password'        => Hash::make($password),
                'active_status'   => $activeStatus,
                'organization_id' => $organizationId,
            ]);

            try {
                $user->syncRoleFields($role);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'role' => $e->getMessage(),
                ]);
            }

            // Email verification is handled deliberately rather than left to
            // chance. An operator-provisioned account is treated as verified —
            // the operator vouched for the address — unless told otherwise.
            // Self-registration paths pass email_verified => false so the
            // standard verification flow applies.
            $verified = array_key_exists('email_verified', $attributes)
                ? (bool) $attributes['email_verified']
                : true;

            if ($verified) {
                $user->email_verified_at = now();
            }

            $user->save();

            // Post-condition: refuse to hand back an account that cannot make
            // an authenticated request. This is the invariant CF-P0-001 broke.
            if (empty($user->organization_id) || empty($user->role_id)) {
                throw ValidationException::withMessages([
                    'organization' => 'User creation failed to establish organization and role context.',
                ]);
            }

            Log::info('user.provisioned', [
                'user_id'         => $user->id,
                'email'           => $user->email,
                'role'            => $user->role,
                'organization_id' => $user->organization_id,
                'active_status'   => $user->active_status,
                'actor_id'        => $actor?->id,
            ]);

            return $user;
        });
    }

    /**
     * Resolve which organisation the new user belongs to.
     *
     * Precedence:
     *  1. An explicitly supplied organization_id — validated for existence and
     *     active status, and (unless the actor is a superuser) required to match
     *     the actor's own organisation so a tenant admin cannot plant a user in
     *     another tenant.
     *  2. The actor's organisation, when they have exactly one.
     *  3. The system's single active organisation, for system contexts only.
     *
     * Anything else is ambiguous and refused.
     *
     * @param  array<string,mixed>  $attributes
     *
     * @throws ValidationException
     */
    protected function resolveOrganizationId(array $attributes, ?User $actor): int
    {
        $explicit = $attributes['organization_id'] ?? null;

        if ($explicit !== null && $explicit !== '') {
            $organization = Organization::find($explicit);

            if ($organization === null) {
                throw ValidationException::withMessages([
                    'organization_id' => 'The selected organization does not exist.',
                ]);
            }

            if ($organization->trashed() || $organization->is_active === false) {
                throw ValidationException::withMessages([
                    'organization_id' => 'The selected organization is inactive.',
                ]);
            }

            // Only a superuser may provision into an organisation other than
            // their own. Without this a tenant-scoped admin could create an
            // account inside another tenant.
            if ($actor !== null
                && ! $actor->isSuperuser()
                && (int) $organization->id !== (int) $actor->organization_id) {
                throw ValidationException::withMessages([
                    'organization_id' => 'You may only create users within your own organization.',
                ]);
            }

            return (int) $organization->id;
        }

        // Inherit from the actor when they are bound to exactly one organisation.
        if ($actor !== null && ! empty($actor->organization_id)) {
            return (int) $actor->organization_id;
        }

        // System contexts, or an actor that somehow lost its organisation link:
        // acceptable only when there is exactly one active organisation, so
        // there is nothing to guess between.
        $activeOrgs = Organization::active()->limit(2)->get();

        if ($activeOrgs->count() === 1) {
            return (int) $activeOrgs->first()->id;
        }

        throw ValidationException::withMessages([
            'organization_id' => $activeOrgs->isEmpty()
                ? 'No active organization exists to create this user in.'
                : 'An organization must be specified explicitly when more than one is active.',
        ]);
    }
}
