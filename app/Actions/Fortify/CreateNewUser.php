<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;
use Illuminate\Validation\ValidationException;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
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
            'role' => ['required', 'string', 'max:50'],
            'active_status' => ['boolean'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        $fullName = trim(($input['first_name'] ?? '').' '.($input['last_name'] ?? ''));

        return User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'name' => $fullName,
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'role' => $input['role'],
            'active_status' => $input['active_status'] ?? true,
            'password' => Hash::make($input['password']),
        ]);
    }
}
