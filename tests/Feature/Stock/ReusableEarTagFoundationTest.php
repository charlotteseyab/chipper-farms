<?php

namespace Tests\Feature\Stock;

use App\Models\EarTag;
use App\Models\EarTagAssignment;
use App\Models\EarTagColor;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\Stock\StockValidationException;
use App\Services\Stock\Tags\EarTagAllocator;
use App\Services\Stock\Tags\EarTagAssignmentService;
use App\Services\Stock\Tags\EarTagShadowService;
use App\Services\Stock\Tags\ReplaceEarTagService;
use App\Services\Archive\RestoreArchivedRecordService;
use App\Support\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReusableEarTagFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $actor;
    private EarTagColor $green;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create(['name' => 'Tag Farm', 'slug' => 'tag-farm']);
        $this->app->make(CurrentOrganization::class)->bind($this->organization);
        $this->actor = User::factory()->create(['organization_id' => $this->organization->id, 'role' => 'admin']);
        $this->green = EarTagColor::create([
            'organization_id' => $this->organization->id,
            'name' => 'Green',
            'normalized_name' => 'green',
            'hex_color' => '#15803D',
        ]);
    }

    public function test_colour_and_normalised_number_identify_a_physical_tag(): void
    {
        EarTag::create([
            'organization_id' => $this->organization->id,
            'ear_tag_color_id' => $this->green->id,
            'tag_number' => '05',
            'normalized_tag_number' => EarTag::normalizeNumber('05'),
        ]);

        $this->expectException(QueryException::class);
        EarTag::create([
            'organization_id' => $this->organization->id,
            'ear_tag_color_id' => $this->green->id,
            'tag_number' => '5',
            'normalized_tag_number' => EarTag::normalizeNumber('5'),
        ]);
    }

    public function test_different_organisations_can_use_the_same_number(): void
    {
        EarTag::create(['organization_id' => $this->organization->id, 'ear_tag_color_id' => $this->green->id, 'tag_number' => '5', 'normalized_tag_number' => '5']);
        $other = Organization::create(['name' => 'Other Farm', 'slug' => 'other-farm']);
        $color = EarTagColor::withoutGlobalScopes()->create(['organization_id' => $other->id, 'name' => 'Green', 'normalized_name' => 'green']);
        EarTag::withoutGlobalScopes()->create(['organization_id' => $other->id, 'ear_tag_color_id' => $color->id, 'tag_number' => '5', 'normalized_tag_number' => '5']);
        $this->assertSame(2, EarTag::withoutGlobalScopes()->count());
    }

    public function test_released_tag_can_be_reused_without_losing_either_animals_history(): void
    {
        [$first, $second] = [$this->pig('1'), $this->pig('2')];
        $service = $this->app->make(EarTagAssignmentService::class);

        $old = $service->assign($first, $this->green, '5', $this->actor);
        $service->release($old, EarTag::AVAILABLE, 'mortality', $this->actor);

        $this->assertSame($first->id, $old->fresh()->pig_id);
        $this->assertNull($old->fresh()->is_current);
        $current = $service->assign($second, $this->green, '5', $this->actor);

        $this->assertSame($second->id, $current->pig_id);
        $this->assertSame('5', $second->fresh()->ear_tag_number);
        $this->assertSame(2, EarTagAssignment::where('ear_tag_id', $old->ear_tag_id)->count());
    }

    public function test_a_tag_cannot_be_assigned_to_two_animals_at_once(): void
    {
        $service = $this->app->make(EarTagAssignmentService::class);
        $service->assign($this->pig('1'), $this->green, '5', $this->actor);

        $this->expectException(StockValidationException::class);
        $service->assign($this->pig('2'), $this->green, '5', $this->actor);
    }

    public function test_green_yellow_and_orange_can_each_use_number_one(): void
    {
        $service = app(EarTagAssignmentService::class);
        $assignments = [];
        foreach (['Green', 'Yellow', 'Orange'] as $index => $name) {
            $color = EarTagColor::firstOrCreate(['organization_id' => $this->organization->id, 'normalized_name' => strtolower($name)], ['name' => $name]);
            $this->assertSame('1', app(EarTagAllocator::class)->propose($this->organization->id, $color)[0]['tag_number']);
            $assignments[] = $service->assign($this->pig((string) (61 + $index)), $color, '1', $this->actor);
            $this->assertSame('2', app(EarTagAllocator::class)->propose($this->organization->id, $color)[0]['tag_number']);
        }
        $this->assertCount(3, array_unique(array_map(fn ($row) => $row->ear_tag_id, $assignments)));
        $service->release($assignments[0], EarTag::AVAILABLE, 'replacement', $this->actor);
        $yellow = $assignments[1]->tag->color;
        $this->assertSame('2', app(EarTagAllocator::class)->propose($this->organization->id, $yellow)[0]['tag_number']);
        $this->assertSame('1', app(EarTagAllocator::class)->propose($this->organization->id, $this->green)[0]['tag_number']);
        $service->assign($assignments[0]->pig, $this->green, '2', $this->actor);
        $this->artisan('ear-tags:reconcile')->assertSuccessful();
    }

    public function test_forward_migration_replaces_the_old_number_only_constraint(): void
    {
        \Illuminate\Support\Facades\Schema::table('ear_tags', function ($table) {
            $table->dropUnique('ear_tags_org_color_identity_ready');
            $table->unique(['organization_id', 'normalized_tag_number'], 'ear_tags_org_number_unique');
        });
        (require database_path('migrations/2028_10_15_000000_scope_tag_identity_to_colour.php'))->up();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasIndex('ear_tags', 'ear_tags_org_number_unique'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('ear_tags', 'ear_tags_org_color_identity_ready'));
    }

    public function test_replacement_closes_the_old_assignment_and_preserves_tag_history(): void
    {
        $pig = $this->pig('31');
        $assignments = $this->app->make(EarTagAssignmentService::class);
        $assignments->assign($pig, $this->green, '31', $this->actor);

        $replacement = $this->app->make(ReplaceEarTagService::class)->execute(
            $pig,
            $this->actor,
            [
                'ear_tag_color_id' => $this->green->id,
                'ear_tag_number' => '32',
                'old_tag_status' => EarTag::DAMAGED,
                'reason' => 'The former tag split at the fastener.',
            ],
        );

        $this->assertSame('32', $pig->fresh()->ear_tag_number);
        $this->assertSame('32', $replacement->tag->normalized_tag_number);
        $this->assertSame(2, EarTagAssignment::where('pig_id', $pig->id)->count());
        $this->assertDatabaseHas('ear_tag_assignments', [
            'pig_id' => $pig->id,
            'release_reason' => 'replacement',
            'is_current' => null,
        ]);
        $this->assertDatabaseHas('ear_tags', [
            'normalized_tag_number' => '31',
            'status' => EarTag::DAMAGED,
        ]);
    }

    public function test_restoring_an_animal_reassigns_its_recovered_available_tag(): void
    {
        $pig = $this->pig('41');
        $assignments = $this->app->make(EarTagAssignmentService::class);
        $old = $assignments->assign($pig, $this->green, '41', $this->actor);
        $assignments->release($old, EarTag::AVAILABLE, 'archive', $this->actor);
        $pig->delete();

        $restored = $this->app->make(RestoreArchivedRecordService::class)
            ->restorePig($pig, $this->actor);

        $this->assertFalse($restored->fresh()->trashed());
        $this->assertSame(2, EarTagAssignment::where('pig_id', $pig->id)->count());
        $this->assertSame(1, EarTagAssignment::where('pig_id', $pig->id)->where('is_current', 1)->count());
        $this->assertDatabaseHas('ear_tags', [
            'normalized_tag_number' => '41',
            'status' => EarTag::IN_USE,
        ]);
    }

    public function test_allocator_prefers_available_recovered_tags_then_unused_gaps(): void
    {
        foreach ([1 => EarTag::IN_USE, 2 => EarTag::AVAILABLE, 4 => EarTag::DAMAGED] as $number => $status) {
            EarTag::create([
                'organization_id' => $this->organization->id,
                'ear_tag_color_id' => $this->green->id,
                'tag_number' => (string) $number,
                'normalized_tag_number' => (string) $number,
                'status' => $status,
            ]);
        }

        $suggestions = $this->app->make(EarTagAllocator::class)
            ->propose($this->organization->id, $this->green, 3);

        $this->assertSame(['2', '3', '5'], array_column($suggestions, 'tag_number'));
    }

    public function test_shadow_sync_is_idempotent_for_a_new_legacy_animal(): void
    {
        $pig = $this->pig('15');
        $shadow = $this->app->make(EarTagShadowService::class);

        $first = $shadow->syncNewPig($pig, $this->actor);
        $second = $shadow->syncNewPig($pig, $this->actor);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EarTagAssignment::where('pig_id', $pig->id)->count());
        $this->assertSame('unspecified', $first->tag->color->normalized_name);
    }

    public function test_reconciliation_backfills_active_and_archived_animals_idempotently(): void
    {
        $active = $this->pig('21');
        $archived = $this->pig('22');
        $archived->delete();

        $this->artisan('ear-tags:reconcile', ['--backfill' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('ear_tag_assignments', [
            'pig_id' => $active->id, 'is_current' => 1,
        ]);
        $this->assertDatabaseHas('ear_tag_assignments', [
            'pig_id' => $archived->id, 'is_current' => null, 'release_reason' => 'other',
        ]);
        $this->assertDatabaseHas('ear_tags', [
            'normalized_tag_number' => '22', 'status' => EarTag::PENDING_RECOVERY,
        ]);

        $this->artisan('ear-tags:reconcile', ['--backfill' => true])
            ->assertSuccessful();
        $this->assertSame(2, EarTagAssignment::count());
    }

    public function test_only_the_farm_admin_can_open_its_reconciliation_page(): void
    {
        $this->actingAs($this->actor)
            ->get(route('settings.ear-tags.reconciliation'))
            ->assertOk()
            ->assertSee('Ear tag reconciliation');

        $outsiderFarm = Organization::create(['name' => 'Outside Farm', 'slug' => 'outside-farm']);
        $outsider = User::factory()->create(['organization_id' => $outsiderFarm->id, 'role' => 'farmmanager']);
        $this->app->make(CurrentOrganization::class)->bind($outsiderFarm);

        $this->actingAs($outsider)
            ->get(route('settings.ear-tags.reconciliation'))
            ->assertForbidden();
    }

    public function test_admin_can_confirm_the_colour_of_a_tag_currently_in_use(): void
    {
        $pig = $this->pig('51');
        $assignment = $this->app->make(EarTagAssignmentService::class)
            ->assign($pig, $this->green, '51', $this->actor);
        $yellow = EarTagColor::create([
            'organization_id' => $this->organization->id,
            'name' => 'Yellow',
            'normalized_name' => 'yellow',
        ]);

        $this->actingAs($this->actor)->patch(
            route('settings.ear-tags.reconciliation.update', $assignment->ear_tag_id),
            [
                'ear_tag_color_id' => $yellow->id,
                'status' => EarTag::IN_USE,
                'condition_notes' => 'Colour verified on the animal.',
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('ear_tags', [
            'id' => $assignment->ear_tag_id,
            'ear_tag_color_id' => $yellow->id,
            'status' => EarTag::IN_USE,
        ]);
        $this->assertDatabaseHas('ear_tag_assignments', [
            'id' => $assignment->id,
            'pig_id' => $pig->id,
            'is_current' => 1,
        ]);
    }

    public function test_reconciliation_cannot_make_an_assigned_tag_available(): void
    {
        $assignment = $this->app->make(EarTagAssignmentService::class)
            ->assign($this->pig('81'), $this->green, '81', $this->actor);

        $this->actingAs($this->actor)->patch(
            route('settings.ear-tags.reconciliation.update', $assignment->ear_tag_id),
            ['ear_tag_color_id' => $this->green->id, 'status' => EarTag::AVAILABLE],
        )->assertSessionHasErrors('status');

        $this->assertSame(EarTag::IN_USE, $assignment->tag->fresh()->status);
        $this->assertTrue($assignment->fresh()->is_current);
    }

    public function test_reconciliation_cannot_mark_a_released_tag_in_use(): void
    {
        $service = $this->app->make(EarTagAssignmentService::class);
        $assignment = $service->assign($this->pig('82'), $this->green, '82', $this->actor);
        $service->release($assignment, EarTag::PENDING_RECOVERY, 'archive', $this->actor);

        $this->actingAs($this->actor)->patch(
            route('settings.ear-tags.reconciliation.update', $assignment->ear_tag_id),
            ['ear_tag_color_id' => $this->green->id, 'status' => EarTag::IN_USE],
        )->assertSessionHasErrors('status');

        $this->assertSame(EarTag::PENDING_RECOVERY, $assignment->tag->fresh()->status);
        $this->assertNull($assignment->fresh()->is_current);
    }

    public function test_restore_after_reuse_keeps_both_animals_and_assignments_separate(): void
    {
        $service = app(EarTagAssignmentService::class);
        $first = $this->pig('101');
        $second = $this->pig('102');
        $old = $service->assign($first, $this->green, '5', $this->actor);
        $service->release($old, EarTag::AVAILABLE, 'sale', $this->actor);
        $first->refresh()->delete();
        $holder = $service->assign($second, $this->green, '5', $this->actor);
        $restored = app(RestoreArchivedRecordService::class)->restorePig($first, $this->actor);
        $this->assertSame('5', $second->fresh()->ear_tag_number);
        $this->assertNotSame('5', $restored->fresh()->ear_tag_number);
        $this->assertSame($first->id, $old->fresh()->pig_id);
        $this->assertTrue($holder->fresh()->is_current);
        $this->assertSame($first->code, $restored->code);
    }

    public function test_reuse_preserves_weight_health_and_medication_history_and_historical_labels(): void
    {
        $first = $this->pig('111');
        $second = $this->pig('112');
        $service = app(EarTagAssignmentService::class);
        $old = $service->assign($first, $this->green, '5', $this->actor, '2026-01-01');
        $weight = \App\Models\PigWeight::create(['pig_id' => $first->id, 'weight' => 40, 'weigh_date' => '2026-02-01']);
        $health = \App\Models\HealthRecord::create(['pig_id' => $first->id, 'record_date' => '2026-02-01', 'condition' => 'Routine check', 'recorded_by' => $this->actor->id]);
        $medication = \App\Models\MedicationAdministration::create(['pig_id' => $first->id, 'administration_date' => '2026-02-01', 'medication_name' => 'Recorded treatment', 'dosage' => '1', 'administered_by' => $this->actor->id]);
        $before = $first->identityAt('2026-02-01');
        $service->release($old, EarTag::AVAILABLE, 'archive', $this->actor, '2026-06-01');
        $first->refresh()->delete();
        $service->assign($second, $this->green, '5', $this->actor, '2026-09-01');
        $this->assertSame($before, $first->fresh()->identityAt('2026-02-01'));
        $this->assertSame('Tag Green 5 · '.$first->code, $before);
        foreach ([$weight, $health, $medication] as $record) {
            $this->assertSame($first->id, $record->fresh()->pig->id);
            $this->assertSame(0, $record->newQuery()->where('pig_id', $second->id)->count());
        }
        $this->assertNull($first->fresh()->tagAt('2026-08-01'));
        $this->assertSame('', $first->fresh()->ear_tag_number);
    }

    public function test_allocator_excludes_every_unavailable_status_and_handles_large_numbers(): void
    {
        foreach ([EarTag::IN_USE, EarTag::PENDING_RECOVERY, EarTag::LOST, EarTag::DAMAGED, EarTag::RESERVED, EarTag::RETIRED] as $index => $status) {
            EarTag::create(['organization_id' => $this->organization->id, 'ear_tag_color_id' => $this->green->id, 'tag_number' => (string) ($index + 1), 'normalized_tag_number' => (string) ($index + 1), 'status' => $status]);
        }
        $this->assertSame('7', app(EarTagAllocator::class)->propose($this->organization->id, $this->green)[0]['tag_number']);
        $this->assertSame('999999999999999999999999', EarTag::normalizeNumber('00999999999999999999999999'));
    }

    public function test_database_rejects_an_open_assignment_with_a_closed_marker(): void
    {
        $row = app(EarTagAssignmentService::class)->assign($this->pig('121'), $this->green, '5', $this->actor);
        $this->expectException(QueryException::class);
        \Illuminate\Support\Facades\DB::table('ear_tag_assignments')->where('id', $row->id)->update(['is_current' => null]);
    }

    public function test_failed_replacement_rolls_back_release_and_cache(): void
    {
        $service = app(EarTagAssignmentService::class);
        $first = $this->pig('131');
        $old = $service->assign($first, $this->green, '5', $this->actor);
        $service->assign($this->pig('132'), $this->green, '6', $this->actor);
        try {
            app(ReplaceEarTagService::class)->execute($first, $this->actor, ['old_tag_status' => EarTag::DAMAGED, 'ear_tag_color_id' => $this->green->id, 'ear_tag_number' => '6', 'reason' => 'Damaged fastener']);
            $this->fail('An occupied replacement must fail.');
        } catch (StockValidationException $exception) {
            $this->assertTrue($old->fresh()->is_current);
            $this->assertSame(EarTag::IN_USE, $old->tag->fresh()->status);
            $this->assertSame('5', $first->fresh()->ear_tag_number);
        }
    }

    public function test_tag_search_history_orders_current_holder_first_and_keeps_archive_link(): void
    {
        $service = app(EarTagAssignmentService::class);
        $first = $this->pig('141');
        $old = $service->assign($first, $this->green, '5', $this->actor);
        $service->release($old, EarTag::AVAILABLE, 'archive', $this->actor);
        $first->refresh()->delete();
        $second = $this->pig('142');
        $service->assign($second, $this->green, '5', $this->actor);
        $this->actingAs($this->actor)->get(route('ear-tags.history', $old->ear_tag_id))->assertOk()->assertSeeInOrder(['Current holder', $second->code, 'Previous holder', $first->code]);
    }

    public function test_assignment_corrections_are_audited_and_cannot_change_ownership(): void
    {
        $row = app(EarTagAssignmentService::class)->assign($this->pig('151'), $this->green, '5', $this->actor, '2026-01-02');
        $this->actingAs($this->actor)->patch(route('ear-tags.correct', $row), ['assigned_at' => '2026-01-01', 'correction_reason' => 'Corrected from original farm register'])->assertSessionHasNoErrors();
        $this->assertSame('2026-01-01', $row->fresh()->assigned_at->toDateString());
        $this->assertSame('Corrected from original farm register', $row->fresh()->correction_reason);
        $this->expectException(\LogicException::class);
        $row->delete();
    }

    public function test_status_change_rolls_back_if_audit_storage_fails(): void
    {
        $row = app(EarTagAssignmentService::class)->assign($this->pig('161'), $this->green, '5', $this->actor);
        app(EarTagAssignmentService::class)->release($row, EarTag::PENDING_RECOVERY, 'archive', $this->actor);
        $audit = \Mockery::mock(\App\Services\Audit\AuditTrailService::class);
        $audit->shouldReceive('log')->once()->andReturnNull();
        $service = new \App\Services\Stock\Tags\EarTagAdministration($audit);
        try {
            $service->reconcile($row->tag, $this->actor, ['ear_tag_color_id' => $this->green->id, 'status' => EarTag::AVAILABLE]);
            $this->fail('A status change must not survive an audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('audit failed', $exception->getMessage());
            $this->assertSame(EarTag::PENDING_RECOVERY, $row->tag->fresh()->status);
        }
    }

    public function test_history_correction_rolls_back_if_audit_storage_fails(): void
    {
        $row = app(EarTagAssignmentService::class)->assign($this->pig('162'), $this->green, '5', $this->actor, '2026-01-02');
        $audit = \Mockery::mock(\App\Services\Audit\AuditTrailService::class);
        $audit->shouldReceive('log')->once()->andReturnNull();
        $service = new \App\Services\Stock\Tags\EarTagAdministration($audit);
        try {
            $service->correct($row, $this->actor, ['assigned_at' => '2026-01-01', 'correction_reason' => 'Original register checked']);
            $this->fail('A correction must not survive an audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('audit failed', $exception->getMessage());
            $this->assertSame('2026-01-02', $row->fresh()->assigned_at->toDateString());
            $this->assertNull($row->fresh()->correction_reason);
        }
    }

    public function test_death_recovery_reuses_tag_without_moving_death_record(): void
    {
        $pig = $this->pig('171');
        $assignment = app(EarTagAssignmentService::class)->assign($pig, $this->green, '5', $this->actor);
        $this->actingAs($this->actor)->post(route('pigs.record-death', $pig), [
            'death_date' => now()->toDateString(), 'death_cause' => 'disease',
            'disposal_method' => 'burial', 'tag_outcome' => EarTag::AVAILABLE,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $death = \App\Models\PigDeath::where('pig_id', $pig->id)->firstOrFail();
        $this->assertSoftDeleted('pigs_management', ['id' => $pig->id]);
        $this->assertSame(EarTag::AVAILABLE, $assignment->tag->fresh()->status);
        $second = $this->pig('172');
        app(EarTagAssignmentService::class)->assign($second, $this->green, '5', $this->actor);
        $this->assertSame($pig->id, $death->fresh()->pig->id);
        $this->assertSame('Tag Green 5 · '.$pig->code, $death->fresh()->pig->identityAt($death->death_date));
        $this->assertDatabaseMissing('pig_deaths', ['pig_id' => $second->id]);
    }

    public function test_sale_with_tag_leaving_farm_retires_it_and_preserves_sale_identity(): void
    {
        $pig = $this->pig('173');
        $assignment = app(EarTagAssignmentService::class)->assign($pig, $this->green, '5', $this->actor);
        $sale = app(\App\Services\Sales\RecordPigSaleService::class)->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(), 'sale_weight' => 80, 'weight_type' => 'live',
            'sale_purpose' => 'market', 'payment_status' => 'pending', 'tag_outcome' => EarTag::RETIRED,
        ]);
        $this->assertSame(EarTag::RETIRED, $assignment->tag->fresh()->status);
        $this->assertNull($assignment->fresh()->is_current);
        $this->assertSame('Tag Green 5 · '.$pig->code, $sale->fresh()->pig->identityAt($sale->sale_date));
        $this->assertNotContains('5', array_column(app(EarTagAllocator::class)->propose($this->organization->id, $this->green, 8), 'tag_number'));
    }

    public function test_database_rejects_cross_organisation_assignment_even_without_the_service(): void
    {
        $pig = $this->pig('174');
        $assignment = app(EarTagAssignmentService::class)->assign($pig, $this->green, '5', $this->actor);
        $other = Organization::create(['name' => 'Other', 'slug' => 'other-cross-assignment']);
        $this->expectException(QueryException::class);
        \Illuminate\Support\Facades\DB::table('ear_tag_assignments')->where('id', $assignment->id)->update(['organization_id' => $other->id]);
    }

    public function test_non_admin_cannot_correct_history_or_reconcile_status(): void
    {
        $assignment = app(EarTagAssignmentService::class)->assign($this->pig('175'), $this->green, '5', $this->actor);
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'role' => 'dataentry']);
        $this->actingAs($user)->patch(route('ear-tags.correct', $assignment), [
            'assigned_at' => '2026-01-01', 'correction_reason' => 'Unauthorised correction attempt',
        ])->assertForbidden();
        $this->patch(route('settings.ear-tags.reconciliation.update', $assignment->ear_tag_id), [
            'ear_tag_color_id' => $this->green->id, 'status' => EarTag::AVAILABLE,
        ])->assertForbidden();
        $this->assertTrue($assignment->fresh()->is_current);
    }

    public function test_staged_backfill_preserves_unique_legacy_cache_for_multiple_archived_animals(): void
    {
        \Illuminate\Support\Facades\Schema::table('pigs_management', function ($table) {
            $table->unique(['organization_id', 'ear_tag_number'], 'pigs_org_ear_tag_unique');
        });
        $first = $this->pig('181');
        $second = $this->pig('182');
        $first->delete();
        $second->delete();
        $this->artisan('ear-tags:reconcile', ['--backfill' => true])->assertSuccessful();
        foreach ([$first, $second] as $pig) {
            $this->assertSame($pig->ear_tag_number, $pig->fresh()->ear_tag_number);
            $history = $pig->earTagAssignments()->firstOrFail();
            $this->assertNull($history->is_current);
            $this->assertSame(EarTag::PENDING_RECOVERY, $history->tag->status);
        }
    }

    public function test_activation_refuses_unreconciled_legacy_animals(): void
    {
        $this->pig('183');
        $migration = require database_path('migrations/2028_10_13_000000_enforce_organization_tag_identity.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tag activation blocked by reconciliation');
        $migration->up();
    }

    public function test_archive_identity_survives_a_delay_after_the_tag_was_released(): void
    {
        $pig = $this->pig('184');
        $service = app(EarTagAssignmentService::class);
        $assignment = $service->assign($pig, $this->green, '5', $this->actor, '2026-01-01 10:00:00');
        $service->release($assignment, EarTag::AVAILABLE, 'sale', $this->actor, '2026-02-01 10:00:00');
        $pig->refresh()->delete();
        $this->assertNull($pig->tagAt('2026-02-01 10:00:01'));
        $this->assertSame('Tag Green 5 · '.$pig->code, $pig->lastRecordedIdentity());
        $service->assign($this->pig('185'), $this->green, '5', $this->actor);
        $this->assertSame('Tag Green 5 · '.$pig->code, $pig->fresh()->lastRecordedIdentity());
    }

    public function test_restoring_a_wrongly_archived_animal_gives_back_its_pending_recovery_tag(): void
    {
        $pig = $this->pig('186');
        $old = app(EarTagAssignmentService::class)->assign($pig, $this->green, '7', $this->actor);
        $pig->refresh()->delete();
        $this->assertSame(EarTag::PENDING_RECOVERY, $old->tag->fresh()->status);

        $restored = app(RestoreArchivedRecordService::class)->restorePig($pig, $this->actor);

        $current = $restored->fresh()->currentEarTagAssignment;
        $this->assertSame($old->ear_tag_id, $current->ear_tag_id);
        $this->assertSame('7', $restored->fresh()->ear_tag_number);
        $this->assertSame(EarTag::IN_USE, $old->tag->fresh()->status);
        $this->assertNull($old->fresh()->is_current);
        $this->assertSame(2, EarTagAssignment::where('pig_id', $pig->id)->count());
    }

    public function test_restore_does_not_reuse_a_lost_former_tag(): void
    {
        $pig = $this->pig('187');
        $service = app(EarTagAssignmentService::class);
        $old = $service->assign($pig, $this->green, '8', $this->actor);
        $service->release($old, EarTag::LOST, 'archive', $this->actor);
        $pig->refresh()->delete();

        $restored = app(RestoreArchivedRecordService::class)->restorePig($pig, $this->actor);

        $this->assertNotSame($old->ear_tag_id, $restored->fresh()->currentEarTagAssignment->ear_tag_id);
        $this->assertSame(EarTag::LOST, $old->tag->fresh()->status);
    }

    public function test_historical_labels_distinguish_tags_that_share_a_number(): void
    {
        $yellow = EarTagColor::create(['organization_id' => $this->organization->id, 'name' => 'Yellow', 'normalized_name' => 'yellow']);
        $service = app(EarTagAssignmentService::class);
        $first = $this->pig('188');
        $second = $this->pig('189');
        $service->assign($first, $this->green, '1', $this->actor, '2026-01-01');
        $service->assign($second, $yellow, '1', $this->actor, '2026-01-01');

        $this->assertSame('Tag Green 1 · '.$first->code, $first->identityAt('2026-02-01'));
        $this->assertSame('Tag Yellow 1 · '.$second->code, $second->identityAt('2026-02-01'));
        $this->assertSame('Yellow 1', $second->tagLabelAt(null));
        $this->assertSame('1', $second->tagAt('2026-02-01'));
    }

    public function test_unconfirmed_legacy_colour_is_omitted_from_labels(): void
    {
        $pig = $this->pig('190');
        app(EarTagShadowService::class)->syncNewPig($pig, $this->actor);

        $this->assertSame('Tag 190 · '.$pig->code, $pig->fresh()->identityAt());
    }

    public function test_preloaded_history_resolves_exactly_like_the_query_path(): void
    {
        $yellow = EarTagColor::create(['organization_id' => $this->organization->id, 'name' => 'Yellow', 'normalized_name' => 'yellow']);
        $service = app(EarTagAssignmentService::class);
        $pig = $this->pig('191');
        $first = $service->assign($pig, $this->green, '9', $this->actor, '2026-01-01 08:00:00');
        $service->release($first, EarTag::LOST, 'replacement', $this->actor, '2026-03-05 09:00:00');
        $service->assign($pig, $yellow, '9', $this->actor, '2026-03-05 10:00:00');
        $legacy = PigManagement::create([
            'organization_id' => $this->organization->id, 'ear_tag_number' => '192', 'code' => 'TAG-PIG-192',
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => now()->subMonths(4)->toDateString(),
        ]);

        $dates = [null, '2025-12-31', '2026-02-01', '2026-03-05', '2026-03-05 09:30:00', '2026-03-05 10:30:00', '2026-06-01'];
        foreach ([$pig, $legacy] as $animal) {
            $loaded = PigManagement::with(\App\Services\Stock\Tags\EarTagHistory::RELATION)->findOrFail($animal->id);
            $queried = PigManagement::findOrFail($animal->id);
            foreach ($dates as $date) {
                $this->assertSame($queried->identityAt($date), $loaded->identityAt($date), 'Date '.($date ?? 'current'));
                $this->assertSame($queried->tagAt($date), $loaded->tagAt($date), 'Date '.($date ?? 'current'));
            }
            $this->assertSame($queried->lastRecordedIdentity(), $loaded->lastRecordedIdentity());
        }
        // Same-day replacement: date-only events resolve to the latest tag that day.
        $this->assertSame('Tag Yellow 9 · '.$pig->code, PigManagement::with(\App\Services\Stock\Tags\EarTagHistory::RELATION)->find($pig->id)->identityAt('2026-03-05'));
    }

    public function test_preloaded_history_keeps_report_queries_constant(): void
    {
        $service = app(EarTagAssignmentService::class);
        foreach (range(1, 12) as $n) {
            $service->assign($this->pig((string) (300 + $n)), $this->green, (string) (300 + $n), $this->actor, '2026-01-01');
        }

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $labels = PigManagement::with(\App\Services\Stock\Tags\EarTagHistory::RELATION)->get()
            ->map(fn (PigManagement $pig) => $pig->identityAt('2026-02-01'));
        $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertCount(12, $labels->filter(fn ($label) => str_starts_with($label, 'Tag Green 3')));
        // Animals, assignments, tags, colours: independent of the row count.
        $this->assertLessThanOrEqual(4, $queries);
    }

    private function pig(string $tag): PigManagement
    {
        return PigManagement::create([
            'organization_id' => $this->organization->id,
            'ear_tag_number' => $tag,
            'code' => 'TAG-PIG-'.$tag,
            'breed' => 'LW', 'gender' => 'F', 'stock_type' => 'standard',
            'date_of_birth' => now()->subMonths(4)->toDateString(),
        ]);
    }
}
