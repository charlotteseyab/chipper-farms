<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Storefront\StorefrontBuyer;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InAppNotificationRelevanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_delivery_is_limited_to_the_same_organization_and_relevant_access(): void
    {
        $org = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $otherOrg = Organization::create(['name' => 'Farm B', 'slug' => 'farm-b']);
        $this->app->make(CurrentOrganization::class)->bind($org);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $irrelevantRole = Role::firstOrCreate(['name' => 'dataentry']);

        $relevant = $this->user($org, $adminRole, 'relevant@example.com', 'admin');
        $irrelevant = $this->user($org, $irrelevantRole, 'irrelevant@example.com', 'dataentry');
        $otherTenant = $this->user($otherOrg, $adminRole, 'other@example.com', 'admin');

        app(InAppNotificationService::class)->notifyRelevantStaff(
            $org->id,
            'finance.period_close_failed',
            'Period close blocked',
            'July cannot be closed.',
        );

        $this->assertCount(1, $relevant->notifications);
        $this->assertCount(0, $irrelevant->notifications);
        $this->assertCount(0, $otherTenant->notifications);
    }

    public function test_a_buyer_cannot_read_another_buyers_notification(): void
    {
        $org = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $this->app->make(CurrentOrganization::class)->bind($org);

        $buyerA = StorefrontBuyer::create(['organization_id' => $org->id, 'name' => 'Ama', 'email' => 'ama@example.com', 'password' => bcrypt('secret')]);
        $buyerB = StorefrontBuyer::create(['organization_id' => $org->id, 'name' => 'Kojo', 'email' => 'kojo@example.com', 'password' => bcrypt('secret')]);

        app(InAppNotificationService::class)->notifyBuyer(
            $buyerA,
            'storefront.order_confirmed',
            'Order confirmed',
            'Your order is confirmed.',
        );
        $notification = $buyerA->notifications()->firstOrFail();

        $this->withSession(['storefront_buyer_id' => $buyerB->id])
            ->post(route('storefront.buyer.notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_buyer_notifications_reject_staff_only_events(): void
    {
        $org = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $buyer = StorefrontBuyer::create(['organization_id' => $org->id, 'name' => 'Ama', 'email' => 'ama@example.com', 'password' => bcrypt('secret')]);

        app(InAppNotificationService::class)->notifyBuyer($buyer, 'finance.period_close_failed', 'Close failed', 'Internal finance issue.');

        $this->assertCount(0, $buyer->notifications);
    }

    public function test_stock_added_is_a_supported_staff_notification(): void
    {
        $org = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = $this->user($org, $adminRole, 'stock-admin@example.com', 'admin');

        app(InAppNotificationService::class)->notifyRelevantStaff(
            $org->id,
            'pigs.stock_added',
            'New stock added',
            'CFPG-2608-1 was registered as breeding stock.',
        );

        $this->assertCount(1, $admin->notifications);
        $this->assertSame('pigs.stock_added', $admin->notifications->first()->data['type']);
    }

    public function test_a_successful_authenticated_write_notifies_the_actor(): void
    {
        Route::middleware(['web', 'action.notification'])->post('/test/action-notification', function () {
            return redirect('/dashboard')->with('success', 'Weight record added successfully!');
        })->name('weights.store-test');

        $org = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $role = Role::firstOrCreate(['name' => 'dataentry']);
        $user = $this->user($org, $role, 'weights@example.com', 'dataentry');

        $this->actingAs($user)->post('/test/action-notification')->assertRedirect('/dashboard');

        $notification = $user->notifications()->firstOrFail();
        $this->assertSame('system.weights_store_test', $notification->data['type']);
        $this->assertStringContainsString('You store test in Weight management', $notification->data['message']);
        $this->assertStringContainsString('Outcome: Weight record added successfully!', $notification->data['message']);
        $this->assertSame('Weight management', $notification->data['module']);
        $this->assertSame('weights@example.com', $notification->data['context']['actor_name']);
    }

    public function test_reading_notifications_does_not_create_another_notification(): void
    {
        $org = Organization::create(['name' => 'Farm A', 'slug' => 'farm-a']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = $this->user($org, $role, 'reader@example.com', 'admin');

        app(InAppNotificationService::class)->notifyRelevantStaff(
            $org->id,
            'pigs.stock_added',
            'New stock added',
            'A pig was added.',
        );
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertCount(1, $user->fresh()->notifications);
    }

    protected function user(Organization $organization, Role $role, string $email, string $roleName): User
    {
        return User::forceCreate([
            'organization_id' => $organization->id,
            'role_id' => $role->id,
            'role' => $roleName,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('secret'),
            'active_status' => true,
            'email_verified_at' => now(),
        ]);
    }
}
