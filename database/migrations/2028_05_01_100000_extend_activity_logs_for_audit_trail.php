<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logs', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('user_id')->constrained('organizations')->nullOnDelete();
            }
            if (! Schema::hasColumn('activity_logs', 'module')) {
                $table->string('module')->nullable()->after('action')->index();
            }
            if (! Schema::hasColumn('activity_logs', 'auditable_type')) {
                $table->string('auditable_type')->nullable()->after('module');
            }
            if (! Schema::hasColumn('activity_logs', 'auditable_id')) {
                $table->unsignedBigInteger('auditable_id')->nullable()->after('auditable_type');
            }
            if (! Schema::hasColumn('activity_logs', 'record_label')) {
                $table->string('record_label')->nullable()->after('auditable_id');
            }
            if (! Schema::hasColumn('activity_logs', 'description')) {
                $table->text('description')->nullable()->after('record_label');
            }
            if (! Schema::hasColumn('activity_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('description');
            }
            if (! Schema::hasColumn('activity_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }

            $table->index(['auditable_type', 'auditable_id'], 'activity_logs_auditable_index');
            $table->index(['module', 'action', 'created_at'], 'activity_logs_module_action_created_index');
        });
    }

    /**
     * The foreign key must go before the column it constrains.
     *
     * This used to drop `organization_id` directly, which MariaDB refuses:
     *
     *   Cannot drop index 'activity_logs_organization_id_foreign':
     *   needed in a foreign key constraint
     *
     * `constrained()` in up() creates both a foreign key and the index it
     * depends on, and neither goes away by dropping the column. SQLite rebuilds
     * the table on a column drop and so never complained, which is why this
     * survived — the rollback was only ever exercised there.
     *
     * Dropped by COLUMN LIST rather than by a hand-written constraint name:
     * Laravel derives the real name from the column, and passing a name it did
     * not generate silently drops nothing (IMP-097, the same mistake in another
     * migration).
     */
    public function down(): void
    {
        if ($this->hasOrganizationForeignKey()) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
            });
        }

        // Index drops are conditional for the same reason the foreign key is:
        // a rollback that failed part-way leaves some of them already gone, and
        // an unconditional drop then fails on the retry — turning one bad
        // migration into a database nobody can roll forward or back.
        foreach ([
            'activity_logs_auditable_index',
            'activity_logs_module_action_created_index',
        ] as $index) {
            if ($this->hasIndex($index)) {
                Schema::table('activity_logs', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            foreach ([
                'new_values',
                'old_values',
                'description',
                'record_label',
                'auditable_id',
                'auditable_type',
                'module',
                'organization_id',
            ] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Whether the foreign key is actually present on this connection.
     *
     * Asked of the schema rather than wrapped in a try/catch, because a
     * try/catch around `$table->dropForeign()` catches nothing: inside a
     * `Schema::table()` closure that call only QUEUES the command, and the SQL
     * runs after the closure returns — outside the try. The first version of
     * this fix made exactly that mistake and still failed, on the opposite
     * error (`Can't DROP FOREIGN KEY ...; check that it exists`) after a partial
     * rollback had already removed it.
     */
    private function hasOrganizationForeignKey(): bool
    {
        if (! Schema::hasTable('activity_logs') || ! Schema::hasColumn('activity_logs', 'organization_id')) {
            return false;
        }

        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            // SQLite rebuilds the table on a column drop and has no named
            // constraint to remove.
            return false;
        }

        return $connection->selectOne(
            'SELECT 1 AS found
               FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = ?
              LIMIT 1',
            ['activity_logs', 'activity_logs_organization_id_foreign', 'FOREIGN KEY']
        ) !== null;
    }

    private function hasIndex(string $index): bool
    {
        if (! Schema::hasTable('activity_logs')) {
            return false;
        }

        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return true; // SQLite drops happily; let the builder handle it.
        }

        return $connection->selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
              LIMIT 1',
            ['activity_logs', $index]
        ) !== null;
    }
};
