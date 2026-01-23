<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Custom route model binding for workspace protection
            Route::bind('pig', function ($value) {
                $pig = \App\Models\PigManagement::findOrFail($value);
                
                // Check workspace access
                if (auth()->check() && auth()->user()->workspace_id) {
                    if ($pig->workspace_id != auth()->user()->workspace_id) {
                        abort(403, 'You do not have access to this resource.');
                    }
                }
                
                return $pig;
            });

            Route::bind('farrowing', function ($value) {
                $farrowing = \App\Models\Farrowing::findOrFail($value);
                
                if (auth()->check() && auth()->user()->workspace_id) {
                    if ($farrowing->workspace_id != auth()->user()->workspace_id) {
                        abort(403, 'You do not have access to this resource.');
                    }
                }
                
                return $farrowing;
            });

            Route::bind('mating', function ($value) {
                return \App\Models\Mating::findOrFail($value);
            });

            Route::bind('weaning', function ($value) {
                return \App\Models\Weaning::findOrFail($value);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        
        // This group should only contain class names, or you can leave it empty.
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\StoreBackUrl::class,
        ]);

        // ALIASES GO HERE
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            //Define the short key (activity) and the class here
            'activity' => \App\Http\Middleware\LogUserActivity::class, 
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
        
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();   