<x-app-layout>
    <x-feed-hub hub="mill" />
    @php
        // Whole kg for big amounts; one decimal below 10 kg, so a small shortage never reads "0 kg".
        $kg = fn ($v) => \App\Support\Qty::kg($v, abs((float) $v) < 10 ? 1 : 0);
        $canMix = auth()->user()->hasPermission('manage_feed_production');
        $canRequest = auth()->user()->can('create', \App\Models\PurchaseRequest::class);
        $short = $materials->where('shortfall_kg', '>', 0);
        $columnCount = count($columns);
        $peak = max(1, collect($columns)->max('required_kg'));
    @endphp

    <main class="max-w-6xl mx-auto px-4 pb-24 sm:px-6 space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Mix &amp; buy</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">What the herd will eat {{ $period === 'today' ? 'today' : 'over '.$periodLabel }}, what is ready, and what to mix and buy. Do this once a week.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-2 text-sm" x-data="{ period: @js($period) }">
                <label class="font-medium text-slate-700">Plan for
                    <select name="period" x-model="period" @change="if (period !== 'custom') $el.form.submit()" class="mt-1 block rounded-xl border-slate-300 text-sm">
                        @foreach(['today' => 'Today', '1w' => 'Next week', '2w' => 'Next 2 weeks', '4w' => 'Next 4 weeks', '8w' => 'Next 8 weeks', 'month' => 'Rest of this month', 'custom' => 'Choose dates…'] as $value => $label)
                            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <template x-if="period === 'custom'">
                    <span class="flex flex-wrap items-end gap-2">
                        <label class="font-medium text-slate-700">From<input type="date" name="from" value="{{ $range['from']->toDateString() }}" required class="mt-1 block rounded-xl border-slate-300 text-sm"></label>
                        <label class="font-medium text-slate-700">To<input type="date" name="to" value="{{ $range['to']->toDateString() }}" required class="mt-1 block rounded-xl border-slate-300 text-sm"></label>
                        <button class="rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white">Show</button>
                    </span>
                </template>
            </form>
        </header>

        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if(session('mix_error'))<p role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ session('mix_error') }}</p>@endif
        @if($errors->any())<ul role="alert" class="list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        @if($exceptions->isNotEmpty())
            <section role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">Some animals are not counted yet</p>
                <ul class="mt-1 list-disc pl-5">@foreach($exceptions as $notice)<li>{{ $notice }}</li>@endforeach</ul>
                <a href="{{ route('feed-setup.index') }}#step-standards" class="mt-2 inline-block font-semibold underline">Set their daily ration in Setup</a>
            </section>
        @endif

        @if(! empty($whole['notes']))
            <section role="status" class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                <p class="font-semibold">Good to know</p>
                <ul class="mt-1 list-disc pl-5">@foreach($whole['notes'] as $note)<li>{{ $note }}</li>@endforeach</ul>
            </section>
        @endif

        {{-- 1. Mix --}}
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="mix-heading">
            <div class="border-b border-slate-100 p-5">
                <h2 id="mix-heading" class="text-lg font-bold text-slate-900"><span class="mr-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-700 text-sm text-white">1</span>Mix feed</h2>
                <p class="mt-1 text-sm text-slate-500">Mixing makes the feed and takes the ingredients out of the store. Change the amount if you mix more or less.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($whole['feeds'] as $feed)
                    @php
                        $toMix = (float) $feed['production_kg'];
                        $allowed = $mixable[$feed['feed_type_id']] ?? null;
                        $suggest = $allowed === null ? ceil($toMix) : min(ceil($toMix), $allowed);
                    @endphp
                    <div class="grid gap-3 p-5 md:grid-cols-[1.4fr_repeat(3,minmax(0,1fr))_minmax(14rem,1.6fr)] md:items-center">
                        <div>
                            <p class="font-bold text-slate-900">{{ $feed['name'] }}</p>
                            @if($feed['days_cover'] !== null)<p class="text-xs text-slate-500">Ready feed lasts about {{ rtrim(rtrim(number_format($feed['days_cover'], 1), '0'), '.') ?: '0' }} days</p>@endif
                        </div>
                        <div><p class="text-xs text-slate-500">Needed</p><p class="font-semibold tabular-nums">{{ $kg($feed['required_kg']) }} kg</p></div>
                        <div><p class="text-xs text-slate-500">Ready now</p><p class="font-semibold tabular-nums">{{ $kg($feed['available_kg']) }} kg</p></div>
                        <div><p class="text-xs text-slate-500">To mix</p><p class="font-bold tabular-nums {{ $toMix > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $toMix > 0 ? $kg($toMix).' kg' : 'Enough' }}</p></div>
                        <div>
                            @if($toMix > 0 && $canMix)
                                @if($suggest > 0)
                                    <form method="POST" action="{{ route('feed-production.mix') }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <input type="hidden" name="feed_type_id" value="{{ $feed['feed_type_id'] }}">
                                        <input type="hidden" name="batch_date" value="{{ now()->toDateString() }}">
                                        <input type="hidden" name="return" value="mix-buy">
                                        @foreach($periodQuery as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                                        <label class="sr-only" for="mix-{{ $feed['feed_type_id'] }}">Kg of {{ $feed['name'] }} to mix</label>
                                        <div class="relative w-28">
                                            <input id="mix-{{ $feed['feed_type_id'] }}" type="number" name="batch_size_kg" min="1" step="1" value="{{ $suggest }}" required class="w-full rounded-lg border-slate-300 pr-8 text-right text-sm">
                                            <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-slate-400">kg</span>
                                        </div>
                                        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Mix</button>
                                    </form>
                                @endif
                                @if($allowed !== null && $allowed < ceil($toMix))
                                    <p class="mt-1 text-xs font-semibold text-red-700">{{ $allowed > 0 ? 'The store only has enough ingredients for '.$kg($allowed).' kg.' : 'Not enough ingredients to mix any.' }} Buy below.</p>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="p-5 text-sm text-slate-500">No feed is needed: there are no animals with a daily ration. Set rations in <a href="{{ route('feed-setup.index') }}" class="font-semibold text-emerald-700 underline">Setup</a>.</p>
                @endforelse
            </div>
        </section>

        {{-- 2. Buy --}}
        <section class="rounded-2xl border {{ $short->isNotEmpty() ? 'border-red-200' : 'border-slate-200' }} bg-white shadow-sm" aria-labelledby="buy-heading">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 p-5">
                <div>
                    <h2 id="buy-heading" class="text-lg font-bold text-slate-900"><span class="mr-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-700 text-sm text-white">2</span>Buy ingredients</h2>
                    <p class="mt-1 text-sm text-slate-500">What it takes to mix all the feed above, against what is in the store.</p>
                </div>
                @if($short->isNotEmpty() && $whole['exceptions'] === [] && $canRequest)
                    <a href="{{ route('purchase-requests.create', ['planning_from' => $whole['from'], 'planning_to' => $whole['to'], 'planning_material' => 'all']) }}"
                       class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Buy all {{ $short->count() }} short on one purchase request</a>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead><tr><th>Ingredient</th><th class="text-right">Needed</th><th class="text-right">In the store</th><th class="text-right">To buy</th></tr></thead>
                    <tbody>
                        @forelse($materials as $material)
                            <tr class="border-t {{ $material['shortfall_kg'] > 0 ? 'bg-red-50/60' : '' }}">
                                <td class="font-semibold">{{ $material['name'] }}</td>
                                <td class="text-right tabular-nums">{{ $kg($material['required_kg']) }} kg</td>
                                <td class="text-right tabular-nums">{{ $kg($material['available_kg']) }} kg</td>
                                <td class="text-right tabular-nums {{ $material['shortfall_kg'] > 0 ? 'font-bold text-red-800' : 'text-emerald-700' }}">{{ $material['shortfall_kg'] > 0 ? $kg($material['shortfall_kg']).' kg' : 'Enough' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-5 text-slate-500">Nothing to buy: the feed already mixed covers {{ $periodLabel }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Week by week (the projection) --}}
        @if($columnCount > 1)
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="weeks-heading">
                <h2 id="weeks-heading" class="text-lg font-bold text-slate-900">Week by week</h2>
                <p class="text-sm text-slate-500">Kg of each feed per week, with wastage{{ end($columns)['days'] < 7 ? ' (the last column is '.end($columns)['days'].' '.\Illuminate\Support\Str::plural('day', end($columns)['days']).')' : '' }}. <span class="rounded bg-red-50 px-1 font-semibold text-red-800">Red</span> is the week its ready feed runs out.</p>
                <div class="mt-4 flex h-24 items-end gap-1.5" role="img" aria-label="Total feed needed per week">
                    @foreach($columns as $column)
                        <div class="flex flex-1 flex-col items-center justify-end gap-1">
                            <span class="text-[10px] font-semibold tabular-nums text-slate-500">{{ $kg($column['required_kg']) }}</span>
                            <div class="w-full rounded-t-md bg-emerald-500/80" style="height: {{ max(2, round($column['required_kg'] / $peak * 70)) }}px"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="data-table w-full text-sm">
                        <thead><tr><th>Feed</th>@foreach($columns as $i => $column)<th class="whitespace-nowrap text-right">{{ $column['from']->format('j M') }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach($feeds as $feed)
                                <tr class="border-t"><td class="font-semibold">{{ $feed['name'] }}</td>
                                    @foreach($feed['weeks'] as $i => $amount)<td class="text-right tabular-nums {{ ($feed['runs_out_week'] ?? null) === $i ? 'bg-red-50 font-bold text-red-800' : '' }}">{{ $kg($amount) }}</td>@endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Where the numbers come from --}}
        <details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <summary class="cursor-pointer font-bold text-slate-900">Where these numbers come from</summary>
            <p class="mt-2 text-sm text-slate-600">Animals are counted from the pig records, plus piglets still on their sows. Each stage's daily ration × its animals × the days gives the feed; each feed's wastage is added; feed already mixed is taken off; the rest is split into ingredients by the feed's recipe. Growing pigs move up a stage as they age. A pregnant sow becomes lactating on her expected farrowing date and goes back to "other sows" once her litter is {{ \App\Services\Feed\FeedStageResolver::weaningDays() }} days old. Matings cannot be forecast, so other sows and boars stay as they are.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead><tr><th>Stage</th>@foreach($columns as $column)<th class="text-right">{{ $column['from']->format('j M') }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($stages as $stage)
                            <tr class="border-t"><td class="font-semibold">{{ $stage['label'] }}</td>@foreach($stage['weeks'] as $count)<td class="text-right tabular-nums">{{ rtrim(rtrim(number_format($count, 1), '0'), '.') ?: '0' }}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ $columnCount + 1 }}" class="p-3 text-slate-500">No animals with a daily ration.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-500">Average animals per day in each week. For one stage, room or building, open the <a href="{{ route('planning-reports.index', ['tab' => 'plan']) }}" class="font-semibold text-emerald-700 underline">detailed plan</a>.</p>
        </details>

        {{-- Recent mixes --}}
        <section aria-labelledby="recent-mixes">
            <div class="flex items-end justify-between gap-3">
                <h2 id="recent-mixes" class="text-lg font-bold text-slate-900">Recent mixes</h2>
                <a href="{{ route('feed-production.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">All mixes and corrections</a>
            </div>
            <div class="mt-2 divide-y divide-slate-100 rounded-2xl border border-slate-100 bg-white shadow-sm">
                @forelse($recentMixes as $batch)
                    <div class="flex items-center justify-between px-4 py-3 text-sm {{ $batch->reversed_at ? 'opacity-60' : '' }}">
                        <span><span class="font-semibold text-slate-900 {{ $batch->reversed_at ? 'line-through' : '' }}">{{ $batch->feedType?->name }}</span> <span class="text-slate-500">· {{ \Carbon\Carbon::parse($batch->batch_date)->format('j M') }}@if($batch->reversed_at) · reversed @endif</span></span>
                        <span class="font-mono font-semibold">{{ $kg($batch->batch_size_kg) }} kg</span>
                    </div>
                @empty
                    <p class="px-4 py-5 text-center text-sm text-slate-500">Nothing mixed yet.</p>
                @endforelse
            </div>
        </section>
    </main>
</x-app-layout>
