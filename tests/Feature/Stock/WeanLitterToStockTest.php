<?php

namespace Tests\Feature\Stock;

use App\Models\Farrowing;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\Room;
use App\Models\Structure;
use App\Models\User;
use App\Models\Weaning;
use App\Services\Stock\StockValidationException;
use App\Services\Stock\WeanLitterToStockService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeanLitterToStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_litter_weaning_creates_individual_main_stock_and_weaning_records(): void
    {
        $organization = Organization::create(['name' => 'Weaning Farm', 'slug' => 'weaning-farm']);
        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        $manager = User::forceCreate([
            'name' => 'Farm Manager', 'email' => 'weaning-manager@example.com', 'password' => bcrypt('secret'),
            'role' => 'farmmanager', 'role_id' => $role->id, 'organization_id' => $organization->id,
        ]);
        $this->actingAs($manager);
        $this->app->make(CurrentOrganization::class)->bind($organization);

        $structure = Structure::create(['name' => 'Nursery', 'code' => 'NURSERY-TEST', 'created_by' => $manager->id]);
        $room = Room::create(['structure_id' => $structure->id, 'name' => 'Weaner Room', 'code' => 'WR-TEST', 'capacity' => 10]);
        $secondRoom = Room::create(['structure_id' => $structure->id, 'name' => 'Weaner Room 2', 'code' => 'WR2-TEST', 'capacity' => 1]);
        $sow = PigManagement::create([
            'organization_id' => $organization->id, 'ear_tag_number' => 'SOW-001', 'breed' => 'LW',
            'gender' => 'F', 'stock_type' => 'breeding', 'date_of_birth' => now()->subYears(2)->toDateString(),
        ]);
        $farrowing = Farrowing::create([
            'sow_id' => $sow->id, 'farrowing_date' => now()->subDays(50)->toDateString(),
            'live_births' => 2, 'total_piglets_born' => 2, 'number_of_males' => 1, 'number_of_females' => 1,
            'recorded_by' => $manager->id, 'status' => 'active',
        ]);

        $result = app(WeanLitterToStockService::class)->execute($farrowing, [
            'weaning_date' => now()->toDateString(),
            'weaning_method' => 'natural', 'weaning_reason' => 'age_appropriate',
            'post_weaning_status' => 'healthy', 'notes' => 'Both piglets healthy.',
            'animals' => [
                ['ear_tag_number' => 'PIGLET-101', 'gender' => 'M', 'breed' => 'LW', 'room_id' => $room->id, 'weaning_weight' => 12.4],
                ['ear_tag_number' => 'PIGLET-102', 'gender' => 'F', 'breed' => 'LW', 'room_id' => $secondRoom->id, 'weaning_weight' => null],
            ],
        ]);

        $this->assertSame('completed', $result->status);
        $this->assertSame(2, PigManagement::where('mother_id', $sow->id)->count());
        $this->assertSame(2, Weaning::where('farrowing_id', $farrowing->id)->count());
        $this->assertDatabaseHas('pigs_management', [
            'organization_id' => $organization->id, 'ear_tag_number' => 'PIGLET-101',
            'stock_type' => 'standard', 'room_id' => $room->id, 'mother_id' => $sow->id,
        ]);
        $this->assertDatabaseHas('pig_weights', ['weight' => 12.4]);
        $this->assertDatabaseHas('pigs_management', ['ear_tag_number' => 'PIGLET-102', 'room_id' => $secondRoom->id]);
    }

    public function test_completed_litter_cannot_be_added_to_stock_twice(): void
    {
        $this->expectException(StockValidationException::class);

        $organization = Organization::create(['name' => 'Closed Farm', 'slug' => 'closed-farm']);
        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        $manager = User::forceCreate([
            'name' => 'Manager', 'email' => 'closed-manager@example.com', 'password' => bcrypt('secret'),
            'role' => 'farmmanager', 'role_id' => $role->id, 'organization_id' => $organization->id,
        ]);
        $this->actingAs($manager);
        $this->app->make(CurrentOrganization::class)->bind($organization);
        $sow = PigManagement::create([
            'organization_id' => $organization->id, 'ear_tag_number' => 'SOW-CLOSED', 'breed' => 'LW',
            'gender' => 'F', 'stock_type' => 'breeding', 'date_of_birth' => now()->subYears(2)->toDateString(),
        ]);
        $farrowing = Farrowing::create([
            'sow_id' => $sow->id, 'farrowing_date' => now()->subDays(50)->toDateString(),
            'live_births' => 1, 'total_piglets_born' => 1, 'recorded_by' => $manager->id, 'status' => 'completed',
        ]);

        app(WeanLitterToStockService::class)->execute($farrowing, [
            'weaning_date' => now()->toDateString(),
            'weaning_method' => 'natural', 'weaning_reason' => 'age_appropriate',
            'post_weaning_status' => 'healthy',
            'animals' => [['ear_tag_number' => 'NEVER-CREATED', 'gender' => 'F', 'breed' => 'LW', 'room_id' => 999]],
        ]);
    }
}
