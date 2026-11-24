<?php

namespace Tests\Feature\Console;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\User;
use App\Notifications\WeightOverdueNotification;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * weights:notify loaded its recipients with role.permissions. Users also have
 * a `role` string column, so when the queued notification restored the user,
 * loadMissing('role.permissions') resolved `role` to that string and crashed.
 * The existing notification tests notify a user without those relations
 * loaded, so only running the real command shows it.
 */
class WeighingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_overdue_pig_notifies_once_per_day(): void
    {
        $admin = User::factory()->create();
        $this->app->make(CurrentOrganization::class)->bind($admin->organization);

        $pig = PigManagement::create([
            'organization_id' => $admin->organization_id,
            'ear_tag_number' => 'W-001',
            'breed' => 'LW',
            'gender' => 'F',
            'date_of_birth' => now()->subMonths(6)->toDateString(),
            'code' => 'W-001',
            'stock_type' => 'breeding',
        ]);
        PigWeight::create([
            'pig_id' => $pig->id,
            'weight' => 80,
            'weigh_date' => now()->subDays(45)->toDateString(),
        ]);

        $this->artisan('weights:notify')->assertSuccessful();
        $this->artisan('weights:notify')->assertSuccessful();

        $this->assertSame(1, $admin->notifications()->where('type', WeightOverdueNotification::class)->count());
    }

    /**
     * The incident: a user queued with role.permissions loaded could not be
     * restored ("relationLoaded() on string"), so the email job failed on
     * every retry and filled failed_jobs. Queued users now carry no loaded
     * relations, so restoring them cannot fail, however they were loaded.
     */
    public function test_a_user_queued_with_its_role_loaded_restores_cleanly(): void
    {
        $user = User::factory()->create();
        $this->app->make(CurrentOrganization::class)->bind($user->organization);
        $pig = PigManagement::create(['organization_id' => $user->organization_id, 'ear_tag_number' => 'Q-1', 'breed' => 'LW',
            'gender' => 'F', 'date_of_birth' => now()->subMonths(6)->toDateString(), 'code' => 'Q-1', 'stock_type' => 'standard']);

        $user->load('role.permissions', 'roleRecord.permissions');
        $pig->load('weights');
        $job = new \Illuminate\Notifications\SendQueuedNotifications(new \Illuminate\Database\Eloquent\Collection([$user]), new WeightOverdueNotification($pig, 'today'), ['mail']);

        $restored = unserialize(serialize($job));

        $this->assertTrue($restored->notifiables->first()->is($user));
        $this->assertArrayNotHasKey('role', $restored->notifiables->first()->getRelations());
        $this->assertArrayNotHasKey('roleRecord', $restored->notifiables->first()->getRelations());
        $this->assertSame([], $job->notifiables->first()->getQueueableRelations(), 'Queued users carry no relations.');
        $this->assertSame([], $restored->notification->pig->getRelations());
        // A failing notification stops after three tries, with backoff.
        $this->assertSame(3, $restored->tries);
    }

    public function test_a_users_permissions_are_read_once(): void
    {
        $user = User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(['name' => 'tester']);
        $role->permissions()->attach(\App\Models\Permission::firstOrCreate(['name' => 'view_weights'], ['module' => 'stock'])->id);
        $user->forceFill(['role' => 'tester', 'role_id' => $role->id])->save();

        $loaded = User::find($user->id);
        \DB::enableQueryLog();
        $this->assertTrue($loaded->hasPermission('view_weights'));
        $this->assertFalse($loaded->hasPermission('manage_finance'));
        $this->assertTrue($loaded->hasPermission('view_weights'));
        $this->assertLessThanOrEqual(3, count(\DB::getQueryLog()), 'Permissions should be read once, not per check.');
    }
}
