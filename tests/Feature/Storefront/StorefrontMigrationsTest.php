<?php

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class StorefrontMigrationsTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_storefront_tables_exist(): void
    {
        foreach ([
            'storefront_categories',
            'storefront_listings',
            'storefront_listing_media',
            'storefront_placements',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_listings_table_has_finance_safe_columns(): void
    {
        $cols = [
            'organization_id', 'category_id', 'kind', 'source_type', 'source_id',
            'title', 'slug', 'reference', 'sale_mode', 'pricing_basis',
            'price_per_kg', 'fixed_price', 'currency_code', 'quantity_available',
            'status', 'published_at', 'created_by_user_id', 'deleted_at',
        ];

        foreach ($cols as $col) {
            $this->assertTrue(
                Schema::hasColumn('storefront_listings', $col),
                "storefront_listings missing column: {$col}"
            );
        }
    }
}
