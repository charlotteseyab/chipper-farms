<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outer confidentiality boundary for the /finance route group (CF-P0-003).
 *
 * Why this exists rather than `permission:view_finance` on the group
 * ------------------------------------------------------------------
 * The obvious fix for CF-P0-003 is to wrap `Route::prefix('finance')` in
 * `permission:view_finance`. That is wrong here, and the seeded role matrix
 * proves it:
 *
 *   role           has view_finance   finance-module permissions
 *   farmmanager    NO                 10  (create_purchase_request_vendor_invoice,
 *                                          receive_against_pr, view_financial_reports,
 *                                          view_inventory_value, view_loans,
 *                                          view_treasury, view_period_close, ...)
 *   mill_operator  NO                  1  (view_inventory_value)
 *
 * A blanket `view_finance` gate would either lock those roles out of routes
 * they are explicitly permissioned for, or force us to grant them
 * `view_finance` — which would hand them the whole finance module and turn an
 * authorization fix into a privilege escalation.
 *
 * So the boundary asks the weaker, correct question: "does this user hold ANY
 * finance-module permission at all?" A user with none has no business inside
 * /finance under any URL. A user with some is admitted here and then held to
 * the specific per-route permission by PermissionMiddleware.
 *
 * This is defence in depth, not the primary control. Every finance route also
 * carries its own `permission:` gate. This layer's job is to guarantee that a
 * route added in future without a gate still is not reachable by a user with
 * zero finance entitlement — it converts the failure mode from "silently
 * world-readable" to "still requires finance standing".
 *
 * Superusers bypass, matching Gate::before and User::hasPermission().
 */
class EnsureFinanceAccess
{
    /**
     * Permission modules whose holders have legitimate business inside /finance.
     *
     * 'finance' is the obvious one. 'sales' is required because the /finance
     * prefix also hosts the feed-sales posting surface, and those permissions
     * (view_feed_sales, record_feed_sales) are catalogued under module 'sales'.
     * A boundary that asked only for module='finance' locked out roles holding
     * exactly the permission the target route requires — the URL prefix and the
     * permission taxonomy do not line up one-to-one, so the boundary must
     * follow the taxonomy.
     */
    protected const ADMITTED_MODULES = ['finance', 'sales'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (method_exists($user, 'isSuperuser') && $user->isSuperuser()) {
            return $next($request);
        }

        if (! $user->role_id) {
            abort(403, 'You do not have permission to access the finance module.');
        }

        if (! $this->holdsAnyAdmittedPermission($user)) {
            abort(403, 'You do not have permission to access the finance module.');
        }

        return $next($request);
    }

    /**
     * Deliberately NOT cached in static state.
     *
     * An earlier revision memoised this per role id. That is unsafe: role ids
     * are stable identifiers for a *mutable* set of permissions, so a memo
     * keyed on them goes stale the moment a role is edited, and it leaks across
     * requests in a long-lived worker (Octane) or across tests sharing a PHP
     * process. It also produced false denials under RefreshDatabase, where role
     * ids are reused between tests with different permission fixtures.
     *
     * This is one indexed EXISTS against a small pivot table, on finance
     * requests only. The correctness is worth far more than the query.
     */
    protected function holdsAnyAdmittedPermission($user): bool
    {
        // The role's permissions on the user's farm (customised or default).
        $names = array_keys(app(\App\Services\Access\RolePermissionResolver::class)
            ->names($user->organization_id ? (int) $user->organization_id : null, (int) $user->role_id));

        return $names !== [] && DB::table('permissions')->whereIn('name', $names)
            ->whereIn('module', static::ADMITTED_MODULES)->exists();
    }
}
