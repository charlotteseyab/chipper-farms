<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Direct-request authorisation tests for farm operations (CF-P0-002).
 *
 * Before remediation, 65 routes across seven controllers — PigHealthController,
 * FarrowingController, FeedProductionController, StructureController,
 * RoomController, WeightManagementController and WeightAlertsController — sat
 * behind nothing but the broad authenticated group. Those controllers contained
 * zero authorization of any kind: no policy call, no permission check, no role
 * guard, not even an abort(). Any verified, active user with an organisation
 * could create, amend and delete health and medication records, mix feed
 * batches, adjust raw-material stock and add or remove facilities by direct
 * request.
 *
 * No permission existed to gate them with either: the catalogue covered
 * finance, sales and storefront only. FarmOperationsPermissionsSeeder adds the
 * livestock/facilities/feed catalogue those gates now use.
 *
 * The `customer` role is the sharpest test of the fix. Storefront customers are
 * real authenticated users of this system, and before remediation a customer
 * could delete a pig's clinical history.
 */
class FarmOperationsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF', 'is_active' => true]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->seed(FarmOperationsPermissionsSeeder::class);
    }

    protected function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name'            => ucfirst($roleName),
            'email'           => $roleName . '_' . uniqid() . '@example.com',
            'password'        => bcrypt('x'),
            'role'            => $roleName,
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
            'active_status'   => true,
        ]);
    }

    /**
     * Representative write endpoints across all seven previously-open
     * controllers.
     *
     * @return array<string, array{0:string, 1:string, 2:array<string,mixed>}>
     */
    public static function farmWriteProvider(): array
    {
        return [
            'record health'        => ['post', 'health.store', []],
            'record medication'    => ['post', 'medications.store', []],
            'mix feed'             => ['post', 'feed-production.mix', []],
            'create feed type'     => ['post', 'feed-production.feed-types.store', []],
            'create feed recipe'   => ['post', 'feed-production.recipes.store', []],
            'create raw material'  => ['post', 'raw-materials.store', []],
            'create structure'     => ['post', 'structures.store', []],
            'create room'          => ['post', 'rooms.store', []],
            'record weight'        => ['post', 'weights.store', []],
        ];
    }

    /**
     * A storefront customer must not be able to write farm records.
     */
    #[DataProvider('farmWriteProvider')]
    public function test_customer_cannot_perform_farm_writes(string $verb, string $routeName, array $payload): void
    {
        if (! \Illuminate\Support\Facades\Route::has($routeName)) {
            $this->fail("Route {$routeName} is not registered; the gate cannot be asserted.");
        }

        $this->actingAs($this->userWithRole('customer'))
            ->{$verb}(route($routeName), $payload)
            ->assertForbidden();
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function farmReadProvider(): array
    {
        return [
            'health index'       => ['health.index'],
            'medications index'  => ['medications.index'],
            'health reports'     => ['health.reports'],
            'health csv export'  => ['health.export'],
            'weights index'      => ['weights.index'],
            'weights reports'    => ['weights.reports'],
            'farrowing index'    => ['farrowing.index'],
            'structures index'   => ['structures.index'],
            'feed production'    => ['feed-production.index'],
            'raw materials'      => ['raw-materials.index'],
        ];
    }

    #[DataProvider('farmReadProvider')]
    public function test_customer_cannot_read_farm_records(string $routeName): void
    {
        if (! \Illuminate\Support\Facades\Route::has($routeName)) {
            $this->fail("Route {$routeName} is not registered; the gate cannot be asserted.");
        }

        $this->actingAs($this->userWithRole('customer'))
            ->get(route($routeName))
            ->assertForbidden();
    }

    /**
     * Destructive clinical operations are separated from ordinary recording.
     * dataentry may record health data but must not erase it — deleting a
     * health record destroys the history behind a medication withdrawal period.
     *
     * These two use real records on purpose. Route model binding runs before
     * the permission middleware, so a non-existent id returns 404 and the gate
     * is never reached — asserting 403 against id 1 would pass for the wrong
     * reason, or fail while the gate was actually fine.
     */
    public function test_dataentry_cannot_delete_health_records(): void
    {
        $pig = $this->makePig();

        $this->actingAs($this->userWithRole('dataentry'))
            ->delete(route('health.destroy', $pig->id))
            ->assertForbidden();
    }

    public function test_dataentry_cannot_amend_medication_records(): void
    {
        $pig = $this->makePig();

        $medication = \App\Models\MedicationAdministration::create([
            'pig_id'              => $pig->id,
            'administration_date' => now()->toDateString(),
            'medication_name'     => 'Test antibiotic',
            'dosage'              => '5ml',
            'purpose'             => 'test fixture',
        ]);

        $this->actingAs($this->userWithRole('dataentry'))
            ->put(route('medications.update', $medication->id))
            ->assertForbidden();
    }

    protected function makePig(): \App\Models\PigManagement
    {
        return \App\Models\PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number'  => 'TAG-' . uniqid(),
            'code'            => 'PIG-' . uniqid(),
            'breed'           => 'Large White',
            'gender'          => 'F',
            'stock_type'      => 'standard',
            'date_of_birth'   => now()->subMonths(8)->toDateString(),
        ]);
    }

    public function test_dataentry_cannot_manage_facilities(): void
    {
        $this->actingAs($this->userWithRole('dataentry'))
            ->post(route('structures.store'), [])
            ->assertForbidden();
    }

    /**
     * The gates must not lock out the roles that do the work. dataentry holds
     * the recording permissions, so it must reach these endpoints — a 403 here
     * would mean the fix broke normal operations.
     */
    public function test_dataentry_can_reach_health_recording(): void
    {
        $response = $this->actingAs($this->userWithRole('dataentry'))
            ->get(route('health.index'));

        $response->assertOk();
    }

    public function test_dataentry_can_reach_weight_recording(): void
    {
        $response = $this->actingAs($this->userWithRole('dataentry'))
            ->get(route('weights.create'));

        $response->assertOk();
    }

    /**
     * mill_operator is scoped to feed and raw materials only — it must reach
     * those and nothing else.
     */
    public function test_mill_operator_reaches_feed_but_not_health(): void
    {
        $operator = $this->userWithRole('mill_operator');

        $feed = $this->actingAs($operator)->get(route('feed-production.index'));
        $feed->assertOk();

        $this->actingAs($operator)
            ->get(route('health.index'))
            ->assertForbidden();
    }

    public function test_farm_manager_reaches_facilities_management(): void
    {
        $response = $this->actingAs($this->userWithRole('farmmanager'))
            ->get(route('structures.create'));

        $response->assertOk();
    }
}
