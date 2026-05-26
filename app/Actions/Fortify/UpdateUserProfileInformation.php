<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        $isSuperuser = $user->isSuperuser();

        Validator::make($input, [
            // Jetstream's default profile form updates `name` + `email`.
            // Our app also supports first_name/last_name, but keep `name` as the
            // primary input to remain compatible with upstream features/tests.
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^[0-9]{0,20}$/'],
            'role' => [$isSuperuser ? 'required' : 'nullable', 'string', 'in:' . implode(',', User::allowedRoleNames())],
            'active_status' => [$isSuperuser ? 'boolean' : 'nullable'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        $nextRole = $isSuperuser ? ($input['role'] ?? $user->role) : $user->role;
        $nextActive = $isSuperuser ? ($input['active_status'] ?? $user->active_status) : $user->active_status;

        $payload = [
            'first_name' => $input['first_name'] ?? $user->first_name,
            'last_name' => $input['last_name'] ?? $user->last_name,
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'active_status' => $nextActive,
        ];

        if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $payload, $nextRole);

            return;
        }

        $user->forceFill($payload);
        $user->syncRoleFields($nextRole);
        $user->save();
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    protected function updateVerifiedUser(User $user, array $input, string $role): void
    {
        $user->forceFill([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
            'phone' => $input['phone'] ?? null,
            'active_status' => $input['active_status'] ?? $user->active_status,
        ]);
        $user->syncRoleFields($role);
        $user->save();

        $user->sendEmailVerificationNotification();
    }
}