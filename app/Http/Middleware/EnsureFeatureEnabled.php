<?php

namespace App\Http\Middleware;

use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route when its feature is switched off for the current organisation
 * (Phase 1; prompt §110 "a disabled feature must not remain usable via URL").
 *
 * Usage:
 *
 *   Route::middleware('feature:finance.quick_expense')->group(...);
 *   Route::middleware('feature:finance.wht,finance.advanced_ap')->group(...);
 *
 * Multiple keys are AND-combined — every one must be usable. That is the
 * opposite of PermissionMiddleware, which OR-combines, and the asymmetry is
 * intentional: permissions ask "does this user have any route in", features ask
 * "is every capability this screen needs switched on".
 *
 * THIS IS NOT AN AUTHORIZATION CONTROL
 * ------------------------------------
 * It answers "is this surface offered", never "is this user allowed". Routes
 * that touch money or tenant data must still carry their own `permission:` and
 * policy checks. Removing those in favour of this middleware would turn an
 * administrator's presentation choice into the only thing standing between a
 * user and someone else's ledger.
 *
 * 404, NOT 403
 * ------------
 * A disabled feature is not an authorization failure — the resource is not part
 * of this organisation's product. 403 would tell an unauthorised prober that
 * the surface exists and is merely switched off; 404 matches the "cross-tenant
 * resources should remain undiscoverable" rule in prompt §118 and reads
 * correctly to a legitimate user, who has no permission problem to fix.
 */
class EnsureFeatureEnabled
{
    public function __construct(
        protected OrganizationFeatureService $features,
    ) {
    }

    public function handle(Request $request, Closure $next, string ...$featureKeys): Response
    {
        foreach ($featureKeys as $key) {
            FeatureCatalog::assertKnown($key);

            if (! $this->features->usable($key)) {
                abort(404);
            }
        }

        return $next($request);
    }
}
