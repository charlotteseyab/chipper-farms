<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures that every authenticated request has a resolvable organization
 * context, and binds it on the CurrentOrganization resolver for the request
 * lifecycle.
 *
 * This middleware runs AFTER authentication. It is safe to apply to all web
 * routes that are also behind auth. Unauthenticated requests are allowed to
 * pass through — authentication itself is the job of the auth middleware.
 *
 * Behaviour:
 *  - No auth user: pass through (other middleware will redirect to login).
 *  - Auth user without organization_id: abort 403 (not redirect to login),
 *    because the user is valid but improperly provisioned. Operators will
 *    see this in logs and can correct the user record. Never auto-assign
 *    a default org here — silent assignment would mask real data issues.
 *  - Auth user with organization_id pointing to a soft-deleted or inactive
 *    organization: abort 403 with a specific message.
 *  - Otherwise: bind the organization on the resolver and continue.
 */
class EnsureOrganizationContext
{
    public function __construct(protected CurrentOrganization $current)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Platform superusers deliberately live above all tenants. They do not
        // receive an implicit/default farm context.
        if ($user->isSuperuser()) {
            $this->current->bind(null);
            return $next($request);
        }

        if (empty($user->organization_id)) {
            abort(403, 'Your account is not linked to an organization. Contact a system administrator.');
        }

        $organization = $user->organization;

        if ($organization === null) {
            abort(403, 'The organization linked to your account no longer exists. Contact a system administrator.');
        }

        if ($organization->trashed() || $organization->is_active === false) {
            abort(403, 'Your organization is inactive. Contact a system administrator.');
        }

        $this->current->bind($organization);
        $this->applyFarmOperationalSettings();

        return $next($request);
    }

    private function applyFarmOperationalSettings(): void
    {
        foreach ([
            'breeding.cycle.pregnancy_check_days',
            'breeding.cycle.gestation_days',
            'breeding.cycle.birth_to_weaning_days',
            'breeding.cycle.birth_to_maturity_days',
            'breeding.parameters.minimum_breeding_age',
            'breeding.parameters.optimal_first_breeding_age',
            'breeding.parameters.minimum_breeding_weight',
            'breeding.parameters.optimal_breeding_weight',
            'breeding.parameters.average_litter_size',
            'breeding.parameters.weaning_to_mating_rest',
            'breeding.parameters.maximum_breeding_age',
            'breeding.parameters.lactation_days',
        ] as $key) {
            $value = Setting::get($key);
            if ($value !== null) {
                config([$key => $value]);
            }
        }
    }
}
