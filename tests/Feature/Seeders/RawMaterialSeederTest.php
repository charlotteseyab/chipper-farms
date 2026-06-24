<?php

namespace Tests\Feature\Seeders;

use App\Models\Finance\Account;
use App\Models\RawMaterial;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\RawMaterialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_feed_ingredients_without_creating_stock(): void
    {
        $this->seed(OrganizationSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RawMaterialSeeder::class);

        $maize = RawMaterial::query()->where('name', 'Maize')->firstOrFail();
        $inventoryAccount = Account::query()->find($maize->inventory_account_id);

        $this->assertSame('grain', $maize->category);
        $this->assertSame('kg', $maize->unit);
        $this->assertSame(0.0, (float) $maize->quantity_in_stock);
        $this->assertSame(0.0, (float) $maize->unit_cost);
        $this->assertSame($inventoryAccount?->id, $maize->inventory_account_id);
        $this->assertGreaterThanOrEqual(10, RawMaterial::query()->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(OrganizationSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RawMaterialSeeder::class);
        $firstCount = RawMaterial::query()->count();

        $this->seed(RawMaterialSeeder::class);

        $this->assertSame($firstCount, RawMaterial::query()->count());
    }
}
