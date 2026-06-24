<?php

namespace Database\Seeders;

use App\Models\Finance\Account;
use App\Models\Organization;
use App\Models\RawMaterial;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared feed-ingredient catalogue used by procurement, vendor
 * invoices, feed recipes, and inventory receiving.
 *
 * No opening quantity or value is created here. Stock must enter through a
 * goods receipt or the audited inventory opening-balances workflow.
 */
class RawMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = Organization::query()
            ->where('slug', 'chipper-farms')
            ->value('id');

        $inventoryAccountId = Account::query()
            ->when(
                $organizationId !== null,
                fn ($query) => $query->where('organization_id', $organizationId),
                fn ($query) => $query->whereNull('organization_id'),
            )
            ->where('code', '1210')
            ->value('id');

        $materials = [
            [
                'name' => 'Maize',
                'category' => 'grain',
                'reorder_level' => 500,
                'notes' => 'Primary energy ingredient for pig-feed formulations.',
            ],
            [
                'name' => 'Soybean Meal',
                'category' => 'protein',
                'reorder_level' => 250,
                'notes' => 'Plant-protein ingredient used across grower and finisher diets.',
            ],
            [
                'name' => 'Wheat Bran',
                'category' => 'fibre',
                'reorder_level' => 250,
                'notes' => 'Fibre and energy ingredient for balanced feed formulations.',
            ],
            [
                'name' => 'Rice Bran',
                'category' => 'fibre',
                'reorder_level' => 200,
                'notes' => 'Alternative fibre and energy ingredient.',
            ],
            [
                'name' => 'Palm Kernel Cake',
                'category' => 'protein',
                'reorder_level' => 200,
                'notes' => 'Locally available protein and fibre ingredient.',
            ],
            [
                'name' => 'Fish Meal',
                'category' => 'protein',
                'reorder_level' => 50,
                'notes' => 'High-quality animal-protein ingredient.',
            ],
            [
                'name' => 'Limestone',
                'category' => 'mineral',
                'reorder_level' => 50,
                'notes' => 'Calcium source for feed formulation.',
            ],
            [
                'name' => 'Dicalcium Phosphate',
                'category' => 'mineral',
                'reorder_level' => 25,
                'notes' => 'Calcium and phosphorus supplement.',
            ],
            [
                'name' => 'Salt',
                'category' => 'mineral',
                'reorder_level' => 25,
                'notes' => 'Feed-grade salt.',
            ],
            [
                'name' => 'Vitamin-Mineral Premix',
                'category' => 'additive',
                'reorder_level' => 25,
                'notes' => 'Concentrated vitamin and trace-mineral premix.',
            ],
            [
                'name' => 'L-Lysine',
                'category' => 'additive',
                'reorder_level' => 10,
                'notes' => 'Essential amino-acid supplement.',
            ],
            [
                'name' => 'DL-Methionine',
                'category' => 'additive',
                'reorder_level' => 10,
                'notes' => 'Essential amino-acid supplement.',
            ],
        ];

        foreach ($materials as $material) {
            $rawMaterial = RawMaterial::firstOrCreate(
                ['name' => $material['name']],
                [
                    'category' => $material['category'],
                    'unit' => 'kg',
                    'quantity_in_stock' => 0,
                    'unit_cost' => 0,
                    'reorder_level' => $material['reorder_level'],
                    'inventory_account_id' => $inventoryAccountId,
                    'expense_account_id' => null,
                    'notes' => $material['notes'],
                ],
            );

            if ($rawMaterial->inventory_account_id === null && $inventoryAccountId !== null) {
                $rawMaterial->update(['inventory_account_id' => $inventoryAccountId]);
            }
        }
    }
}
