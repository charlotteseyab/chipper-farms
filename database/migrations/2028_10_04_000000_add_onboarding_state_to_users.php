<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('onboarding_required')->default(false)->after('active_status');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_required');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['onboarding_required', 'onboarding_completed_at']);
        });
    }
};
