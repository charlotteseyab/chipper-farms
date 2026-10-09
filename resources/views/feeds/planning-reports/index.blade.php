<x-app-layout>
    <x-feed-hub :hub="['plan' => 'mill', 'consumption' => 'today', 'setup' => 'setup'][$tab]" />
    <main class="max-w-7xl mx-auto px-4 pb-6 sm:px-6 !pb-24 space-y-6">
        @php
            $varianceThreshold = \App\Models\Setting::get('feed.alerts.variance_percent');
            $coverThreshold = \App\Models\Setting::get('feed.alerts.cover_days');
            $notices = collect($plan['exceptions'])->map(fn($message) => preg_replace('/ on \d{4}-\d{2}-\d{2}\.$/', ' for part of this period.', $message))->unique();
        @endphp
        @php
            $titles = [
                'plan' => ['Plan', 'How much feed to mix and which ingredients to buy for the period.'],
                'consumption' => ['Planned vs actual', 'Feed given to each stage against its ration, day by day.'],
                'setup' => ['Ration history & settings', 'Every ration version, stage overrides, wastage allowance and alert thresholds.'],
            ];
        @endphp
        <header>
            <h1 class="text-2xl font-bold text-slate-900">{{ $titles[$tab][0] }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $titles[$tab][1] }}</p>
        </header>
        @if(session('success'))<p role="status" class="rounded-xl bg-green-50 p-4 text-green-800">{{ session('success') }}</p>@endif
        @if($errors->any())<ul role="alert" class="rounded-xl bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        @if($tab==='setup')
            @include('feeds.planning-reports.partials.setup')
        @else
            <form method="GET" x-data="{ period: @js($period) }" class="rounded-2xl border bg-white p-4 space-y-4">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="flex flex-wrap items-end gap-4">
                    <label class="text-sm font-medium">Period<select name="period" x-model="period" class="block mt-1 rounded-xl border-slate-300">@foreach(['today'=>'Today','weekly'=>($tab==='consumption'?'Last 7 days':'Next 7 days'),'bi-weekly'=>($tab==='consumption'?'Last 14 days':'Next 14 days'),'monthly'=>'Current month','custom'=>'Choose dates'] as $value=>$label)<option value="{{ $value }}" @selected($period===$value)>{{ $label }}</option>@endforeach</select></label>
                    <div x-show="period==='custom'" class="flex flex-wrap gap-4">
                        <label class="text-sm">From<input name="from" type="date" :disabled="period!=='custom'" :required="period==='custom'" value="{{ $plan['from'] }}" class="block mt-1 rounded-xl border-slate-300"></label>
                        <label class="text-sm">To<input name="to" type="date" :disabled="period!=='custom'" :required="period==='custom'" value="{{ $plan['to'] }}" class="block mt-1 rounded-xl border-slate-300"></label>
                    </div>
                    <button class="rounded-xl bg-emerald-700 text-white px-5 py-2.5">{{ $tab==='plan'?'Calculate plan':'Show consumption' }}</button>
                    <a href="{{ url()->current() }}" class="py-2 text-sm text-slate-500 underline">Reset</a>
                </div>
                <details @if(request()->anyFilled(['stage','feed_type_id','group_id','room_id','structure_id'])) open @endif>
                    <summary class="cursor-pointer text-sm font-medium text-slate-600">Filter by feed, stage or location</summary>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 mt-3">
                        <label class="text-sm">Stage<select name="stage" class="block w-full rounded-xl border-slate-300"><option value="">All stages</option>@foreach($stages as $value=>$label)<option value="{{ $value }}" @selected(request('stage')===$value)>{{ $label }}</option>@endforeach</select></label>
                        @foreach(['feed_type_id'=>['Feed type',$feedTypes],'group_id'=>['Group',$groups],'room_id'=>['Room',$rooms],'structure_id'=>['Structure',$structures]] as $field=>$options)
                            <label class="text-sm">{{ $options[0] }}<select name="{{ $field }}" class="block w-full rounded-xl border-slate-300"><option value="">All</option>@foreach($options[1] as $option)<option value="{{ $option->id }}" @selected((string)request($field)===(string)$option->id)>{{ $option->name }}</option>@endforeach</select></label>
                        @endforeach
                    </div>
                </details>
            </form>
            <p class="text-sm text-slate-600"><strong>{{ $plan['from'] }} to {{ $plan['to'] }}</strong> · {{ $plan['days'] }} days · {{ request()->anyFilled(['stage','feed_type_id','group_id','room_id','structure_id'])?'Filtered selection':'Whole farm' }}</p>
            @if($tab==='consumption')
                @include('feeds.planning-reports.partials.consumption')
            @else
                <div class="grid sm:grid-cols-3 gap-4">
                    @foreach(['Feed needed (kg)'=>$plan['required_kg'], 'In stock for these feeds (kg)'=>array_sum(array_column($plan['feeds'],'available_kg')), 'To produce (kg)'=>$plan['production_kg']] as $label=>$amount)
                        <div class="rounded-2xl border bg-white p-5"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ rtrim(rtrim(number_format($amount,4), '0'), '.') }}</p>@if(!$plan['demand_complete'])<p class="text-sm text-amber-800 mt-2">Partial total — feeding setup is missing.</p>@endif</div>
                    @endforeach
                </div>
                @if($plan['wastage_percent'] > 0)
                    <p class="text-sm text-slate-600">Includes a {{ rtrim(rtrim(number_format($plan['wastage_percent'], 2), '0'), '.') }}% wastage allowance: {{ rtrim(rtrim(number_format($plan['base_kg'], 2), '0'), '.') }} kg by ration + {{ rtrim(rtrim(number_format($plan['wastage_kg'], 2), '0'), '.') }} kg allowance.</p>
                @endif
                @if($notices->isNotEmpty())
                    <section role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><h2 class="font-semibold text-amber-900">Check feeding setup</h2><ul class="list-disc pl-5 mt-2 text-sm text-amber-900">@foreach($notices as $notice)<li>{{ $notice }}</li>@endforeach</ul><a class="inline-block mt-3 text-sm font-semibold underline" href="{{ route('feed-setup.settings') }}">Review feeding standards</a><a class="inline-block mt-3 ml-4 text-sm font-semibold underline" href="{{ route('feed-production.index') }}">Review recipes</a></section>
                @endif
                <section class="rounded-2xl border bg-white p-4 sm:p-6 space-y-4">
                    <div><h2 class="text-lg font-semibold">Feed to prepare</h2><p class="text-sm text-slate-500">Demand covers {{ collect($plan['rows'])->unique('stage')->sum('animal_count') }} animals with configured rates. Stock is shared across the farm and counted once per feed. Creating a plan does not reserve or deduct it.</p></div>
                    <div class="grid lg:grid-cols-2 gap-4">
                        @forelse($plan['feeds'] as $feed)
                            <article class="rounded-xl border p-4 space-y-4">
                                <div class="flex flex-wrap justify-between gap-2"><h3 class="font-semibold">{{ $feed['name'] }}</h3><span class="text-sm {{ $feed['production_kg']>0?'text-amber-800':'text-emerald-800' }}">{{ $feed['production_kg']>0?'Production needed':'Stock covers this plan' }}</span></div>
                                <dl class="grid grid-cols-3 gap-3 text-sm">@foreach(['Needed'=>$feed['required_kg'],'In stock'=>$feed['available_kg'],'To produce'=>$feed['production_kg']] as $label=>$amount)<div><dt class="text-slate-500">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ number_format($amount,4) }} kg</dd></div>@endforeach</dl>
                                @if($feed['production_kg']>0 && isset($feed['recipe_snapshot']) && $plan['demand_complete'] && auth()->user()->hasPermission('manage_feed_production'))
                                    <form method="POST" action="{{ route('feed-production.plans') }}">@csrf<input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="feed_type_id" value="{{ $feed['feed_type_id'] }}"><input type="hidden" name="planned_quantity_kg" value="{{ $feed['production_kg'] }}"><input type="hidden" name="production_date" value="{{ $plan['from'] }}"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Create production plan</button></form>
                                @endif
                                <details class="text-sm text-slate-500"><summary class="cursor-pointer">Stock coverage estimate</summary><p class="mt-2">{{ $feed['days_cover'] ?? '—' }} days at this forecast's average daily rate. Approximate stock-out: {{ $feed['stock_out_date'] ?? '—' }}.</p>@if(is_numeric($coverThreshold) && $feed['days_cover']!==null && $feed['days_cover']<$coverThreshold)<p class="text-amber-800">Below your configured coverage threshold.</p>@endif</details>
                            </article>
                        @empty
                            <p class="text-sm text-slate-500">{{ $plan['eligible_animal_count']===0?'No active animals match this selection.':'No feed requirement is available. Check the selected filters and feeding standards.' }}</p>
                        @endforelse
                    </div>
                </section>
                @include('feeds.planning-reports.partials.materials')
                <details class="rounded-2xl border bg-white p-4 sm:p-6">
                    <summary class="cursor-pointer font-semibold">How this plan was calculated</summary>
                    <p class="mt-3 text-sm text-slate-500">Growing pigs move up stages by age on each day of the forecast; breeding animals keep today's stage. Animals = average per day. Rates follow their effective dates. Unregistered litters are excluded. Monthly plans use the actual calendar month.</p>
                    <div class="overflow-x-auto mt-4"><table class="min-w-full text-sm"><thead><tr>@foreach(['Stage / feed','Animals','kg per animal/day','Days at this rate','Period total (kg)'] as $label)<th class="p-3 text-left">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($plan['rows'] as $row)<tr class="border-t"><td class="p-3">{{ $stages[$row['stage']] }}<br><span class="text-slate-500">{{ $row['feed_type'] }}</span></td><td>{{ rtrim(rtrim(number_format($row['animal_days'] / max(1, $row['applicable_days']), 1), '0'), '.') }}</td><td>{{ number_format($row['rate'],4) }}</td><td>{{ $row['applicable_days'] }}</td><td>{{ number_format($row['required_kg'],4) }}</td></tr>@empty<tr><td colspan="5" class="p-3">No configured demand for this selection.</td></tr>@endforelse</tbody></table></div>
                </details>
            @endif
        @endif
    </main>
</x-app-layout>
