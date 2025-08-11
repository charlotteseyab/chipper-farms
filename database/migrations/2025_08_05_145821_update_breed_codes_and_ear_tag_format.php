<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update breed codes: MB -> LWDR (Mixed Breed -> Large White Duroc)
        DB::table('pigs_management')
            ->where('breed', 'MB')
            ->update(['breed' => 'LWDR']);

        // Update ear tag numbers to include gender
        $pigs = DB::table('pigs_management')->get();

        foreach ($pigs as $pig) {
            $breedGenderCode = $pig->breed . $pig->gender;

            // Count existing pigs with same breed and gender (excluding current pig)
            $count = DB::table('pigs_management')
                ->where('breed', $pig->breed)
                ->where('gender', $pig->gender)
                ->where('id', '<=', $pig->id)
                ->count();

            $newEarTag = $breedGenderCode . ' ' . $count;

            DB::table('pigs_management')
                ->where('id', $pig->id)
                ->update(['ear_tag_number' => $newEarTag]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse breed codes: LWDR -> MB
        DB::table('pigs_management')
            ->where('breed', 'LWDR')
            ->update(['breed' => 'MB']);

        // Reverse ear tag numbers to remove gender
        $pigs = DB::table('pigs_management')->get();

        foreach ($pigs as $pig) {
            // Count existing pigs with same breed (excluding current pig)
            $count = DB::table('pigs_management')
                ->where('breed', $pig->breed)
                ->where('id', '<=', $pig->id)
                ->count();

            $oldEarTag = $pig->breed . ' ' . $count;

            DB::table('pigs_management')
                ->where('id', $pig->id)
                ->update(['ear_tag_number' => $oldEarTag]);
        }
    }
};
