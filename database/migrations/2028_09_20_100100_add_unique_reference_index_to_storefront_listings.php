<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 16 (§47) — the listing reference gets the backstop orders and quotes have.
 *
 * §47 names `StorefrontReferenceAllocator` as the idempotency control for three
 * series: order, quote and listing references. Two of the three tables also carry
 * a UNIQUE index on (organization_id, reference); listings carried only a unique
 * slug, so a duplicate LIST- reference would have been accepted silently.
 *
 * In practice the allocator is a single writer and serialises through
 * `NumberingService`'s `SELECT ... FOR UPDATE`, and `PushToStorefrontService` is
 * the only code path that creates a listing — so this is defence in depth rather
 * than a live defect. It is worth having anyway: the class docblock on the
 * allocator records that order references were once generated in two places from
 * a row count, and it was the unique index that turned that into a visible
 * failure instead of two orders sharing a reference. Listings had no such net.
 *
 * NULLS ARE STILL ALLOWED
 * -----------------------
 * The column is nullable and stays that way — both MariaDB and SQLite permit
 * repeated NULLs in a unique index, so listings that predate the allocator and
 * carry no reference are unaffected.
 *
 * IF DUPLICATES ALREADY EXIST
 * ---------------------------
 * The migration refuses, with the offending references named. It deliberately
 * does not repair them by reallocating: a listing reference is printed, linked
 * and quoted to customers, and choosing which of two rows keeps the number is a
 * business decision, not one a migration should make quietly at deploy time. A
 * loud stop with the data in hand is the honest outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_listings')) {
            return;
        }

        $duplicates = DB::table('storefront_listings')
            ->select('organization_id', 'reference', DB::raw('COUNT(*) as occurrences'))
            ->whereNotNull('reference')
            ->groupBy('organization_id', 'reference')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $detail = $duplicates
                ->map(fn ($row) => "org {$row->organization_id}: {$row->reference} × {$row->occurrences}")
                ->implode('; ');

            throw new RuntimeException(
                'Cannot add a unique index to storefront_listings.reference because duplicates '
                . 'already exist. Decide which listing keeps each reference and reallocate the '
                . 'others before running this migration: ' . $detail
            );
        }

        Schema::table('storefront_listings', function (Blueprint $table) {
            $table->unique(['organization_id', 'reference'], 'storefront_listings_org_ref_uq');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('storefront_listings')) {
            return;
        }

        Schema::table('storefront_listings', function (Blueprint $table) {
            $table->dropUnique('storefront_listings_org_ref_uq');
        });
    }
};
