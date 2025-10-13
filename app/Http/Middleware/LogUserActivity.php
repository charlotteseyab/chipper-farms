<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class LogUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            // last_seen indicates "currently active" ping
            // last_active_at tracks the most recent known activity moment
            $now = Carbon::now();
            Auth::user()->forceFill([
                'last_seen' => $now,
                'last_active_at' => $now,
            ])->save();
        }

        return $next($request);
    }
}