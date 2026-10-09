<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Also upgrade installations that already applied the former number-only rule.
        if (! Schema::hasIndex('ear_tags', 'ear_tags_org_color_identity_ready')) {
            Schema::table('ear_tags', fn (Blueprint $table) => $table->unique(
                ['organization_id', 'ear_tag_color_id', 'normalized_tag_number'], 'ear_tags_org_color_identity_ready'
            ));
        }
        if (Schema::hasIndex('ear_tags', 'ear_tags_org_number_unique')) {
            Schema::table('ear_tags', fn (Blueprint $table) => $table->dropUnique('ear_tags_org_number_unique'));
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Colour-based identities cannot safely revert to number-only uniqueness after use. Restore a verified backup if required.');
    }
};
