<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ear_tag_colors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('normalized_name', 60);
            $table->string('short_code', 20)->nullable();
            $table->string('hex_color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'normalized_name'], 'ear_tag_colors_org_name_unique');
        });

        Schema::create('ear_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ear_tag_color_id')->constrained('ear_tag_colors')->restrictOnDelete();
            $table->string('tag_number', 50);
            $table->string('normalized_tag_number', 50);
            $table->string('status', 30)->default('available');
            $table->text('condition_notes')->nullable();
            $table->string('reserved_for')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'ear_tag_color_id', 'normalized_tag_number'],
                'ear_tags_org_color_number_unique'
            );
            $table->index(['organization_id', 'status']);
        });

        Schema::create('ear_tag_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ear_tag_id')->constrained('ear_tags')->restrictOnDelete();
            $table->foreignId('pig_id')->constrained('pigs_management')->restrictOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('released_at')->nullable();
            $table->string('release_reason', 30)->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('correction_reason')->nullable();
            // Open rows carry 1; closed rows carry NULL. Both MariaDB and
            // SQLite permit repeated NULL values in a unique index, giving us
            // a portable database guard for one open assignment per entity.
            $table->boolean('is_current')->nullable()->default(1);
            $table->timestamps();

            $table->unique(['ear_tag_id', 'is_current'], 'ear_tag_assignments_one_open_tag');
            $table->unique(['pig_id', 'is_current'], 'ear_tag_assignments_one_open_pig');
            $table->index(['organization_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ear_tag_assignments');
        Schema::dropIfExists('ear_tags');
        Schema::dropIfExists('ear_tag_colors');
    }
};
