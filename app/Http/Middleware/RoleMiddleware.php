<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            switch ($role) {
                case 'admin':
                    if ($user->isAdmin()) {
                        return $next($request);
                    }
                    break;
                case 'manager':
                case 'farmmanager':
                    if ($user->isManager()) {
                        return $next($request);
                    }
                    break;
                case 'superuser':
                    if ($user->isSuperuser()) {
                        return $next($request);
                    }
                    break;
                case 'dataentry':
                    if ($user->isDataEntry()) {
                        return $next($request);
                    }
                    break;
                case 'accountant':
                    if ($user->isAccountant()) {
                        return $next($request);
                    }
                    break;
            }
        }

        // If no role matches, deny access
        abort(403, 'Access denied. You do not have permission to access this resource.');
    }
}