<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontListing;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 16 (§47) — the listing reference backstop.
 *
 * §47 lists order, quote and listing references as idempotency-controlled by
 * `StorefrontReferenceAllocator`. Orders and quotes also carry a UNIQUE index on
 * (organization_id, reference); listings did not, so a duplicate would have been
 * accepted in silence.
 */
class ListingReferenceUniquenessTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_two_listings_cannot_share_a_reference_within_an_organisation(): void
    {
        $this->makeListing(['reference' => 'LIST-000001']);

        $this->expectException(QueryException::class);

        $this->makeListing(['reference' => 'LIST-000001']);
    }

    /**
     * References are per-organisation, as they are for orders and quotes. Two
     * farms both starting at LIST-000001 is correct, not a collision.
     */
    public function test_two_organisations_may_each_use_the_same_reference(): void
    {
        $this->makeListing(['reference' => 'LIST-000001']);

        $other = \App\Models\Organization::create(['slug' => 'other-ref-farm', 'name' => 'Other Farm']);

        $listing = $this->makeListing([
            'organization_id' => $other->id,
            'reference'       => 'LIST-000001',
        ]);

        $this->assertSame('LIST-000001', $listing->reference);
        $this->assertSame(2, StorefrontListing::where('reference', 'LIST-000001')->count());
    }

    /**
     * The column is nullable and stays that way. Listings that predate the
     * allocator carry no reference, and repeated NULLs must remain legal —
     * otherwise this index would make old rows unsaveable.
     */
    public function test_listings_without_a_reference_are_still_allowed(): void
    {
        $this->makeListing(['reference' => null]);
        $this->makeListing(['reference' => null]);

        $this->assertSame(2, StorefrontListing::whereNull('reference')->count());
    }

    /**
     * The migration refuses rather than repairing.
     *
     * A listing reference is printed, linked and quoted to customers. Choosing
     * which of two rows keeps the number is a business decision, so the
     * migration stops with the data in hand instead of reallocating quietly at
     * deploy time.
     */
    public function test_the_migration_refuses_when_duplicates_already_exist(): void
    {
        $migration = require base_path(
            'database/migrations/2028_09_20_100100_add_unique_reference_index_to_storefront_listings.php'
        );

        // Drop the index so duplicates can be manufactured at all.
        Schema::table('storefront_listings', function ($table) {
            $table->dropUnique('storefront_listings_org_ref_uq');
        });

        $this->makeListing(['reference' => 'LIST-000009']);
        $this->makeListing(['reference' => 'LIST-000009']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LIST-000009');

        $migration->up();
    }

    /**
     * Phase 12b's lesson: run the rollback rather than assuming it works.
     */
    public function test_the_index_can_be_dropped_and_re_added(): void
    {
        $migration = require base_path(
            'database/migrations/2028_09_20_100100_add_unique_reference_index_to_storefront_listings.php'
        );

        $migration->down();

        // With the index gone the duplicate is accepted, which is what proves
        // the drop actually happened rather than silently doing nothing.
        $this->makeListing(['reference' => 'LIST-000010']);
        $this->makeListing(['reference' => 'LIST-000010']);
        $this->assertSame(2, StorefrontListing::where('reference', 'LIST-000010')->count());

        DB::table('storefront_listings')->where('reference', 'LIST-000010')->limit(1)->delete();

        $migration->up();

        $this->expectException(QueryException::class);
        $this->makeListing(['reference' => 'LIST-000010']);
    }
}
