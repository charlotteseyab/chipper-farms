<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        // Step 1: Validate all fields including current password
        // Use the input array directly - Laravel Validator handles null/empty properly
        Validator::make($input, [
            'current_password' => ['required', 'string'],
            'password' => $this->passwordRules(),
            'password_confirmation' => ['required', 'string'],
        ], [
            'current_password.required' => __('The current password field is required.'),
            'password.confirmed' => __('The new password and confirmation password do not match.'),
            'password_confirmation.required' => __('Please confirm your new password.'),
        ])->validateWithBag('updatePassword');

        // Step 2: Verify current password matches database (only after validation passes)
        if (!Hash::check($input['current_password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'current_password' => [__('The provided password does not match your current password.')],
            ])->errorBag('updatePassword');
        }

        // Step 3: Explicitly check password confirmation matches
        if ($input['password'] !== $input['password_confirmation']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'password' => [__('The new password and confirmation password do not match.')],
            ])->errorBag('updatePassword');
        }

        // Step 4: Update password only if all validations pass
        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
