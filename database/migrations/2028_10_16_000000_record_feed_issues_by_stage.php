<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Feed is mixed and fed per feeding stage. Each stage gets one system
        // group so the group cost pool and GL posting paths stay unchanged.
        Schema::table('groups', function (Blueprint $table) {
            $table->string('feeding_stage', 40)->nullable()->after('type');
            $table->unique(['organization_id', 'feeding_stage'], 'groups_org_feeding_stage_unique');
        });

        Schema::table('feed_issues', function (Blueprint $table) {
            $table->string('feeding_stage', 40)->nullable()->after('group_id');
            $table->index(['organization_id', 'feeding_stage', 'issued_at'], 'feed_issues_stage_time_idx');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('feed_issues')->whereNotNull('feeding_stage')->exists()) {
            throw new RuntimeException('Stage feed issues exist; rollback would lose which stage was fed.');
        }

        Schema::table('feed_issues', function (Blueprint $table) {
            $table->dropIndex('feed_issues_stage_time_idx');
            $table->dropColumn('feeding_stage');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique('groups_org_feeding_stage_unique');
            $table->dropColumn('feeding_stage');
        });
    }
};
