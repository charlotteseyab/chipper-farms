<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Illuminate\Validation\ValidationException;

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
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:50', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^[0-9]{0,20}$/'],
            'role' => [$isSuperuser ? 'required' : 'nullable', 'string', 'max:50'],
            'active_status' => [$isSuperuser ? 'boolean' : 'nullable'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        $nextRole = $isSuperuser ? ($input['role'] ?? $user->role) : $user->role;
        $nextActive = $isSuperuser ? ($input['active_status'] ?? $user->active_status) : $user->active_status;

        $payload = [
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'name' => trim(($input['first_name'] ?? '').' '.($input['last_name'] ?? '')),
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'role' => $nextRole,
            'active_status' => $nextActive,
        ];

        if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $payload);
        } else {
            $user->forceFill($payload)->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
            'phone' => $input['phone'] ?? null,
            'role' => $input['role'] ?? $user->role,
            'active_status' => $input['active_status'] ?? $user->active_status,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
