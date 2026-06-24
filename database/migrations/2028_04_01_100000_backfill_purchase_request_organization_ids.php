<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests')
            || ! Schema::hasColumn('purchase_requests', 'organization_id')) {
            return;
        }

        $fallbackOrganizationId = DB::table('organizations')->count() === 1
            ? DB::table('organizations')->value('id')
            : null;

        DB::table('purchase_requests')
            ->whereNull('organization_id')
            ->orderBy('id')
            ->eachById(function ($purchaseRequest) use ($fallbackOrganizationId) {
                $organizationId = null;

                if ($purchaseRequest->supplier_id !== null) {
                    $organizationId = DB::table('counterparties')
                        ->where('id', $purchaseRequest->supplier_id)
                        ->value('organization_id');
                }

                if ($organizationId === null && $purchaseRequest->requested_by !== null) {
                    $organizationId = DB::table('users')
                        ->where('id', $purchaseRequest->requested_by)
                        ->value('organization_id');
                }

                $organizationId ??= $fallbackOrganizationId;

                if ($organizationId !== null) {
                    DB::table('purchase_requests')
                        ->where('id', $purchaseRequest->id)
                        ->update(['organization_id' => $organizationId]);
                }
            });
    }

    public function down(): void
    {
        // Data backfill only. Organization ownership must not be erased.
    }
};
