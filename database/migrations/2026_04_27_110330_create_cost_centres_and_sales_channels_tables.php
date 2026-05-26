<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — cost_centres + sales_channels.
 *
 * Explicit accounting dimensions. The prompt calls for:
 *   "accounting dimension foundation for farm/site/structure/room/group/channel/cost centre"
 *
 * Structures / rooms / groups already exist as operational entities. For
 * accounting purposes those are reused via the accounting_dimensions
 * polymorphic table (next migration). What's missing is two dedicated
 * accounting dimensions that don't map to existing operational entities:
 *
 *   cost_centres — e.g. 'BreedingHerd', 'FinishingHerd', 'Nursery', 'Admin',
 *                  'Maintenance'. Usage: cost allocation and reporting.
 *   sales_channels — e.g. 'Walk-in', 'Wholesale', 'Contract', 'Online Store'.
 *                    Usage: revenue segmentation.
 *
 * Both are simple active/inactive reference tables with a parent_id for
 * hierarchies (e.g. 'BreedingHerd > Sows').
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cost_centres')) {
            Schema::create('cost_centres', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable();

                $table->string('code', 30);
                $table->string('name');
                $table->text('description')->nullable();

                $table->unsignedBigInteger('parent_id')->nullable();
                $table->foreign('parent_id')
                      ->references('id')
                      ->on('cost_centres')
                      ->nullOnDelete();

                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('display_order')->default(100);

                $table->timestamps();

                $table->unique(['organization_id', 'code'], 'cost_centres_org_code_unique');
                $table->index(['organization_id', 'is_active'], 'cost_centres_active_idx');
                $table->index('parent_id', 'cost_centres_parent_idx');
            });
        }

        if (! Schema::hasTable('sales_channels')) {
            Schema::create('sales_channels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable();

                $table->string('code', 30);
                $table->string('name');
                $table->text('description')->nullable();

                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('display_order')->default(100);

                $table->timestamps();

                $table->unique(['organization_id', 'code'], 'sales_channels_org_code_unique');
                $table->index(['organization_id', 'is_active'], 'sales_channels_active_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_channels');
        Schema::dropIfExists('cost_centres');
    }
};