<?php

namespace Database\Seeders;

use App\Models\Counterparty;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\Setting;
use App\Models\User;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Feed\FeedCorrectionService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedProductionPlanService;
use App\Services\Feed\FeedRecipeService;
use App\Services\Feed\IssueFeedToGroupService;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Support\CurrentOrganization;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A week of feed for Chipper Farms that shows how the module works end to end.
 *
 * Every step goes through the service its screen uses, as the person who would
 * do it, on the day it would happen; nothing is written straight into tables.
 * The notes on each record say which step of the flow it is.
 *
 *   Day 1  Stock      Admin receives the ingredients.
 *          Setup      Farm Manager adds feed types, recipes (one typed as a
 *                     1,000 kg mixing sheet), daily rations and wastage.
 *          Mix & buy  The first eight days of feed are mixed; the
 *                     ingredients come off stock.
 *   Day 1–8 Today     Data Entry fills each round from the ration. Two slips
 *                     are left in on purpose: sows over-fed one day (shows on
 *                     Reports) and a typing slip that was reversed.
 *   Today  Today      Sows and growers fed; weaners and finishers not yet, so
 *                     "Needs doing" says so.
 *          Mix & buy  The next weeks need mixing again, two ingredients are
 *                     short and premix is running low: try Mix, and "Buy all".
 *
 * Run on a farm with no feed data (php artisan feed:reset --organization=1 --force first):
 *
 *   php artisan db:seed --class=FeedWalkthroughSeeder
 */
class FeedWalkthroughSeeder extends Seeder
{
    private const ORGANIZATION = 'Chipper Farms';

    private const SUPPLIER = 'Pure farms';

    /** name => [category, unit, kg per unit, received (in units), cost per unit, reorder level (units), note] */
    private const INGREDIENTS = [
        'Maize'                  => ['Energy', 'bag', 50, 6, 225.00, 2, 'Bought in 50 kg bags. The planner works in kg and orders whole bags.'],
        'Soybean Meal'           => ['Protein', 'kg', null, 150, 9.00, 40, 'Main protein for every feed.'],
        'Wheat Bran'             => ['Energy', 'kg', null, 200, 2.50, 50, 'Fibre, mostly for the sow feed.'],
        'Fish Meal'              => ['Protein', 'kg', null, 10, 14.00, 5, 'Small amounts in every feed. Bought short on purpose: next week\'s mix is short of it.'],
        'Limestone'              => ['Mineral', 'kg', null, 20, 1.20, 5, 'Calcium.'],
        'Vitamin-Mineral Premix' => ['Additive', 'bag', 25, 1, 500.00, 1, 'One 25 kg bag. Its reorder level is one bag, so it shows as running low once opened.'],
        'Salt'                   => ['Mineral', 'kg', null, 10, 1.50, 2, 'Sodium.'],
    ];

    public function run(): void
    {
        $org = Organization::where('name', self::ORGANIZATION)->firstOrFail();
        app(CurrentOrganization::class)->bind($org);

        if (FeedType::where('organization_id', $org->id)->exists() || RawMaterial::where('organization_id', $org->id)->exists()) {
            $this->command?->warn('Chipper Farms already has feed data. Clear it first: php artisan feed:reset --organization='.$org->id.' --force');

            return;
        }

        $admin = $this->user($org, 'Admin');
        $manager = $this->user($org, 'Farm Manager');
        $dataEntry = $this->user($org, 'Data Entry');

        $today = CarbonImmutable::today();
        $start = $today->subDays(7);

        try {
            // All or nothing: a step that fails leaves the farm as it was.
            DB::transaction(function () use ($admin, $manager, $dataEntry, $start, $today) {
                $this->at($start->setTime(8, 0), fn () => $this->receive($admin));
                $this->at($start->setTime(9, 0), fn () => $this->setUp($manager));
                $this->at($start->setTime(10, 0), fn () => $this->mixFirstWeek($manager, $start, $today));

                for ($day = $start; $day->lt($today); $day = $day->addDay()) {
                    $this->at($day->setTime(7, 30), fn () => $this->feedingRound($dataEntry, $manager, $day, $start));
                }
                $this->at($today->setTime(7, 30), fn () => $this->feedingRound($dataEntry, $manager, $today, $start, onlyStages: ['gestating_sow', 'lactating_sow', 'other_sows', 'grower']));
            });
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
            auth()->logout();
        }

        $this->command?->info('Feed walkthrough loaded for '.self::ORGANIZATION.'. Start at Feed Today → Overview.');
    }

    /** Store → Raw materials → Add material, with its first delivery. */
    private function receive(User $admin): void
    {
        $this->actAs($admin);
        $supplier = Counterparty::where('name', self::SUPPLIER)->firstOrFail();

        foreach (self::INGREDIENTS as $name => [$category, $unit, $kgPerUnit, $received, $cost, $reorder, $note]) {
            $material = RawMaterial::create([
                'name' => $name, 'category' => $category, 'unit' => $unit, 'kg_per_unit' => $kgPerUnit,
                'quantity_in_stock' => 0, 'unit_cost' => $cost, 'reorder_level' => $reorder,
                'notes' => 'Walkthrough, step 1 (Store): '.$note,
            ]);
            app(ReceiveRawMaterialService::class)->execute($material, $admin, [
                'quantity' => $received, 'unit_cost' => $cost, 'supplier_id' => $supplier->id,
                'receipt_reason' => 'direct_purchase', 'reference' => 'Delivery note PF-2201',
                'notes' => 'First delivery. Receiving puts the ingredient in stock and values it.',
            ]);
        }
    }

    /** Feed Setup, steps 2 to 4, and the settings at the bottom of the page. */
    private function setUp(User $manager): array
    {
        $this->actAs($manager);
        $id = fn (string $name) => RawMaterial::where('name', $name)->value('id');

        $feeds = [
            'weaner'   => FeedType::create(['name' => 'Weaner Feed', 'is_active' => true, 'wastage_percent' => 5,
                'notes' => 'Walkthrough, step 2 (Setup): for weaners. Its own 5% wastage, because young pigs spill more; other feeds use the farm\'s 2%.']),
            'grower'   => FeedType::create(['name' => 'Grower Feed', 'is_active' => true, 'notes' => 'Walkthrough, step 2 (Setup): for growers.']),
            'finisher' => FeedType::create(['name' => 'Finisher Feed', 'is_active' => true, 'notes' => 'Walkthrough, step 2 (Setup): for finishers.']),
            'sow'      => FeedType::create(['name' => 'Sow Feed', 'is_active' => true, 'notes' => 'Walkthrough, step 2 (Setup): one feed for every sow stage. Its recipe was typed as a 1,000 kg mixing sheet.']),
        ];

        $percent = fn (array $shares) => collect($shares)->map(fn ($kg, $name) => ['raw_material_id' => $id($name), 'quantity_per_100kg' => (string) $kg])->values()->all();
        $recipes = app(FeedRecipeService::class);
        $recipes->save($manager, ['feed_type_id' => $feeds['weaner']->id, 'items' => $percent([
            'Maize' => 50, 'Soybean Meal' => 26, 'Wheat Bran' => 12, 'Fish Meal' => 8, 'Limestone' => 1.5, 'Vitamin-Mineral Premix' => 2, 'Salt' => 0.5])]);
        $recipes->save($manager, ['feed_type_id' => $feeds['grower']->id, 'items' => $percent([
            'Maize' => 60, 'Soybean Meal' => 20, 'Wheat Bran' => 15, 'Fish Meal' => 2, 'Limestone' => 1.5, 'Vitamin-Mineral Premix' => 1, 'Salt' => 0.5])]);
        $recipes->save($manager, ['feed_type_id' => $feeds['finisher']->id, 'items' => $percent([
            'Maize' => 65, 'Soybean Meal' => 15, 'Wheat Bran' => 16, 'Fish Meal' => 1, 'Limestone' => 1.5, 'Vitamin-Mineral Premix' => 1, 'Salt' => 0.5])]);
        // Typed as "kg in a batch" from the mill's sheet for a 1,000 kg mix; saved as shares.
        $recipes->save($manager, ['feed_type_id' => $feeds['sow']->id, 'items' => $recipes->percentagesFromBatch(collect([
            'Maize' => 540, 'Soybean Meal' => 150, 'Wheat Bran' => 250, 'Fish Meal' => 20, 'Limestone' => 15, 'Vitamin-Mineral Premix' => 15, 'Salt' => 10,
        ])->map(fn ($kg, $name) => ['raw_material_id' => $id($name), 'quantity_kg' => (string) $kg])->values()->all())]);

        // Step 4: kg per animal per day, for every stage that has animals.
        foreach ([['weaner', 'weaner', 1.2], ['grower', 'grower', 2.0], ['finisher', 'finisher', 2.8],
            ['gestating_sow', 'sow', 2.5], ['lactating_sow', 'sow', 6.0], ['other_sows', 'sow', 2.5]] as [$stage, $feed, $kg]) {
            app(FeedConfigurationService::class)->standard($manager, [
                'stage' => $stage, 'feed_type_id' => $feeds[$feed]->id, 'daily_kg' => (string) $kg,
                'effective_from' => now()->toDateString(), 'is_active' => true,
            ]);
        }

        // Settings: a farm-wide wastage allowance, and an alert when feeding is more than 10% off the ration.
        Setting::set(FeedPlanningService::WASTAGE_SETTING, '2');
        Setting::set('feed.alerts.variance_percent', '10');

        return $feeds;
    }

    /** Mill → Plan for the first eight days → "Plan all mixes" → confirm each mix. */
    private function mixFirstWeek(User $manager, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->actAs($manager);
        $orgId = (int) $manager->organization_id;
        $forecast = app(FeedPlanningService::class)->calculate($orgId, $from->toDateString(), $to->toDateString());
        $plans = app(FeedProductionPlanService::class);
        $key = (string) Str::uuid();

        foreach ($forecast['feeds'] as $feed) {
            if ($feed['production_kg'] <= 0) {
                continue;
            }
            // Mixed in whole kilograms, rounded up, as the mill would.
            $kg = (string) ceil($feed['production_kg']);
            $plan = $plans->create($manager, [
                'feed_type_id' => $feed['feed_type_id'], 'production_date' => $from->toDateString(),
                'planned_quantity_kg' => $kg,
                'submission_key' => \Ramsey\Uuid\Uuid::uuid5($key, 'feed:'.$feed['feed_type_id'])->toString(),
            ]);
            $plans->complete($manager, $plan, [
                'batch_date' => $from->toDateString(), 'finished_quantity_kg' => $kg,
                'actual_quantities' => collect($plan->recipe_snapshot['ingredients'])
                    ->mapWithKeys(fn ($i) => [$i['raw_material_id'] => round($kg * (float) $i['quantity_per_100kg'] / 100, 4)])->all(),
            ]);
        }
    }

    /**
     * Feed Today → Record feeding → "Fill all with today's ration" → Save.
     * Two slips are kept in on purpose so the checks have something to show.
     */
    private function feedingRound(User $dataEntry, User $manager, CarbonImmutable $day, CarbonImmutable $start, ?array $onlyStages = null): void
    {
        $this->actAs($dataEntry);
        $orgId = (int) $dataEntry->organization_id;
        $ration = app(FeedPlanningService::class)->calculate($orgId, $day->toDateString(), $day->toDateString(), [], applyWastage: false);
        $dayNumber = (int) $start->diffInDays($day) + 1;

        DB::transaction(function () use ($ration, $dataEntry, $manager, $day, $dayNumber, $onlyStages) {
            foreach ($ration['rows'] as $row) {
                if ($onlyStages !== null && ! in_array($row['stage'], $onlyStages, true)) {
                    continue;
                }
                $kg = round((float) $row['daily_kg'], 2);
                $note = 'Walkthrough, day '.$dayNumber.': filled from the ration.';

                // Day 4: the lactating sows were given 21 kg instead of 18.
                if ($dayNumber === 4 && $row['stage'] === 'lactating_sow') {
                    $kg = 21;
                    $note = 'Walkthrough, day 4: fed more than the ration on purpose. Planned vs actual flags it, because it is over the 10% alert.';
                }

                // Day 6: 25 kg typed for the other sows instead of 12.5, reversed and re-entered.
                if ($dayNumber === 6 && $row['stage'] === 'other_sows') {
                    $slip = $this->feed($dataEntry, $row, 25, 'Walkthrough, day 6: a typing slip (25 instead of 12.5).');
                    $this->actAs($manager);
                    app(FeedCorrectionService::class)->reverseIssue($manager, $slip, 'Typed 25 kg instead of 12.5 kg. The feed goes back into stock.');
                    $this->actAs($dataEntry);
                    $note = 'Walkthrough, day 6: the corrected entry, after the slip was reversed from Feedings → Open → Reverse.';
                }

                $this->feed($dataEntry, $row, $kg, $note);
            }
        });
    }

    private function feed(User $dataEntry, array $row, float $kg, string $note): FeedIssue
    {
        return app(IssueFeedToGroupService::class)->execute($dataEntry, [
            'submission_key' => (string) Str::uuid(), 'feeding_stage' => $row['stage'], 'feed_type_id' => $row['feed_type_id'],
            'quantity_kg' => $kg, 'issued_at' => now()->toDateTimeString(), 'reference' => 'Morning feeding', 'notes' => $note,
        ]);
    }

    /** Run a step as if it were that moment, so dates, "today" and reports read naturally. */
    private function at(CarbonImmutable $moment, \Closure $step): mixed
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);

        return $step();
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
