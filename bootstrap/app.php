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
            // Route model bindings.
            //
            // NOTE: earlier versions of this file contained a "workspace_id"
            // check on pig and farrowing bindings. That check was non-functional
            // (auth()->user()->workspace_id never existed in the users schema)
            // and has been removed. Real per-resource authorization will be
            // enforced via Policies introduced in Phase 0.3, and tenant
            // isolation via the EnsureOrganizationContext middleware below.
            Route::bind('pig', function ($value) {
                return \App\Models\PigManagement::findOrFail($value);
            });

            Route::bind('farrowing', function ($value) {
                return \App\Models\Farrowing::findOrFail($value);
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

        // EnsureOrganizationContext runs AFTER the framework's auth layer,
        // binding the organization for the request lifecycle and rejecting
        // auth'd-but-orgless users with 403.
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\EnsureOrganizationContext::class,
            \App\Http\Middleware\StoreBackUrl::class,
        ]);

        // ALIASES GO HERE
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            //Define the short key (activity) and the class here
            'activity' => \App\Http\Middleware\LogUserActivity::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'org' => \App\Http\Middleware\EnsureOrganizationContext::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();