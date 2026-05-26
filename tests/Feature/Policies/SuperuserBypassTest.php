<?php

namespace Tests\Feature\Policies;

use App\Models\Farrowing;
use App\Models\Mating;
use App\Models\Organization;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Weaning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Verifies the Gate::before superuser bypass registered in AppServiceProvider.
 *
 * A superuser must pass every policy check for every resource, regardless of
 * what the per-policy rules say. This is the contract the rest of the
 * codebase is allowed to rely on.
 */
class SuperuserBypassTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'chipper-farms',
            'name' => 'Chipper Farms',
        ]);
    }

    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName),
            'email' => $roleName . '@example.com',
            'password' => bcrypt('x'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_superuser_can_view_any_pig_sale(): void
    {
        $superuser = $this->makeUser('superuser');
        $this->actingAs($superuser);

        $this->assertTrue(Gate::allows('viewAny', PigSale::class));
    }

    public function test_superuser_can_delete_a_pig_sale(): void
    {
        $superuser = $this->makeUser('superuser');
        $this->actingAs($superuser);

        $sale = new PigSale();  // An unpersisted model is sufficient for gate evaluation.
        $this->assertTrue(Gate::allows('delete', $sale));
    }

    public function test_superuser_can_approve_a_purchase_request(): void
    {
        $superuser = $this->makeUser('superuser');
        $this->actingAs($superuser);

        $pr = new PurchaseRequest(['status' => 'pending']);
        $this->assertTrue(Gate::allows('approve', $pr));
    }

    public function test_superuser_can_delete_an_organization(): void
    {
        // OrganizationPolicy::delete() returns false for everyone.
        // The bypass must still let the superuser through.
        $superuser = $this->makeUser('superuser');
        $this->actingAs($superuser);

        $this->assertTrue(Gate::allows('delete', $this->org));
    }

    public function test_superuser_bypasses_every_registered_policy(): void
    {
        $superuser = $this->makeUser('superuser');
        $this->actingAs($superuser);

        $cases = [
            ['viewAny', PigManagement::class],
            ['viewAny', PigSale::class],
            ['viewAny', PigDeath::class],
            ['viewAny', Farrowing::class],
            ['viewAny', Mating::class],
            ['viewAny', Weaning::class],
            ['viewAny', PurchaseRequest::class],
        ];

        foreach ($cases as [$ability, $class]) {
            $this->assertTrue(
                Gate::allows($ability, $class),
                "Superuser should bypass {$ability} on {$class}"
            );
        }
    }

    public function test_non_superuser_does_not_receive_bypass(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        // Admin can create sales per policy — so this is allowed, but via the
        // policy rule, not the bypass. Delete is admin-allowed too, so we
        // pick an ability that admin policy denies: nothing in the current
        // policy set. Instead we verify via an ability the policy explicitly
        // denies for admin — PurchaseRequest::approve is admin-allowed, but
        // if the request is not pending, it's denied for EVERYONE below
        // superuser level.
        $nonPendingPr = new PurchaseRequest(['status' => 'approved']);

        $this->assertFalse(
            Gate::allows('approve', $nonPendingPr),
            'Admin should be denied approving a non-pending request; bypass must not leak.'
        );
    }

    public function test_non_superuser_without_permission_is_denied(): void
    {
        $dataEntry = $this->makeUser('dataentry');
        $this->actingAs($dataEntry);

        // Data entry cannot create PigSale per policy.
        $this->assertFalse(Gate::allows('create', PigSale::class));
    }

    public function test_unauthenticated_user_does_not_trigger_bypass(): void
    {
        // No actingAs. Gate::before receives null user.
        $this->assertFalse(Gate::allows('viewAny', PigSale::class));
    }
}