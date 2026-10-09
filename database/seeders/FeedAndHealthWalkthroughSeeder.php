<?php

namespace Database\Seeders;

use App\Models\FeedType;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\RawMaterial;
use App\Models\User;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Feed\FeedProductionPlanService;
use App\Services\Feed\FeedRecipeService;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Feed\ProduceFeedBatchService;
use App\Services\Health\HealthRecordService;
use App\Services\Health\MedicineCatalogueService;
use App\Services\Health\MedicineStockService;
use App\Services\Health\RecordDoseService;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Support\CurrentOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Walkthrough data for Chipper Farms (local copy only).
 *
 * Every entry is made through the same service the matching screen uses,
 * acting as the user who would do it on the farm, with values those forms
 * accept. Nothing is written straight into tables, so what you see is exactly
 * what manual entry would have produced.
 *
 *   php artisan db:seed --class=FeedAndHealthWalkthroughSeeder
 */
class FeedAndHealthWalkthroughSeeder extends Seeder
{
    private const ORGANIZATION = 'Chipper Farms';

    public function run(): void
    {
        $org = Organization::where('name', self::ORGANIZATION)->firstOrFail();
        app(CurrentOrganization::class)->bind($org);

        if (FeedType::where('name', 'Sow Feed')->exists()) {
            $this->command?->warn('Walkthrough data is already loaded (Sow Feed exists). Nothing was added.');

            return;
        }

        $admin = $this->user($org, 'Admin');
        $manager = $this->user($org, 'Farm Manager');
        $dataEntry = $this->user($org, 'Data Entry');

        $this->receiveRawMaterials($admin);
        [$grower, $sow, $weaner] = $this->setUpFeeds($manager);
        $this->setRations($manager, $grower, $sow, $weaner);
        $this->mix($manager, $grower, $sow, $weaner);
        $this->recordFeedings($dataEntry, $grower, $sow, $weaner);
        $this->recordHealth($dataEntry, $manager);

        $this->command?->info('Walkthrough data loaded for '.self::ORGANIZATION.'.');
    }

    /** Raw Materials → Receive Stock, as Admin, from supplier "Pure farms". */
    private function receiveRawMaterials(User $admin): void
    {
        $this->actAs($admin);
        $supplier = \App\Models\Counterparty::where('name', 'Pure farms')->firstOrFail();
        $deliveries = [
            'Maize' => [2000, 4.50], 'Soybean Meal' => [600, 9.00], 'Wheat Bran' => [600, 2.50],
            'Fish Meal' => [100, 14.00], 'Limestone' => [60, 1.20], 'Dicalcium Phosphate' => [40, 8.00],
            'Salt' => [20, 1.50], 'Vitamin-Mineral Premix' => [25, 20.00], 'L-Lysine' => [15, 25.00],
        ];
        foreach ($deliveries as $name => [$kg, $price]) {
            app(ReceiveRawMaterialService::class)->execute(RawMaterial::where('name', $name)->firstOrFail(), $admin, [
                'quantity' => $kg, 'unit_cost' => $price, 'supplier_id' => $supplier->id,
                'receipt_reason' => 'direct_purchase', 'reference' => 'Delivery note PF-1042',
            ]);
        }

        // Raw Materials → Edit: a reorder level so one material shows as running low after mixing.
        RawMaterial::where('name', 'L-Lysine')->firstOrFail()->update(['reorder_level' => 10]);
    }

    /** Feed Setup → feed types and recipes (kg per 100 kg), as Farm Manager. */
    private function setUpFeeds(User $manager): array
    {
        $this->actAs($manager);
        $grower = FeedType::where('name', 'Grower feed')->firstOrFail();
        $sow = FeedType::create(['name' => 'Sow Feed', 'notes' => 'Gestating and lactating sows', 'is_active' => true]);
        $weaner = FeedType::create(['name' => 'Weaner Feed', 'notes' => 'From weaning to about 14 weeks', 'is_active' => true]);

        $recipes = [
            [$grower, ['Maize' => 60, 'Soybean Meal' => 20, 'Wheat Bran' => 15, 'Limestone' => 1.5, 'Dicalcium Phosphate' => 1, 'Salt' => 0.5, 'Vitamin-Mineral Premix' => 1, 'L-Lysine' => 1]],
            [$sow, ['Maize' => 54, 'Soybean Meal' => 15, 'Wheat Bran' => 25, 'Fish Meal' => 2, 'Limestone' => 1.5, 'Dicalcium Phosphate' => 1, 'Salt' => 0.5, 'Vitamin-Mineral Premix' => 1]],
            [$weaner, ['Maize' => 50, 'Soybean Meal' => 25, 'Wheat Bran' => 12, 'Fish Meal' => 6, 'Limestone' => 1.5, 'Dicalcium Phosphate' => 1.5, 'Salt' => 0.5, 'Vitamin-Mineral Premix' => 1.5, 'L-Lysine' => 2]],
        ];
        foreach ($recipes as [$type, $ingredients]) {
            app(FeedRecipeService::class)->save($manager, [
                'feed_type_id' => $type->id,
                'items' => collect($ingredients)->map(fn ($kg, $name) => [
                    'raw_material_id' => RawMaterial::where('name', $name)->value('id'), 'quantity_per_100kg' => (string) $kg,
                ])->values()->all(),
            ]);
        }

        return [$grower, $sow, $weaner];
    }

    /** Feed Setup → Daily rations. Finisher is left unset on purpose, for you to try. */
    private function setRations(User $manager, FeedType $grower, FeedType $sow, FeedType $weaner): void
    {
        $rations = [
            ['weaner', $weaner, 1.2], ['grower', $grower, 2.0], ['gestating_sow', $sow, 2.5],
            ['lactating_sow', $sow, 6.0], ['other_sows', $sow, 2.5],
        ];
        foreach ($rations as [$stage, $feed, $kg]) {
            app(FeedConfigurationService::class)->standard($manager, [
                'stage' => $stage, 'feed_type_id' => $feed->id, 'daily_kg' => (string) $kg,
                'effective_from' => now()->toDateString(), 'is_active' => true,
            ]);
        }
    }

    /** Feed Production → Record a batch, and Feed Planning → one plan left awaiting confirmation. */
    private function mix(User $manager, FeedType $grower, FeedType $sow, FeedType $weaner): void
    {
        foreach ([[$grower, 400], [$sow, 600], [$weaner, 300]] as [$feed, $kg]) {
            app(ProduceFeedBatchService::class)->execute($manager, [
                'submission_key' => (string) Str::uuid(), 'feed_type_id' => $feed->id,
                'batch_date' => now()->toDateString(), 'batch_size_kg' => $kg, 'notes' => 'Morning mix',
            ]);
        }

        app(FeedProductionPlanService::class)->create($manager, [
            'feed_type_id' => $grower->id, 'planned_quantity_kg' => '200',
            'production_date' => now()->toDateString(), 'submission_key' => (string) Str::uuid(),
        ]);
    }

    /** Feedings → Record Feeding (one morning round), as Data Entry. */
    private function recordFeedings(User $dataEntry, FeedType $grower, FeedType $sow, FeedType $weaner): void
    {
        $this->actAs($dataEntry);
        $round = [
            ['weaner', $weaner, 11],          // expected 9.6 kg — fed a little more
            ['grower', $grower, 2],
            ['gestating_sow', $sow, 2.5],
            ['lactating_sow', $sow, 18],
            ['other_sows', $sow, 150],        // expected 15 kg — a typing slip to try reversing
        ];
        foreach ($round as [$stage, $feed, $kg]) {
            app(IssueFeedToGroupService::class)->execute($dataEntry, [
                'submission_key' => (string) Str::uuid(), 'feeding_stage' => $stage, 'feed_type_id' => $feed->id,
                'quantity_kg' => $kg, 'issued_at' => now()->setTime(7, 30)->toDateTimeString(), 'reference' => 'Morning feeding',
            ]);
        }
    }

    /** Health → Add Health Record / Add Medication. */
    private function recordHealth(User $dataEntry, User $manager): void
    {
        $health = app(HealthRecordService::class);
        $pig = fn (string $code) => PigManagement::where('code', $code)->firstOrFail();
        $daysAgo = fn (int $days) => now()->subDays($days)->toDateString();

        // Doses come from the medicine catalogue and take stock (health
        // upgrade Phase 3): set up the medicines and receive a batch of each.
        $this->actAs($manager);
        $catalogue = app(MedicineCatalogueService::class);
        $stock = app(MedicineStockService::class);
        $medicines = [];
        foreach ([
            ['Amoxicillin', '150 mg/ml', 14, 'Injection (IM)'],
            ['Oxytetracycline LA', '200 mg/ml', 28, 'Injection (IM)'],
            ['Ivermectin', '1%', null, 'Injection (SC)'],          // no withdrawal entered: shows as a caution
            ['Penicillin-Streptomycin', '200/250 mg/ml', 21, 'Injection (IM)'],
            ['Penicillin G', '300 mg/ml', 10, 'Injection (IM)'],
        ] as [$name, $strength, $withdrawal, $route]) {
            $medicine = $catalogue->save($manager, [
                'name' => $name, 'formulation' => 'Injectable solution', 'concentration' => $strength, 'dose_unit' => 'ml', 'stock_unit' => 'ml',
                'dose_units_per_stock_unit' => '1', 'allowed_routes' => [$route], 'meat_withdrawal_days' => $withdrawal,
                'withdrawal_source_note' => $withdrawal === null ? null : 'Product label', 'reorder_level' => '20', 'is_active' => true,
            ]);
            $stock->receive($manager, $medicine, [
                'batch_number' => strtoupper(substr($name, 0, 3)).'-2601', 'received_at' => now()->subDays(45)->toDateString(),
                'expiry_date' => now()->addMonths(10)->toDateString(), 'quantity_received' => '100', 'unit_cost' => '1.20', 'supplier' => 'Agro vet shop',
            ]);
            $medicines[$name] = $medicine;
        }
        $dose = fn (string $code, $medicine, int $days, string $ml, string $route, string $purpose) => app(RecordDoseService::class)->record($dataEntry, [
            'medicine_id' => $medicine->id, 'administered_at' => now()->subDays($days)->setTime(8, 0)->toDateTimeString(), 'route' => $route,
            'amount_basis' => 'per_animal', 'dose_amount' => $ml, 'dose_unit' => 'ml', 'purpose' => $purpose, 'idempotency_key' => (string) Str::uuid(),
        ], [$pig($code)]);

        $this->actAs($dataEntry);
        // A weaner with scours, under treatment and inside its withdrawal.
        $health->create($dataEntry, $pig('CFPG-2607-9'), [
            'record_date' => $daysAgo(2), 'condition' => 'Diarrhoea (scours)', 'severity' => 'moderate',
            'symptoms' => 'Loose yellow droppings, slightly off feed', 'treatment_plan' => 'Amoxicillin for 3 days, keep warm and dry',
            'treatment_status' => 'ongoing',
        ]);
        $dose('CFPG-2607-9', $medicines['Amoxicillin'], 1, '2', 'Injection (IM)', 'Treatment');
        // A finisher given a 28-day medicine three days ago: its sale is blocked.
        $health->create($dataEntry, $pig('CFPG-2509-21'), [
            'record_date' => $daysAgo(3), 'condition' => 'Lameness, front left leg', 'severity' => 'mild',
            'treatment_status' => 'ongoing',
        ]);
        $dose('CFPG-2509-21', $medicines['Oxytetracycline LA'], 3, '5', 'Injection (IM)', 'Treatment');
        // A finisher dewormed with no withdrawal entered: the sale form shows a caution.
        $dose('CFPG-2510-18', $medicines['Ivermectin'], 5, '3', 'Injection (SC)', 'Deworming');
        // A finisher whose withdrawal finished long ago: it can be sold.
        $dose('CFPG-2511-17', $medicines['Penicillin-Streptomycin'], 40, '4', 'Injection (IM)', 'Treatment');
        // A sow dose entered as 6 ml instead of 4 — the kind of slip to try correcting.
        $dose('CFPG-2512-16', $medicines['Penicillin G'], 1, '6', 'Injection (IM)', 'Treatment');

        $this->actAs($manager);
        // An older case that has been resolved.
        $health->create($manager, $pig('CFPG-2409-5'), [
            'record_date' => $daysAgo(20), 'condition' => 'Mastitis', 'severity' => 'severe',
            'diagnosis' => 'Mastitis, rear quarter', 'treatment_status' => 'completed', 'outcome' => 'recovered',
            'resolved_at' => $daysAgo(12), 'resolution_notes' => 'Udder soft, eating normally',
        ]);
    }

    private function user(Organization $org, string $name): User
    {
        return User::where('organization_id', $org->id)->where('name', $name)->firstOrFail();
    }

    private function actAs(User $user): void
    {
        auth()->login($user);
    }
}
