<?php

namespace App\Actions\Fortify;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Phase 0.2 update: the new user is now assigned an organization_id,
     * inherited from the acting superuser. Without this, newly-created users
     * would fail the EnsureOrganizationContext middleware on first login.
     *
     * Self-registration is effectively closed because this action requires a
     * logged-in superuser. The organization_id inheritance means the path
     * from "create user" to "user logs in and uses the system" works end-to-end.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $actor = auth()->user();

        if (!$actor || !$actor->isSuperuser()) {
            throw ValidationException::withMessages([
                'authorization' => 'Only a superuser can create new users.',
            ]);
        }

        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:50', 'unique:users'],
            'phone' => ['nullable', 'regex:/^[0-9]{0,20}$/'],
            'role' => ['required', 'string', 'in:' . implode(',', User::allowedRoleNames())],
            'active_status' => ['boolean'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        // Resolve the organization for the new user.
        //
        // Precedence:
        //  1. The acting superuser's own organization_id (normal case).
        //  2. The single active organization if the system has exactly one
        //     (covers edge cases where a seed superuser somehow lost org link).
        //
        // If neither is resolvable we refuse — rather than guessing or creating
        // orphaned users. Operators will see the explicit error in logs.
        $organizationId = $actor->organization_id;

        if ($organizationId === null) {
            $activeOrgs = Organization::active()->limit(2)->get();
            if ($activeOrgs->count() === 1) {
                $organizationId = $activeOrgs->first()->id;
            } else {
                throw ValidationException::withMessages([
                    'authorization' => 'Cannot resolve an organization for the new user. Contact a system administrator.',
                ]);
            }
        }

        $user = new User([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'name' => trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? '')),
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'active_status' => $input['active_status'] ?? true,
            'password' => Hash::make($input['password']),
            'organization_id' => $organizationId,
        ]);

        $user->syncRoleFields($input['role']);
        $user->save();

        return $user;
    }
}