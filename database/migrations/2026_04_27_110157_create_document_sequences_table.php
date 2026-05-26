<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — document_sequences.
 *
 * Issues per-type, per-scope, per-year document numbers for finance-relevant
 * source documents (PR, GRN, INV, RC, JV, etc.). Used by NumberingService,
 * which allocates the next number atomically via SELECT ... FOR UPDATE.
 *
 * Keys are scoped by organization_id + sequence_type + year. This is the
 * canonical pattern for ERPs: a single counter per (org, type, year).
 *
 * organization_id is nullable for the initial deployment — this batch
 * introduces the column on source tables but Phase 0.1's organizations
 * migration owns table creation. Once Phase 0.1 is merged, organization_id
 * becomes effectively required.
 *
 * Safe and idempotent: guarded by Schema::hasTable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_sequences')) {
            return;
        }

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();

            // organization_id is added as a nullable unsignedBigInteger without
            // an FK constraint because the organizations table from Phase 0.1
            // may not yet be present when this migration runs in isolation.
            // When organizations lands, a follow-up migration can add the FK.
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('sequence_type', 50);  // 'pig_sale', 'purchase_request', 'grn', 'pig_death', 'feed_batch', 'journal_voucher', ...

            $table->string('prefix', 20);          // 'INV', 'PR', 'GRN', 'JV', ...
            $table->unsignedSmallInteger('year'); // 4-digit fiscal year the counter resets against
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedSmallInteger('padding')->default(6);  // 6-digit zero-padded by default: 000001

            $table->string('format_template')->nullable();
            // Optional override for the formatted output, e.g. '{prefix}-{year}-{number:6}'.
            // If null, the default format applies: {prefix}-{year}-{number:padded}

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['organization_id', 'sequence_type', 'year'],
                'document_sequences_org_type_year_unique'
            );
            $table->index(['sequence_type', 'is_active'], 'document_sequences_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};