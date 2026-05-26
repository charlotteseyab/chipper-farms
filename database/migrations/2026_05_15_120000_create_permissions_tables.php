<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d — permissions infrastructure (correction to Phase 1a's
 * FinancePermissionsSeeder which referenced a Permission model and
 * pivot table that didn't exist).
 *
 * Two tables:
 *   permissions          The permission definitions (name + description).
 *   role_permissions     Pivot from role to permissions.
 *
 * Why now and not in Phase 1a?
 *   - 1a delivered schema for the COA + journal infrastructure. RBAC is
 *     orthogonal to that. Splitting it out kept 1a focused.
 *   - Until 1d, no UI needed permission-based gating; role-only middleware
 *     was enough. 1d's UI (propose_manual_journal vs approve_journal as
 *     different permissions on the same role) is the first real use case.
 *
 * The previous Phase 1a FinancePermissionsSeeder is replaced by this
 * batch's seeder, which works correctly with the new tables. Re-running
 * it is idempotent and safe.
 *
 * Future batches will add per-module permissions to this same table:
 *   manage_customers, post_invoice, allocate_receipt, etc. (Phase 2)
 *   manage_suppliers, post_bill, allocate_payment, etc. (Phase 3)
 *   etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->unique();
                // e.g. 'view_finance', 'post_journal', 'manage_coa'
                $table->string('description', 255)->nullable();
                $table->string('module', 50)->nullable();
                // e.g. 'finance', 'sales', 'procurement' — for grouping
                // permissions in the user management UI.
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');

                $table->foreign('role_id')
                      ->references('id')
                      ->on('roles')
                      ->cascadeOnDelete();

                $table->foreign('permission_id')
                      ->references('id')
                      ->on('permissions')
                      ->cascadeOnDelete();

                $table->unique(['role_id', 'permission_id'], 'role_permissions_unique');

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
