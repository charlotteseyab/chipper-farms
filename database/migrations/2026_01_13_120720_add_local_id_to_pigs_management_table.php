
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('pigs_management', 'local_id')) {
            return;
        }

        $afterColumn = Schema::hasColumn('pigs_management', 'workspace_id') ? 'workspace_id' : 'id';

        Schema::table('pigs_management', function (Blueprint $table) use ($afterColumn) {
            $table->unsignedBigInteger('local_id')->nullable()->after($afterColumn);
        });

        if (Schema::hasColumn('pigs_management', 'workspace_id')) {
            DB::statement('
                UPDATE pigs_management p1
                SET local_id = (
                    SELECT COUNT(*) + 1
                    FROM pigs_management p2
                    WHERE p2.workspace_id = p1.workspace_id
                    AND p2.id < p1.id
                )
                WHERE local_id IS NULL
            ');

            Schema::table('pigs_management', function (Blueprint $table) {
                $table->unique(['workspace_id', 'local_id'], 'pigs_workspace_local_unique');
            });
        } else {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                $rows = DB::table('pigs_management')->orderBy('id')->pluck('id');
                $counter = 1;
                foreach ($rows as $id) {
                    DB::table('pigs_management')->where('id', $id)->update(['local_id' => $counter++]);
                }
            } else {
                DB::statement('SET @local_counter := 0');
                DB::statement('UPDATE pigs_management SET local_id = (@local_counter := @local_counter + 1) WHERE local_id IS NULL ORDER BY id');
            }

            Schema::table('pigs_management', function (Blueprint $table) {
                $table->unique('local_id', 'pigs_local_id_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigs_management', function (Blueprint $table) {
            if (Schema::hasColumn('pigs_management', 'workspace_id')) {
                $table->dropUnique('pigs_workspace_local_unique');
            } else {
                $table->dropUnique('pigs_local_id_unique');
            }

            $table->dropColumn('local_id');
        });
    }
};