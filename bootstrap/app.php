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
            // Outer confidentiality boundary for /finance (CF-P0-003). Admits
            // any user holding at least one finance-module permission; the
            // per-route 'permission' gates enforce the specific action.
            'finance.access' => \App\Http\Middleware\EnsureFinanceAccess::class,
            // Organisation feature gate (Phase 1). Hides a surface that is
            // switched off for the tenant. NOT an authorization control — the
            // 'permission' alias above remains authoritative and every gated
            // route must still carry it.
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            // Public shop open/closed (Phase 8). Distinct from 'feature'
            // because the public shop resolves its organisation for guests,
            // and because a closed shop answers 503 with a page rather than
            // 404 — its visitors are the public, with no other way in.
            'storefront.open' => \App\Http\Middleware\EnsureStorefrontOpen::class,
        ]);

        // Paystack posts server-to-server webhooks with no CSRF token; the
        // request is authenticated instead by HMAC signature verification in
        // PaystackWebhookController. Exempt only that exact path.
        $middleware->validateCsrfTokens(except: [
            'shop/paystack/webhook',
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
