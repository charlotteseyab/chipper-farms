<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreBackUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Store the current URL as a potential back URL for certain pages
        $currentRoute = $request->route()->getName();
        
        // List of routes that should be stored as back URLs
        $storableRoutes = [
            'breeding-stocks.index',
            'standard-stocks.index',
            'all-stocks.index',
            'mating.index',
            'mating.manage',
            'gestation.index',
            'sales.index',
            'dashboard'
        ];
        
        // If current route is storable and it's a GET request
        if (in_array($currentRoute, $storableRoutes) && $request->isMethod('GET')) {
            session(['last_list_url' => $request->fullUrl()]);
        }
        
        return $next($request);
    }
}
