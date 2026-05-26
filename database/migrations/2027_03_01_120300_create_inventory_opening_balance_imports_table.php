<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — opening balance import audit table.
 *
 * Records each opening-balance import attempt: the SHA-256 hash of its
 * canonicalised payload, the row count, the cutover date, and the final
 * outcome. The hash provides idempotency: re-uploading the same payload
 * returns the existing audit row instead of duplicating the movement.
 *
 * Lifecycle of an import:
 *
 *   1. Service receives rows + cutover date.
 *   2. Service canonicalises rows + computes hash.
 *   3. Service looks up existing (org, kind, hash). If found and COMPLETED,
 *      returns it short-circuit.
 *   4. Service inserts audit row with status='pending'.
 *   5. Service opens a transaction and writes inventory_movements rows.
 *   6. On success, audit row flips to status='completed'. On failure,
 *      audit row flips to status='failed' with error_message captured.
 *
 * Why the audit row exists in 'pending' before the import writes?
 *   The UNIQUE on (org, kind, hash) is the race-safe dedup. If two
 *   concurrent imports try the same payload simultaneously, exactly one
 *   wins the audit-row insert; the other catches the unique violation
 *   and treats it as a duplicate. The transaction containing the
 *   movement writes is started AFTER the audit row exists, so the
 *   "pending" status is the cleanup marker if the writer dies mid-flight.
 *
 * Stuck pending / stuck failed:
 *   A pending row with no completion timestamp older than a few minutes
 *   indicates the writer crashed. The service refuses to retry such
 *   rows automatically (the partial state is unclear); operators must
 *   investigate and archive the stuck row to retry. The 'archived'
 *   status lets accountants suppress a failed import without losing
 *   the audit history.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inventory_opening_balance_imports')) {
            return;
        }

        Schema::create('inventory_opening_balance_imports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete();

            // Which kind this import covers ('raw_material' or
            // 'finished_feed'). Splitting per-kind makes the hash dedup
            // tighter: re-uploading a RM CSV after fixing one row in a
            // separate finished_feed CSV doesn't collide.
            $table->string('item_kind', 30);

            // 'manual' = entered via form, 'csv' = uploaded CSV.
            $table->string('import_mode', 20);

            // SHA-256 hex hash of the canonicalised payload (sorted JSON
            // for manual, normalised CSV text for upload). 64 chars hex.
            $table->string('import_hash', 64);

            // Original filename for csv uploads — informational only.
            $table->string('original_filename', 255)->nullable();

            // Business date of the opening balance.
            $table->date('cutover_date');

            // Declared row count from the import payload (validated
            // against actual rows persisted via persistedRowCount()).
            $table->unsignedInteger('row_count');

            // Lifecycle status.
            $table->string('status', 20);   // 'pending' | 'completed' | 'failed' | 'archived'

            // Capture failure details if the transaction rolls back.
            // Truncated to 1000 chars; full stack trace goes to the log.
            $table->text('error_message')->nullable();

            // Audit columns.
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->foreign('performed_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // The dedup invariant — same payload + same org + same kind
            // is idempotent. Two different orgs uploading the same
            // payload independently is NOT a duplicate.
            $table->unique(
                ['organization_id', 'item_kind', 'import_hash'],
                'inventory_opening_imports_hash_unique',
            );

            $table->index(['organization_id', 'status'], 'inventory_opening_imports_status_idx');
            $table->index(['cutover_date'],              'inventory_opening_imports_date_idx');
        });

        // Add the deferred FK on inventory_movements.opening_balance_import_id.
        if (Schema::hasTable('inventory_movements') &&
            Schema::hasColumn('inventory_movements', 'opening_balance_import_id')) {
            try {
                Schema::table('inventory_movements', function (Blueprint $table) {
                    $table->foreign('opening_balance_import_id', 'inventory_movements_opening_fk')
                          ->references('id')
                          ->on('inventory_opening_balance_imports')
                          ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                logger()->warning(
                    'Could not add inventory_movements.opening_balance_import_id FK: '
                    . $e->getMessage()
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_movements')) {
            try {
                Schema::table('inventory_movements', function (Blueprint $table) {
                    $table->dropForeign('inventory_movements_opening_fk');
                });
            } catch (\Throwable) {}
        }

        Schema::dropIfExists('inventory_opening_balance_imports');
    }
};
