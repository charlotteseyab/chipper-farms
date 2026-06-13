<?php

namespace Tests\Feature\Storefront;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StorefrontPermissionsTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_storefront_permissions_and_roles(): void
    {
        $this->seedStorefrontPermissions();

        foreach ([
            'view_storefront_dashboard',
            'view_storefront_listings',
            'manage_storefront_listings',
            'push_to_storefront',
            'manage_storefront_placements',
            'manage_storefront_settings',
            'view_storefront_reports',
        ] as $perm) {
            $this->assertNotNull(Permission::where('name', $perm)->first(), "Missing permission: {$perm}");
        }

        $this->assertNotNull(Role::where('name', 'salesperson')->first());
        $this->assertNotNull(Role::where('name', 'customer')->first());
    }

    public function test_salesperson_can_manage_listings_but_not_sensitive_finance(): void
    {
        $this->seedStorefrontPermissions();
        $sales = $this->makeUser('salesperson');

        $this->assertTrue($sales->hasPermission('manage_storefront_listings'));
        $this->assertTrue($sales->hasPermission('push_to_storefront'));
        $this->assertTrue($sales->hasPermission('view_storefront_dashboard'));

        // Salesperson must NOT inherit finance settings or refund approval.
        $this->assertFalse($sales->hasPermission('manage_storefront_settings'));
        $this->assertFalse($sales->hasPermission('approve_refund'));
        $this->assertFalse($sales->hasPermission('view_finance'));
    }

    public function test_customer_role_has_no_storefront_back_office_permissions(): void
    {
        $this->seedStorefrontPermissions();
        $customer = $this->makeUser('customer');

        $this->assertFalse($customer->hasPermission('view_storefront_dashboard'));
        $this->assertFalse($customer->hasPermission('manage_storefront_listings'));
        $this->assertFalse($customer->hasPermission('push_to_storefront'));
    }

    public function test_admin_inherits_storefront_management(): void
    {
        $this->seedStorefrontPermissions();
        $admin = $this->makeUser('admin');

        $this->assertTrue($admin->hasPermission('manage_storefront_listings'));
        $this->assertTrue($admin->hasPermission('manage_storefront_settings'));
    }
}
