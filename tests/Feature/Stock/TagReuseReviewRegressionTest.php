<?php

namespace Tests\Feature\Stock;

use App\Models\EarTag;
use App\Models\EarTagColor;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Stock\Tags\EarTagAssignmentService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagReuseReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private EarTagColor $green;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(10, 0));
        $this->org = Organization::factory()->create();
        app(CurrentOrganization::class)->bind($this->org);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->actor = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin', 'role_id' => $role->id, 'active_status' => true]);
        $this->actingAs($this->actor);
        Setting::setAnimalBreeds(['LW' => 'Large White']);
        $this->green = EarTagColor::create(['name' => 'Green', 'normalized_name' => 'green', 'organization_id' => $this->org->id]);
    }

    private function pig()
    {
        return PigManagement::factory()->create(['ear_tag_number' => '']);
    }

    public function test_future_correction_is_rejected_without_changing_current_identity(): void
    {
        $p = $this->pig();
        $a = app(EarTagAssignmentService::class)->assign($p, $this->green, '5', $this->actor);
        $this->patch(route('ear-tags.correct', $a), ['assigned_at' => '2026-06-16 10:00:00', 'correction_reason' => 'Review date correction'])->assertSessionHasErrors('assigned_at');
        $this->assertTrue($a->fresh()->is_current);
        $this->assertSame('in_use', $a->tag->fresh()->status);
        $this->assertSame('Green 5', $p->fresh()->tagLabelAt('2026-06-15 12:00:00'));
        $this->assertSame('Green 5', $p->fresh()->tagLabelAt(null));
    }

    public function test_correction_rejects_tag_before_animal_was_born(): void
    {
        $p = $this->pig();
        $a = app(EarTagAssignmentService::class)->assign($p, $this->green, '5', $this->actor);
        $this->patch(route('ear-tags.correct', $a), ['assigned_at' => '2020-01-01 10:00:00', 'correction_reason' => 'Review birth boundary'])->assertSessionHasErrors('assigned_at');
        $this->assertFalse($a->fresh()->assigned_at->lt($p->date_of_birth));
    }

    public function test_inline_edit_cannot_claim_an_occupied_number_without_assignment(): void
    {
        $holder = $this->pig();
        app(EarTagAssignmentService::class)->assign($holder, $this->green, '5', $this->actor);
        $untagged = $this->pig();
        $this->put(route('standard-stocks.update', $untagged), ['ear_tag_number' => '5', 'breed' => 'LW', 'gender' => 'F', 'date_of_birth' => $untagged->date_of_birth->toDateString()])->assertSessionHasErrors('ear_tag_number');
        $this->assertSame('', $untagged->fresh()->ear_tag_number);
        $this->assertSame(0, $untagged->earTagAssignments()->count());
        $this->assertSame('5', $holder->fresh()->ear_tag_number);
    }

    public function test_untagged_animal_can_receive_its_first_tag(): void
    {
        $p = $this->pig();
        $this->post(route('pigs.ear-tag.store', $p), ['ear_tag_color_id' => $this->green->id, 'ear_tag_number' => '5', 'reason' => 'First physical tag'])->assertSessionHas('success');
        $this->assertSame(1, $p->earTagAssignments()->count());
        $this->assertSame('Green 5', $p->fresh()->tagLabelAt(null));
    }

    public function test_standard_csv_distinguishes_colour_for_identical_numbers(): void
    {
        $a = $this->pig();
        $b = $this->pig();
        $yellow = EarTagColor::create(['name' => 'Yellow', 'normalized_name' => 'yellow', 'organization_id' => $this->org->id]);
        app(EarTagAssignmentService::class)->assign($a, $this->green, '5', $this->actor);
        app(EarTagAssignmentService::class)->assign($b, $yellow, '5', $this->actor);
        $response = $this->get(route('standard-stocks.export.csv'))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($a->code, $csv);
        $this->assertStringContainsString($b->code, $csv);
        $this->assertStringContainsString('Green', $csv);
        $this->assertStringContainsString('Yellow', $csv);
    }

    public function test_parent_picker_distinguishes_different_colour_tags(): void
    {
        $first = $this->pig();
        $second = $this->pig();
        $child = $this->pig();
        $first->update(['gender' => 'M']);
        $second->update(['gender' => 'M']);
        $yellow = EarTagColor::create(['name' => 'Yellow', 'normalized_name' => 'yellow', 'organization_id' => $this->org->id]);
        app(EarTagAssignmentService::class)->assign($first, $this->green, '5', $this->actor);
        app(EarTagAssignmentService::class)->assign($second, $yellow, '5', $this->actor);
        $html = $this->get(route('standard-stocks.edit', $child))->assertOk()->getContent();
        $this->assertStringContainsString('Tag Green 5 · '.$first->code, $html);
        $this->assertStringContainsString('Tag Yellow 5 · '.$second->code, $html);
    }

    public function test_first_assignment_page_omits_old_tag_outcome_and_is_linked_from_edit(): void
    {
        $pig = $this->pig();
        $this->get(route('pigs.ear-tag.assign', $pig))->assertOk()->assertSee('Assign physical ear tag')->assertDontSee('name="old_tag_status"', false);
        $this->get(route('standard-stocks.edit', $pig))->assertOk()->assertSee(route('pigs.ear-tag.assign', $pig), false);
        $this->put(route('standard-stocks.update', $pig), ['ear_tag_number' => '', 'breed' => 'LW', 'gender' => 'M', 'date_of_birth' => $pig->date_of_birth->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('M', $pig->fresh()->gender);
        $this->assertDatabaseCount('ear_tag_assignments', 0);
    }

    public function test_first_assignment_cannot_claim_an_occupied_tag_or_overwrite_current_holder(): void
    {
        $holder = $this->pig();
        app(EarTagAssignmentService::class)->assign($holder, $this->green, '5', $this->actor);
        $untagged = $this->pig();
        $body = ['ear_tag_color_id' => $this->green->id, 'ear_tag_number' => '5', 'reason' => 'First tag fitted'];
        $this->post(route('pigs.ear-tag.store', $untagged), $body)->assertSessionHasErrors('ear_tag_number');
        $body['ear_tag_number'] = '6';
        $this->post(route('pigs.ear-tag.store', $holder), $body)->assertSessionHasErrors('ear_tag_number');
        $this->assertSame('Green 5', $holder->fresh()->tagLabelAt(null));
        $this->assertSame(0, $untagged->earTagAssignments()->count());
        $this->assertDatabaseCount('ear_tag_assignments', 1);
    }

    public function test_first_assignment_reuses_recovered_tag_with_both_histories_intact(): void
    {
        $former = $this->pig();
        $assignment = app(EarTagAssignmentService::class)->assign($former, $this->green, '5', $this->actor);
        app(EarTagAssignmentService::class)->release($assignment, EarTag::AVAILABLE, 'archive', $this->actor);
        $new = $this->pig();
        $this->post(route('pigs.ear-tag.store', $new), ['ear_tag_color_id' => $this->green->id, 'ear_tag_number' => '5', 'reason' => 'Recovered tag fitted'])->assertSessionHas('success');
        $this->assertSame($former->id, $assignment->fresh()->pig_id);
        $this->assertNotNull($assignment->fresh()->released_at);
        $this->assertSame($new->id, $assignment->tag->fresh()->currentAssignment->pig_id);
        $this->assertSame(2, $assignment->tag->assignments()->count());
    }

    public function test_first_assignment_requires_update_permission_and_same_farm_colour(): void
    {
        $pig = $this->pig();
        $other = Organization::factory()->create();
        $colour = EarTagColor::withoutGlobalScopes()->create(['organization_id' => $other->id, 'name' => 'Other green', 'normalized_name' => 'other-green']);
        $body = ['ear_tag_color_id' => $colour->id, 'ear_tag_number' => '5', 'reason' => 'First tag fitted'];
        $this->post(route('pigs.ear-tag.store', $pig), $body)->assertSessionHasErrors('ear_tag_color_id');
        $role = Role::firstOrCreate(['name' => 'accountant']);
        $reader = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'accountant', 'role_id' => $role->id]);
        $body['ear_tag_color_id'] = $this->green->id;
        $this->actingAs($reader)->post(route('pigs.ear-tag.store', $pig), $body)->assertForbidden();
        $this->assertDatabaseCount('ear_tag_assignments', 0);
    }

    public function test_future_release_correction_is_rejected_and_valid_correction_is_audited(): void
    {
        $pig = $this->pig();
        $service = app(EarTagAssignmentService::class);
        $assignment = $service->assign($pig, $this->green, '5', $this->actor, '2026-06-13 10:00:00');
        $service->release($assignment, EarTag::AVAILABLE, 'archive', $this->actor, '2026-06-14 10:00:00');
        $body = ['assigned_at' => '2026-06-13 10:00:00', 'released_at' => '2026-06-16 10:00:00', 'release_reason' => 'archive', 'correction_reason' => 'Register date checked'];
        $this->patch(route('ear-tags.correct', $assignment), $body)->assertSessionHasErrors('released_at');
        $this->assertSame('2026-06-14', $assignment->fresh()->released_at->toDateString());
        $body['assigned_at'] = '2026-06-12 10:00:00';
        $body['released_at'] = '2026-06-14 10:00:00';
        $this->patch(route('ear-tags.correct', $assignment), $body)->assertSessionHas('success');
        $this->assertSame('2026-06-12', $assignment->fresh()->assigned_at->toDateString());
        $this->assertDatabaseHas('activity_logs', ['action' => 'ear_tag_corrected', 'auditable_id' => $assignment->id]);
    }

    public function test_breeding_csv_and_stock_pdfs_include_coloured_identity(): void
    {
        $pig = $this->pig();
        $pig->update(['stock_type' => 'breeding']);
        app(EarTagAssignmentService::class)->assign($pig, $this->green, '5', $this->actor);
        $this->assertStringContainsString('Green 5', $this->get(route('breeding-stocks.export.csv'))->assertOk()->streamedContent());
        foreach (['breeding_stocks.pdf', 'stocks.standard.pdf', 'stocks.all.pdf'] as $view) {
            $html = view($view, ['pigs' => collect([$pig->fresh()])])->render();
            $this->assertStringContainsString('Green 5', $html);
        }
    }
}
