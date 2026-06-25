<?php

namespace Tests\Feature\Finance\FeedSales;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FeedSalesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class FeedSaleControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        foreach (['admin', 'accountant', 'manager', 'farmmanager', 'superuser', 'dataentry'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $this->seed(FeedSalesPermissionsSeeder::class);
    }

    public function test_farm_manager_can_see_record_sale_button(): void
    {
        $manager = $this->makeUser('farmmanager');

        $this->actingAs($manager)
            ->get(route('finance.feed-sales.index'))
            ->assertOk()
            ->assertSee(route('finance.feed-sales.create'), false)
            ->assertSee('Record sale');
    }

    public function test_view_only_user_does_not_see_record_sale_button(): void
    {
        $viewer = $this->makeUser('dataentry');
        $viewPermission = Permission::where('name', 'view_feed_sales')->firstOrFail();
        $viewer->role()->first()?->permissions()->syncWithoutDetaching([$viewPermission->id]);

        $this->actingAs($viewer)
            ->get(route('finance.feed-sales.index'))
            ->assertOk()
            ->assertDontSee(route('finance.feed-sales.create'), false);
    }

    protected function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName),
            'email' => $roleName . '_' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }
}
