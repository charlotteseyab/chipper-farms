<?php

namespace Tests\Feature\Costing\Controllers;

use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Finance\PigMortalityWriteoff;
use App\Models\Group;
use App\Models\Permission;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Services\Finance\Costing\GroupCostPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PigMortalityWriteoffControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_index_forbidden_without_permission(): void
    {
        $resp = $this->actingAs($this->user)->get(route('costing.mortality-writeoffs.index'));
        $resp->assertForbidden();
    }

    public function test_index_renders_with_writeoffs(): void
    {
        $this->grantPermission('manage_pig_mortality');

        $group = Group::create(['name' => 'G-1', 'type' => 'pen']);
        $pig = PigManagement::create([
            'ear_tag_number' => 'P-1',
            'gender'         => 'male',
            'stock_type'     => 'fattening',
            'date_of_birth'  => '2027-01-01',
        ]);
        $pig->group_id = $group->id;
        $pig->save();

        $death = PigDeath::create([
            'pig_id'          => $pig->id,
            'death_date'      => '2027-06-01',
            'death_cause'     => 'disease',
            'final_weight'    => 80,
            'organization_id' => $this->org->id,
            'recorded_by'     => $this->user->id,
        ]);

        PigMortalityWriteoff::create([
            'organization_id'           => $this->org->id,
            'pig_death_id'              => $death->id,
            'pig_id'                    => $pig->id,
            'group_id'                  => $group->id,
            'pig_weight_at_death_kg'    => '80',
            'group_weight_before_kg'    => '2000',
            'group_value_before'        => '5000',
            'group_pig_count_before'    => 50,
            'used_group_average_weight' => false,
            'writeoff_amount'           => '200',
            'occurred_at'               => now(),
        ]);

        $resp = $this->actingAs($this->user)->get(route('costing.mortality-writeoffs.index'));
        $resp->assertOk();
        $resp->assertSee('P-1');
        $resp->assertSee('200.00');
    }

    public function test_index_flags_estimated_weight_rows(): void
    {
        $this->grantPermission('manage_pig_mortality');

        $group = Group::create(['name' => 'G-1', 'type' => 'pen']);
        $pig = PigManagement::create([
            'ear_tag_number' => 'P-EST',
            'gender'         => 'male',
            'stock_type'     => 'fattening',
            'date_of_birth'  => '2027-01-01',
        ]);
        $pig->group_id = $group->id;
        $pig->save();

        $death = PigDeath::create([
            'pig_id'       => $pig->id,
            'death_date'   => '2027-06-01',
            'death_cause'  => 'unknown',
            'organization_id' => $this->org->id,
            'recorded_by'  => $this->user->id,
        ]);

        PigMortalityWriteoff::create([
            'organization_id'           => $this->org->id,
            'pig_death_id'              => $death->id,
            'pig_id'                    => $pig->id,
            'group_id'                  => $group->id,
            'pig_weight_at_death_kg'    => '40',
            'group_weight_before_kg'    => '2000',
            'group_value_before'        => '5000',
            'group_pig_count_before'    => 50,
            'used_group_average_weight' => true,
            'writeoff_amount'           => '100',
            'occurred_at'               => now(),
        ]);

        $resp = $this->actingAs($this->user)->get(route('costing.mortality-writeoffs.index'));
        $resp->assertOk();
        $resp->assertSee('est');  // the estimated-weight badge
    }

    protected function grantPermission(string $code): void
    {
        $perm = Permission::firstOrCreate(['name' => $code], [
            'name' => $code, 'description' => 'test', 'module' => 'finance',
        ]);
        $this->user->role()->first()?->permissions()->syncWithoutDetaching([$perm->id]);
    }
}
