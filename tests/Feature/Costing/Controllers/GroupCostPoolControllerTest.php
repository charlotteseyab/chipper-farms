<?php

namespace Tests\Feature\Costing\Controllers;

use App\Models\Finance\GroupCostPool;
use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Group;
use App\Models\Permission;
use App\Services\Finance\Costing\GroupCostPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class GroupCostPoolControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->grantPermission('view_group_pools');
    }

    public function test_index_renders_with_pools(): void
    {
        $this->seedOpenPool('Group A', 50, '2000', '5000');
        $this->seedOpenPool('Group B', 30, '1000', '2500');

        $resp = $this->actingAs($this->user)->get(route('finance.costing.group-pools.index'));
        $resp->assertOk();
        $resp->assertSee('Group A');
        $resp->assertSee('Group B');
        // Two pools, total value 7500
        $resp->assertSee('7,500.00');
    }

    public function test_index_excludes_closed_by_default(): void
    {
        [$group, $pool] = $this->seedOpenPool('Closed Group', 50, '2000', '5000');
        $pool->update(['closed_at' => now()]);

        $resp = $this->actingAs($this->user)->get(route('finance.costing.group-pools.index'));
        $resp->assertOk();
        $resp->assertDontSee('Closed Group');
    }

    public function test_index_shows_closed_when_requested(): void
    {
        [$group, $pool] = $this->seedOpenPool('Closed Group', 50, '2000', '5000');
        $pool->update(['closed_at' => now()]);

        $resp = $this->actingAs($this->user)->get(route('finance.costing.group-pools.index', ['show_closed' => 1]));
        $resp->assertOk();
        $resp->assertSee('Closed Group');
    }

    public function test_show_displays_movement_history(): void
    {
        [$group, $pool] = $this->seedOpenPool('Group A', 50, '2000', '5000');

        $resp = $this->actingAs($this->user)->get(route('finance.costing.group-pools.show', $pool));
        $resp->assertOk();
        $resp->assertSee('Group A');
        $resp->assertSee('opening');
    }

    public function test_index_forbidden_without_permission(): void
    {
        $this->user->role()->first()?->permissions()->detach();

        $resp = $this->actingAs($this->user)->get(route('finance.costing.group-pools.index'));
        $resp->assertForbidden();
    }

    // -------- helpers --------

    /** @return array{0: Group, 1: GroupCostPool} */
    protected function seedOpenPool(string $name, int $count, string $weight, string $value): array
    {
        $group = Group::create(['name' => $name, 'type' => 'pen']);
        $import = GroupOpeningPoolImport::create([
            'organization_id'      => $this->org->id,
            'import_mode'          => 'manual',
            'import_hash'          => bin2hex(random_bytes(32)),
            'cutover_date'         => '2027-04-30',
            'row_count'            => 1,
            'status'               => 'pending',
            'performed_by_user_id' => $this->user->id,
        ]);
        $this->app->make(GroupCostPoolService::class)
            ->recordOpening($group->id, $count, $weight, $value, $import, 1);

        $pool = GroupCostPool::where('group_id', $group->id)->first();
        return [$group, $pool];
    }

    protected function grantPermission(string $code): void
    {
        $perm = Permission::firstOrCreate(['name' => $code], [
            'name' => $code, 'description' => 'test', 'module' => 'finance',
        ]);
        $this->user->role()->first()?->permissions()->syncWithoutDetaching([$perm->id]);
    }
}
