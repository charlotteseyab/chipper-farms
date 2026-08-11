<?php

namespace App\Http\Middleware;

use App\Storefront\Support\StorefrontSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the customer account area when the farm does not offer accounts
 * (Phase 16, blueprint §18, §46; parked by Phase 14).
 *
 * WHY THIS WAS PARKED TWICE
 * -------------------------
 * Phase 14 gated every other optional surface and stopped here, because gating
 * `account/*` naively would hide existing customers' orders — a §46 dead end
 * manufactured by a feature flag. Prompt §110 still applies though: a disabled
 * feature must not remain usable via URL.
 *
 * THE RESOLUTION IS ALREADY WRITTEN DOWN
 * --------------------------------------
 * `EnsureStorefrontOpen` faced the same tension when the shop closes and
 * answered it: "What closes is the ability to browse and to place something
 * new" — existing customers' order pages stay open. This middleware applies the
 * same rule. Account self-service (profile, addresses, saved items, quotes)
 * closes; ORDER HISTORY DOES NOT, and is deliberately left ungated in
 * `routes/web.php`.
 *
 * A person's record of what they bought is theirs. A farm switching off a
 * convenience feature is not a reason to take it away, and there is no other
 * route to it for someone who no longer has their confirmation email.
 *
 * 503 AND A PAGE, NOT 404
 * -----------------------
 * Same reasoning as the closed shop, and the same reason it is not the generic
 * `feature:` middleware: the visitor is a customer, not a member of staff
 * probing for surfaces. They are told accounts are unavailable and pointed at
 * the two things that still work — their orders, and guest order lookup.
 *
 * NOT AN AUTHORIZATION CONTROL
 * ----------------------------
 * SEC-001 is unchanged. This hides a surface the farm does not offer; who may
 * see whose data is still decided by the controllers, which scope every query
 * to the signed-in customer.
 */
class EnsureCustomerAccountsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (StorefrontSettings::customerAccountsEnabled()) {
            return $next($request);
        }

        return response()->view('storefront.public.accounts-unavailable', [
            'storeName'    => StorefrontSettings::storeName(),
            'supportEmail' => StorefrontSettings::supportEmail(),
            'supportPhone' => StorefrontSettings::supportPhone(),
        ], 503);
    }
}
