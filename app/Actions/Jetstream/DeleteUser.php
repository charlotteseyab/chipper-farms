<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        // User removal is intentionally permanent. Reusing a deleted user's
        // email address must be possible, and retaining a soft-deleted account
        // would keep that unique identity occupied.
        $user->forceDelete();
    }
}
