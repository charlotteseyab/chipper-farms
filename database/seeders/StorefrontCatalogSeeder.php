<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontPlacement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Storefront Phase 1 — default catalogue scaffolding per organization.
 *
 * Seeds the canonical categories (Live Pigs, Piglets, Breeding Stock, Feed,
 * Manure, Bulk/Service) and the empty homepage placement slots so the admin
 * placement manager has rows to populate. Idempotent (firstOrCreate per org).
 *
 * Placement slots are created with listing_id = null (empty) and is_active
 * true; the admin pins listings into them later.
 */
class StorefrontCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $orgs = Organization::query()->get();

        // If no orgs exist yet (very fresh DB), seed for null-org so the
        // catalogue is still queryable; OrganizationSeeder normally runs first.
        if ($orgs->isEmpty()) {
            $this->seedFor(null);
            return;
        }

        foreach ($orgs as $org) {
            $this->seedFor($org->id);
        }
    }

    protected function seedFor(?int $organizationId): void
    {
        $categories = [
            ['code' => 'live-pigs',       'name' => 'Live Pigs',       'kind' => StorefrontCategory::KIND_LIVE_PIG,       'icon' => 'pig',   'order' => 10],
            ['code' => 'piglets',         'name' => 'Piglets',         'kind' => StorefrontCategory::KIND_PIGLET_LOT,     'icon' => 'piglet','order' => 20],
            ['code' => 'breeding-stock',  'name' => 'Breeding Stock',  'kind' => StorefrontCategory::KIND_BREEDING_STOCK, 'icon' => 'heart', 'order' => 30],
            ['code' => 'feed',            'name' => 'Feed',            'kind' => StorefrontCategory::KIND_FEED,           'icon' => 'wheat', 'order' => 40],
            ['code' => 'manure',          'name' => 'Manure',          'kind' => StorefrontCategory::KIND_MANURE,         'icon' => 'leaf',  'order' => 50],
            ['code' => 'services',        'name' => 'Services',        'kind' => StorefrontCategory::KIND_SERVICE,        'icon' => 'truck', 'order' => 60],
        ];

        foreach ($categories as $cat) {
            StorefrontCategory::firstOrCreate(
                ['organization_id' => $organizationId, 'code' => $cat['code']],
                [
                    'name'          => $cat['name'],
                    'slug'          => Str::slug($cat['name']),
                    'kind'          => $cat['kind'],
                    'icon'          => $cat['icon'],
                    'display_order' => $cat['order'],
                    'is_active'     => true,
                ],
            );
        }

        foreach (StorefrontPlacement::CODES as $code) {
            StorefrontPlacement::firstOrCreate(
                ['organization_id' => $organizationId, 'code' => $code, 'position' => 0],
                ['listing_id' => null, 'is_active' => true],
            );
        }

        $this->command?->info('Storefront catalogue seeded for org ' . ($organizationId ?? 'null') . '.');
    }
}
