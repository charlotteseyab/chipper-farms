<?php

namespace Tests\Feature\Costing\Controllers;

use App\Models\Finance\GroupCostPool;
use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Group;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class OpeningPigPoolControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_index_forbidden_without_view_permission(): void
    {
        $resp = $this->actingAs($this->user)->get(route('costing.opening-pig-inventory.index'));
        $resp->assertForbidden();
    }

    public function test_index_renders_for_viewer(): void
    {
        $this->grantPermission('view_group_pools');
        $resp = $this->actingAs($this->user)->get(route('costing.opening-pig-inventory.index'));
        $resp->assertOk();
    }

    public function test_create_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('view_group_pools');
        $resp = $this->actingAs($this->user)->get(route('costing.opening-pig-inventory.create'));
        $resp->assertForbidden();
    }

    public function test_store_creates_opening_pool_and_redirects(): void
    {
        $this->grantPermission('manage_opening_pig_pool');
        $this->grantPermission('view_group_pools');

        $g1 = Group::create(['name' => 'Nursery', 'type' => 'pen']);
        $g2 = Group::create(['name' => 'Grower',  'type' => 'pen']);

        $resp = $this->actingAs($this->user)->post(route('costing.opening-pig-inventory.store'), [
            'cutover_date' => '2027-04-30',
            'rows' => [
                ['group_id' => $g1->id, 'pig_count' => 30, 'total_weight_kg' => 600,  'total_value' => 1500],
                ['group_id' => $g2->id, 'pig_count' => 50, 'total_weight_kg' => 3000, 'total_value' => 8000],
            ],
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $this->assertEquals(1, GroupOpeningPoolImport::count());

        $p1 = GroupCostPool::query()->where('group_id', $g1->id)->first();
        $this->assertEquals(30, $p1->on_hand_pig_count);
    }

    public function test_store_with_validation_errors_returns_to_form(): void
    {
        $this->grantPermission('manage_opening_pig_pool');
        $g = Group::create(['name' => 'X', 'type' => 'pen']);

        $resp = $this->actingAs($this->user)
            ->from(route('costing.opening-pig-inventory.create'))
            ->post(route('costing.opening-pig-inventory.store'), [
                'cutover_date' => '2027-04-30',
                'rows' => [
                    ['group_id' => $g->id, 'pig_count' => -5, 'total_weight_kg' => 100, 'total_value' => 500],
                ],
            ]);

        $resp->assertRedirect(route('costing.opening-pig-inventory.create'));
        $resp->assertSessionHasErrors('rows.0.pig_count');
    }

    protected function grantPermission(string $code): void
    {
        $perm = Permission::firstOrCreate(['name' => $code], [
            'name' => $code, 'description' => 'test', 'module' => 'finance',
        ]);
        $this->user->role()->first()?->permissions()->syncWithoutDetaching([$perm->id]);
    }
}
