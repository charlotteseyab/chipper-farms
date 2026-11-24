<x-app-layout>
    <x-feed-hub hub="today" />
    @php $n = fn ($v, $d = 1) => rtrim(rtrim(number_format((float) $v, $d), '0'), '.'); @endphp
    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24 space-y-5">
        @include('feeds.partials.report-switch')
        <header>
            <h1 class="text-2xl font-bold text-slate-900">Feed Conversion</h1>
            <p class="text-sm text-slate-500">Kg of feed per kg of weight gained, by stage. Lower is better. Weight gain comes from pigs weighed at least twice in the period.</p>
        </header>

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <label class="text-sm font-medium text-slate-700">From <input type="date" name="from" value="{{ $report['from'] }}" class="mt-1 block rounded-lg border-slate-300"></label>
            <label class="text-sm font-medium text-slate-700">To <input type="date" name="to" value="{{ $report['to'] }}" max="{{ now()->toDateString() }}" class="mt-1 block rounded-lg border-slate-300"></label>
            <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Show</button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th class="num">Feed fed</th>
                        @if($showCosts)<th class="num">Feed cost</th>@endif
                        <th class="num">Weight gained</th>
                        <th class="num">Feed conversion</th>
                        @if($showCosts)<th class="num">Cost per kg gained</th>@endif
                        <th>Based on</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['rows'] as $row)
                        @continue(! $row['growing'] && $row['feed_kg'] <= 0)
                        <tr>
                            <td class="font-semibold text-slate-900">{{ $row['label'] }}</td>
                            <td class="text-right font-mono num">{{ $n($row['feed_kg']) }} kg</td>
                            @if($showCosts)<td class="text-right font-mono num">{{ number_format($row['feed_cost'], 2) }}</td>@endif
                            <td class="text-right font-mono num">{{ $row['growing'] ? $n($row['gain_kg']).' kg' : '—' }}</td>
                            <td class="text-right font-mono font-bold num">{{ $row['fcr'] ?? '—' }}</td>
                            @if($showCosts)<td class="text-right font-mono num">{{ $row['cost_per_kg_gain'] !== null ? number_format($row['cost_per_kg_gain'], 2) : '—' }}</td>@endif
                            <td class="px-4 py-3 text-xs {{ $row['partial'] ? 'text-amber-700' : 'text-slate-500' }}">
                                @if(! $row['growing'])
                                    Breeding animals: not expected to gain
                                @elseif($row['pigs_weighed'] === 0)
                                    No pig weighed twice
                                @else
                                    {{ $row['pigs_weighed'] }} of {{ $row['pigs_now'] }} pigs weighed{{ $row['partial'] ? ' — partial, read with care' : '' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-sm text-slate-600">Growing pigs overall: <strong>{{ $report['totals']['growing_fcr'] ?? '—' }}</strong> kg feed per kg gained ({{ $n($report['totals']['growing_gain_kg']) }} kg gained).</p>
        @if($report['unstaged_feed_kg'] > 0)
            <p class="text-xs text-slate-500">{{ $n($report['unstaged_feed_kg']) }} kg of older feedings were recorded per group, not per stage, and are not included.</p>
        @endif
        <p class="text-xs text-slate-500">A stage's figure only reflects the whole stage when every pig in it is weighed at the start and end of the period. Feed for a stage is credited by the stage it was recorded against.</p>
    </div>
</x-app-layout>
