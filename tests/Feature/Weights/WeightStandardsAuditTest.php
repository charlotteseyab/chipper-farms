<?php

namespace Tests\Feature\Weights;

use App\Models\ActivityLog;
use App\Models\ExpectedWeightStandard;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightStandardsAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::create([
            'slug' => 'standard-audit-tests',
            'name' => 'Standard Audit Tests',
            'is_active' => true,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($organization);
        $this->seed(FarmOperationsPermissionsSeeder::class);

        $role = Role::where('name', 'admin')->firstOrFail();
        $this->admin = User::forceCreate([
            'name' => 'Standards Administrator',
            'email' => 'standards-audit@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'role_id' => $role->id,
            'organization_id' => $organization->id,
            'active_status' => true,
        ]);
    }

    public function test_standard_creation_and_updates_are_logged(): void
    {
        $this->actingAs($this->admin);

        $standard = ExpectedWeightStandard::create([
            'breed' => 'LW',
            'gender' => 'F',
            'stock_type' => 'breeding',
            'week_number' => 20,
            'expected_min_weight' => 45,
            'expected_max_weight' => 55,
            'optimal_weight' => 50,
            'created_by' => $this->admin->id,
            'is_active' => true,
        ]);
        $standard->update([
            'expected_min_weight' => 46,
            'optimal_weight' => 51,
        ]);

        $logs = ActivityLog::where('module', 'weight_standards')
            ->where('auditable_id', $standard->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertSame('created', $logs->first()->action);
        $this->assertSame('updated', $logs->last()->action);
        $this->assertArrayHasKey('expected_min_weight', $logs->last()->old_values);
        $this->assertArrayHasKey('optimal_weight', $logs->last()->new_values);
        $this->assertArrayNotHasKey('expected_max_weight', $logs->last()->new_values);
    }

    public function test_shared_audit_page_has_weight_record_and_standard_tabs(): void
    {
        $this->actingAs($this->admin);

        ExpectedWeightStandard::create([
            'breed' => 'DR',
            'gender' => 'M',
            'stock_type' => 'standard',
            'week_number' => 16,
            'expected_min_weight' => 35,
            'expected_max_weight' => 45,
            'optimal_weight' => 40,
            'created_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->get(route('weights.audit.index', ['tab' => 'standards']))
            ->assertOk()
            ->assertSee('Weight Records')
            ->assertSee('Weight Standards')
            ->assertSee('All Weight Standards')
            ->assertSee('Duroc · Male · Standard · Week 16')
            ->assertSee('Minimum Weight')
            ->assertSee('35.00 kg');
    }
}
