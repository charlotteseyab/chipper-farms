<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;


abstract class Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function abortUnlessRole(string ...$roles): void
    {
        $user = auth()->user();

        abort_unless($user, 403, 'Authentication is required.');

        foreach ($roles as $role) {
            if ($user->hasRoleName($role)) {
                return;
            }
        }

        abort(403, 'Access denied. You do not have permission to access this resource.');
    }
}
