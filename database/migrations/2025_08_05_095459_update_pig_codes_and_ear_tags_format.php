<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing pig codes from PG00001 format to CFPG-YM-ID format
        $pigs = DB::table('pigs_management')->get();

        foreach ($pigs as $pig) {
            $birthDate = Carbon::parse($pig->date_of_birth);
            $yearMonth = $birthDate->format('ym'); // e.g., 2501 for January 2025

            // New code format: CFPG-YM-ID (without leading zeros)
            $newCode = 'CFPG-' . $yearMonth . '-' . $pig->id;

            // Update ear tag to remove leading zeros
            $earTagParts = explode(' ', $pig->ear_tag_number);
            if (count($earTagParts) === 2) {
                $breed = $earTagParts[0];
                $number = ltrim($earTagParts[1], '0'); // Remove leading zeros
                $newEarTag = $breed . ' ' . ($number ?: '1'); // If all zeros, make it 1
            } else {
                $newEarTag = $pig->ear_tag_number; // Keep original if format is unexpected
            }

            DB::table('pigs_management')
                ->where('id', $pig->id)
                ->update([
                    'code' => $newCode,
                    'ear_tag_number' => $newEarTag
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the changes - convert back to old format
        $pigs = DB::table('pigs_management')->get();

        foreach ($pigs as $pig) {
            // Convert back to PG00001 format
            $oldCode = 'PG' . str_pad($pig->id, 5, '0', STR_PAD_LEFT);

            // Convert ear tag back to padded format
            $earTagParts = explode(' ', $pig->ear_tag_number);
            if (count($earTagParts) === 2) {
                $breed = $earTagParts[0];
                $number = $earTagParts[1];
                $oldEarTag = $breed . ' ' . str_pad($number, 3, '0', STR_PAD_LEFT);
            } else {
                $oldEarTag = $pig->ear_tag_number;
            }

            DB::table('pigs_management')
                ->where('id', $pig->id)
                ->update([
                    'code' => $oldCode,
                    'ear_tag_number' => $oldEarTag
                ]);
        }
    }
};
