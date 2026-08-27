<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformSuperuserScope
{
    /** @var list<string> */
    private array $allowed = [
        'dashboard',
        'superuser.*',
        'settings.*',
        'notifications.*',
        'profile.show',
        'user-profile-information.update',
        'user-password.update',
        'two-factor.*',
        'other-browser-sessions.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isSuperuser()) {
            return $next($request);
        }

        foreach ($this->allowed as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        abort(403, 'Platform superusers do not operate inside a farm. Use a farm administrator account for farm operations.');
    }
}
