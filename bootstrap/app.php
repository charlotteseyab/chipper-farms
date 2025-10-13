<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        
        // This group should only contain class names, or you can leave it empty.
        $middleware->web(append: [
            \App\Http\Middleware\StoreBackUrl::class,
        ]);

        // ALIASES GO HERE
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            //Define the short key (activity) and the class here
            'activity' => \App\Http\Middleware\LogUserActivity::class, 
        ]);
        
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();   