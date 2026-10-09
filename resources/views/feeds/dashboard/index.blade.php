<x-app-layout>
    <x-feed-hub hub="today" />
    @php
        $kg = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
        $badge = [
            'not_fed' => ['Not fed yet', 'bg-slate-100 text-slate-600'],
            'under'   => ['Under ration', 'bg-amber-100 text-amber-800'],
            'over'    => ['Over ration', 'bg-amber-100 text-amber-800'],
            'fed'     => ['Fed', 'bg-emerald-100 text-emerald-800'],
        ];
    @endphp
    <div class="max-w-7xl mx-auto px-4 pt-6 pb-24 space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Feed Dashboard</h1>
                <p class="text-sm text-slate-500">{{ now()->format('l j F Y') }} · today's feeding, stock against the next week and ingredients short for the next two weeks.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                @if(auth()->user()->hasPermission('manage_feed_issues'))<a href="{{ route('feed.issues.create') }}" class="rounded-xl bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700">Record feeding</a>@endif
                <a href="{{ route('feed-production.index') }}" class="rounded-xl border bg-white px-4 py-2 font-semibold text-slate-700 hover:bg-slate-50">Mix feed</a>
                <a href="{{ route('mill.plan') }}" class="rounded-xl border bg-white px-4 py-2 font-semibold text-slate-700 hover:bg-slate-50">Plan</a>
            </div>
        </header>

        @if($setupIncomplete)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Some stages have animals but no daily ration, so their expected amounts are missing. <a href="{{ route('feed-setup.index') }}#step-standards" class="font-semibold underline">Finish feed setup</a>
            </div>
        @endif

        <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Today's feeding</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($stages as $row)
                    <div class="rounded-xl border border-slate-100 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-bold text-slate-900">{{ $row['label'] }}</p>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$row['status']][1] }}">{{ $badge[$row['status']][0] }}</span>
                        </div>
                        <p class="text-xs text-slate-500">{{ $row['animals'] }} {{ \Illuminate\Support\Str::plural('animal', $row['animals']) }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $kg($row['fed_kg']) }} <span class="text-sm font-semibold text-slate-500">kg fed</span></p>
                        <p class="text-xs text-slate-500">{{ $row['expected_kg'] !== null ? 'of '.$kg($row['expected_kg']).' kg by ration' : 'No ration set' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No animals or feedings today.</p>
                @endforelse
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Finished feed: next 7 days</h2>
                @if($wastagePercent > 0)<p class="text-xs text-slate-500">Needs include a {{ $kg($wastagePercent) }}% wastage allowance.</p>@endif
                <div class="mt-3 divide-y divide-slate-100">
                    @forelse($feeds as $feed)
                        <div class="flex items-center justify-between gap-3 py-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $feed['name'] }}</p>
                                <p class="text-xs text-slate-500">{{ $kg($feed['available_kg']) }} kg in stock · {{ $kg($feed['required_kg']) }} kg needed</p>
                            </div>
                            <div class="text-right">
                                @if($feed['production_kg'] > 0)
                                    <p class="text-sm font-bold text-red-700">Mix {{ $kg($feed['production_kg']) }} kg</p>
                                @else
                                    <p class="text-sm font-bold text-emerald-700">Covered</p>
                                @endif
                                <p class="text-xs text-slate-500">{{ $feed['days_cover'] !== null ? $kg($feed['days_cover']).' days of stock' : '' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="py-3 text-sm text-slate-500">No feed needed — check the daily rations.</p>
                    @endforelse
                </div>
                @if($openPlans > 0)<a href="{{ route('feed-production.index') }}" class="mt-2 inline-block text-sm font-semibold text-emerald-700 underline">{{ $openPlans }} production {{ \Illuminate\Support\Str::plural('plan', $openPlans) }} waiting to be confirmed</a>@endif
            </section>

            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Ingredients</h2>
                <p class="text-xs text-slate-500">Short for the next 14 days of mixing, and at or below their reorder level.</p>
                <div class="mt-3 divide-y divide-slate-100">
                    @foreach($shortMaterials as $material)
                        <div class="flex justify-between gap-3 py-2 text-sm">
                            <span class="font-semibold text-slate-900">{{ $material['name'] }}</span>
                            <span class="font-semibold text-red-700">short {{ $kg($material['shortfall_kg']) }} kg</span>
                        </div>
                    @endforeach
                    @foreach($lowMaterials as $material)
                        <div class="flex justify-between gap-3 py-2 text-sm">
                            <span class="text-slate-800">{{ $material->name }}</span>
                            <span class="{{ (float) $material->quantity_in_stock <= 0 ? 'text-red-700' : 'text-amber-700' }}">{{ $kg($material->quantity_in_stock) }} {{ $material->unit }} left (reorder at {{ $kg($material->reorder_level) }})</span>
                        </div>
                    @endforeach
                    @if($shortMaterials->isEmpty() && $lowMaterials->isEmpty())
                        <p class="py-2 text-sm text-emerald-700">Nothing short or low.</p>
                    @endif
                </div>
                <a href="{{ route('raw-materials.index') }}" class="mt-2 inline-block text-sm font-semibold text-emerald-700 underline">Raw materials</a>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Last 30 days</h2>
                    <p class="text-xs text-slate-500">Feed fed and, for growing pigs, kg of feed per kg gained.</p>
                </div>
                <a href="{{ route('feed-performance.index') }}" class="text-sm font-semibold text-emerald-700 underline">Feed conversion report</a>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div><p class="text-sm text-slate-500">Feed fed</p><p class="text-2xl font-bold text-slate-900">{{ $kg($performance['totals']['feed_kg']) }} kg</p></div>
                <div><p class="text-sm text-slate-500">Growing pigs' feed conversion</p><p class="text-2xl font-bold text-slate-900">{{ $performance['totals']['growing_fcr'] ?? '—' }}</p></div>
                @if($showCosts)<div><p class="text-sm text-slate-500">Feed cost</p><p class="text-2xl font-bold text-slate-900">GH₵ {{ number_format($performance['totals']['feed_cost'], 2) }}</p></div>@endif
            </div>
        </section>
    </div>
</x-app-layout>
