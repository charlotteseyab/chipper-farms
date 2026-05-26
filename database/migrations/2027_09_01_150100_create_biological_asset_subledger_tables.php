<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — biological-asset subledger tables.
 *
 * biological_asset_stage_balances:
 *   The operational mirror of the 1310–1370 GL stage accounts, per group.
 *   One row per (organization, group, stage). Tracks the carrying value,
 *   pig count and weight currently sitting in a stage for a group. This is
 *   the subledger behind the stage control accounts — the analogue of
 *   group_cost_pools but split by stage so the balance sheet ties out by
 *   stage account.
 *
 *   Because a group's cost pool (Phase 5) is the source of truth for total
 *   carrying value, the sum of a group's stage-balance rows must equal its
 *   group_cost_pool carrying value. The reconciliation report checks this.
 *
 * biological_asset_stage_transfers:
 *   Audit log of cost moving between stages for a group (e.g. when a cohort
 *   ages weaner -> grower, or is promoted standard -> breeding). Each row
 *   links to the journal batch that posted the Dr new-stage / Cr old-stage
 *   transfer, for full traceability.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('biological_asset_stage_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('group_id')->index();
            $table->string('stage_key', 30)->index();

            $table->decimal('carrying_value', 18, 4)->default(0);
            $table->unsignedInteger('pig_count')->default(0);
            $table->decimal('weight_kg', 18, 4)->default(0);

            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'group_id', 'stage_key'], 'bas_org_group_stage_unique');
        });

        Schema::create('biological_asset_stage_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('group_id')->index();

            $table->string('from_stage_key', 30)->nullable();   // null = initial capitalisation into a stage
            $table->string('to_stage_key', 30);

            $table->decimal('amount', 18, 4);                   // cost moved
            $table->unsignedInteger('pig_count')->default(0);
            $table->decimal('weight_kg', 18, 4)->default(0);

            $table->string('reason', 50);                       // 'feed_issue' | 'stage_progression' | 'promotion' | 'opening' | 'sale_cogs' | 'mortality'
            $table->unsignedBigInteger('journal_batch_id')->nullable();
            $table->unsignedBigInteger('source_domain_event_id')->nullable();

            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'group_id', 'to_stage_key'], 'bast_org_group_tostage_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biological_asset_stage_transfers');
        Schema::dropIfExists('biological_asset_stage_balances');
    }
};
