<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen operational quantities to the existing ledger precision.
        Schema::table('feed_batches', fn (Blueprint $table) => $table->decimal('batch_size_kg', 14, 4)->change());
        Schema::table('feed_batch_items', function (Blueprint $table) {
            $table->decimal('quantity_used', 14, 4)->change();
            $table->decimal('unit_cost', 18, 6)->default(0)->change();
            $table->decimal('total_cost', 18, 4)->default(0)->change();
        });
        Schema::create('feeding_standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('stage', 40);
            $table->foreignId('feed_type_id')->constrained()->restrictOnDelete();
            $table->decimal('daily_kg', 12, 4);
            $table->string('stage_basis', 40);
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'stage', 'effective_from'], 'feeding_standard_effective_unique');
        });
        Schema::create('feed_stage_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('pig_id')->constrained('pigs_management')->restrictOnDelete();
            $table->string('previous_stage', 40)->nullable();
            $table->string('stage', 40)->nullable();
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'pig_id', 'created_at']);
        });
        Schema::create('feed_recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('feed_type_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            $table->json('ingredients');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'feed_type_id', 'effective_from'], 'feed_recipe_effective_unique');
        });
        Schema::create('feed_production_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('feed_type_id')->constrained()->restrictOnDelete();
            $table->decimal('planned_quantity_kg', 14, 4);
            $table->date('production_date');
            $table->json('recipe_snapshot');
            $table->string('status', 20)->default('planned');
            $table->foreignId('feed_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('submission_key');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'submission_key'], 'feed_plan_submission_unique');
        });
        Schema::table('feed_batches', function (Blueprint $table) {
            $table->uuid('submission_key')->nullable();
            $table->json('recipe_snapshot')->nullable();
            $table->decimal('planned_quantity_kg', 14, 4)->nullable();
            $table->unique(['organization_id', 'submission_key'], 'feed_batch_submission_unique');
        });
        Schema::table('feed_issues', function (Blueprint $table) {
            $table->uuid('submission_key')->nullable();
            $table->decimal('planned_quantity_kg', 14, 4)->nullable();
            $table->json('planning_snapshot')->nullable();
            $table->unique(['organization_id', 'submission_key'], 'feed_issue_submission_unique');
        });
        foreach (['feed_types', 'raw_materials'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        }
    }

    public function down(): void
    {
        // Widened decimal columns deliberately remain wide on rollback to avoid rounding data.
        // Once used, these snapshots are operational history. Restoring a
        // verified backup is safer than discarding it with a schema rollback.
        foreach (['feeding_standards', 'feed_stage_overrides', 'feed_recipe_versions', 'feed_production_plans'] as $name) {
            if (\Illuminate\Support\Facades\DB::table($name)->exists()) {
                throw new RuntimeException('Feed history exists. Restore a verified backup instead of dropping snapshots.');
            }
        }
        if (\Illuminate\Support\Facades\DB::table('feed_issues')->whereNotNull('planning_snapshot')->exists()
            || \Illuminate\Support\Facades\DB::table('feed_batches')->whereNotNull('recipe_snapshot')->exists()) {
            throw new RuntimeException('Feed operational snapshots exist; rollback would lose history.');
        }
        Schema::dropIfExists('feed_production_plans');
        Schema::dropIfExists('feed_recipe_versions');
        Schema::dropIfExists('feed_stage_overrides');
        Schema::dropIfExists('feeding_standards');
        foreach (['feed_batches' => 'feed_batch_submission_unique', 'feed_issues' => 'feed_issue_submission_unique'] as $name => $index) {
            Schema::table($name, function (Blueprint $table) use ($index, $name) {
                $table->dropUnique($index);
                $table->dropColumn(['submission_key', 'planned_quantity_kg', $name === 'feed_batches' ? 'recipe_snapshot' : 'planning_snapshot']);
            });
        }
        foreach (['feed_types', 'raw_materials'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('is_active'));
        }
    }
};
