<?php

namespace Tests\Feature\Livestock;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the pig promote/demote lifecycle routes.
 *
 * These routes had NO test coverage, which is why two defects survived in
 * PigManagementController — a facade that delegates to PigLifecycleController:
 *
 *  1. promoteToBreeding() referenced `PIgLifecycleController::class` (capital
 *     "I"). PHP resolves class names case-insensitively only once a class is
 *     already loaded; Composer's autoloader does a case-sensitive lookup. On a
 *     request where promote is the first thing to touch that class — which is
 *     the normal case — the autoloader looks for PIgLifecycleController.php,
 *     fails, and the request errors. demoteToStandard() spelled it correctly,
 *     so the two routes behaved differently.
 *
 *  2. Both facade methods called the delegate as ->promote($id) / ->demote($id),
 *     but the delegate signatures are promote(Request, PromotePigService, $id).
 *     Controller method injection only applies when the router invokes the
 *     method; calling it manually passes $id into $request and omits the
 *     service entirely.
 *
 * These tests assert the routes complete without a server error. They
 * deliberately do not assert the business outcome of promotion — eligibility
 * rules belong to PromotePigService and its own tests. What is asserted here is
 * that the endpoint is reachable and wired correctly, which is what was broken.
 */
class PigLifecycleRouteTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'cf',
            'name' => 'Chipper Farms',
            'is_active' => true,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $role = Role::firstOrCreate(['name' => 'admin']);

        $this->manager = User::forceCreate([
            'name'            => 'Admin',
            'email'           => 'admin_' . uniqid() . '@example.com',
            'password'        => bcrypt('x'),
            'role'            => 'admin',
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
            'active_status'   => true,
        ]);
    }

    protected function makePig(string $stockType, string $gender = 'F'): PigManagement
    {
        return PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number'  => 'TAG-' . uniqid(),
            'code'            => 'PIG-' . uniqid(),
            'breed'           => 'Large White',
            'gender'          => $gender,
            'stock_type'      => $stockType,
            'date_of_birth'   => now()->subMonths(10)->toDateString(),
        ]);
    }

    public function test_promote_to_breeding_route_does_not_error(): void
    {
        $pig = $this->makePig('standard');

        $response = $this->actingAs($this->manager)
            ->post(route('pigs.promote-to-breeding', $pig->id));

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            'pigs.promote-to-breeding raised a server error. This route referenced a '
            . 'mis-cased controller class and called the delegate with the wrong arity.',
        );
    }

    public function test_demote_to_standard_route_does_not_error(): void
    {
        $pig = $this->makePig('breeding');

        $response = $this->actingAs($this->manager)
            ->post(route('pigs.demote-to-standard', $pig->id));

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            'pigs.demote-to-standard raised a server error — the facade calls the '
            . 'delegate with the wrong argument list.',
        );
    }

    /**
     * Structural guard for the whole class of defect.
     *
     * PigManagementController is a facade: ~60 methods that each do
     * `return app(SomeController::class)->method(...)`. Because the delegate is
     * invoked manually, Laravel's controller method injection does NOT apply —
     * every required argument must be passed explicitly. Six delegations were
     * passing too few, so six production routes threw TypeError on every call,
     * and no test caught it.
     *
     * Rather than one test per route, this reflects over every delegation in the
     * facade and asserts it is call-compatible with its target. Any future
     * delegation that forgets a service argument fails here immediately.
     */
    public function test_every_facade_delegation_is_call_compatible(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/PigManagementController.php'));

        $matched = preg_match_all(
            '/public function (\w+)\([^)]*\)\s*\{\s*return app\((\w+)::class\)->(\w+)\((.*?)\);/s',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        $this->assertGreaterThan(
            30,
            $matched,
            'Expected to find the facade delegations; the parse likely broke rather than the code.',
        );

        $problems = [];

        foreach ($matches as [$_, $facadeMethod, $delegateShort, $delegateMethod, $passedArgs]) {
            $delegateClass = 'App\\Http\\Controllers\\' . $delegateShort;

            // A mis-cased reference resolves case-insensitively only once the
            // class is loaded, so assert on the exact declared name too.
            if (! class_exists($delegateClass)) {
                $problems[] = "{$facadeMethod}: delegate class {$delegateShort} does not exist";
                continue;
            }

            $declared = (new \ReflectionClass($delegateClass))->getName();
            if ($declared !== $delegateClass) {
                $problems[] = "{$facadeMethod}: referenced {$delegateShort} but the class is actually "
                    . substr($declared, strrpos($declared, '\\') + 1);
            }

            if (! method_exists($delegateClass, $delegateMethod)) {
                $problems[] = "{$facadeMethod}: {$delegateShort}::{$delegateMethod}() does not exist";
                continue;
            }

            $required = (new \ReflectionMethod($delegateClass, $delegateMethod))
                ->getNumberOfRequiredParameters();

            $trimmed = trim($passedArgs);
            $passed  = $trimmed === ''
                ? 0
                : count(array_filter(array_map('trim', explode(',', $trimmed)), fn ($a) => $a !== ''));

            if ($passed < $required) {
                $problems[] = "{$facadeMethod}: passes {$passed} arg(s) to "
                    . "{$delegateShort}::{$delegateMethod}() which requires {$required}";
            }
        }

        $this->assertSame(
            [],
            $problems,
            "Facade delegations are not call-compatible:\n - " . implode("\n - ", $problems),
        );
    }

    /**
     * The policy must still be enforced through the facade. A role with no
     * livestock entitlement must not be able to promote stock.
     */
    public function test_promote_is_denied_for_unentitled_role(): void
    {
        $pig  = $this->makePig('standard');
        $role = Role::firstOrCreate(['name' => 'customer']);

        $outsider = User::forceCreate([
            'name'            => 'Customer',
            'email'           => 'cust_' . uniqid() . '@example.com',
            'password'        => bcrypt('x'),
            'role'            => 'customer',
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
            'active_status'   => true,
        ]);

        $this->actingAs($outsider)
            ->post(route('pigs.promote-to-breeding', $pig->id))
            ->assertForbidden();
    }
}
