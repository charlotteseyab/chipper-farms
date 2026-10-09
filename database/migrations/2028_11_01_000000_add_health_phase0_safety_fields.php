<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Old free-text severities and the fixed value each now means (decision D2). */
    private const SEVERITY_MAP = [
        'low' => 'mild', 'mild' => 'mild',
        'medium' => 'moderate', 'moderate' => 'moderate',
        'high' => 'severe', 'severe' => 'severe', 'critical' => 'severe',
    ];

    public function up(): void
    {
        // Health history is never deleted: a mistaken record is voided with a
        // reason and stays on the pig's history, marked as void.
        Schema::table('health_records', function (Blueprint $table) {
            $table->string('legacy_severity')->nullable()->after('severity');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
        });

        // A dose is corrected by voiding the original and saving a replacement
        // that points back at it, so both stay readable.
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('corrects_id')->nullable()->constrained('medication_administrations')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->index(['pig_id', 'voided_at', 'withdrawal_ends_on'], 'medication_withdrawal_lookup_idx');
        });

        // Severity becomes a fixed list. Whenever the stored text changes, the
        // original is kept in legacy_severity; unknown text is not guessed at.
        DB::table('health_records')->whereNotNull('severity')->orderBy('id')->each(function ($row) {
            $original = (string) $row->severity;
            $mapped = self::SEVERITY_MAP[strtolower(trim($original))] ?? null;
            if ($mapped === $original) {
                return;
            }
            DB::table('health_records')->where('id', $row->id)->update(['severity' => $mapped, 'legacy_severity' => $original]);
        });
    }

    public function down(): void
    {
        if (DB::table('health_records')->whereNotNull('voided_at')->exists()
            || DB::table('medication_administrations')->whereNotNull('voided_at')->exists()) {
            throw new RuntimeException('Voided health or medication records exist; rolling back would make them look valid again.');
        }

        DB::table('health_records')->whereNotNull('legacy_severity')->update(['severity' => DB::raw('legacy_severity')]);

        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropIndex('medication_withdrawal_lookup_idx');
            $table->dropConstrainedForeignId('corrects_id');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason', 'correction_reason']);
        });
        Schema::table('health_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['legacy_severity', 'voided_at', 'void_reason']);
        });
    }
};
