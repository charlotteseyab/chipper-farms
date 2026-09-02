<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pigs_management', function (Blueprint $table): void {
            $table->dropUnique('pigs_management_ear_tag_number_unique');
            $table->dropUnique('pigs_management_code_unique');
            $table->dropUnique('pigs_local_id_unique');

            $table->unique(
                ['organization_id', 'ear_tag_number'],
                'pigs_org_ear_tag_unique',
            );
            $table->unique(
                ['organization_id', 'code'],
                'pigs_org_code_unique',
            );
            $table->unique(
                ['organization_id', 'local_id'],
                'pigs_org_local_id_unique',
            );
        });
    }

    public function down(): void
    {
        // Reintroducing global uniqueness can fail once different farms have
        // legitimately reused identifiers, so this security/tenancy change is
        // intentionally not reversed automatically.
    }
};
