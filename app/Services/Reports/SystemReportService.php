<?php

namespace App\Services\Reports;

use App\Models\Farrowing;
use App\Models\FeedIssue;
use App\Models\HealthRecord;
use App\Models\Mating;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigletDeath;
use App\Models\PigSale;
use App\Models\PigWeight;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Structure;
use App\Models\Weaning;
use App\Models\Storefront\StorefrontOrder;
use App\Services\Finance\Inventory\InventoryLedgerReader;
use App\Services\Finance\Reporting\IncomeStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SystemReportService
{
    public function __construct(
        private readonly IncomeStatementService $incomeStatements,
        private readonly InventoryLedgerReader $inventory,
    ) {
    }

    /**
     * The farm report. Finance figures read the "current" farm, which a
     * superuser viewing one farm does not have, so the farm is made current
     * while the report is built and the previous one is restored after.
     */
    public function build(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $current = app(\App\Support\CurrentOrganization::class);
        $previousOrganization = $current->get();
        $current->bind(\App\Models\Organization::find($organizationId));

        try {
            return $this->report($organizationId, $from, $to);
        } finally {
            $current->bind($previousOrganization);
        }
    }

    private function report(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $days = max(1, $from->diffInDays($to) + 1);
        $previousTo = $from->subSecond();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();

        $current = $this->periodSummary($organizationId, $from, $to);
        $previous = $this->periodSummary($organizationId, $previousFrom, $previousTo);

        // Pig records only: piglets still with the sow are counted under
        // Piglet tracking until they are weaned into stock.
        $opening = $this->herdAsOf($organizationId, $from->subSecond());
        $closing = $this->herdAsOf($organizationId, $to);

        $herdMovement = [
            'opening' => $opening,
            'weaned_into_stock' => $current['weaned_into_stock'],
            'purchased' => $current['purchased'],
            'manually_added' => $current['manually_added'],
            'sold' => $current['sold'],
            'deaths' => $current['deaths'],
            'transferred_or_culled' => $current['exits'],
            'closing' => $closing,
        ];
        $herdMovement['variance'] = $closing - ($opening + $current['new_pigs'] - $current['sold'] - $current['deaths'] - $current['exits']);

        $activeHerd = $this->pigs($organizationId)->active();
        $distribution = (clone $activeHerd)
            ->selectRaw("COALESCE(NULLIF(stock_type, ''), 'Unspecified') as label, COUNT(*) as total")
            ->groupBy('stock_type')->orderByDesc('total')->get();
        $genderDistribution = (clone $activeHerd)
            ->selectRaw("COALESCE(NULLIF(gender, ''), 'Unspecified') as label, COUNT(*) as total")
            ->groupBy('gender')->orderByDesc('total')->get();
        $breedDistribution = (clone $activeHerd)
            ->selectRaw("COALESCE(NULLIF(breed, ''), 'Unspecified') as label, COUNT(*) as total")
            ->groupBy('breed')->orderByDesc('total')->get()
            ->map(function ($row) {
                $row->label = Setting::breedName($row->label) ?? 'Unspecified';
                return $row;
            });
        $herdIndicators = [
            'active' => (clone $activeHerd)->count(),
            'unassigned_room' => (clone $activeHerd)->whereNull('room_id')->count(),
            'missing_weight' => (clone $activeHerd)->whereDoesntHave('weights')->count(),
            'missing_breed' => (clone $activeHerd)->where(function (Builder $query) {
                $query->whereNull('breed')->orWhere('breed', '')->orWhere('breed', 'unknown');
            })->count(),
        ];

        $monthlySales = $this->sales($organizationId)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->get(['sale_date', 'sale_price'])
            ->groupBy(fn (PigSale $sale) => $sale->sale_date->format('Y-m'))
            ->map(fn (Collection $sales, string $period) => (object) [
                'period' => $period,
                'revenue' => $sales->sum(fn (PigSale $sale) => (float) $sale->sale_price),
                'sales' => $sales->count(),
            ])->sortBy('period')->values();

        $breeding = [
            'matings' => $this->matings($organizationId)->whereBetween('actual_date', [$from, $to])->count(),
            'farrowings' => $this->farrowings($organizationId)->whereBetween('farrowing_date', [$from, $to])->count(),
            'weanings' => $this->weanings($organizationId)->whereBetween('weaning_date', [$from, $to])->count(),
            'average_litter_size' => (float) ($this->farrowings($organizationId)->whereBetween('farrowing_date', [$from, $to])->avg('total_piglets_born') ?? 0),
        ];
        // Of matings whose pregnancy has been checked: one not checked yet is
        // neither a success nor a failure.
        // A mating that later farrowed, or was lost or aborted, did conceive.
        $conceived = ['pregnant', 'farrowed', 'lost_pregnancy', 'aborted'];
        $checked = $this->matings($organizationId)->whereBetween('actual_date', [$from, $to])->whereIn('pregnancy_status', [...$conceived, 'not_pregnant']);
        $checkedCount = (clone $checked)->count();
        $pregnant = (clone $checked)->whereIn('pregnancy_status', $conceived)->count();
        $breeding['pregnancy_checked'] = $checkedCount;
        $breeding['pregnancy_rate'] = $checkedCount ? round(($pregnant / $checkedCount) * 100, 1) : null;

        $periodLitters = $this->farrowings($organizationId)
            ->whereBetween('farrowing_date', [$from, $to])
            ->with(['sow:id,organization_id,ear_tag_number,code', 'sow.earTagAssignments.tag.color', 'pigletDeaths'])
            ->orderByDesc('farrowing_date')
            ->get();
        $pigletDeaths = PigletDeath::query()
            ->whereHas('farrowing.sow', fn (Builder $query) => $query->forOrganization($organizationId))
            ->whereBetween('death_date', [$from, $to]);
        $weaningsInPeriod = $this->weanings($organizationId)->whereBetween('weaning_date', [$from, $to]);
        $liveBirths = (int) $periodLitters->sum('live_births');
        $stillbirths = (int) $periodLitters->sum('stillbirths');
        $deathsBeforeWeaning = (int) (clone $pigletDeaths)->sum('count');
        $pigletTracking = [
            'litters' => $periodLitters->count(),
            'total_born' => (int) $periodLitters->sum('total_piglets_born'),
            'live_births' => $liveBirths,
            'stillbirths' => $stillbirths,
            'males' => (int) $periodLitters->sum('number_of_males'),
            'females' => (int) $periodLitters->sum('number_of_females'),
            'pre_weaning_deaths' => $deathsBeforeWeaning,
            'currently_alive' => max(0, $liveBirths - $deathsBeforeWeaning),
            'survival_rate' => $liveBirths > 0 ? round((($liveBirths - $deathsBeforeWeaning) / $liveBirths) * 100, 1) : null,
            'stillbirth_rate' => ($liveBirths + $stillbirths) > 0 ? round(($stillbirths / ($liveBirths + $stillbirths)) * 100, 1) : null,
            'average_litter_size' => (float) ($periodLitters->avg('total_piglets_born') ?? 0),
            'average_birth_weight' => (float) ($periodLitters->whereNotNull('average_birth_weight')->avg('average_birth_weight') ?? 0),
            'weaned' => (clone $weaningsInPeriod)->count(),
            'average_weaning_age' => (float) ((clone $weaningsInPeriod)->avg('age_at_weaning_days') ?? 0),
            'average_weaning_weight' => (float) ((clone $weaningsInPeriod)->avg('weaning_weight') ?? 0),
            'death_causes' => (clone $pigletDeaths)->selectRaw("COALESCE(NULLIF(cause, ''), 'Unspecified') as label, SUM(count) as total")->groupBy('cause')->orderByDesc('total')->get(),
            'litter_details' => $periodLitters,
        ];

        $weightQuery = $this->weights($organizationId)->whereBetween('weigh_date', [$from, $to]);
        $weights = [
            'records' => (clone $weightQuery)->count(),
            'average_kg' => (float) ((clone $weightQuery)->avg('weight') ?? 0),
        ];

        $deathCauses = $this->deaths($organizationId)->whereBetween('death_date', [$from, $to])
            ->selectRaw("COALESCE(NULLIF(death_cause, ''), 'Unspecified') as label, COUNT(*) as total")
            ->groupBy('death_cause')->orderByDesc('total')->limit(6)->get();

        $health = $this->health($organizationId, $from, $to);

        // Feedings that were reversed (entered by mistake) are not feed given.
        $feedBase = FeedIssue::query()->forOrganization($organizationId)->notReversed()->whereBetween('issued_at', [$from, $to]);
        $mixed = \App\Models\FeedBatch::query()->where('organization_id', $organizationId)->whereNull('reversed_at')
            ->whereBetween('batch_date', [$from->toDateString(), $to->toDateString()]);
        $feed = [
            'issued_kg' => (float) ((clone $feedBase)->sum('quantity_kg') ?? 0),
            'issued_cost' => (float) ((clone $feedBase)->sum('total_cost') ?? 0),
            'issues' => (clone $feedBase)->count(),
            'mixed_kg' => (float) ((clone $mixed)->sum('batch_size_kg') ?? 0),
            'mixed_cost' => (float) ((clone $mixed)->sum('total_cost') ?? 0),
            'mixes' => (clone $mixed)->count(),
            'by_stage' => (clone $feedBase)->get(['feeding_stage', 'quantity_kg', 'total_cost'])->groupBy(fn (FeedIssue $issue) => $issue->feeding_stage ?: 'other')
                ->map(fn (Collection $rows, string $stage) => (object) [
                    'name' => \App\Services\Feed\FeedStageResolver::STAGES[$stage] ?? 'Other (not by stage)',
                    'quantity_kg' => $rows->sum(fn (FeedIssue $issue) => (float) $issue->quantity_kg),
                    'cost' => $rows->sum(fn (FeedIssue $issue) => (float) $issue->total_cost),
                ])->sortByDesc('quantity_kg')->values(),
        ];

        // Shop pig orders also record a pig sale, so pig sales already include
        // them; this splits the two rather than adding the shop on top.
        $shopSaleIds = \Illuminate\Support\Facades\DB::table('storefront_order_item_pig_allocations')->whereNotNull('pig_sale_id')->pluck('pig_sale_id');
        $periodSales = $this->sales($organizationId)->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()]);
        $pigSales = [
            'direct' => (clone $periodSales)->whereNotIn('id', $shopSaleIds)->count(),
            'direct_revenue' => (float) ((clone $periodSales)->whereNotIn('id', $shopSaleIds)->sum('sale_price') ?? 0),
            'shop' => (clone $periodSales)->whereIn('id', $shopSaleIds)->count(),
            'shop_revenue' => (float) ((clone $periodSales)->whereIn('id', $shopSaleIds)->sum('sale_price') ?? 0),
        ];

        $intakes = \App\Models\PurchaseStockIntake::query()->where('organization_id', $organizationId)
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()]);
        $purchases = [
            'intakes' => (clone $intakes)->where('status', '!=', \App\Models\PurchaseStockIntake::STATUS_CANCELLED)->count(),
            'animals_bought' => (int) ((clone $intakes)->where('status', '!=', \App\Models\PurchaseStockIntake::STATUS_CANCELLED)->sum('quantity_received') ?? 0),
            'added_to_stock' => (int) $this->pigs($organizationId)->withTrashed()->whereIn('purchase_stock_intake_id', (clone $intakes)->select('id'))->count(),
            'lost_in_quarantine' => (int) ((clone $intakes)->sum('quarantine_rejected_quantity') ?? 0),
            'cost' => (float) (clone $intakes)->where('status', '!=', \App\Models\PurchaseStockIntake::STATUS_CANCELLED)->get()->sum(fn ($i) => $i->totalLandedCost()),
            'waiting' => \App\Models\PurchaseStockIntake::query()->where('organization_id', $organizationId)
                ->whereIn('status', [\App\Models\PurchaseStockIntake::STATUS_PENDING_RECEIVE, \App\Models\PurchaseStockIntake::STATUS_RECEIVED, \App\Models\PurchaseStockIntake::STATUS_QUARANTINED, \App\Models\PurchaseStockIntake::STATUS_APPROVED])->count(),
        ];

        $storefrontBase = StorefrontOrder::query()->forOrganization($organizationId)->whereBetween('placed_at', [$from, $to]);
        $completedOrders = (clone $storefrontBase)->where('status', StorefrontOrder::STATUS_COMPLETED);
        $storefront = [
            'orders' => (clone $storefrontBase)->count(),
            'completed' => (clone $completedOrders)->count(),
            'open' => (clone $storefrontBase)->open()->count(),
            'realised_revenue' => (float) ((clone $completedOrders)->sum('total') ?? 0),
            'pipeline' => (float) ((clone $storefrontBase)->open()->sum('total') ?? 0),
            'average_order' => (float) ((clone $completedOrders)->avg('total') ?? 0),
            'by_status' => (clone $storefrontBase)->selectRaw('status, COUNT(*) as total_orders, COALESCE(SUM(total), 0) as value')->groupBy('status')->orderByDesc('total_orders')->get(),
        ];

        $incomeStatement = $this->incomeStatements->generate($from, $to, $previousFrom, $previousTo);
        $inventoryValue = $this->inventory->totalValue();
        $finance = [
            'revenue' => (float) $incomeStatement['totals']['revenue'],
            'cost_of_sales' => (float) $incomeStatement['totals']['cost_of_sales'],
            'gross_profit' => (float) $incomeStatement['gross_profit'],
            'operating_expenses' => (float) $incomeStatement['totals']['opex'],
            'net_profit' => (float) $incomeStatement['net_profit'],
            'gross_margin' => (float) $incomeStatement['totals']['revenue'] != 0.0 ? round(((float) $incomeStatement['gross_profit'] / (float) $incomeStatement['totals']['revenue']) * 100, 1) : null,
            'inventory_value' => (float) $inventoryValue['total_value'],
        ];

        $rooms = $this->rooms($organizationId)->with('structure')->withCount(['pigs' => fn ($query) => $query->active()])->get();
        $roomCapacity = (int) $rooms->sum(fn (Room $room) => max(0, (int) $room->capacity));
        $roomOccupancy = (int) $rooms->sum('pigs_count');
        $facilities = [
            'structures' => $this->structures($organizationId)->count(),
            'rooms' => $rooms->count(),
            'capacity' => $roomCapacity,
            'occupancy' => $roomOccupancy,
            'utilization_rate' => $roomCapacity > 0 ? round(($roomOccupancy / $roomCapacity) * 100, 1) : null,
            'over_capacity' => $rooms->filter(fn (Room $room) => $room->capacity > 0 && $room->pigs_count > $room->capacity)->count(),
            'details' => $rooms->sortBy(fn (Room $room) => ($room->structure?->name ?? '').$room->name)->values(),
        ];

        $exceptions = collect();
        if ($current['mortality_rate'] !== null && $current['mortality_rate'] > 3) {
            $exceptions->push(['severity' => 'high', 'title' => 'Mortality is above 3%', 'detail' => number_format($current['mortality_rate'], 1).'% for this period.']);
        }
        if ($facilities['over_capacity'] > 0) {
            $exceptions->push(['severity' => 'high', 'title' => 'Rooms are over capacity', 'detail' => $facilities['over_capacity'].' room(s) need attention.']);
        }
        if ($weights['records'] === 0 && $closing > 0) {
            $exceptions->push(['severity' => 'medium', 'title' => 'No weights recorded', 'detail' => 'No weighing activity exists in the selected period.']);
        }
        if ($health['severe_open'] > 0) {
            $exceptions->push(['severity' => 'high', 'title' => 'Severe health cases are open', 'detail' => $health['severe_open'].' severe case(s) not yet resolved.']);
        }
        if ($herdMovement['variance'] !== 0) {
            $exceptions->push(['severity' => 'medium', 'title' => 'Herd movement needs reconciliation', 'detail' => abs($herdMovement['variance']).' animal(s) are not reconciled.']);
        }

        return compact(
            'from', 'to', 'previousFrom', 'previousTo', 'current', 'previous', 'herdMovement',
            'distribution', 'genderDistribution', 'breedDistribution', 'herdIndicators', 'monthlySales',
            'breeding', 'pigletTracking', 'weights', 'deathCauses', 'health', 'feed',
            'pigSales', 'purchases', 'storefront', 'finance', 'facilities', 'exceptions'
        );
    }

    /** Herd, sales and mortality for one farm and period; the platform report uses it too. */
    public function periodSummary(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = $this->sales($organizationId)->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()]);
        $deaths = $this->deaths($organizationId)->whereBetween('death_date', [$from->toDateString(), $to->toDateString()])->count();
        $pigletDeaths = (int) PigletDeath::query()
            ->whereHas('farrowing.sow', fn (Builder $query) => $query->forOrganization($organizationId))
            ->whereBetween('death_date', [$from->toDateString(), $to->toDateString()])->sum('count');
        $exits = \App\Models\PigExit::withoutGlobalScopes()->where('organization_id', $organizationId)
            ->whereDate('exit_date', '>=', $from->toDateString())->whereDate('exit_date', '<=', $to->toDateString())->count();
        $opening = $this->herdAsOf($organizationId, $from->subSecond());

        // New pig records, by where they came from.
        $newPigs = $this->pigs($organizationId)->withTrashed()->whereBetween('created_at', [$from, $to]);
        $weanedIntoStock = (clone $newPigs)->whereIn('id', Weaning::withoutGlobalScopes()->whereNotNull('pig_id')->select('pig_id'))->count();
        $purchased = (clone $newPigs)->whereNotNull('purchase_stock_intake_id')->count();
        $newCount = (clone $newPigs)->count();

        // Every animal on the farm during the period: the opening herd, piglets
        // already with their sows, piglets born, and animals bought or added.
        // Weaned piglets are already counted as piglets, so not again as new pigs.
        $litters = Farrowing::query()->whereHas('sow', fn (Builder $query) => $query->forOrganization($organizationId))->with('pigletDeaths');
        $pigletsAtStart = (clone $litters)->where('farrowing_date', '<', $from->toDateString())
            ->where(fn ($q) => $q->whereNull('date_weaned')->orWhere('date_weaned', '>=', $from->toDateString()))
            ->get()->sum(fn (Farrowing $f) => max(0, (int) $f->live_births - (int) $f->pigletDeaths->where('death_date', '<', $from)->sum('count')));
        $liveBirths = (int) (clone $litters)->whereBetween('farrowing_date', [$from->toDateString(), $to->toDateString()])->sum('live_births');
        $populationAtRisk = $opening + $pigletsAtStart + $liveBirths + ($newCount - $weanedIntoStock);
        $allDeaths = $deaths + $pigletDeaths;

        return [
            'closing_herd' => $this->herdAsOf($organizationId, $to),
            'new_pigs' => $newCount,
            'weaned_into_stock' => $weanedIntoStock,
            'purchased' => $purchased,
            'manually_added' => max(0, $newCount - $weanedIntoStock - $purchased),
            'sold' => (clone $sales)->count(),
            'deaths' => $deaths,
            'piglet_deaths' => $pigletDeaths,
            'exits' => $exits,
            'revenue' => (float) ((clone $sales)->sum('sale_price') ?? 0),
            'average_sale' => (float) ((clone $sales)->avg('sale_price') ?? 0),
            'average_price_per_kg' => (float) ((clone $sales)->whereNotNull('price_per_kg')->avg('price_per_kg') ?? 0),
            // Pig and piglet deaths over every animal on the farm in the period.
            'mortality_rate' => $populationAtRisk > 0 ? round(($allDeaths / $populationAtRisk) * 100, 2) : null,
        ];
    }

    /** Health work in the period, from health cases and recorded doses. */
    private function health(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cases = HealthRecord::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereNull('voided_at')->whereNull('deleted_at');
        $opened = (clone $cases)->whereBetween('record_date', [$from->toDateString(), $to->toDateString()]);
        $openNow = (clone $cases)->whereIn('case_status', ['open', 'under_treatment']);

        $doses = \App\Models\MedicationAdministration::query()->withoutGlobalScopes()->where('organization_id', $organizationId)
            ->whereNull('voided_at')->whereBetween('administration_date', [$from->toDateString(), $to->toDateString()]);
        $purpose = fn (?string $p) => match (true) {
            str_contains(strtolower((string) $p), 'vaccin') => 'Vaccination',
            str_contains(strtolower((string) $p), 'deworm') => 'Deworming',
            str_contains(strtolower((string) $p), 'treat') => 'Treatment',
            blank($p) => 'Not stated',
            default => 'Other',
        };

        $medicineCost = (float) \Illuminate\Support\Facades\DB::table('medication_dosing_allocations as a')
            ->join('medication_dosing_events as e', 'e.id', '=', 'a.medication_dosing_event_id')
            ->where('e.organization_id', $organizationId)->where('e.status', 'recorded')
            ->whereBetween('e.administered_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(a.stock_quantity * a.unit_cost_snapshot), 0) as cost')->value('cost');

        return [
            'cases_opened' => (clone $opened)->count(),
            'animals_affected' => (int) \Illuminate\Support\Facades\DB::table('health_record_animals')
                ->whereIn('health_record_id', (clone $opened)->select('id'))->distinct()->count('pig_id'),
            'cases_finished' => (clone $cases)->whereIn('case_status', ['resolved', 'closed'])->whereBetween('resolved_at', [$from->toDateString(), $to->toDateString()])->count(),
            'open_now' => (clone $openNow)->count(),
            'severe_open' => (clone $openNow)->where('severity', 'severe')->count(),
            'top_conditions' => (clone $opened)->selectRaw("COALESCE(NULLIF(`condition`, ''), 'Not stated') as label, COUNT(*) as total")
                ->groupBy('label')->orderByDesc('total')->limit(6)->get(),
            'doses' => (clone $doses)->count(),
            'doses_by_purpose' => (clone $doses)->pluck('purpose')->map($purpose)->countBy()->sortDesc()->all(),
            'medicine_cost' => $medicineCost,
            'under_withdrawal' => app(\App\Services\Health\WithdrawalStatusService::class)->restrictedCount(now()->toDateString()),
        ];
    }

    private function pigs(int $organizationId): Builder
    {
        return PigManagement::query()->forOrganization($organizationId);
    }

    private function herdAsOf(int $organizationId, CarbonImmutable $date): int
    {
        $registered = $this->pigs($organizationId)->withTrashed()->where('created_at', '<=', $date)->count();
        // A reversed (deleted) sale or death did not take the pig away.
        $sold = $this->sales($organizationId)->where('sale_date', '<=', $date->toDateString())->count();
        $deceased = $this->deaths($organizationId)->where('death_date', '<=', $date->toDateString())->count();
        $exited = \App\Models\PigExit::withoutGlobalScopes()->where('organization_id', $organizationId)
            ->whereDate('exit_date', '<=', $date->toDateString())->count();

        return max(0, $registered - $sold - $deceased - $exited);
    }

    private function sales(int $organizationId): Builder
    {
        return PigSale::query()->forOrganization($organizationId);
    }

    private function deaths(int $organizationId): Builder
    {
        return PigDeath::query()->where('organization_id', $organizationId);
    }

    private function weights(int $organizationId): Builder
    {
        return PigWeight::query()->whereHas('pig', fn (Builder $query) => $query->forOrganization($organizationId));
    }

    private function matings(int $organizationId): Builder
    {
        return Mating::query()->whereHas('female', fn (Builder $query) => $query->forOrganization($organizationId));
    }

    private function farrowings(int $organizationId): Builder
    {
        return Farrowing::query()->whereHas('sow', fn (Builder $query) => $query->forOrganization($organizationId));
    }

    private function weanings(int $organizationId): Builder
    {
        return Weaning::query()->whereHas('pig', fn (Builder $query) => $query->forOrganization($organizationId));
    }

    private function structures(int $organizationId): Builder
    {
        return Structure::query()->withoutGlobalScopes()->where('organization_id', $organizationId);
    }

    private function rooms(int $organizationId): Builder
    {
        return Room::query()->withoutGlobalScopes()->where('organization_id', $organizationId);
    }
}
