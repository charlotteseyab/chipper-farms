<?php

namespace App\Http\Middleware;

use App\Storefront\Support\StorefrontSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the public shop when the organisation has switched it off
 * (Phase 8, blueprint §34 "store enabled").
 *
 * WHY NOT THE `feature:` MIDDLEWARE
 * ---------------------------------
 * `EnsureFeatureEnabled` resolves the organisation from `CurrentOrganization`,
 * which is null for an unauthenticated visitor — so on the public shop it would
 * fall back to the catalogue default and answer for no tenant in particular.
 * The public shop resolves its organisation the way the rest of the storefront
 * does, through `StorefrontSettings`, so the page a guest sees and the switch
 * that governs it describe the same farm.
 *
 * 503 AND A PAGE, NOT 404
 * -----------------------
 * The other difference from `EnsureFeatureEnabled`, and a deliberate one. A
 * disabled finance module is not part of that organisation's product and 404 is
 * honest. A closed shop is a real shop that is temporarily not selling, and its
 * customers are members of the public who have no other way in — a bare 404
 * would be exactly the dead end blueprint §46 exists to prevent. They get a
 * page that says the shop is closed and how to reach the farm, and the status
 * is 503 so crawlers treat it as temporary and do not de-index a farm's shop
 * because it paused for a season.
 *
 * WHAT IS DELIBERATELY NOT CLOSED
 * -------------------------------
 * The Paystack webhook and the payment callback. Money already in flight must
 * still be verified and reconciled; closing the shop must never strand a
 * payment a buyer has already made. Existing customers' order pages stay open
 * for the same reason. What closes is the ability to browse and to place
 * something new.
 */
class EnsureStorefrontOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (StorefrontSettings::enabled()) {
            return $next($request);
        }

        return response()->view('storefront.public.closed', [
            'storeName'    => StorefrontSettings::storeName(),
            'supportEmail' => StorefrontSettings::supportEmail(),
            'supportPhone' => StorefrontSettings::supportPhone(),
        ], 503);
    }
}
