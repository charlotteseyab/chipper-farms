<?php

namespace Tests\Browser;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * IMP-114 / blueprint §55, §60 — the viewport review, measured rather than eyeballed.
 *
 * WHAT THIS TESTS THAT NOTHING ELSE DID
 * -------------------------------------
 * Phase 17 closed the runtime-colour-class defect and said plainly that it was
 * not a substitute for this: it catches a class that is never generated, but
 * says nothing about whether a screen is usable at 390px. This is that check.
 *
 * The measurement is horizontal overflow — `documentElement.scrollWidth`
 * exceeding the viewport width. It is the single most objective mobile defect
 * and the one users feel immediately: the whole page slides sideways, headers
 * detach from content, and buttons sit off-screen. Wide tables are the usual
 * cause, which is why the older ledger screens are the ones under suspicion.
 *
 * 390px is the iPhone 12/13/14 logical width and a reasonable floor for the
 * phones a Ghanaian farm actually uses.
 *
 * WHY A TOLERANCE
 * ---------------
 * A few pixels of overflow come from sub-pixel rounding and scrollbar gutters
 * rather than layout, and asserting equality makes the test fail for reasons
 * nobody can act on. Anything beyond a small tolerance is real: it means an
 * element genuinely does not fit.
 */
class MobileViewportTest extends DuskTestCase
{
    /*
     * DELIBERATELY NOT `DatabaseMigrations`.
     *
     * That trait runs `migrate:fresh` — it DROPS EVERY TABLE on the connected
     * database. Dusk drives a real HTTP server, so the connection is the one the
     * served application uses, and the first run of this file destroyed a
     * development database exactly that way.
     *
     * This suite only READS pages to measure their layout. It needs a migrated
     * database with a user it can log in as; it does not need a pristine one per
     * test. Removing the trait removes the destructive step altogether, which is
     * a better answer than making the destruction safer.
     *
     * `DuskTestCase` still refuses to run against anything but the disposable
     * database, so both belts are on.
     *
     * A side effect worth recording: while the trait was in place its rollback
     * surfaced a run of migrations whose `down()` had never been exercised on
     * MariaDB and could not complete there. One is fixed (see
     * `extend_activity_logs_for_audit_trail`); the rest are IMP-134.
     */

    private const VIEWPORT_WIDTH  = 390;
    private const VIEWPORT_HEIGHT = 844;

    /**
     * Sub-pixel rounding, not layout. Beyond this something really is too wide.
     */
    private const TOLERANCE_PX = 4;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed once into the disposable database; harmless to repeat because
        // every seeder in this application is idempotent.
        $this->artisan('db:seed', ['--class' => \Database\Seeders\DatabaseSeeder::class, '--force' => true]);
    }

    protected function admin(): User
    {
        $organization = Organization::first()
            ?? Organization::create(['slug' => 'viewport-farm', 'name' => 'Viewport Farm']);

        $this->app->make(CurrentOrganization::class)->bind($organization);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching(Permission::pluck('id')->all());

        // Idempotent: this suite does not reset the database between runs (see
        // the class comment), so a fixture that always inserts would fail on the
        // second run for a reason that has nothing to do with layout.
        $user = User::withoutGlobalScopes()->firstOrNew(['email' => 'viewport-admin@example.com']);

        $user->forceFill([
            'name'              => 'Viewport Admin',
            'password'          => bcrypt('secret'),
            'role'              => 'admin',
            'role_id'           => $role->id,
            'organization_id'   => $organization->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * The screens a farm actually opens on a phone.
     *
     * Deliberately mixes screens this programme built (finance overview,
     * expenses, storefront orders) with older ones it did not (dashboard,
     * pigs, sales), because §55's open question was specifically about the
     * older ledger and operations screens.
     *
     * @return array<string, string>
     */
    public static function screens(): array
    {
        return [
            'dashboard'          => '/dashboard',
            'finance overview'   => '/finance/overview',
            'finance expenses'   => '/finance/expenses',
            'money in'           => '/finance/money-in',
            'contacts'           => '/finance/contacts',
            'storefront listings'=> '/storefront-admin/listings',
            'storefront orders'  => '/storefront-admin/orders',
            'public shop'        => '/shop/browse',
            'all stock'          => '/all-stocks',
            'sales'              => '/sales',
        ];
    }

    public function test_key_screens_do_not_scroll_sideways_on_a_phone(): void
    {
        $admin    = $this->admin();
        $offenders = [];

        $this->browse(function (Browser $browser) use ($admin, &$offenders) {
            $browser->resize(self::VIEWPORT_WIDTH, self::VIEWPORT_HEIGHT)
                ->loginAs($admin);

            foreach (self::screens() as $label => $path) {
                $browser->visit($path)->pause(400);

                // Only elements that ACTUALLY push the page wide are reported.
                //
                // A first version of this simply named the widest box, which was
                // misleading: a table inside an `overflow-x-auto` container is
                // legitimately wider than the screen and scrolls within its own
                // box without moving the page. Reporting it sent the
                // investigation at a table that was already correct.
                //
                // So the walk skips any element with a scrollable ancestor, and
                // what remains is the set of elements genuinely responsible for
                // the page scrolling sideways.
                $metrics = $browser->script([
                    'return (function () {
                        var docWidth = document.documentElement.clientWidth;
                        var culprits = [];
                        var scrollable = [];

                        function hasScrollableAncestor(el) {
                            for (var p = el.parentElement; p && p !== document.body; p = p.parentElement) {
                                var ox = window.getComputedStyle(p).overflowX;
                                if (ox === "auto" || ox === "scroll" || ox === "hidden") {
                                    return true;
                                }
                            }
                            return false;
                        }

                        document.querySelectorAll("body *").forEach(function (el) {
                            var extra = Math.round(el.getBoundingClientRect().right - docWidth);
                            if (extra > 4) {
                                var label = (el.tagName || "?").toLowerCase()
                                    + (typeof el.className === "string" && el.className
                                        ? "." + el.className.trim().split(/\\s+/).slice(0, 3).join(".")
                                        : "")
                                    + " (+" + extra + "px)";

                                if (hasScrollableAncestor(el)) {
                                    scrollable.push(label);
                                } else {
                                    culprits.push(label);
                                }
                            }
                        });

                        return {
                            scrollWidth: document.documentElement.scrollWidth,
                            clientWidth: docWidth,
                            culprits: culprits.slice(0, 4),
                            scrollable: scrollable.slice(0, 4)
                        };
                    })();'
                ])[0];

                $overflow = $metrics['scrollWidth'] - $metrics['clientWidth'];

                if ($overflow > self::TOLERANCE_PX) {
                    $offenders[] = sprintf(
                        '%s (%s) overflows by %dpx — caused by: %s',
                        $label,
                        $path,
                        $overflow,
                        $metrics['culprits']
                            ? implode(', ', $metrics['culprits'])
                            : 'scroll-contained: ' . implode(', ', $metrics['scrollable'] ?? []),
                    );
                }
            }
        });

        $this->assertSame(
            [],
            $offenders,
            "These screens scroll sideways at {$this->viewportLabel()}, which puts controls "
            . "off-screen on a phone:\n  " . implode("\n  ", $offenders)
        );
    }

    protected function viewportLabel(): string
    {
        return self::VIEWPORT_WIDTH . '×' . self::VIEWPORT_HEIGHT;
    }
}
