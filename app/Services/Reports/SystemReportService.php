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

    public function build(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $days = max(1, $from->diffInDays($to) + 1);
        $previousTo = $from->subSecond();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();

        $current = $this->periodSummary($organizationId, $from, $to);
        $previous = $this->periodSummary($organizationId, $previousFrom, $previousTo);

        $opening = $this->herdAsOf($organizationId, $from->subSecond());
        $periodPigs = $this->pigs($organizationId)->withTrashed()->whereBetween('created_at', [$from, $to]);
        $purchased = (clone $periodPigs)->whereNotNull('purchase_stock_intake_id')->count();
        $manuallyAdded = max(0, $current['new_pigs'] - $purchased);
        $closing = $this->herdAsOf($organizationId, $to);

        $herdMovement = [
            'opening' => $opening,
            'manually_added' => $manuallyAdded,
            'purchased' => $purchased,
            'sold' => $current['sold'],
            'deaths' => $current['deaths'],
            'transferred_or_culled' => $current['exits'],
            'closing' => $closing,
        ];
        $herdMovement['variance'] = $closing - ($opening + $manuallyAdded + $purchased - $current['sold'] - $current['deaths'] - $current['exits']);

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
        $eligibleMatings = $this->matings($organizationId)->whereBetween('actual_date', [$from, $to])->whereNotNull('female_id')->count();
        $pregnant = $this->matings($organizationId)->whereBetween('actual_date', [$from, $to])->where('pregnancy_status', 'pregnant')->count();
        $breeding['pregnancy_rate'] = $eligibleMatings ? round(($pregnant / $eligibleMatings) * 100, 1) : null;

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

        $health = [
            'records' => HealthRecord::query()->whereHas('pig', fn (Builder $query) => $query->forOrganization($organizationId))->whereBetween('record_date', [$from, $to])->count(),
            'open_cases' => HealthRecord::query()->whereHas('pig', fn (Builder $query) => $query->forOrganization($organizationId))->whereBetween('record_date', [$from, $to])->whereNotIn('treatment_status', ['completed', 'resolved'])->count(),
        ];

        $feedBase = FeedIssue::query()->forOrganization($organizationId)->whereBetween('issued_at', [$from, $to]);
        $feed = [
            'issued_kg' => (float) ((clone $feedBase)->sum('quantity_kg') ?? 0),
            'issued_cost' => (float) ((clone $feedBase)->sum('total_cost') ?? 0),
            'issues' => (clone $feedBase)->count(),
            'by_group' => (clone $feedBase)->with('group:id,name')->get()->groupBy('group_id')->map(fn (Collection $rows) => (object) [
                'name' => $rows->first()?->group?->name ?? 'Unassigned group',
                'quantity_kg' => $rows->sum(fn (FeedIssue $issue) => (float) $issue->quantity_kg),
                'cost' => $rows->sum(fn (FeedIssue $issue) => (float) $issue->total_cost),
            ])->sortByDesc('quantity_kg')->values(),
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
        if ($herdMovement['variance'] !== 0) {
            $exceptions->push(['severity' => 'medium', 'title' => 'Herd movement needs reconciliation', 'detail' => abs($herdMovement['variance']).' animal(s) are not reconciled.']);
        }

        return compact(
            'from', 'to', 'previousFrom', 'previousTo', 'current', 'previous', 'herdMovement',
            'distribution', 'genderDistribution', 'breedDistribution', 'herdIndicators', 'monthlySales',
            'breeding', 'pigletTracking', 'weights', 'deathCauses', 'health', 'feed',
            'storefront', 'finance', 'facilities', 'exceptions'
        );
    }

    private function periodSummary(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = $this->sales($organizationId)->whereBetween('sale_date', [$from, $to]);
        $deaths = $this->deaths($organizationId)->whereBetween('death_date', [$from, $to])->count();
        $exits = \App\Models\PigExit::withoutGlobalScopes()->where('organization_id', $organizationId)
            ->whereDate('exit_date', '>=', $from->toDateString())->whereDate('exit_date', '<=', $to->toDateString())->count();
        $opening = $this->herdAsOf($organizationId, $from->subSecond());
        $newPigs = $this->pigs($organizationId)->withTrashed()->whereBetween('created_at', [$from, $to])->count();
        $populationAtRisk = $opening + $newPigs;

        return [
            'closing_herd' => $this->herdAsOf($organizationId, $to),
            'new_pigs' => $newPigs,
            'sold' => (clone $sales)->count(),
            'deaths' => $deaths,
            'exits' => $exits,
            'revenue' => (float) ((clone $sales)->sum('sale_price') ?? 0),
            'average_sale' => (float) ((clone $sales)->avg('sale_price') ?? 0),
            'average_price_per_kg' => (float) ((clone $sales)->whereNotNull('price_per_kg')->avg('price_per_kg') ?? 0),
            'mortality_rate' => $populationAtRisk > 0 ? round(($deaths / $populationAtRisk) * 100, 2) : null,
        ];
    }

    private function pigs(int $organizationId): Builder
    {
        return PigManagement::query()->forOrganization($organizationId);
    }

    private function herdAsOf(int $organizationId, CarbonImmutable $date): int
    {
        $registered = $this->pigs($organizationId)->withTrashed()->where('created_at', '<=', $date)->count();
        $sold = $this->sales($organizationId)->withTrashed()->where('sale_date', '<=', $date->toDateString())->count();
        $deceased = $this->deaths($organizationId)->withTrashed()->where('death_date', '<=', $date->toDateString())->count();
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
        return Structure::query()->whereIn('created_by', $this->organizationUserIds($organizationId));
    }

    private function rooms(int $organizationId): Builder
    {
        return Room::query()->whereHas('structure', fn (Builder $query) => $query->whereIn('created_by', $this->organizationUserIds($organizationId)));
    }

    private function organizationUserIds(int $organizationId): Collection
    {
        return \App\Models\User::query()->where('organization_id', $organizationId)->pluck('id');
    }
}
