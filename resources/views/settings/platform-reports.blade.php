<x-app-layout>
    @php
        $tabs = [
            'overview' => ['Overview', 'Platform governance, adoption, data quality and consolidated outcomes.'],
            'livestock' => ['Livestock & stock', 'Stock position, additions, sales, deaths and mortality exposure.'],
            'breeding' => ['Breeding & piglets', 'Mating outcomes, farrowing activity, births and weaning progress.'],
            'health' => ['Health & medication', 'Recorded health activity, unresolved cases and livestock losses.'],
            'weights' => ['Weights & growth', 'Weight-record coverage, average measured weight and growth alerts.'],
            'feed' => ['Feed & production', 'Feed issued, recorded cost and cost per kilogram.'],
            'procurement' => ['Procurement', 'Requests, approvals, pending work and committed purchase value.'],
            'sales' => ['Sales & storefront', 'Farm sales, shop orders, payment and fulfilment completion.'],
            'finance' => ['Finance', 'Consolidated recorded inflows, procurement outflows and net movement indicator.'],
            'users' => ['Users & adoption', 'Account coverage, active users, auditable actions and data completeness.'],
            'risk' => ['Risk & compliance', 'Critical alerts, unresolved cases, inactivity and data-quality exceptions.'],
        ];

        $module = match ($activeTab) {
            'livestock' => [
                'title' => 'Livestock and stock performance', 'logic' => 'Standing is based on low recorded mortality. Farms without livestock activity are not ranked.',
                'metrics' => [['Active livestock',$totals['active_pigs'],'Current records'],['Added',$totals['added'],'During period'],['Sold',$totals['sold'],'Completed sales'],['Deaths',$totals['deaths'],'Recorded losses']],
                'columns' => [['active_pigs','Active'],['added','Added'],['sold','Sold'],['deaths','Deaths'],['mortality_rate','Mortality','percent']],
            ],
            'breeding' => [
                'title' => 'Breeding and piglet performance', 'logic' => 'Standing is based on confirmed pregnancies as a share of matings. Low-volume farms remain visible and are not treated as failures.',
                'metrics' => [['Matings',$totals['matings'],'Recorded matings'],['Farrowings',$totals['farrowings'],'Completed events'],['Live births',$totals['live_births'],'Piglets born alive'],['Weanings',$totals['weanings'],'Weaning records']],
                'columns' => [['matings','Matings'],['pregnant','Pregnant'],['conception_rate','Conception','percent'],['farrowings','Farrowings'],['live_births','Live births'],['weanings','Weanings']],
            ],
            'health' => [
                'title' => 'Health and medication oversight', 'logic' => 'Standing reflects the share of health records resolved, reduced by critical alerts. No diagnosis is made by this report.',
                'metrics' => [['Health records',$totals['health_records'],'Recorded in period'],['Open cases',$totals['open_health_cases'],'Unresolved'],['Critical alerts',$totals['critical_alerts'],'Immediate review'],['Deaths',$totals['deaths'],'Recorded losses']],
                'columns' => [['health_records','Records'],['open_health_cases','Open cases'],['health_resolution_rate','Resolution','percent'],['critical_alerts','Critical alerts'],['deaths','Deaths']],
            ],
            'weights' => [
                'title' => 'Weights and growth monitoring', 'logic' => 'Standing reflects weight-record coverage with deductions for critical growth alerts. Average weight is descriptive, not a ranking target.',
                'metrics' => [['Weight entries',$totals['weight_records'],'Recorded in period'],['Average weight',$totals['average_weight'].' kg','Across entries'],['Open alerts',$totals['alerts'],'Unacknowledged'],['Critical alerts',$totals['critical_alerts'],'Immediate review']],
                'columns' => [['weight_records','Entries'],['average_weight','Average','kg'],['alerts','Alerts'],['critical_alerts','Critical'],['weight_alert_rate','Alert rate','percent']],
            ],
            'feed' => [
                'title' => 'Feed usage and cost', 'logic' => 'Comparison is based on completeness of feed records and lower recorded cost per kilogram. It does not judge ration quality.',
                'metrics' => [['Feed issued',number_format($totals['feed_issued_kg'],1).' kg','Recorded issues'],['Feed cost','GHS '.number_format($totals['feed_cost'],2),'Recorded value'],['Average cost',$totals['feed_issued_kg'] > 0 ? 'GHS '.number_format($totals['feed_cost']/$totals['feed_issued_kg'],2).'/kg' : '—','Weighted average'],['Organizations reporting',$rows->where('feed_issued_kg','>',0)->count(),'With feed activity']],
                'columns' => [['feed_issued_kg','Issued','kg'],['feed_cost','Cost','currency'],['feed_cost_per_kg','Cost / kg','currency'],['active_pigs','Livestock context']],
            ],
            'procurement' => [
                'title' => 'Procurement performance', 'logic' => 'Standing is based on approved or completed requests as a share of all requests, with pending work shown separately.',
                'metrics' => [['Requests',$totals['purchase_requests'],'Submitted in period'],['Approved / completed',$totals['approved_purchases'],'Progressed'],['Pending',$totals['pending_purchases'],'Awaiting action'],['Purchase value','GHS '.number_format($totals['purchase_value'],2),'Requested value']],
                'columns' => [['purchase_requests','Requests'],['approved_purchases','Approved'],['pending_purchases','Pending'],['procurement_rate','Progressed','percent'],['purchase_value','Value','currency']],
            ],
            'sales' => [
                'title' => 'Sales and storefront performance', 'logic' => 'Standing is based on completed storefront orders as a share of orders placed. Revenue is shown separately and does not alone define performance.',
                'metrics' => [['Farm-sale revenue','GHS '.number_format($totals['revenue'],2),$totals['sold'].' pig sales'],['Storefront orders',$totals['orders'],'Placed in period'],['Completed orders',$totals['completed_orders'],'Fulfilled'],['Paid storefront value','GHS '.number_format($totals['storefront_revenue'],2),$totals['paid_orders'].' paid orders']],
                'columns' => [['revenue','Farm sales','currency'],['orders','Shop orders'],['paid_orders','Paid'],['completed_orders','Completed'],['order_completion_rate','Completion','percent'],['storefront_revenue','Shop value','currency']],
            ],
            'finance' => [
                'title' => 'Consolidated financial movement', 'logic' => 'The indicator compares recorded sales inflows with procurement value. It is not profit and excludes expenses not represented in these source modules.',
                'metrics' => [['Recorded sales inflow','GHS '.number_format($totals['revenue'],2),'Posted farm sales'],['Procurement value','GHS '.number_format($totals['purchase_value'],2),'Recorded requests'],['Net movement','GHS '.number_format($totals['revenue']-$totals['purchase_value'],2),'Not accounting profit'],['Open order pipeline','GHS '.number_format($totals['order_pipeline'],2),$totals['open_orders'].' orders']],
                'columns' => [['recorded_inflow','Sales inflow','currency'],['purchase_value','Procurement','currency'],['recorded_balance','Net movement','currency'],['order_pipeline','Pipeline','currency']],
            ],
            'users' => [
                'title' => 'Users and platform adoption', 'logic' => 'Standing combines the share of organization accounts active during the period with livestock data completeness.',
                'metrics' => [['Organization users',$totals['staff'],'Farm-level accounts'],['Active users',$totals['active_users'],'Used system in period'],['Audit events',$totals['audited_events'],'Traceable actions'],['Active modules',$totals['active_modules'],'Used in period']],
                'columns' => [['staff','Accounts'],['active_users','Active users'],['user_adoption','Adoption','percent'],['audit_events','Audit events'],['data_quality','Data quality','percent']],
            ],
            'risk' => [
                'title' => 'Risk and compliance signals', 'logic' => 'Standing starts from data completeness and deducts unresolved critical, health and weight exceptions. It is an attention indicator, not a compliance certification.',
                'metrics' => [['Healthy',$portfolio['healthy'],'Organizations'],['Watch list',$portfolio['watch'],'Monitor'],['Needs attention',$portfolio['attention'],'Immediate review'],['Critical alerts',$totals['critical_alerts'],'Across platform']],
                'columns' => [['status','Status'],['critical_alerts','Critical'],['alerts','All alerts'],['open_health_cases','Open health'],['data_quality','Data quality','percent']],
            ],
            default => [
                'title' => 'Executive platform overview', 'logic' => 'Standing combines data completeness, platform activity and unresolved risks. Open a module tab for the measures behind each result.',
                'metrics' => [['Organizations',$totals['active_organizations'].' / '.$totals['organizations'],'Enabled / total'],['User accounts',$totals['staff']+$totals['platform_users'],$totals['platform_users'].' system administrators'],['Audit events',$totals['audited_events'],'Recorded actions'],['Data quality',$portfolio['average_data_quality'].'%','Platform average']],
                'columns' => [['staff','Users'],['active_pigs','Livestock'],['revenue','Revenue','currency'],['audit_events','Activity'],['data_quality','Data quality','percent'],['status','Health']],
            ],
        };
        $module['columns'] = collect($module['columns'])
            ->map(fn ($column) => [$column[0], $column[1], $column[2] ?? null])
            ->all();

        $reportRows = $rows->map(function ($row) use ($activeTab) {
            $row['mortality_rate'] = ($row['active_pigs'] + $row['sold'] + $row['deaths']) > 0 ? round($row['deaths'] / ($row['active_pigs'] + $row['sold'] + $row['deaths']) * 100, 1) : 0;
            $row['conception_rate'] = $row['matings'] > 0 ? round($row['pregnant'] / $row['matings'] * 100, 1) : 0;
            $row['health_resolution_rate'] = $row['health_records'] > 0 ? round(($row['health_records'] - $row['open_health_cases']) / $row['health_records'] * 100, 1) : 0;
            $row['weight_alert_rate'] = $row['weight_records'] > 0 ? round($row['alerts'] / $row['weight_records'] * 100, 1) : 0;
            $row['feed_cost_per_kg'] = $row['feed_issued_kg'] > 0 ? round($row['feed_cost'] / $row['feed_issued_kg'], 2) : 0;
            $row['procurement_rate'] = $row['purchase_requests'] > 0 ? round($row['approved_purchases'] / $row['purchase_requests'] * 100, 1) : 0;
            $row['order_completion_rate'] = $row['orders'] > 0 ? round($row['completed_orders'] / $row['orders'] * 100, 1) : 0;
            $row['recorded_inflow'] = $row['revenue'];
            $row['recorded_balance'] = $row['recorded_inflow'] - $row['purchase_value'];
            $row['user_adoption'] = $row['staff'] > 0 ? min(100, round($row['active_users'] / $row['staff'] * 100, 1)) : 0;
            [$activity, $score] = match ($activeTab) {
                'livestock' => [$row['active_pigs'] + $row['added'] + $row['sold'] + $row['deaths'] > 0, 100 - $row['mortality_rate']],
                'breeding' => [$row['matings'] + $row['farrowings'] + $row['live_births'] > 0, $row['conception_rate']],
                'health' => [$row['health_records'] + $row['critical_alerts'] > 0, max(0, $row['health_resolution_rate'] - $row['critical_alerts'] * 10)],
                'weights' => [$row['weight_records'] + $row['alerts'] > 0, max(0, 100 - $row['weight_alert_rate'] - $row['critical_alerts'] * 10)],
                'feed' => [$row['feed_issued_kg'] > 0, $row['feed_issued_kg'] > 0 ? max(0, 100 - min(60, $row['feed_cost_per_kg'])) : 0],
                'procurement' => [$row['purchase_requests'] > 0, $row['procurement_rate']],
                'sales' => [$row['orders'] + $row['sold'] > 0, $row['orders'] > 0 ? $row['order_completion_rate'] : 70],
                'finance' => [$row['recorded_inflow'] + $row['purchase_value'] > 0, $row['recorded_inflow'] > 0 ? max(0, min(100, 50 + ($row['recorded_balance'] / $row['recorded_inflow'] * 50))) : 0],
                'users' => [$row['staff'] > 0, round(($row['user_adoption'] + $row['data_quality']) / 2, 1)],
                'risk' => [true, max(0, $row['data_quality'] - $row['critical_alerts'] * 15 - $row['open_health_cases'] * 5)],
                default => [true, max(0, round(($row['data_quality'] + min(100, $row['audit_events'] * 5)) / 2 - $row['critical_alerts'] * 10, 1))],
            };
            $row['score'] = $activity ? round($score, 1) : null;
            $row['standing'] = $row['score'] === null ? 'No activity' : ($row['score'] >= 80 ? 'Strong' : ($row['score'] >= 60 ? 'Stable' : 'Needs attention'));
            return $row;
        })->sortByDesc(fn ($row) => $row['score'] ?? -1)->values();

        $formatValue = fn ($value, $format = null) => match ($format) {
            'currency' => 'GHS '.number_format((float) $value, 2),
            'percent' => number_format((float) $value, 1).'%',
            'kg' => number_format((float) $value, 1).' kg',
            default => is_numeric($value) ? number_format((float) $value, floor((float) $value) == (float) $value ? 0 : 1) : $value,
        };
    @endphp

    <main class="min-h-screen bg-slate-50/70 pb-28"><div class="mx-auto max-w-[1550px] space-y-5 px-3 py-4 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="p-6 sm:p-8" style="background:linear-gradient(110deg,#0f172a 0%,#172033 52%,#047857 100%);color:#fff"><p class="text-xs font-black uppercase tracking-[.2em]" style="color:#6ee7b7">Platform management reporting</p><h1 class="mt-2 text-3xl font-black" style="color:#fff">System reports</h1><p class="mt-2 max-w-3xl text-sm leading-6" style="color:#e2e8f0">Cross-organization reporting by module, with read-only comparisons, transparent performance indicators and organization drill-downs.</p></div><form method="GET" class="grid gap-3 p-4 sm:grid-cols-[170px_170px_auto] sm:items-end"><input type="hidden" name="organization_id" value="all"><input type="hidden" name="tab" value="{{ $activeTab }}"><label class="text-xs font-bold uppercase text-slate-500">From<input type="date" name="from" value="{{ $from->toDateString() }}" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label><label class="text-xs font-bold uppercase text-slate-500">To<input type="date" name="to" value="{{ $to->toDateString() }}" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm"></label><div class="flex gap-2"><button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">Apply period</button><a href="{{ route('settings.reports', ['tab' => $activeTab]) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold">Reset</a></div></form></section>

        <nav class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Report modules"><div class="flex min-w-max gap-1">@foreach($tabs as $key => [$label,$description])<a href="{{ route('settings.reports', ['organization_id'=>'all','tab'=>$key,'from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}" class="rounded-xl px-4 py-2.5 text-sm font-bold transition {{ $activeTab === $key ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}" @if($activeTab === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach</div></nav>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1"><p class="text-xs font-black uppercase tracking-[.16em] text-emerald-700">{{ $tabs[$activeTab][0] }}</p><h2 class="mt-1 text-2xl font-black text-slate-950">{{ $module['title'] }}</h2><p class="mt-2 max-w-4xl text-sm leading-6 text-slate-600">{{ $tabs[$activeTab][1] }}</p><div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"><strong>How standing is calculated:</strong> {{ $module['logic'] }}</div></div>
                <div class="flex shrink-0 flex-wrap items-center gap-2 self-start" style="height:fit-content;align-self:flex-start">
                    <a href="{{ route('settings.reports.export.csv', ['organization_id'=>'all','tab'=>$activeTab,'from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}" class="inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-4 text-sm font-black text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50" style="height:44px;min-height:44px;max-height:44px">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Export CSV
                    </a>
                    <a href="{{ route('settings.reports.export.pdf', ['organization_id'=>'all','tab'=>$activeTab,'from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}" class="inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-emerald-700 px-4 text-sm font-black text-white shadow-sm transition hover:bg-emerald-800" style="height:44px;min-height:44px;max-height:44px">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Export PDF
                    </a>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">@foreach($module['metrics'] as [$label,$value,$hint])<article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"><p class="text-[11px] font-black uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mt-2 text-xl font-black text-slate-950 sm:text-2xl">{{ is_numeric($value) ? number_format((float)$value, floor((float)$value)==(float)$value?0:1) : $value }}</p><p class="mt-1 text-xs text-slate-500">{{ $hint }}</p></article>@endforeach</section>

        @php $leader = $reportRows->first(fn ($row) => $row['score'] !== null); $attention = $reportRows->where('standing', 'Needs attention')->first(); @endphp
        <section class="grid gap-4 lg:grid-cols-3"><article class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5"><p class="text-xs font-black uppercase text-emerald-700">Leading organization</p>@if($leader)<p class="mt-3 text-xl font-black text-emerald-950">{{ $leader['name'] }}</p><p class="mt-1 text-sm text-emerald-800">{{ $leader['standing'] }} · {{ $leader['score'] }} indicator</p>@else<p class="mt-3 font-bold text-emerald-900">No activity to compare</p>@endif</article><article class="rounded-3xl border border-amber-200 bg-amber-50 p-5"><p class="text-xs font-black uppercase text-amber-700">Requires attention</p>@if($attention)<p class="mt-3 text-xl font-black text-amber-950">{{ $attention['name'] }}</p><p class="mt-1 text-sm text-amber-800">Review the detailed measures below.</p>@else<p class="mt-3 font-bold text-amber-900">No organization currently flagged</p>@endif</article><article class="rounded-3xl border border-blue-200 bg-blue-50 p-5"><p class="text-xs font-black uppercase text-blue-700">Coverage</p><p class="mt-3 text-xl font-black text-blue-950">{{ $reportRows->whereNotNull('score')->count() }} of {{ $reportRows->count() }}</p><p class="mt-1 text-sm text-blue-800">Organizations with comparable activity</p></article></section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 p-5"><h2 class="text-lg font-black text-slate-950">Organization comparison</h2><p class="mt-1 text-sm text-slate-500">Ranked read-only indicators for {{ strtolower($tabs[$activeTab][0]) }}. Select a farm to open its platform summary.</p></div><div class="overflow-x-auto"><table class="w-full min-w-[900px]"><thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Rank / organization</th>@foreach($module['columns'] as [$key,$label,$format])<th class="px-4 py-3">{{ $label }}</th>@endforeach<th class="px-4 py-3">Standing</th><th class="px-4 py-3">View</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($reportRows as $index => $row)<tr class="hover:bg-slate-50/70"><td class="px-5 py-4"><div class="flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-black text-slate-600">{{ $row['score'] === null ? '—' : $index+1 }}</span><div><p class="font-black text-slate-950">{{ $row['name'] }}</p><p class="text-xs text-slate-500">{{ $row['active'] ? 'Active organization' : 'Inactive organization' }}</p></div></div></td>@foreach($module['columns'] as [$key,$label,$format])<td class="px-4 py-4 text-sm font-semibold text-slate-700">{{ $formatValue($row[$key] ?? 0, $format ?? null) }}</td>@endforeach<td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-black {{ $row['standing']==='Strong'?'bg-emerald-100 text-emerald-800':($row['standing']==='Stable'?'bg-blue-100 text-blue-800':($row['standing']==='No activity'?'bg-slate-100 text-slate-600':'bg-amber-100 text-amber-800')) }}">{{ $row['standing'] }}@if($row['score'] !== null) · {{ $row['score'] }}@endif</span></td><td class="px-4 py-4"><a href="{{ route('settings.organizations.show',$row['id']) }}" class="text-sm font-black text-emerald-700 hover:text-emerald-900">Farm summary →</a></td></tr>@empty<tr><td colspan="12" class="p-10 text-center text-slate-500">No organizations available.</td></tr>@endforelse</tbody></table></div></section>
    </div></main>
</x-app-layout>
