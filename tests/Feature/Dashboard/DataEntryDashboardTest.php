<?php

namespace Tests\Feature\Dashboard;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The data entry dashboard leads with today's work. */
class DataEntryDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_todays_jobs_quick_entry_and_missing_details(): void
    {
        $farm = Organization::create(['name' => 'Farm', 'slug' => 'farm']);
        $this->app->make(CurrentOrganization::class)->bind($farm);
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $role = Role::firstOrCreate(['name' => 'dataentry']);
        $role->permissions()->syncWithoutDetaching(\App\Models\Permission::pluck('id')->all());
        $clerk = User::forceCreate(['name' => 'Ama Mensah', 'first_name' => 'Ama', 'last_name' => 'Mensah', 'email' => 'ama@farm.test',
            'password' => bcrypt('x'), 'role' => 'dataentry', 'role_id' => $role->id, 'organization_id' => $farm->id,
            'active_status' => true, 'email_verified_at' => now()]);

        // A pig with no room and no weight: two missing details.
        PigManagement::create(['organization_id' => $farm->id, 'ear_tag_number' => '7', 'code' => 'CF-7', 'breed' => 'LW',
            'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(3)->toDateString()]);

        $this->actingAs($clerk)->get(route('dataentry.dashboard'))->assertOk()
            ->assertSee('Data Entry Dashboard')->assertSee(', Ama')
            ->assertSeeInOrder(['Entries today', 'Weights recorded', 'Pending weighing', 'Missing details', 'Purchased intake'])
            ->assertSeeInOrder(["Today's work", 'done', 'Health tasks', 'Weigh pigs'])
            ->assertSeeInOrder(['Quick entry', 'Record weight'])
            ->assertSeeInOrder(['Fix missing details', 'No first weight', 'No room'])
            ->assertSeeInOrder(['Latest entries', 'CF-7', 'New pig'])
            // The manager panels are not on this page.
            ->assertDontSee('Herd health')->assertDontSee('30-day mortality rate');
    }
}
