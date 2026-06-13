<?php

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class StorefrontPhase2MigrationsTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_phase2_tables_exist(): void
    {
        foreach (['storefront_favourites', 'storefront_quotes', 'storefront_quote_items'] as $t) {
            $this->assertTrue(Schema::hasTable($t), "Missing table: {$t}");
        }
    }
}
