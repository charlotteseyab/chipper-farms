<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 1d — gates routes by permission name.
 *
 * Usage in routes/web.php:
 *
 *   Route::middleware('permission:approve_journal')->group(function () { ... });
 *   Route::middleware('permission:propose_manual_journal,approve_journal')->group(function () { ... });
 *
 * Multiple permissions in one declaration are OR-combined (any one grants access).
 *
 * Superuser bypass is handled inside User::hasPermission() — we don't
 * duplicate the check here.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (! method_exists($user, 'hasPermission')) {
            // User model not yet using HasPermissions trait — defensive deny.
            abort(500, 'Permission system not properly configured.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this resource.');
    }
}
