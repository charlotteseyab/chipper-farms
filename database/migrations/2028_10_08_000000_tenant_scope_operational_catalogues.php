<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string|null> */
    private array $tables = [
        'structures' => 'structures_organization_id_index',
        'rooms' => 'rooms_organization_id_index',
        'groups' => 'groups_organization_id_index',
        'raw_materials' => 'raw_materials_organization_id_index',
        'feed_types' => 'feed_types_organization_id_index',
        'ingredients' => 'ingredients_organization_id_index',
        'expected_weight_standards' => 'expected_weight_standards_organization_id_index',
        'performance_metrics' => 'performance_metrics_organization_id_index',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $index) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'organization_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->unsignedBigInteger('organization_id')->nullable();
                    $blueprint->index('organization_id', $index);
                });
            }
        }

        $fallbackOrganizationId = DB::table('organizations')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        if ($fallbackOrganizationId !== null) {
            // Preserve existing installations by assigning legacy global rows
            // to the original farm. New farms intentionally start clean.
            foreach (array_keys($this->tables) as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereNull('organization_id')->update([
                        'organization_id' => $fallbackOrganizationId,
                    ]);
                }
            }

            if (Schema::hasTable('structures') && Schema::hasColumn('structures', 'created_by')) {
                DB::table('structures')->orderBy('id')->eachById(function (object $row): void {
                    $organizationId = DB::table('users')->where('id', $row->created_by)->value('organization_id');
                    if ($organizationId) {
                        DB::table('structures')->where('id', $row->id)->update(['organization_id' => $organizationId]);
                    }
                });
            }

            if (Schema::hasTable('rooms')) {
                DB::table('rooms')->orderBy('id')->eachById(function (object $row): void {
                    $organizationId = DB::table('structures')->where('id', $row->structure_id)->value('organization_id');
                    if ($organizationId) {
                        DB::table('rooms')->where('id', $row->id)->update(['organization_id' => $organizationId]);
                    }
                });
            }

            if (Schema::hasTable('expected_weight_standards')) {
                DB::table('expected_weight_standards')->orderBy('id')->eachById(function (object $row): void {
                    $organizationId = DB::table('users')->where('id', $row->created_by)->value('organization_id');
                    if ($organizationId) {
                        DB::table('expected_weight_standards')->where('id', $row->id)->update(['organization_id' => $organizationId]);
                    }
                });
            }
        }

        $this->replaceGlobalUniqueIndexes();
    }

    private function replaceGlobalUniqueIndexes(): void
    {
        Schema::table('structures', function (Blueprint $table): void {
            $table->dropUnique('structures_code_unique');
            $table->unique(['organization_id', 'code'], 'structures_org_code_unique');
        });
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropUnique('ingredients_name_unique');
            $table->unique(['organization_id', 'name'], 'ingredients_org_name_unique');
        });
        Schema::table('feed_types', function (Blueprint $table): void {
            $table->dropUnique('feed_types_name_unique');
            $table->unique(['organization_id', 'name'], 'feed_types_org_name_unique');
        });
        Schema::table('performance_metrics', function (Blueprint $table): void {
            $table->dropUnique('performance_metrics_measure_unique');
            $table->unique(['organization_id', 'measure'], 'performance_metrics_org_measure_unique');
        });
        Schema::table('expected_weight_standards', function (Blueprint $table): void {
            $table->dropUnique('unique_weight_standard');
            $table->unique(
                ['organization_id', 'breed', 'gender', 'stock_type', 'week_number'],
                'weight_standard_org_unique',
            );
        });
    }

    public function down(): void
    {
        // This migration establishes a security boundary and intentionally
        // does not merge farm-owned catalogues back into global tables.
    }
};
