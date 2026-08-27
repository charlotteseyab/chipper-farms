<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->text('deletion_reason')->nullable()->after('is_active');
            $table->foreignId('deleted_by_user_id')->nullable()->after('deletion_reason')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deleted_by_user_id');
            $table->dropColumn('deletion_reason');
        });
    }
};
