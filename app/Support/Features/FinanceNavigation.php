<?php

namespace App\Support\Features;

use App\Models\User;

/**
 * The finance menu, built from features and permissions (Phase 3, blueprint §20,
 * §26, §50). Since the finance merge it lists the FinanceHubs pages; every
 * other finance screen is a tab on one of them. Advanced Accounting, the paid
 * add-on, stays one entry of its own.
 *
 * WHAT CHANGED
 * ------------
 * `NavigationHelper::sidebarSections()` builds the finance menu from hardcoded
 * role arrays — `['superuser', 'admin', 'accountant']` repeated per link. That
 * has two problems the blueprint names directly:
 *
 *   §26: "Do not rely on hardcoded roles where permissions are more
 *        appropriate." A role list cannot express "whoever may record an
 *        expense", so adding a role means editing every line it should see.
 *
 *   §20: the default menu should be ten farm-language entries with the
 *        ledger-heavy screens gathered under Advanced Accounting, rather than
 *        twenty-plus accounting concepts flat.
 *
 * THREE CONDITIONS, ALL REQUIRED
 * ------------------------------
 * An entry appears only when the feature is on, the user holds the permission,
 * and the route exists. The feature test is presentation; the permission test is
 * the real control and is enforced again by middleware on the route itself.
 * Showing a link is not granting access, and hiding one is not protecting
 * anything.
 *
 * ROUTE EXISTENCE IS CHECKED
 * --------------------------
 * Several targets here are built in later phases. A menu that references a route
 * that does not exist yet throws on every page render for every user, which is
 * exactly the class of defect this programme has already hit once. Entries whose
 * route is not registered are simply omitted until their phase lands.
 */
final class FinanceNavigation
{
    /**
     * One sidebar entry per finance page (see FinanceHubs); the page's tabs
     * lead to the rest. An entry covers every screen of its page, so it
     * stays highlighted on any of them.
     *
     * @return list<array{label:string, route:string, icon:string, active:list<string>, addon:bool}>
     */
    public static function build(?User $user): array
    {
        $built = [];
        foreach (FinanceHubs::visible($user) as $hub) {
            $built[] = [
                'label'  => $hub['title'],
                'route'  => $hub['route'],
                'icon'   => $hub['icon'],
                'active' => $hub['active'],
                'addon'  => $hub['addon'],
            ];
        }

        return $built;
    }
}
