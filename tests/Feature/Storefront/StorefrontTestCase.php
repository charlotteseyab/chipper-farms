<?php

namespace Tests\Feature\Storefront;

use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\StorefrontPermissionsSeeder;
use Tests\TestCase;

/**
 * Shared scaffolding for storefront feature tests. Mirrors the setup style of
 * the existing finance/sales tests (explicit org, CurrentOrganization bind,
 * role-backed users).
 */
abstract class StorefrontTestCase extends TestCase
{
    protected Organization $org;
    protected int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'chipper-farms',
            'name' => 'Chipper Farms',
        ]);

        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    protected function seedStorefrontPermissions(): void
    {
        $this->seed(StorefrontPermissionsSeeder::class);
    }

    protected function seedStorefrontQuotePermissions(): void
    {
        $this->seed(\Database\Seeders\StorefrontQuotePermissionsSeeder::class);
    }

    protected function seedStorefrontOrderPermissions(): void
    {
        $this->seed(\Database\Seeders\StorefrontOrderPermissionsSeeder::class);
    }

    protected function makeListing(array $overrides = []): \App\Models\Storefront\StorefrontListing
    {
        return \App\Models\Storefront\StorefrontListing::create(array_merge([
            'organization_id'    => $this->org->id,
            'kind'               => \App\Models\Storefront\StorefrontListing::KIND_LIVE_PIG,
            'title'              => 'Test listing',
            'slug'               => 'test-listing-' . uniqid(),
            'reference'          => 'LIST-' . strtoupper(uniqid()),
            'sale_mode'          => \App\Models\Storefront\StorefrontListing::MODE_FIXED,
            'pricing_basis'      => \App\Models\Storefront\StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '500.00',
            'quantity_available' => '1',
            'status'             => \App\Models\Storefront\StorefrontListing::STATUS_DRAFT,
        ], $overrides));
    }

    protected function makePublishedListing(array $overrides = []): \App\Models\Storefront\StorefrontListing
    {
        return $this->makeListing(array_merge([
            'status'       => \App\Models\Storefront\StorefrontListing::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $overrides));
    }

    protected function makeUser(string $roleName = 'admin', ?string $name = null): User
    {
        $normalized = User::normalizeRoleName($roleName) ?? $roleName;
        $role = Role::firstOrCreate(['name' => $normalized]);

        return User::forceCreate([
            'name'            => $name ?? ucfirst($normalized),
            'email'           => $normalized . '-' . (++$this->userSequence) . '@example.com',
            'password'        => bcrypt('secret'),
            'role'            => $normalized,
            'role_id'         => $role->id,
            'organization_id' => $this->org->id,
            'active_status'   => true,
            'email_verified_at' => now(),
        ]);
    }

    protected function makePig(array $overrides = []): PigManagement
    {
        return PigManagement::create(array_merge([
            'organization_id'         => $this->org->id,
            'ear_tag_number'          => 'TAG-' . uniqid(),
            'breed'                   => 'Large White',
            'gender'                  => 'female',
            'stock_type'              => 'standard',
            'date_of_birth'           => now()->subMonths(6)->toDateString(),
            'code'                    => 'PIG-' . uniqid(),
            'current_weight_kg_cache' => '72.5000',
        ], $overrides));
    }
}
