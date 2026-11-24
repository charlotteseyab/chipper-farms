<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each farm's admin decides what each role may do on that farm.
 *
 * organization_role_settings marks a role a farm has customised;
 * organization_role_permissions holds that farm's permissions for it. A role a
 * farm has not customised keeps the default permissions (role_permissions),
 * so nothing changes for any farm until its admin edits a role.
 *
 * Also adds the livestock permissions that replace role-name checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_role_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'role_id']);
        });

        Schema::create('organization_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'role_id', 'permission_id'], 'org_role_permission_unique');
        });

        // The permission rows only; which roles have them by default is in
        // code (LivestockAccessPermissionsSeeder::defaultsFor), so no role
        // needs to exist when this runs.
        \Database\Seeders\LivestockAccessPermissionsSeeder::createPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_role_permissions');
        Schema::dropIfExists('organization_role_settings');
    }
};
