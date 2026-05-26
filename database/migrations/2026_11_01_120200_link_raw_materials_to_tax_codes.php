<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3b — defer FK on raw_materials.default_purchase_tax_code_id.
 *
 * In Phase 3a we added the column but deferred the FK because tax_codes
 * already existed as a reference table from Phase 0.8 — we just couldn't
 * be sure the table was present in every deployment when 3a ran.
 *
 * Now in 3b we lock in the FK. tax_codes definitely exists (it's a
 * pre-Phase 1 reference table).
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('raw_materials')) {
            return;
        }
        if (! Schema::hasTable('tax_codes')) {
            return;  // Defensive — should never happen
        }
        if (! Schema::hasColumn('raw_materials', 'default_purchase_tax_code_id')) {
            return;  // 3a wasn't applied
        }

        // Check FK doesn't already exist (idempotency).
        try {
            Schema::table('raw_materials', function (Blueprint $table) {
                $table->foreign('default_purchase_tax_code_id', 'raw_materials_default_purchase_tax_fk')
                      ->references('id')->on('tax_codes')->nullOnDelete();
            });
        } catch (\Throwable) {
            // FK may already exist on re-run.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('raw_materials')) {
            return;
        }
        try {
            Schema::table('raw_materials', function (Blueprint $table) {
                $table->dropForeign('raw_materials_default_purchase_tax_fk');
            });
        } catch (\Throwable) {}
    }
};
