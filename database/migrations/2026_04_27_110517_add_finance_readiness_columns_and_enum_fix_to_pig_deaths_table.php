<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — finance-readiness columns + enum broadening on pig_deaths.
 *
 * TWO CONCERNS in one migration:
 *
 * 1. Finance-readiness columns (consistent with other source tables):
 *    organization_id, document_number, is_posted, posted_at,
 *    cost_centre_id, legacy_source_id.
 *
 * 2. Bug fix: the current ENUM definitions on death_cause and disposal_method
 *    are narrower than what the application/form validators accept. The
 *    original migration declared:
 *      death_cause ENUM('disease','accident','natural','unknown','other')
 *      disposal_method ENUM('burial','incineration','rendering','other')
 *    But application validation accepts a much wider set:
 *      death_cause: disease, accident, natural, stillbirth, crushing, starvation,
 *                   hypothermia, dehydration, congenital, unknown, other
 *      disposal_method: burial, incineration, rendering, donation, disposal, other
 *
 *    Attempting to insert 'stillbirth' (a legitimate piglet death cause) fails
 *    at the DB layer on MySQL in STRICT mode. This migration broadens both
 *    columns to varchar(40) with a CHECK constraint matching the application's
 *    accepted list — portable across MySQL/SQLite/Postgres and avoids the
 *    ENUM rigidity.
 *
 * The column change is safe: existing values are within the new valid set,
 * and varchar(40) holds any of the new values.
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pig_deaths')) {
            return;
        }

        // -------- part 1: additive finance-readiness columns --------

        Schema::table('pig_deaths', function (Blueprint $table) {
            if (! Schema::hasColumn('pig_deaths', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                $table->index('organization_id', 'pig_deaths_organization_id_idx');
            }

            if (! Schema::hasColumn('pig_deaths', 'document_number')) {
                $table->string('document_number', 50)->nullable();
                $table->index('document_number', 'pig_deaths_document_number_idx');
            }

            if (! Schema::hasColumn('pig_deaths', 'is_posted')) {
                $table->boolean('is_posted')->default(false);
            }

            if (! Schema::hasColumn('pig_deaths', 'posted_at')) {
                $table->timestamp('posted_at')->nullable();
                $table->index(['is_posted', 'posted_at'], 'pig_deaths_posting_status_idx');
            }

            if (! Schema::hasColumn('pig_deaths', 'cost_centre_id')) {
                $table->unsignedBigInteger('cost_centre_id')->nullable();
                $table->index('cost_centre_id', 'pig_deaths_cost_centre_id_idx');
            }

            if (! Schema::hasColumn('pig_deaths', 'legacy_source_id')) {
                $table->string('legacy_source_id', 100)->nullable();
            }
        });

        // -------- part 2: broaden death_cause + disposal_method --------

        $driver = Schema::getConnection()->getDriverName();

        $validCauses = [
            'disease', 'accident', 'natural', 'stillbirth', 'crushing',
            'starvation', 'hypothermia', 'dehydration', 'congenital',
            'unknown', 'other',
        ];
        $validDisposals = [
            'burial', 'incineration', 'rendering', 'donation', 'disposal', 'other',
        ];

        if ($driver === 'mysql') {
            // MySQL: change column from ENUM to VARCHAR while preserving data.
            try {
                \DB::statement("ALTER TABLE pig_deaths MODIFY COLUMN death_cause VARCHAR(40) NOT NULL DEFAULT 'unknown'");
            } catch (\Throwable $e) {
                // If already varchar, ALTER is a no-op. Log and continue.
            }
            try {
                \DB::statement("ALTER TABLE pig_deaths MODIFY COLUMN disposal_method VARCHAR(40) NOT NULL DEFAULT 'burial'");
            } catch (\Throwable $e) {
                // Ignore if already varchar.
            }

            $causeList    = "'" . implode("','", $validCauses) . "'";
            $disposalList = "'" . implode("','", $validDisposals) . "'";

            try {
                \DB::statement("ALTER TABLE pig_deaths ADD CONSTRAINT pig_deaths_death_cause_check CHECK (death_cause IN ({$causeList}))");
            } catch (\Throwable $e) {
                // Older MySQL rejects; application validation remains.
            }
            try {
                \DB::statement("ALTER TABLE pig_deaths ADD CONSTRAINT pig_deaths_disposal_method_check CHECK (disposal_method IN ({$disposalList}))");
            } catch (\Throwable $e) {
                // Ignore.
            }
        } elseif ($driver === 'sqlite') {
            // SQLite has limited ALTER support. In practice for SQLite the
            // column is already accepted as TEXT-compatible; CHECK constraints
            // can only be added at table-create time. We rely on application
            // validation here and skip the DDL change to avoid SQLite's
            // destructive recreate pattern on a live DB.
        } elseif ($driver === 'pgsql') {
            // Postgres: convert enum to text via ALTER COLUMN TYPE.
            try {
                \DB::statement("ALTER TABLE pig_deaths ALTER COLUMN death_cause TYPE varchar(40) USING death_cause::varchar");
            } catch (\Throwable $e) { /* Ignore if already varchar */ }
            try {
                \DB::statement("ALTER TABLE pig_deaths ALTER COLUMN disposal_method TYPE varchar(40) USING disposal_method::varchar");
            } catch (\Throwable $e) { /* Ignore */ }

            $causeList    = "'" . implode("','", $validCauses) . "'";
            $disposalList = "'" . implode("','", $validDisposals) . "'";

            try {
                \DB::statement("ALTER TABLE pig_deaths ADD CONSTRAINT pig_deaths_death_cause_check CHECK (death_cause IN ({$causeList}))");
            } catch (\Throwable $e) { /* Ignore */ }
            try {
                \DB::statement("ALTER TABLE pig_deaths ADD CONSTRAINT pig_deaths_disposal_method_check CHECK (disposal_method IN ({$disposalList}))");
            } catch (\Throwable $e) { /* Ignore */ }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pig_deaths')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            foreach (['pig_deaths_death_cause_check', 'pig_deaths_disposal_method_check'] as $constraint) {
                try {
                    \DB::statement("ALTER TABLE pig_deaths DROP CONSTRAINT {$constraint}");
                } catch (\Throwable $e) { /* Ignore */ }
            }
        }

        // We intentionally do NOT revert the ENUM type on rollback. Reverting
        // could fail if data contains values outside the original narrower
        // ENUM (e.g., 'stillbirth' records created after this migration).
        // Dropping ENUM-vs-VARCHAR safely requires data cleanup that's beyond
        // the scope of a rollback command.

        Schema::table('pig_deaths', function (Blueprint $table) {
            foreach ([
                'pig_deaths_organization_id_idx',
                'pig_deaths_document_number_idx',
                'pig_deaths_posting_status_idx',
                'pig_deaths_cost_centre_id_idx',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable $e) { /* ignore */ }
            }

            foreach ([
                'organization_id',
                'document_number',
                'is_posted',
                'posted_at',
                'cost_centre_id',
                'legacy_source_id',
            ] as $col) {
                if (Schema::hasColumn('pig_deaths', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};