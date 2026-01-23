<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pigs_management', function (Blueprint $table) {
            $table->unsignedBigInteger('local_id')->nullable()->after('workspace_id');
        });

        // Populate local_id for existing records (workspace-scoped sequential IDs)
        \DB::statement('
            UPDATE pigs_management p1
            SET local_id = (
                SELECT COUNT(*) + 1
                FROM pigs_management p2
                WHERE p2.workspace_id = p1.workspace_id
                AND p2.id < p1.id
            )
            WHERE local_id IS NULL
        ');

        // Make local_id not null and add unique constraint
        Schema::table('pigs_management', function (Blueprint $table) {
            $table->unsignedBigInteger('local_id')->nullable(false)->change();
            $table->unique(['workspace_id', 'local_id'], 'pigs_workspace_local_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigs_management', function (Blueprint $table) {
            $table->dropUnique('pigs_workspace_local_unique');
            $table->dropColumn('local_id');
        });
    }
};
