<?php
namespace Tests\Feature\Storefront;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontPlacement;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
class CatalogSeederCheckTest extends StorefrontTestCase {
    use RefreshDatabase;
    public function test_catalog_seeder_runs_and_is_idempotent(): void {
        $this->seed(StorefrontCatalogSeeder::class);
        $this->assertSame(6, StorefrontCategory::where('organization_id',$this->org->id)->count());
        $this->assertSame(count(StorefrontPlacement::CODES), StorefrontPlacement::where('organization_id',$this->org->id)->count());
        $this->seed(StorefrontCatalogSeeder::class);
        $this->assertSame(6, StorefrontCategory::where('organization_id',$this->org->id)->count());
    }
}
