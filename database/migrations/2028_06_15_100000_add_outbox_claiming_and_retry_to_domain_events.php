<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds atomic claiming, retry and dead-letter support to the finance outbox
 * (CF-P1-015).
 *
 * What was missing
 * ----------------
 * ProcessPendingDomainEventsJob selected pending events with a plain get() and
 * processed them with no claim, no lease and $tries = 1. The status column
 * supported only pending / processed / failed / skipped, so there was no state
 * in which to record "a worker is working on this", and no columns in which to
 * record who claimed it, when, or when it should next be attempted.
 *
 * On duplicate work, the existing design was better than the register implies:
 * journal_postings carries a real UNIQUE idempotency key
 * (journal_postings_idempotency_unique), and Subscriber treats
 * DuplicatePostingException as success. So two workers racing the same event
 * could not produce duplicate journals. That claim in the class docblock stands.
 *
 * The serious gap was elsewhere: FAILURE WAS TERMINAL. Any transient fault — a
 * lock-wait timeout, a dropped connection, a deadlock — moved the event to
 * 'failed' permanently, and the docblock justified not retrying on the grounds
 * that failed events are "already at terminal state". The consequence is that a
 * momentary blip silently and permanently drops a finance posting. `--replay-failed`
 * existed but required a human to notice, and nothing surfaced that there was
 * anything to notice.
 *
 * Columns added
 * -------------
 *  claimed_at     when the current worker claimed the event; drives stale-claim
 *                 recovery when a worker dies mid-flight.
 *  claimed_by     opaque per-run worker token, so a worker can re-select exactly
 *                 the rows its own atomic UPDATE won.
 *  next_retry_at  earliest time a re-queued event may be attempted again; NULL
 *                 means "eligible now".
 *  last_error_at  when the most recent failure occurred, for triage.
 *
 * `status` is a varchar, so the new 'processing' value needs no schema change.
 *
 * processing_attempts already exists as TINYINT UNSIGNED (max 255), which is
 * ample for a retry ceiling in single digits and is left as-is.
 *
 * Safety: purely additive. All new columns are nullable with no default
 * backfill required — existing rows read as "never claimed, eligible now",
 * which is the correct interpretation of pre-migration state. down() drops
 * only what up() added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domain_events')) {
            return;
        }

        Schema::table('domain_events', function (Blueprint $table) {
            if (! Schema::hasColumn('domain_events', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable()->after('processing_attempts');
            }

            if (! Schema::hasColumn('domain_events', 'claimed_by')) {
                $table->string('claimed_by', 100)->nullable()->after('claimed_at');
            }

            if (! Schema::hasColumn('domain_events', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable()->after('claimed_by');
            }

            if (! Schema::hasColumn('domain_events', 'last_error_at')) {
                $table->timestamp('last_error_at')->nullable()->after('next_retry_at');
            }
        });

        // Index supporting the claim query: find eligible pending work in
        // occurrence order. Without it the claim UPDATE degrades to a scan as
        // the table grows, and the outbox table only ever grows.
        Schema::table('domain_events', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('domain_events'))
                ->pluck('name')
                ->map(fn ($n) => strtolower((string) $n))
                ->all();

            if (! in_array('domain_events_claim_idx', $indexes, true)) {
                $table->index(['status', 'next_retry_at', 'occurred_at'], 'domain_events_claim_idx');
            }

            if (! in_array('domain_events_claimed_by_idx', $indexes, true)) {
                $table->index(['claimed_by'], 'domain_events_claimed_by_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('domain_events')) {
            return;
        }

        Schema::table('domain_events', function (Blueprint $table) {
            foreach (['domain_events_claim_idx', 'domain_events_claimed_by_idx'] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                    // Index may not exist on a partially-applied schema.
                }
            }
        });

        Schema::table('domain_events', function (Blueprint $table) {
            foreach (['claimed_at', 'claimed_by', 'next_retry_at', 'last_error_at'] as $column) {
                if (Schema::hasColumn('domain_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
