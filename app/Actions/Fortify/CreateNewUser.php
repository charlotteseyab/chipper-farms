<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Users\UserProvisioningService;
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

        // Organisation resolution, role assignment, active status, email
        // verification and the duplicate-email guard all live in
        // UserProvisioningService — the single authoritative creation path
        // (CF-P0-001). This action keeps only what is specific to Fortify:
        // the superuser check above and Jetstream's terms validation.
        return app(UserProvisioningService::class)->provision($input, $actor);
    }
}