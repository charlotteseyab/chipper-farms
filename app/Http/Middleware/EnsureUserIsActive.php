<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * If the authenticated user has been marked inactive, immediately
     * log them out, invalidate the session and redirect to login with
     * a clear message.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->active_status) {
            // Explicitly use the session-based guard (RequestGuard doesn't support logout()).
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->withErrors([
                'email' => __('Your account has been deactivated. Please contact an administrator.'),
            ]);
        }

        return $next($request);
    }
}

