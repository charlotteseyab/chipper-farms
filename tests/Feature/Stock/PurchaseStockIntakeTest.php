<?php

namespace Tests\Feature\Stock;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PurchaseStockIntake;
use App\Models\Role;
use App\Models\Room;
use App\Models\StockMovement;
use App\Models\Structure;
use App\Models\User;
use App\Services\Stock\PurchaseStockIntakeService;
use App\Services\Stock\StockValidationException;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseStockIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected User $manager;
    protected User $dataEntry;
    protected User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);
        $this->admin = $this->makeUser('admin');
        $this->manager = $this->makeUser('farmmanager');
        $this->dataEntry = $this->makeUser('dataentry');
        $this->accountant = $this->makeUser('accountant');

        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    public function test_creating_a_purchase_intake_does_not_increase_stock(): void
    {
        $this->actingAs($this->dataEntry)
            ->post(route('purchase-stock-intakes.store'), [
                'supplier_name' => 'External Farm',
                'purchase_date' => now()->toDateString(),
                'animal_category' => PurchaseStockIntake::CATEGORY_PIGLET,
                'breed' => 'LWDR',
                'quantity_ordered' => 5,
                'male_quantity' => 2,
                'female_quantity' => 3,
                'total_purchase_cost' => 1000,
                'transport_cost' => 50,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_stock_intakes', [
            'supplier_name' => 'External Farm',
            'animal_category' => PurchaseStockIntake::CATEGORY_PIGLET,
            'quantity_ordered' => 5,
            'status' => PurchaseStockIntake::STATUS_PENDING_RECEIVE,
        ]);
        $this->assertSame(0, PurchaseStockIntake::activePigletQuantity());
        $this->assertSame(0, PigManagement::count());
    }

    public function test_purchase_stock_index_returns_ajax_table_partial(): void
    {
        $this->makePendingIntake(PurchaseStockIntake::CATEGORY_PIGLET, 5);

        $response = $this->actingAs($this->manager)
            ->get(route('purchase-stock-intakes.index', ['q' => 'External']), [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['html', 'url'])
            ->assertJsonFragment(['url' => route('purchase-stock-intakes.index', ['q' => 'External'])]);

        $this->assertStringContainsString('External Farm', $response->json('html'));
    }

    public function test_purchased_piglets_become_individual_main_stock_records(): void
    {
        $this->actingAs($this->manager);
        $service = app(PurchaseStockIntakeService::class);
        $room = $this->makeRoom(capacity: 20);
        $intake = $this->makePendingIntake(PurchaseStockIntake::CATEGORY_PIGLET, 4);

        $intake = $service->receive($intake, ['quantity_received' => 4, 'quantity_rejected' => 0]);
        $this->assertSame(0, PurchaseStockIntake::activePigletQuantity());

        $intake = $service->quarantine($intake, ['room_id' => $room->id]);
        $this->assertSame(0, PurchaseStockIntake::activePigletQuantity());

        $intake = $service->approve($intake);
        $this->assertSame(0, PurchaseStockIntake::activePigletQuantity());

        $intake = $service->addToStock($intake, ['PIGLET-001', 'PIGLET-002', 'PIGLET-003', 'PIGLET-004']);

        $this->assertSame(PurchaseStockIntake::STATUS_ADDED_TO_STOCK, $intake->status);
        $this->assertSame(4, PigManagement::where('purchase_stock_intake_id', $intake->id)->count());
        $this->assertSame(0, StockMovement::where('source_id', $intake->id)->count(), 'Individual pigs must not also be counted as a batch movement.');
    }

    public function test_individual_purchased_pigs_are_created_with_purchase_metadata_after_add_to_stock(): void
    {
        $this->actingAs($this->manager);
        $room = $this->makeRoom(capacity: 10);
        $intake = $this->makeApprovedIntake(PurchaseStockIntake::CATEGORY_GROWER, 2, $room);

        $intake = app(PurchaseStockIntakeService::class)->addToStock($intake, ['BUY-GR-001', 'BUY-GR-002']);

        $this->assertSame(PurchaseStockIntake::STATUS_ADDED_TO_STOCK, $intake->status);
        $this->assertSame(2, PigManagement::where('purchase_stock_intake_id', $intake->id)->count());

        $pig = PigManagement::where('purchase_stock_intake_id', $intake->id)->first();
        $this->assertSame('purchased', $pig->acquisition_source);
        $this->assertSame('BUY-GR-001', $pig->ear_tag_number);
        $this->assertSame('CFPG-' . $pig->date_of_birth->format('ym') . '-' . $intake->id . '-001', $pig->code);
        $this->assertSame($intake->purchase_date->toDateString(), $pig->acquisition_date->toDateString());
        $this->assertSame($room->id, $pig->room_id);
        $this->assertEquals(550, (float) $pig->acquisition_cost);
    }

    public function test_room_capacity_is_enforced_before_individual_purchased_pigs_are_created(): void
    {
        $this->actingAs($this->manager);
        $room = $this->makeRoom(capacity: 1);

        PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number' => 'ROOM-FULL-001',
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(90)->toDateString(),
            'room_id' => $room->id,
        ]);

        $intake = $this->makeApprovedIntake(PurchaseStockIntake::CATEGORY_GROWER, 1, $room);

        $this->expectException(StockValidationException::class);

        app(PurchaseStockIntakeService::class)->addToStock($intake, ['BUY-CAP-001']);
    }

    public function test_add_to_stock_cannot_run_twice_for_individual_pigs(): void
    {
        $this->actingAs($this->manager);
        $room = $this->makeRoom(capacity: 10);
        $intake = $this->makeApprovedIntake(PurchaseStockIntake::CATEGORY_GROWER, 1, $room);
        $service = app(PurchaseStockIntakeService::class);

        $service->addToStock($intake, ['BUY-ONCE-001']);

        try {
            $service->addToStock($intake->fresh(), ['BUY-ONCE-002']);
            $this->fail('Second add-to-stock should have been rejected.');
        } catch (StockValidationException $e) {
            $this->assertSame('status', $e->field);
        }

        $this->assertSame(1, PigManagement::where('purchase_stock_intake_id', $intake->id)->count());
    }

    public function test_add_to_stock_cannot_duplicate_piglet_stock_movement(): void
    {
        $this->actingAs($this->manager);
        $room = $this->makeRoom(capacity: 10);
        $service = app(PurchaseStockIntakeService::class);
        $intake = $this->makeApprovedIntake(PurchaseStockIntake::CATEGORY_PIGLET, 3, $room);

        $service->addToStock($intake, ['PIGLET-ONCE-001', 'PIGLET-ONCE-002', 'PIGLET-ONCE-003']);

        try {
            $service->addToStock($intake->fresh(), ['PIGLET-TWICE-001', 'PIGLET-TWICE-002', 'PIGLET-TWICE-003']);
            $this->fail('Second piglet add-to-stock should have been rejected.');
        } catch (StockValidationException $e) {
            $this->assertSame('status', $e->field);
        }

        $this->assertSame(3, PigManagement::where('purchase_stock_intake_id', $intake->id)->count());
        $this->assertSame(0, StockMovement::where('source_id', $intake->id)->count());
    }

    public function test_ear_tag_proposals_continue_the_latest_animal_sequence(): void
    {
        PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number' => 'CF-009',
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(90)->toDateString(),
        ]);

        $this->assertSame(
            ['CF-010', 'CF-011', 'CF-012'],
            app(PurchaseStockIntakeService::class)->proposeEarTagNumbers(3, $this->org->id),
        );
    }

    public function test_missing_ear_tags_fail_before_add_to_stock(): void
    {
        $room = $this->makeRoom(capacity: 10);
        $intake = $this->makeApprovedIntake(PurchaseStockIntake::CATEGORY_GROWER, 1, $room);

        $this->actingAs($this->manager)
            ->post(route('purchase-stock-intakes.add-to-stock', $intake))
            ->assertSessionHasErrors('ear_tag_numbers');

        $this->assertSame(0, PigManagement::where('purchase_stock_intake_id', $intake->id)->count());
    }

    public function test_duplicate_submitted_ear_tags_fail_before_add_to_stock(): void
    {
        $room = $this->makeRoom(capacity: 10);
        $intake = $this->makeApprovedIntake(PurchaseStockIntake::CATEGORY_GROWER, 2, $room);

        $this->actingAs($this->manager)
            ->post(route('purchase-stock-intakes.add-to-stock', $intake), [
                'ear_tag_numbers' => ['DUP-001', 'DUP-001'],
            ])
            ->assertSessionHasErrors('ear_tag_numbers.1');

        $this->assertSame(0, PigManagement::where('purchase_stock_intake_id', $intake->id)->count());
    }

    public function test_existing_ear_tag_fails_before_add_to_stock(): void
    {
        $room = $this->makeRoom(capacity: 10);
        PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number' => 'EXISTING-001',
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(90)->toDateString(),
            'room_id' => $room->id,
        ]);
        $intake = $this->makeApprovedIntake(PurchaseStockIntake::CATEGORY_GROWER, 1, $room);

        $this->actingAs($this->manager)
            ->post(route('purchase-stock-intakes.add-to-stock', $intake), [
                'ear_tag_numbers' => ['EXISTING-001'],
            ])
            ->assertSessionHasErrors('ear_tag_numbers.0');
    }

    public function test_users_cannot_view_or_action_another_organizations_purchase_intake(): void
    {
        $otherOrg = Organization::create(['slug' => 'other-farm', 'name' => 'Other Farm']);
        $otherIntake = $this->makePendingIntake(PurchaseStockIntake::CATEGORY_PIGLET, 2, $otherOrg);

        $this->actingAs($this->manager)
            ->get(route('purchase-stock-intakes.show', $otherIntake))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->post(route('purchase-stock-intakes.receive', $otherIntake), [
                'quantity_received' => 2,
                'quantity_rejected' => 0,
            ])
            ->assertForbidden();
    }

    public function test_wrong_status_transitions_are_rejected(): void
    {
        $this->actingAs($this->manager);
        $intake = $this->makePendingIntake(PurchaseStockIntake::CATEGORY_GROWER, 1);

        $this->expectException(StockValidationException::class);
        app(PurchaseStockIntakeService::class)->approve($intake);
    }

    public function test_purchase_stock_permissions_block_accountants_from_creating_and_adding_stock(): void
    {
        $room = $this->makeRoom(capacity: 10);
        $intake = $this->makeApprovedIntake(PurchaseStockIntake::CATEGORY_GROWER, 1, $room);

        $this->actingAs($this->accountant)
            ->get(route('purchase-stock-intakes.create'))
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->post(route('purchase-stock-intakes.add-to-stock', $intake))
            ->assertForbidden();
    }

    public function test_data_entry_can_receive_but_cannot_approve_or_add_to_stock(): void
    {
        $room = $this->makeRoom(capacity: 10);
        $intake = $this->makePendingIntake(PurchaseStockIntake::CATEGORY_GROWER, 1);

        $this->actingAs($this->dataEntry)
            ->post(route('purchase-stock-intakes.receive', $intake), [
                'quantity_received' => 1,
                'quantity_rejected' => 0,
            ])
            ->assertRedirect();

        $intake->refresh()->update([
            'room_id' => $room->id,
            'status' => PurchaseStockIntake::STATUS_APPROVED,
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($this->dataEntry)
            ->post(route('purchase-stock-intakes.add-to-stock', $intake))
            ->assertForbidden();
    }

    protected function makeUser(string $roleName, ?Organization $organization = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $organization ??= $this->org;

        return User::forceCreate([
            'name' => ucfirst($roleName),
            'email' => $roleName . '@example.com',
            'password' => bcrypt('x'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $organization->id,
        ]);
    }

    protected function makeRoom(int $capacity): Room
    {
        $structure = Structure::create([
            'name' => 'Quarantine House',
            'code' => 'QH-' . uniqid(),
            'created_by' => $this->admin->id,
        ]);

        return Room::create([
            'structure_id' => $structure->id,
            'name' => 'Quarantine Pen',
            'code' => 'QP-' . uniqid(),
            'capacity' => $capacity,
            'type' => 'quarantine',
        ]);
    }

    protected function makePendingIntake(string $category, int $quantity, ?Organization $organization = null): PurchaseStockIntake
    {
        $organization ??= $this->org;

        return PurchaseStockIntake::create([
            'batch_number' => PurchaseStockIntake::nextBatchNumber((int) now()->year),
            'organization_id' => $organization->id,
            'supplier_name' => 'External Farm',
            'purchase_date' => now()->toDateString(),
            'animal_category' => $category,
            'breed' => 'LWDR',
            'quantity_ordered' => $quantity,
            'male_quantity' => $quantity,
            'female_quantity' => 0,
            'estimated_age_days' => 90,
            'average_weight_kg' => 25,
            'total_purchase_cost' => 1000,
            'transport_cost' => 100,
            'status' => PurchaseStockIntake::STATUS_PENDING_RECEIVE,
            'created_by' => $this->dataEntry->id,
        ]);
    }

    protected function makeApprovedIntake(string $category, int $quantity, Room $room): PurchaseStockIntake
    {
        $intake = $this->makePendingIntake($category, $quantity);
        $intake->update([
            'quantity_received' => $quantity,
            'quantity_rejected' => 0,
            'room_id' => $room->id,
            'received_by' => $this->dataEntry->id,
            'received_at' => now(),
            'quarantined_by' => $this->dataEntry->id,
            'quarantined_at' => now(),
            'approved_by' => $this->manager->id,
            'approved_at' => now(),
            'status' => PurchaseStockIntake::STATUS_APPROVED,
        ]);

        return $intake->fresh();
    }
}
