<?php

namespace Tests\Feature\Weights;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PigManagement $pig;
    private PigWeight $weight;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::create([
            'slug' => 'weight-audit-tests',
            'name' => 'Weight Audit Tests',
            'is_active' => true,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($organization);
        $this->seed(FarmOperationsPermissionsSeeder::class);

        $role = Role::where('name', 'dataentry')->firstOrFail();
        $this->user = User::forceCreate([
            'name' => 'Weight Recorder',
            'email' => 'weight-audit@example.com',
            'password' => bcrypt('password'),
            'role' => 'dataentry',
            'role_id' => $role->id,
            'organization_id' => $organization->id,
            'active_status' => true,
        ]);

        $this->pig = PigManagement::create([
            'organization_id' => $organization->id,
            'ear_tag_number' => 'WEIGHT-001',
            'code' => 'PIG-WEIGHT-001',
            'breed' => 'Large White',
            'gender' => 'F',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subWeeks(20)->toDateString(),
        ]);

        $this->weight = PigWeight::create([
            'pig_id' => $this->pig->id,
            'weight' => 80,
            'weight_unit' => 'kg',
            'weigh_date' => now()->subDay()->toDateString(),
            'target_week' => 20,
            'actual_age_weeks' => 20,
            'purpose' => 'routine',
            'weighed_by' => $this->user->name,
            'is_estimated' => false,
        ]);
    }

    public function test_updating_a_weight_logs_only_changed_fields(): void
    {
        $this->actingAs($this->user)
            ->put(route('weights.update', $this->weight), [
                'pig_id' => $this->pig->id,
                'weight_kg' => 84.5,
                'weigh_date' => now()->subDay()->toDateString(),
                'target_week' => 20,
                'purpose' => 'health',
                'body_condition_score' => 3,
                'health_status' => 'good',
                'notes' => 'Weight rechecked.',
            ])
            ->assertRedirect(route('weights.index'));

        $log = ActivityLog::where('module', 'weights')
            ->where('auditable_type', PigWeight::class)
            ->where('auditable_id', $this->weight->id)
            ->sole();

        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('updated', $log->action);
        $this->assertArrayHasKey('weight', $log->old_values);
        $this->assertArrayHasKey('weight', $log->new_values);
        $this->assertArrayHasKey('purpose', $log->new_values);
        $this->assertArrayNotHasKey('target_week', $log->new_values);
    }

    public function test_weight_create_lists_active_pigs_for_the_current_organization(): void
    {
        $otherOrganization = Organization::create([
            'slug' => 'other-weight-farm',
            'name' => 'Other Weight Farm',
            'is_active' => true,
        ]);

        PigManagement::create([
            'organization_id' => $otherOrganization->id,
            'ear_tag_number' => 'OTHER-WEIGHT-001',
            'code' => 'PIG-OTHER-WEIGHT-001',
            'breed' => 'Landrace',
            'gender' => 'M',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subWeeks(18)->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->get(route('weights.create'))
            ->assertOk()
            ->assertSee('WEIGHT-001')
            ->assertSee('PIG-WEIGHT-001')
            ->assertSee('Local ' . $this->pig->local_id)
            ->assertDontSee('OTHER-WEIGHT-001');
    }

    public function test_weight_audit_page_displays_before_and_after_values(): void
    {
        $this->actingAs($this->user);
        $this->weight->update(['weight' => 85]);

        $this->get(route('weights.audit', $this->weight))
            ->assertOk()
            ->assertSee('Weight Change History')
            ->assertSee('Before')
            ->assertSee('After')
            ->assertSee('80.00 kg')
            ->assertSee('85.00 kg')
            ->assertSee($this->user->name);
    }

    public function test_sidebar_links_to_the_organization_wide_weight_audit_page(): void
    {
        $this->actingAs($this->user);
        $this->weight->update(['weight' => 86]);

        $this->get(route('weights.audit.index'))
            ->assertOk()
            ->assertSee('Weight Audit Trail')
            ->assertSee('All Weight Records')
            ->assertSee(route('weights.audit.index'), false)
            ->assertSee('View record history');
    }

    public function test_weight_audit_page_does_not_expose_another_organizations_record(): void
    {
        $otherOrganization = Organization::create([
            'slug' => 'other-weight-audit',
            'name' => 'Other Weight Audit',
            'is_active' => true,
        ]);
        $otherPig = PigManagement::create([
            'organization_id' => $otherOrganization->id,
            'ear_tag_number' => 'OTHER-001',
            'code' => 'OTHER-PIG-001',
            'breed' => 'Duroc',
            'gender' => 'M',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subWeeks(20)->toDateString(),
        ]);
        $otherWeight = PigWeight::create([
            'pig_id' => $otherPig->id,
            'weight' => 75,
            'weight_unit' => 'kg',
            'weigh_date' => now()->toDateString(),
            'target_week' => 20,
            'purpose' => 'routine',
        ]);

        $this->actingAs($this->user)
            ->get(route('weights.audit', $otherWeight))
            ->assertNotFound();
    }
}
