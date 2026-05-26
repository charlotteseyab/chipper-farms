<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.4 — domain_events outbox table.
 *
 * This table is the integration surface between operational modules and the
 * (yet-to-be-built) finance posting engine. Every material business event
 * (a sale, a death, a stock receipt, a feed batch, a purchase approval) is
 * recorded here at the exact moment its source transaction commits.
 *
 * Design notes:
 *
 *   idempotency_key : globally unique per event. A replayed or duplicated
 *                     dispatch with the same key is rejected by the UNIQUE
 *                     constraint, which is the hard guarantee that prevents
 *                     double-posting downstream. Format:
 *                        {event_type}:{source_type}:{source_id}[:suffix]
 *                     Example: pig_sale.recorded:PigSale:1423
 *
 *   source_type     : the FQCN of the source model, e.g. 'App\Models\PigSale'.
 *   source_id       : the primary key of the source record.
 *   event_type      : a dotted machine name, e.g. 'pig_sale.recorded'.
 *                     Consumers subscribe by event_type.
 *
 *   organization_id : every event is tenant-scoped. Phase 1 finance will
 *                     post to the organization's own GL.
 *
 *   payload         : JSON snapshot of the event at dispatch time. Sufficient
 *                     for finance to post without re-querying the source (the
 *                     source may have changed by the time the listener runs).
 *
 *   status          : pending | processed | failed | skipped
 *                     pending   : fresh, waiting for a consumer.
 *                     processed : successfully consumed by the finance worker.
 *                     failed    : consumed but failed; see processing_error.
 *                     skipped   : deliberately not posted (rare, e.g. voids
 *                                 that post a reversal elsewhere).
 *
 *   occurred_at     : when the business event happened (may differ from
 *                     created_at if the source has a back-dated date field).
 *
 *   processed_at    : when the consumer finished, for latency monitoring.
 *
 *   dispatched_by_user_id : the actor who triggered the source action.
 *                           Nullable for system-generated events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            $table->string('event_type', 100);
            $table->string('idempotency_key', 200)->unique();

            $table->string('source_module', 50);      // e.g. 'sales', 'procurement', 'inventory', 'feed'
            $table->string('source_type', 150);       // FQCN of the model
            $table->unsignedBigInteger('source_id');

            $table->json('payload');
            $table->unsignedInteger('payload_version')->default(1);

            $table->string('status', 20)->default('pending');

            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->unsignedTinyInteger('processing_attempts')->default(0);

            $table->unsignedBigInteger('dispatched_by_user_id')->nullable();
            $table->foreign('dispatched_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->timestamps();

            // Heavy-read paths:
            //   - consumer polling: status='pending' ordered by id
            //   - per-organization queries
            //   - lookup by source for reconciliation
            //   - lookup by event_type
            $table->index(['organization_id', 'status', 'id'], 'domain_events_polling_idx');
            $table->index(['source_type', 'source_id'], 'domain_events_source_idx');
            $table->index(['event_type', 'occurred_at'], 'domain_events_type_time_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_events');
    }
};