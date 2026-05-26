<?php

namespace Tests\Feature\Inventory\Controllers;

use App\Models\Finance\InventoryMovement;
use App\Models\Permission;
use App\Models\RawMaterial;
use App\Services\Finance\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class InventoryValueControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->grantPermission('view_inventory_value');
    }

    public function test_index_renders_for_user_with_view_permission(): void
    {
        $rm = $this->seedSomeInventory();

        $resp = $this->actingAs($this->user)
            ->get(route('inventory.value.index'));

        $resp->assertOk();
        $resp->assertSee($rm->name);
    }

    public function test_index_forbidden_without_permission(): void
    {
        // Revoke the permission.
        $this->user->role()->first()?->permissions()->detach();

        $resp = $this->actingAs($this->user)
            ->get(route('inventory.value.index'));

        $resp->assertForbidden();
    }

    public function test_show_renders_movements(): void
    {
        $rm = $this->seedSomeInventory();

        $resp = $this->actingAs($this->user)
            ->get(route('inventory.value.show', ['raw_material', $rm->id]));

        $resp->assertOk();
        $resp->assertSee($rm->name);
    }

    public function test_show_404_for_unknown_item(): void
    {
        $resp = $this->actingAs($this->user)
            ->get(route('inventory.value.show', ['raw_material', 99999]));

        $resp->assertNotFound();
    }

    public function test_show_404_for_invalid_kind(): void
    {
        $resp = $this->actingAs($this->user)
            ->get(route('inventory.value.show', ['bogus_kind', 1]));

        $resp->assertNotFound();
    }

    protected function seedSomeInventory(): RawMaterial
    {
        $rm = RawMaterial::create([
            'name' => 'TestMaize-' . uniqid(),
            'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);

        $this->app->make(InventoryService::class)->recordReceive(
            InventoryMovement::ITEM_KIND_RAW_MATERIAL, $rm->id, 100.0, 5.0,
            InventoryMovement::SOURCE_STOCK_LOG, 1,
        );

        return $rm;
    }

    protected function grantPermission(string $code): void
    {
        $perm = Permission::firstOrCreate(['name' => $code], [
            'name' => $code, 'description' => 'test', 'module' => 'finance',
        ]);
        $this->user->role()->first()?->permissions()->syncWithoutDetaching([$perm->id]);
    }
}
