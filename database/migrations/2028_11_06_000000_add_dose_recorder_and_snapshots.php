<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who typed the dose in, kept apart from who gave it (administered_by):
        // a clerk often records doses given by a stockman.
        Schema::table('medication_dosing_events', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('administered_by')->constrained('users')->nullOnDelete();
        });

        // Each dose keeps the conversion it was taken from stock with, and its
        // share of the cost, so a later catalogue change cannot alter either.
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->decimal('dose_units_per_stock_unit_snapshot', 14, 4)->nullable()->after('stock_quantity');
            $table->decimal('cost_snapshot', 18, 4)->nullable()->after('dose_units_per_stock_unit_snapshot');
        });

        // Doses recorded since Phase 3: their medicine's conversion cannot have
        // changed (it is locked once stock exists), and whoever gave them typed them in.
        DB::statement('UPDATE medication_administrations SET dose_units_per_stock_unit_snapshot = (SELECT dose_units_per_stock_unit FROM medicines WHERE medicines.id = medication_administrations.medicine_id) WHERE medication_dosing_event_id IS NOT NULL AND dose_units_per_stock_unit_snapshot IS NULL');
        DB::statement('UPDATE medication_dosing_events SET recorded_by = administered_by WHERE recorded_by IS NULL');
    }

    public function down(): void
    {
        Schema::table('medication_administrations', fn (Blueprint $table) => $table->dropColumn(['dose_units_per_stock_unit_snapshot', 'cost_snapshot']));
        Schema::table('medication_dosing_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('recorded_by'));
    }
};
