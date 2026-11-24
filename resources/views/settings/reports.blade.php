<x-app-layout>
    <style>
        .reports-header-grid {
            display: grid;
            gap: 1rem;
        }
        .reports-filter-grid {
            display: grid;
            gap: .5rem;
        }
        .report-export-button {
            display: inline-flex;
            min-height: 38px;
            min-width: 108px;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
            white-space: nowrap;
            line-height: 1;
        }
        @media (min-width: 720px) {
            .reports-header-grid {
                grid-template-columns: minmax(220px, .75fr) minmax(0, 1.5fr);
                align-items: center;
            }
            .reports-filter-grid {
                grid-template-columns: minmax(120px, 1fr) minmax(120px, 1fr) auto auto;
                align-items: end;
            }
        }
    </style>
    @php
        $change = function ($currentValue, $previousValue) {
            if ((float) $previousValue === 0.0) return null;
            return round((((float) $currentValue - (float) $previousValue) / abs((float) $previousValue)) * 100, 1);
        };
        $maxRevenue = max(1, (float) ($monthlySales->max('revenue') ?? 1));
        $totalDistribution = max(1, (int) $distribution->sum('total'));
        $palette = ['#087f5b', '#34a853', '#80c99a', '#d5ede0', '#f4c95d', '#8da399'];
        $kpis = [
            ['label' => 'Closing herd', 'value' => number_format($current['closing_herd']), 'raw' => $current['closing_herd'], 'previous' => $previous['closing_herd'], 'tone' => 'emerald'],
            ['label' => 'Sales revenue', 'value' => 'GHS '.number_format($current['revenue'], 2), 'raw' => $current['revenue'], 'previous' => $previous['revenue'], 'tone' => 'emerald'],
            ['label' => 'Average sale', 'value' => 'GHS '.number_format($current['average_sale'], 2), 'raw' => $current['average_sale'], 'previous' => $previous['average_sale'], 'tone' => 'blue'],
            ['label' => 'Mortality rate', 'value' => $current['mortality_rate'] === null ? 'N/A' : number_format($current['mortality_rate'], 2).'%', 'raw' => $current['mortality_rate'] ?? 0, 'previous' => $previous['mortality_rate'] ?? 0, 'tone' => 'rose', 'inverse' => true],
            ['label' => 'Average price / kg', 'value' => 'GHS '.number_format($current['average_price_per_kg'], 2), 'raw' => $current['average_price_per_kg'], 'previous' => $previous['average_price_per_kg'], 'tone' => 'amber'],
        ];
    @endphp

    <div class="min-h-screen bg-slate-50/70 pb-24">
        <div class="mx-auto max-w-[1500px] px-3 py-4 sm:px-6 lg:px-8 lg:py-7">
            <section x-data="{ report: 'overview' }">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="reports-header-grid px-4 py-4 sm:px-5">
                        <div>
                            <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.14em] text-emerald-700">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Management reporting
                            </div>
                            <h1 class="text-2xl font-bold tracking-tight text-slate-950">Farm reports</h1>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Review, compare and export farm performance.</p>
                        </div>

                        <div class="min-w-0">
                            <form method="GET" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div class="reports-filter-grid">
                                    @if(auth()->user()->isSuperuser())
                                        <label class="block min-w-0 sm:col-span-full">
                                            <span class="mb-1 block text-[11px] font-semibold text-slate-600">Farm organization</span>
                                            <select name="organization_id" class="w-full rounded-lg border-slate-300 bg-white py-2 text-sm">
                                                <option value="all">All organizations</option>
                                                @foreach($reportOrganizations as $organization)<option value="{{ $organization->id }}" @selected($reportOrganizationId === $organization->id)>{{ $organization->name }}</option>@endforeach
                                            </select>
                                        </label>
                                    @endif
                                    <label class="block min-w-0">
                                        <span class="mb-1 block text-[11px] font-semibold text-slate-600">From date</span>
                                        <input type="date" name="from" value="{{ $from->toDateString() }}" class="w-full rounded-lg border-slate-300 bg-white py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    </label>
                                    <label class="block min-w-0">
                                        <span class="mb-1 block text-[11px] font-semibold text-slate-600">To date</span>
                                        <input type="date" name="to" value="{{ $to->toDateString() }}" class="w-full rounded-lg border-slate-300 bg-white py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    </label>
                                    <button class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">Apply</button>
                                    <a href="{{ route('settings.reports', auth()->user()->isSuperuser() ? ['organization_id' => $reportOrganizationId] : []) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span>{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span>
                            <span class="text-slate-300">•</span>
                            <span>Refreshed {{ now()->format('g:i A') }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:flex">
                            <a href="{{ route('settings.reports.export.csv', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'organization_id' => auth()->user()->isSuperuser() ? $reportOrganizationId : null]) }}" class="report-export-button rounded-lg border border-slate-300 bg-white text-center text-xs font-semibold text-slate-700 transition hover:bg-slate-100">Export CSV</a>
                            <a href="{{ route('settings.reports.export.pdf', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'organization_id' => auth()->user()->isSuperuser() ? $reportOrganizationId : null]) }}" class="report-export-button rounded-lg bg-emerald-700 text-center text-xs font-semibold text-white transition hover:bg-emerald-800">Export PDF</a>
                        </div>
                    </div>
                </div>

                <nav class="mt-5 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Report sections">
                    @foreach(['overview' => 'Overview', 'herd' => 'Pig Stock & Movement', 'piglets' => 'Piglet Tracking', 'breeding' => 'Breeding', 'growth' => 'Growth & feed', 'health' => 'Health', 'sales' => 'Sales & storefront', 'purchases' => 'Bought animals', 'finance' => 'Finance', 'facilities' => 'Facilities'] as $key => $tab)
                        <button type="button" @click="report = '{{ $key }}'" :class="report === '{{ $key }}' ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'" class="whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition">{{ $tab }}</button>
                    @endforeach
                </nav>

                <div class="space-y-6 py-6">
                    <section x-show="report === 'overview'" id="overview" class="grid grid-cols-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:grid-cols-2 xl:grid-cols-5">
                        @foreach($kpis as $kpi)
                            @php $delta = $change($kpi['raw'], $kpi['previous']); $good = $delta !== null && (!($kpi['inverse'] ?? false) ? $delta >= 0 : $delta <= 0); @endphp
                            <article class="border-b border-slate-100 p-5 sm:border-r xl:border-b-0">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-slate-500">{{ $kpi['label'] }}</p>
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ ['emerald' => '#34d399', 'blue' => '#60a5fa', 'rose' => '#fb7185', 'amber' => '#fbbf24'][$kpi['tone']] }}"></span>
                                </div>
                                <p class="mt-3 break-words text-2xl font-bold tracking-tight text-slate-950">{{ $kpi['value'] }}</p>
                                <div class="mt-3 flex items-center gap-2 text-xs">
                                    @if($delta === null)
                                        <span class="font-semibold text-slate-400">No comparison</span>
                                    @else
                                        <span class="font-bold {{ $good ? 'text-emerald-700' : 'text-rose-600' }}">{{ $delta > 0 ? '↑' : ($delta < 0 ? '↓' : '—') }} {{ number_format(abs($delta), 1) }}%</span>
                                    @endif
                                    <span class="text-slate-400">vs previous period</span>
                                </div>
                            </article>
                        @endforeach
                    </section>

                    <section x-show="report === 'herd'" class="space-y-6">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div><h2 class="text-lg font-bold text-slate-950">Pig stock summary</h2><p class="mt-1 text-sm text-slate-500">Population position and recorded movement for {{ $from->format('d M') }} – {{ $to->format('d M Y') }}.</p></div>
                                <span class="w-fit rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">{{ number_format($herdIndicators['active']) }} currently active</span>
                            </div>
                            <div class="mt-5 grid grid-cols-1 overflow-hidden rounded-xl border border-slate-200 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach([
                                    ['label' => 'Opening', 'value' => $herdMovement['opening'], 'description' => 'Pigs on hand before this period began.'],
                                    ['label' => 'Added', 'value' => $herdMovement['weaned_into_stock'] + $herdMovement['purchased'] + $herdMovement['manually_added'], 'description' => $herdMovement['weaned_into_stock'].' weaned from own litters · '.$herdMovement['purchased'].' bought · '.$herdMovement['manually_added'].' added by hand or imported'],
                                    ['label' => 'Removed', 'value' => $herdMovement['sold'] + $herdMovement['deaths'] + $herdMovement['transferred_or_culled'], 'description' => $herdMovement['sold'].' sold · '.$herdMovement['deaths'].' deaths · '.$herdMovement['transferred_or_culled'].' transferred or culled'],
                                    ['label' => 'Closing', 'value' => $herdMovement['closing'], 'description' => 'Pigs remaining at the end of the period.'],
                                ] as $item)
                                    <div class="border-b border-r border-slate-100 p-4">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $item['label'] }}</p>
                                        <p class="mt-2 text-xl font-bold {{ $item['label'] === 'Removed' ? 'text-rose-600' : ($item['label'] === 'Closing' ? 'text-emerald-700' : 'text-slate-900') }}">{{ number_format($item['value']) }}</p>
                                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ $item['description'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 flex items-center gap-2 rounded-xl px-4 py-3 text-sm {{ $herdMovement['variance'] === 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}"><span class="font-bold">{{ $herdMovement['variance'] === 0 ? '✓ Stock movement is balanced.' : 'Movement variance: '.number_format($herdMovement['variance']) }}</span><span class="hidden sm:inline">Opening stock plus additions, less sales and deaths, agrees with closing stock.</span></div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-3">
                            @foreach([['title' => 'By stock type', 'rows' => $distribution], ['title' => 'By gender', 'rows' => $genderDistribution], ['title' => 'By breed', 'rows' => $breedDistribution]] as $breakdown)
                                @php $breakdownTotal = max(1, (int) $breakdown['rows']->sum('total')); @endphp
                                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-bold text-slate-900">{{ $breakdown['title'] }}</h3><div class="mt-4 space-y-3">@forelse($breakdown['rows'] as $row) @php $share = round(($row->total / $breakdownTotal) * 100, 1); @endphp <div><div class="mb-1 flex justify-between gap-3 text-sm"><span class="capitalize text-slate-600">{{ str_replace('_', ' ', $row->label) }}</span><span class="font-bold text-slate-900">{{ $row->total }} <span class="font-normal text-slate-400">{{ $share }}%</span></span></div><div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-600" style="width:{{ $share }}%"></div></div></div>@empty<p class="py-8 text-center text-sm text-slate-400">No stock data.</p>@endforelse</div></div>
                            @endforeach
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-bold text-slate-900">Stock record quality</h3><p class="mt-1 text-xs text-slate-500">Records that may need attention before management decisions or audits.</p><div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Without assigned room</p><p class="mt-1 text-2xl font-bold">{{ $herdIndicators['unassigned_room'] }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Without weight records</p><p class="mt-1 text-2xl font-bold">{{ $herdIndicators['missing_weight'] }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Missing breed</p><p class="mt-1 text-2xl font-bold">{{ $herdIndicators['missing_breed'] }}</p></div></div></div>
                    </section>

                    <section x-show="report === 'piglets'" class="space-y-6">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="text-lg font-bold text-slate-950">Piglet tracking</h2><p class="mt-1 text-sm text-slate-500">Birth, survival, loss and weaning performance for litters born in the selected period.</p></div><span class="w-fit rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">{{ $pigletTracking['litters'] }} litters recorded</span></div>
                            <div class="mt-5 grid grid-cols-2 overflow-hidden rounded-xl border border-slate-200 sm:grid-cols-4 lg:grid-cols-7">
                                @foreach(['Total born' => $pigletTracking['total_born'], 'Live births' => $pigletTracking['live_births'], 'Stillbirths' => $pigletTracking['stillbirths'], 'Pre-weaning deaths' => $pigletTracking['pre_weaning_deaths'], 'Currently alive' => $pigletTracking['currently_alive'], 'Weaned' => $pigletTracking['weaned'], 'Survival rate' => $pigletTracking['survival_rate'] === null ? 'N/A' : $pigletTracking['survival_rate'].'%'] as $label => $value)
                                    <div class="border-b border-r border-slate-100 p-4"><p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-2 text-xl font-bold {{ in_array($label, ['Stillbirths','Pre-weaning deaths']) ? 'text-rose-600' : (in_array($label, ['Currently alive','Survival rate']) ? 'text-emerald-700' : 'text-slate-900') }}">{{ is_numeric($value) ? number_format($value) : $value }}</p></div>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-bold text-slate-900">Birth performance</h3><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between border-b border-slate-100 pb-3"><dt class="text-slate-500">Average litter size</dt><dd class="font-bold">{{ number_format($pigletTracking['average_litter_size'], 1) }}</dd></div><div class="flex justify-between border-b border-slate-100 pb-3"><dt class="text-slate-500">Average birth weight</dt><dd class="font-bold">{{ number_format($pigletTracking['average_birth_weight'], 2) }} kg</dd></div><div class="flex justify-between border-b border-slate-100 pb-3"><dt class="text-slate-500">Male piglets</dt><dd class="font-bold">{{ $pigletTracking['males'] }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Female piglets</dt><dd class="font-bold">{{ $pigletTracking['females'] }}</dd></div></dl></div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-bold text-slate-900">Weaning performance</h3><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between border-b border-slate-100 pb-3"><dt class="text-slate-500">Piglets weaned</dt><dd class="font-bold">{{ $pigletTracking['weaned'] }}</dd></div><div class="flex justify-between border-b border-slate-100 pb-3"><dt class="text-slate-500">Average weaning age</dt><dd class="font-bold">{{ number_format($pigletTracking['average_weaning_age'], 1) }} days</dd></div><div class="flex justify-between"><dt class="text-slate-500">Average weaning weight</dt><dd class="font-bold">{{ number_format($pigletTracking['average_weaning_weight'], 2) }} kg</dd></div></dl></div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-bold text-slate-900">Piglet losses by cause</h3><div class="mt-4 space-y-3">@forelse($pigletTracking['death_causes'] as $cause)<div class="flex justify-between gap-3 text-sm"><span class="text-slate-500">{{ $cause->label }}</span><span class="font-bold text-rose-600">{{ $cause->total }}</span></div>@empty<p class="py-8 text-center text-sm text-slate-400">No piglet losses recorded in this period.</p>@endforelse</div></div>
                        </div>

                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 p-5"><h3 class="font-bold text-slate-900">Litter details</h3><p class="mt-1 text-xs text-slate-500">Performance for each farrowing recorded in this period.</p></div><div class="overflow-x-auto"><table class="w-full min-w-[850px] text-sm"><thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Farrowing date</th><th class="px-5 py-3">Sow</th><th class="px-5 py-3 text-right">Born</th><th class="px-5 py-3 text-right">Live</th><th class="px-5 py-3 text-right">Stillborn</th><th class="px-5 py-3 text-right">Deaths</th><th class="px-5 py-3 text-right">Alive</th><th class="px-5 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($pigletTracking['litter_details'] as $litter) @php $litterDeaths = $litter->pigletDeaths->sum('count'); $alive = max(0, (int)$litter->live_births - $litterDeaths); @endphp <tr><td class="px-5 py-4 text-slate-600">{{ $litter->farrowing_date?->format('d M Y') }}</td><td class="px-5 py-4 font-semibold text-slate-900">{{ $litter->sow?->identityAt($litter->farrowing_date) ?: 'Unknown sow' }}</td><td class="px-5 py-4 text-right">{{ $litter->total_piglets_born }}</td><td class="px-5 py-4 text-right text-emerald-700">{{ $litter->live_births }}</td><td class="px-5 py-4 text-right text-rose-600">{{ $litter->stillbirths }}</td><td class="px-5 py-4 text-right text-rose-600">{{ $litterDeaths }}</td><td class="px-5 py-4 text-right font-bold">{{ $alive }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600">{{ str_replace('_', ' ', $litter->status ?: 'recorded') }}</span></td></tr>@empty<tr><td colspan="8" class="px-5 py-12 text-center text-slate-400">No litters were recorded in the selected period.</td></tr>@endforelse</tbody></table></div></div>
                    </section>

                    <div x-show="report === 'overview'" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                        <section id="sales" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                            <div class="flex items-start justify-between gap-4">
                                <div><h2 class="font-bold text-slate-950">Sales revenue trend</h2><p class="mt-1 text-xs text-slate-500">Based on sale date, not record creation date</p></div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $current['sold'] }} sales</span>
                            </div>
                            <div class="mt-8 flex h-56 items-end gap-3 border-b border-slate-200 px-2 sm:gap-5">
                                @forelse($monthlySales as $month)
                                    <div class="group flex h-full min-w-0 flex-1 flex-col justify-end">
                                        <div class="mb-2 text-center text-[10px] font-semibold text-slate-500">GHS {{ number_format($month->revenue, 0) }}</div>
                                        <div class="mx-auto rounded-t-lg transition" style="display:block; width:72%; max-width:72px; min-height:8px; height:{{ max(8, ($month->revenue / $maxRevenue) * 170) }}px; background:linear-gradient(180deg, #10b981 0%, #047857 100%); border:1px solid #047857; border-bottom:0; box-shadow:0 8px 18px rgba(5, 150, 105, .16);"></div>
                                        <div class="py-3 text-center text-[11px] font-medium text-slate-500">{{ \Carbon\Carbon::createFromFormat('Y-m', $month->period)->format('M y') }}</div>
                                    </div>
                                @empty
                                    <div class="flex h-full w-full items-center justify-center text-sm text-slate-400">No sales were recorded in this period.</div>
                                @endforelse
                            </div>
                        </section>

                        <section id="herd" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="font-bold text-slate-950">Active herd composition</h2>
                            <p class="mt-1 text-xs text-slate-500">Animals grouped by stock type</p>
                            <div class="mt-6 space-y-4">
                                @forelse($distribution as $index => $row)
                                    @php $percentage = round(($row->total / $totalDistribution) * 100, 1); @endphp
                                    <div>
                                        <div class="mb-1.5 flex justify-between gap-3 text-sm"><span class="capitalize text-slate-600">{{ str_replace('_', ' ', $row->label) }}</span><span class="font-bold text-slate-900">{{ number_format($row->total) }} <span class="font-normal text-slate-400">{{ $percentage }}%</span></span></div>
                                        <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" style="width: {{ $percentage }}%; background: {{ $palette[$index % count($palette)] }}"></div></div>
                                    </div>
                                @empty
                                    <p class="py-12 text-center text-sm text-slate-400">No active herd data available.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>

                    <div x-show="report === 'overview'" class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-4"><div><h2 class="font-bold text-slate-950">Operational exceptions</h2><p class="mt-1 text-xs text-slate-500">Items that may need action</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $exceptions->count() }}</span></div>
                            <div class="mt-5 divide-y divide-slate-100">
                                @forelse($exceptions as $exception)
                                    <div class="flex items-start gap-3 py-3">
                                        <span class="mt-0.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $exception['severity'] === 'high' ? 'bg-rose-500' : 'bg-amber-400' }}"></span>
                                        <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-slate-800">{{ $exception['title'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $exception['detail'] }}</p></div>
                                        <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase {{ $exception['severity'] === 'high' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700' }}">{{ $exception['severity'] }}</span>
                                    </div>
                                @empty
                                    <div class="flex items-center gap-3 rounded-xl bg-emerald-50 p-4"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-600 text-white">✓</span><div><p class="text-sm font-bold text-emerald-900">No exceptions detected</p><p class="text-xs text-emerald-700">Recorded activity is within the current checks.</p></div></div>
                                @endforelse
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-bold text-slate-950">Herd movement reconciliation</h2><p class="mt-1 text-xs text-slate-500">Opening + additions − exits = closing</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $herdMovement['variance'] === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $herdMovement['variance'] === 0 ? 'Balanced' : 'Variance '.$herdMovement['variance'] }}</span></div>
                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-[620px] w-full text-sm">
                                    <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-400">@foreach(['Opening','Weaned into stock','Bought','Added / imported','Sold','Deaths','Transferred / culled','Closing'] as $heading)<th class="pb-3 pr-4 font-semibold">{{ $heading }}</th>@endforeach</tr></thead>
                                    <tbody><tr class="border-t border-slate-100">@foreach(['opening','weaned_into_stock','purchased','manually_added','sold','deaths','transferred_or_culled','closing'] as $key)<td class="py-4 pr-4 text-lg font-bold {{ in_array($key, ['sold','deaths','transferred_or_culled']) ? 'text-rose-600' : ($key === 'closing' ? 'text-emerald-700' : 'text-slate-800') }}">{{ in_array($key, ['sold','deaths','transferred_or_culled']) ? '−' : '' }}{{ number_format($herdMovement[$key]) }}</td>@endforeach</tr></tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        <section x-show="report === 'breeding'" id="breeding" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 class="font-bold text-slate-950">Breeding performance</h2>
                            <dl class="mt-5 space-y-3">@foreach(['Matings' => $breeding['matings'], 'Farrowings' => $breeding['farrowings'], 'Weanings' => $breeding['weanings'], 'Average litter size' => number_format($breeding['average_litter_size'], 1), 'Pregnancy rate (of '.$breeding['pregnancy_checked'].' checked matings)' => $breeding['pregnancy_rate'] === null ? 'Not checked yet' : $breeding['pregnancy_rate'].'%'] as $label => $value)<div class="flex justify-between gap-4 border-b border-slate-100 pb-3"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="font-bold text-slate-900">{{ $value }}</dd></div>@endforeach</dl>
                        </section>
                        <section x-show="report === 'growth'" id="growth-weights" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 class="text-lg font-bold text-slate-950">Growth & feed performance</h2><p class="mt-1 text-sm text-slate-500">Weight and feed activity recorded during this period.</p>
                            <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Weight records</p><p class="mt-2 text-2xl font-bold">{{ number_format($weights['records']) }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Average weight</p><p class="mt-2 text-2xl font-bold">{{ number_format($weights['average_kg'], 1) }} kg</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Feed given</p><p class="mt-2 text-2xl font-bold">{{ number_format($feed['issued_kg'], 1) }} kg</p><p class="mt-1 text-xs text-slate-400">{{ $feed['issues'] }} feedings · GHS {{ number_format($feed['issued_cost'], 2) }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Feed mixed</p><p class="mt-2 text-2xl font-bold">{{ number_format($feed['mixed_kg'], 1) }} kg</p><p class="mt-1 text-xs text-slate-400">{{ $feed['mixes'] }} mixes · GHS {{ number_format($feed['mixed_cost'], 2) }}</p></div></div>
                            <div class="mt-6 overflow-x-auto"><table class="w-full min-w-[520px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Feeding stage</th><th class="px-4 py-3 text-right">Feed given</th><th class="px-4 py-3 text-right">Cost</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($feed['by_stage'] as $row)<tr><td class="px-4 py-3 font-medium">{{ $row->name }}</td><td class="px-4 py-3 text-right">{{ number_format($row->quantity_kg, 1) }} kg</td><td class="px-4 py-3 text-right">GHS {{ number_format($row->cost, 2) }}</td></tr>@empty<tr><td colspan="3" class="px-4 py-10 text-center text-slate-400">No feed given in this period. Reversed feedings are left out.</td></tr>@endforelse</tbody></table></div>
                        </section>
                        <section x-show="report === 'health'" id="health" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h2 class="font-bold text-slate-950">Health & mortality</h2>
                            <p class="mt-1 text-sm text-slate-500">Health cases and doses recorded in this period; voided records are left out.</p>
                            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Cases opened</p><p class="text-2xl font-bold">{{ $health['cases_opened'] }}</p><p class="mt-1 text-xs text-slate-400">{{ $health['animals_affected'] }} animals · {{ $health['cases_finished'] }} resolved or closed</p></div>
                                <div class="rounded-xl bg-amber-50 p-4"><p class="text-xs text-amber-700">Open now</p><p class="text-2xl font-bold text-amber-900">{{ $health['open_now'] }}</p><p class="mt-1 text-xs text-amber-700">{{ $health['severe_open'] }} severe</p></div>
                                <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Doses given</p><p class="text-2xl font-bold">{{ $health['doses'] }}</p><p class="mt-1 text-xs text-slate-400">Medicine used: GHS {{ number_format($health['medicine_cost'], 2) }}</p></div>
                                <div class="rounded-xl bg-rose-50 p-4"><p class="text-xs text-rose-600">Mortality</p><p class="text-2xl font-bold text-rose-700">{{ $current['mortality_rate'] === null ? 'N/A' : number_format($current['mortality_rate'], 2).'%' }}</p><p class="mt-1 text-xs text-rose-600">{{ $current['deaths'] }} pigs · {{ $current['piglet_deaths'] }} piglets</p></div>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">Mortality: pig and piglet deaths out of every animal on the farm in the period (opening herd, piglets with their sows, births, and animals bought or added). {{ $health['under_withdrawal'] }} {{ str('animal')->plural($health['under_withdrawal']) }} under a medicine withdrawal today.</p>
                            <div class="mt-5 grid gap-6 lg:grid-cols-3">
                                <div><h3 class="text-sm font-bold text-slate-900">Doses by purpose</h3><div class="mt-2 space-y-2">@forelse($health['doses_by_purpose'] as $label => $total)<div class="flex justify-between text-sm"><span class="text-slate-500">{{ $label }}</span><span class="font-bold text-slate-900">{{ $total }}</span></div>@empty<p class="text-sm text-slate-400">No doses recorded.</p>@endforelse</div></div>
                                <div><h3 class="text-sm font-bold text-slate-900">Most common conditions</h3><div class="mt-2 space-y-2">@forelse($health['top_conditions'] as $condition)<div class="flex justify-between text-sm"><span class="text-slate-500">{{ $condition->label }}</span><span class="font-bold text-slate-900">{{ $condition->total }}</span></div>@empty<p class="text-sm text-slate-400">No cases opened.</p>@endforelse</div></div>
                                <div><h3 class="text-sm font-bold text-slate-900">Causes of pig deaths</h3><div class="mt-2 space-y-2">@forelse($deathCauses as $cause)<div class="flex justify-between text-sm"><span class="text-slate-500">{{ $cause->label }}</span><span class="font-bold text-slate-900">{{ $cause->total }}</span></div>@empty<p class="text-sm text-slate-400">No deaths recorded.</p>@endforelse</div></div>
                            </div>
                        </section>
                    </div>

                    <section x-show="report === 'sales'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-950">Sales & storefront</h2><p class="mt-1 text-sm text-slate-500">A pig bought in the shop is also recorded as a pig sale, so pig sales include shop pig sales; they are split here, not added twice.</p>
                        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Pig sales recorded on the farm</p><p class="mt-2 text-2xl font-bold">{{ $pigSales['direct'] }}</p><p class="mt-1 text-xs text-slate-400">GHS {{ number_format($pigSales['direct_revenue'], 2) }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Pig sales through the shop</p><p class="mt-2 text-2xl font-bold">{{ $pigSales['shop'] }}</p><p class="mt-1 text-xs text-slate-400">GHS {{ number_format($pigSales['shop_revenue'], 2) }}</p></div><div class="rounded-xl bg-emerald-50 p-4"><p class="text-xs text-emerald-700">All pig sales</p><p class="mt-2 text-2xl font-bold text-emerald-900">GHS {{ number_format($current['revenue'], 2) }}</p></div></div>
                        <h3 class="mt-6 text-sm font-bold text-slate-900">Shop orders (all products)</h3>
                        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Orders</p><p class="mt-2 text-2xl font-bold">{{ $storefront['orders'] }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Completed</p><p class="mt-2 text-2xl font-bold">{{ $storefront['completed'] }}</p></div><div class="rounded-xl bg-emerald-50 p-4"><p class="text-xs text-emerald-700">Realised revenue</p><p class="mt-2 text-2xl font-bold text-emerald-900">GHS {{ number_format($storefront['realised_revenue'], 2) }}</p></div><div class="rounded-xl bg-amber-50 p-4"><p class="text-xs text-amber-700">Open pipeline</p><p class="mt-2 text-2xl font-bold text-amber-900">GHS {{ number_format($storefront['pipeline'], 2) }}</p></div></div>
                        <div class="mt-6 overflow-x-auto"><table class="w-full min-w-[560px] text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Order status</th><th class="px-4 py-3 text-right">Orders</th><th class="px-4 py-3 text-right">Value</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($storefront['by_status'] as $row)<tr><td class="px-4 py-3 font-medium capitalize">{{ str_replace('_', ' ', $row->status) }}</td><td class="px-4 py-3 text-right">{{ $row->total_orders }}</td><td class="px-4 py-3 text-right">GHS {{ number_format($row->value, 2) }}</td></tr>@empty<tr><td colspan="3" class="px-4 py-10 text-center text-slate-400">No storefront orders in this period.</td></tr>@endforelse</tbody></table></div>
                    </section>

                    <section x-show="report === 'purchases'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-950">Bought animals</h2><p class="mt-1 text-sm text-slate-500">Purchased stock intakes dated in this period (cancelled ones left out).</p>
                        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-5">@foreach(['Purchases' => number_format($purchases['intakes']), 'Animals received' => number_format($purchases['animals_bought']), 'Added to stock' => number_format($purchases['added_to_stock']), 'Lost in quarantine' => number_format($purchases['lost_in_quarantine']), 'Cost incl. transport' => 'GHS '.number_format($purchases['cost'], 2)] as $label => $value)<div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold">{{ $value }}</p></div>@endforeach</div>
                        <p class="mt-4 text-sm text-slate-500">{{ $purchases['waiting'] }} {{ str('purchase')->plural($purchases['waiting']) }} still on the way to stock (any date).</p>
                    </section>

                    <section x-show="report === 'finance'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-950">Financial performance</h2><p class="mt-1 text-sm text-slate-500">From the farm's posted books for this period. Cost of sales is only what has been posted; sales without a posted cost show their full value as profit.</p>
                        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-3">@foreach(['Revenue' => $finance['revenue'], 'Cost of sales' => $finance['cost_of_sales'], 'Gross profit' => $finance['gross_profit'], 'Operating expenses' => $finance['operating_expenses'], 'Net profit' => $finance['net_profit'], 'Current inventory value' => $finance['inventory_value']] as $label => $value)<div class="rounded-xl {{ $label === 'Net profit' ? 'bg-slate-900 text-white' : 'bg-slate-50' }} p-4"><p class="text-xs {{ $label === 'Net profit' ? 'text-slate-300' : 'text-slate-500' }}">{{ $label }}</p><p class="mt-2 text-2xl font-bold">GHS {{ number_format($value, 2) }}</p></div>@endforeach</div>
                    </section>

                    <section x-show="report === 'facilities'" id="facilities" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-bold text-slate-950">Facility occupancy</h2><p class="mt-1 text-xs text-slate-500">Animal occupancy measured against room animal capacity</p></div><div class="flex flex-wrap gap-2 text-xs font-semibold"><span class="rounded-full bg-slate-100 px-3 py-1.5">{{ $facilities['structures'] }} structures</span><span class="rounded-full bg-slate-100 px-3 py-1.5">{{ $facilities['rooms'] }} rooms</span><span class="rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700">{{ $facilities['utilization_rate'] === null ? 'Capacity not set' : $facilities['utilization_rate'].'% occupied' }}</span></div></div>
                        <div class="overflow-x-auto"><table class="min-w-[720px] w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Structure</th><th class="px-5 py-3">Room</th><th class="px-5 py-3">Animals</th><th class="px-5 py-3">Capacity</th><th class="px-5 py-3">Utilization</th><th class="px-5 py-3">Status</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($facilities['details'] as $room) @php $utilization = $room->capacity > 0 ? round(($room->pigs_count / $room->capacity) * 100, 1) : null; @endphp <tr><td class="px-5 py-4 font-medium text-slate-700">{{ $room->structure?->name ?? 'Unassigned' }}</td><td class="px-5 py-4 text-slate-600">{{ $room->name }}</td><td class="px-5 py-4 font-bold">{{ $room->pigs_count }}</td><td class="px-5 py-4">{{ $room->capacity ?: 'Not set' }}</td><td class="px-5 py-4">{{ $utilization === null ? 'N/A' : $utilization.'%' }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $utilization !== null && $utilization > 100 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $utilization !== null && $utilization > 100 ? 'Over capacity' : 'Available' }}</span></td></tr>@empty<tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">No rooms available for this farm.</td></tr>@endforelse</tbody></table></div>
                    </section>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
