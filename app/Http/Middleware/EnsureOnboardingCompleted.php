<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperuser() || $user->isCustomer()) {
            return $next($request);
        }

        if ($request->routeIs('onboarding.*') || $request->routeIs('logout')) {
            return $next($request);
        }

        if ($user->onboarding_required && $user->onboarding_completed_at === null) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
