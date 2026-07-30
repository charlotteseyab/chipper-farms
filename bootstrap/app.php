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
            // Organisation-scoped route model bindings (CF-P0-005).
            //
            // These were global findOrFail(). Policies now deny cross-tenant
            // access, so the record could not be acted on — but the BINDING
            // resolved it first, which leaks existence: requesting another
            // tenant's pig returned 403 while a genuinely absent id returned
            // 404. That difference is an enumeration oracle over the whole
            // livestock table.
            //
            // Scoping the binding closes it: a record outside the caller's
            // organisation is indistinguishable from one that does not exist.
            //
            // 404 rather than 403 is deliberate for the same reason — telling an
            // attacker "this exists but is not yours" is the disclosure we are
            // removing.
            //
            // Farrowing, Mating and Weaning have no organization_id column, so
            // their tenancy is DERIVED through the parent pig via the
            // ResolvesOrganization contract. That is the same resolution the
            // policies use, so binding and policy cannot disagree.
            $scopeToOrganization = function ($model) {
                if ($model === null) {
                    abort(404);
                }

                $currentOrganizationId = app(\App\Support\CurrentOrganization::class)->resolve()?->id;

                // No organisation context: EnsureOrganizationContext rejects
                // such requests at the door for authenticated users, but the
                // binding must not fail open if it is ever reached another way.
                if ($currentOrganizationId === null) {
                    abort(404);
                }

                $recordOrganizationId = $model instanceof \App\Models\Concerns\ResolvesOrganization
                    ? $model->resolveOrganizationId()
                    : $model->getAttribute('organization_id');

                // Unresolvable tenancy denies, matching EnforcesOrganizationScope.
                if ($recordOrganizationId === null
                    || (int) $recordOrganizationId !== (int) $currentOrganizationId) {
                    abort(404);
                }

                return $model;
            };

            Route::bind('pig', function ($value) use ($scopeToOrganization) {
                return $scopeToOrganization(\App\Models\PigManagement::find($value));
            });

            Route::bind('farrowing', function ($value) use ($scopeToOrganization) {
                return $scopeToOrganization(\App\Models\Farrowing::find($value));
            });

            Route::bind('mating', function ($value) use ($scopeToOrganization) {
                return $scopeToOrganization(\App\Models\Mating::find($value));
            });

            Route::bind('weaning', function ($value) use ($scopeToOrganization) {
                return $scopeToOrganization(\App\Models\Weaning::find($value));
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
            // Outer confidentiality boundary for /finance (CF-P0-003). Admits
            // any user holding at least one finance-module permission; the
            // per-route 'permission' gates enforce the specific action.
            'finance.access' => \App\Http\Middleware\EnsureFinanceAccess::class,
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