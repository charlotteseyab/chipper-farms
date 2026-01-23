<x-app-layout>
    <style>
        /* Custom scrollbar for tables */
        .custom-scrollbar::-webkit-scrollbar { height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Chart container constraint */
        .chart-container { position: relative; height: 320px; width: 100%; }

        /* Subtle background pattern */
        /* .bg-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 24px 24px;
        } */
    </style>

    <div class="min-h-screen font-sans text-slate-600 pb-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 pt-8">
            
            <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-6">
                <div class="relative z-10">
                    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                        Feed Analytics
                    </h1>
                    <p class="mt-2 text-base text-slate-500 max-w-2xl">
                        Track consumption, optimize recipes, and monitor ingredient costs across your farm.
                    </p>
                </div>
                
                <div class="relative z-10  p-2 rounded-2xl border border-white shadow-lg shadow-slate-200/50 flex flex-wrap items-center gap-3">
                    <form method="GET" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <div class="flex items-center gap-2 bg-slate-50 rounded-xl px-3 py-1.5 border border-slate-200">
                            <div class="relative">
                                <input type="date" name="start_date" value="{{ $from ?? now()->subDays(30)->toDateString() }}" 
                                    class="border-0 bg-transparent p-0 text-sm font-semibold text-slate-700 focus:ring-0 cursor-pointer w-[110px]">
                            </div>
                            <span class="text-slate-300 font-light">|</span>
                            <div class="relative">
                                <input type="date" name="end_date" value="{{ $to ?? now()->toDateString() }}" 
                                    class="border-0 bg-transparent p-0 text-sm font-semibold text-slate-700 focus:ring-0 cursor-pointer w-[110px]">
                            </div>
                        </div>

                        <button type="submit" class="inline-flex items-center px-5 py-2 bg-gradient-to-r from-violet-600 to-indigo-600 rounded-xl text-sm font-bold text-white shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 hover:scale-[1.02] transition-all transform focus:outline-none">
                            Update View
                        </button>
                        <a href="{{ route('feeds.reports.export.pdf', ['start_date' => $from, 'end_date' => $to]) }}" 
                            class="inline-flex items-center px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:text-rose-600 hover:border-rose-200 hover:bg-rose-50 transition-colors shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Export PDF
                        </a>
                    </form>
                </div>
            </div>

            <!-- Weekly / Monthly Required Totals -->
            <!-- <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-semibold text-gray-900">Weekly Feed Need vs Used</h3>
                        <span class="text-xs text-gray-500">Planned vs fed (kg) per ISO week</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Week</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide">Planned (kg)</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide">Fed (kg)</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide">Variance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @php
                                    $weeks = collect($weeklyRequiredTotals ?? [])->keys()
                                        ->merge(collect($weeklyFedTotals ?? [])->keys())
                                        ->unique()->sort();
                                @endphp
                                @forelse($weeks as $week)
                                    @php
                                        $planned = ($weeklyRequiredTotals[$week] ?? 0);
                                        $fed = ($weeklyFedTotals[$week] ?? 0);
                                        $variance = $fed - $planned;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $week }}</td>
                                        <td class="px-4 py-2 text-sm text-right font-semibold text-gray-900">{{ number_format($planned, 2) }}</td>
                                        <td class="px-4 py-2 text-sm text-right font-semibold text-gray-900">{{ number_format($fed, 2) }}</td>
                                        <td class="px-4 py-2 text-sm text-right font-semibold {{ $variance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                            {{ number_format($variance, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500 text-sm">No data</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-semibold text-gray-900">Monthly Feed Need vs Used</h3>
                        <span class="text-xs text-gray-500">Planned vs fed (kg) per month</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Month</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide">Planned (kg)</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide">Fed (kg)</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide">Variance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @php
                                    $months = collect($monthlyRequiredTotals ?? [])->keys()
                                        ->merge(collect($monthlyFedTotals ?? [])->keys())
                                        ->unique()->sort();
                                @endphp
                                @forelse($months as $month)
                                    @php
                                        $planned = ($monthlyRequiredTotals[$month] ?? 0);
                                        $fed = ($monthlyFedTotals[$month] ?? 0);
                                        $variance = $fed - $planned;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $month }}</td>
                                        <td class="px-4 py-2 text-sm text-right font-semibold text-gray-900">{{ number_format($planned, 2) }}</td>
                                        <td class="px-4 py-2 text-sm text-right font-semibold text-gray-900">{{ number_format($fed, 2) }}</td>
                                        <td class="px-4 py-2 text-sm text-right font-semibold {{ $variance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                            {{ number_format($variance, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500 text-sm">No data</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> -->

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @php
                    $defaultWeekKey = collect($weeklyIdealTotals ?? [])->keys()->first();
                    $defaultMonthKey = collect($monthlyIdealTotals ?? [])->keys()->first();
                    $defaultWeekTotal = $defaultWeekKey ? ($weeklyIdealTotals[$defaultWeekKey]['total'] ?? $weeklyIdealTotals[$defaultWeekKey] ?? 0) : 0;
                    $defaultMonthTotal = $defaultMonthKey ? ($monthlyIdealTotals[$defaultMonthKey]['total'] ?? $monthlyIdealTotals[$defaultMonthKey] ?? 0) : 0;
                @endphp

                <div class="group relative overflow-hidden rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-emerald-100 transition-all hover:-translate-y-1 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)]" x-data="{
                    mode: 'week',
                    week: '{{ $defaultWeekKey }}',
                    month: '{{ $defaultMonthKey }}',
                    totals: {
                        weeks: {
                        @foreach($weeklyIdealTotals ?? [] as $wKey => $val)
                            '{{ $wKey }}': {{ is_array($val) ? ($val['total'] ?? 0) : $val }},
                        @endforeach
                        },
                        months: {
                        @foreach($monthlyIdealTotals ?? [] as $mKey => $val)
                            '{{ $mKey }}': {{ is_array($val) ? ($val['total'] ?? 0) : $val }},
                        @endforeach
                        }
                    },
                    formatted(val){ return (val ?? 0).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' kg'; }
                }">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold uppercase text-emerald-500">Estimated Feed Need</p>
                        <div class="bg-emerald-50 p-1 rounded-lg flex gap-1">
                            <button type="button" @click="mode='week'" :class="mode==='week' ? 'bg-emerald-600 text-white' : 'text-emerald-700'" class="px-2 py-1 rounded-md text-[11px] font-semibold">Week</button>
                            <button type="button" @click="mode='month'" :class="mode==='month' ? 'bg-emerald-600 text-white' : 'text-emerald-700'" class="px-2 py-1 rounded-md text-[11px] font-semibold">Month</button>
                        </div>
                    </div>
                    <div class="mt-1">
                        <template x-if="mode==='week'">
                            <div>
                                <select x-model="week" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                    @foreach($weeklyIdealTotals ?? [] as $wKey => $val)
                                        <option value="{{ $wKey }}">{{ $wKey }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] text-slate-500 mt-1">Total for <span class="font-semibold" x-text="week"></span></p>
                                <p class="text-2xl font-black text-emerald-700" x-text="formatted(totals.weeks[week])">{{ number_format($defaultWeekTotal,1) }} kg</p>
                            </div>
                        </template>
                        <template x-if="mode==='month'">
                            <div>
                                <select x-model="month" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                    @foreach($monthlyIdealTotals ?? [] as $mKey => $val)
                                        <option value="{{ $mKey }}">{{ $mKey }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] text-slate-500 mt-1">Total for <span class="font-semibold" x-text="month"></span></p>
                                <p class="text-2xl font-black text-emerald-700" x-text="formatted(totals.months[month])">{{ number_format($defaultMonthTotal,1) }} kg</p>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="group relative overflow-hidden rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 transition-all hover:-translate-y-1 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)]">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-violet-50 blur-2xl opacity-50 group-hover:opacity-100 transition-opacity"></div>
                    <div class="flex items-center gap-5 relative">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-lg shadow-violet-500/30">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-400 uppercase tracking-wide">Total Scheduled</p>
                            <p class="mt-1 text-3xl font-black text-slate-800">{{ number_format($totalScheduledKg ?? 0, 1) }} <span class="text-lg font-medium text-slate-400">kg</span></p>
                        </div>
                    </div>
                </div>

                <div class="group relative overflow-hidden rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 transition-all hover:-translate-y-1 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)]">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-blue-50 blur-2xl opacity-50 group-hover:opacity-100 transition-opacity"></div>
                    <div class="flex items-center gap-5 relative">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-500 text-white shadow-lg shadow-blue-500/30">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-400 uppercase tracking-wide">Feed Types</p>
                            <p class="mt-1 text-3xl font-black text-slate-800">{{ isset($byType) ? $byType->count() : 0 }} <span class="text-lg font-medium text-slate-400">vars</span></p>
                        </div>
                    </div>
                </div>

                <div class="group relative overflow-hidden rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 transition-all hover:-translate-y-1 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)]">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-emerald-50 blur-2xl opacity-50 group-hover:opacity-100 transition-opacity"></div>
                    <div class="flex items-center gap-5 relative">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-lg shadow-emerald-500/30">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-400 uppercase tracking-wide">Ingredients</p>
                            <p class="mt-1 text-3xl font-black text-slate-800">{{ isset($ingredientDetails) ? count($ingredientDetails) : 0 }} <span class="text-lg font-medium text-slate-400">items</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100 flex flex-col">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Volume by Feed Type</h3>
                            <p class="text-xs text-slate-400">Total consumption weight</p>
                        </div>
                    </div>
                    <div class="chart-container flex-1">
                        <canvas id="feedTypeChart"></canvas>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100 flex flex-col">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Ingredient Mix</h3>
                            <p class="text-xs text-slate-400">Breakdown of composition</p>
                        </div>
                    </div>
                    <div class="chart-container flex-1">
                        <canvas id="ingredientChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <div class="rounded-3xl bg-white shadow-sm ring-1 ring-slate-100 flex flex-col h-full" x-data="{ tab: 'daily' }">
                    <div class="px-6 py-5 border-b border-slate-50 flex items-center justify-between bg-white rounded-t-3xl">
                        <h3 class="font-bold text-slate-800 text-lg">Structure Consumption</h3>
                        <div class="bg-slate-100 p-1 rounded-xl flex gap-1">
                            <button @click="tab='daily'" :class="tab==='daily' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all">Daily</button>
                            <button @click="tab='weekly'" :class="tab==='weekly' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all">Weekly</button>
                            <button @click="tab='monthly'" :class="tab==='monthly' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all">Monthly</button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-hidden relative">
                        <div class="overflow-x-auto custom-scrollbar max-h-[350px]">
                            <table class="min-w-full divide-y divide-slate-50">
                                <thead class="bg-slate-50/50 sticky top-0 backdrop-blur-sm z-10">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Structure</th>
                                        <th class="px-6 py-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider">Kg</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-50" x-show="tab==='daily'">
                                    @forelse($structureDailyPaginated as $date => $rows)
                                        @php $periodTotal = collect($rows)->sum('total'); @endphp
                                        @foreach($rows as $row)
                                            <tr class="hover:bg-violet-50/30 transition-colors group">
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 font-medium">{{ $date }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm font-semibold text-slate-700 group-hover:text-violet-700 transition-colors">{{ $row['structure_name'] }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-bold text-slate-800">{{ number_format($row['total'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-violet-50/40 text-violet-900 font-semibold">
                                            <td class="px-6 py-2 text-xs uppercase tracking-wide">Total for {{ $date }}</td>
                                            <td class="px-6 py-2 text-xs text-right" colspan="2">{{ number_format($periodTotal, 2) }} kg</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-6 text-sm text-center text-slate-400">No daily data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tbody class="bg-white divide-y divide-slate-50" x-show="tab==='weekly'">
                                    @forelse($structureWeeklyPaginated as $week => $rows)
                                        @php $periodTotal = collect($rows)->sum('total'); @endphp
                                        @foreach($rows as $row)
                                            <tr class="hover:bg-violet-50/30 transition-colors group">
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 font-medium">{{ $week }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm font-semibold text-slate-700 group-hover:text-violet-700 transition-colors">{{ $row['structure_name'] }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-bold text-slate-800">{{ number_format($row['total'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-violet-50/40 text-violet-900 font-semibold">
                                            <td class="px-6 py-2 text-xs uppercase tracking-wide">Total for {{ $week }}</td>
                                            <td class="px-6 py-2 text-xs text-right" colspan="2">{{ number_format($periodTotal, 2) }} kg</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-6 text-sm text-center text-slate-400">No weekly data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tbody class="bg-white divide-y divide-slate-50" x-show="tab==='monthly'">
                                    @forelse($structureMonthlyPaginated as $month => $rows)
                                        @php $periodTotal = collect($rows)->sum('total'); @endphp
                                        @foreach($rows as $row)
                                            <tr class="hover:bg-violet-50/30 transition-colors group">
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 font-medium">{{ $month }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm font-semibold text-slate-700 group-hover:text-violet-700 transition-colors">{{ $row['structure_name'] }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-bold text-slate-800">{{ number_format($row['total'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-violet-50/40 text-violet-900 font-semibold">
                                            <td class="px-6 py-2 text-xs uppercase tracking-wide">Total for {{ $month }}</td>
                                            <td class="px-6 py-2 text-xs text-right" colspan="2">{{ number_format($periodTotal, 2) }} kg</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-6 text-sm text-center text-slate-400">No monthly data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if($structureDailyPaginated->hasPages())
                        <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500" x-show="tab==='daily'">
                            <div>
                                @if($structureDailyPaginated->total())
                                    Showing {{ $structureDailyPaginated->firstItem() }} to {{ $structureDailyPaginated->lastItem() }} of {{ $structureDailyPaginated->total() }} days
                                @else
                                    Showing 0 records
                                @endif
                            </div>
                            <x-simple-pagination :paginator="$structureDailyPaginated" />
                        </div>
                    @endif
                    @if($structureWeeklyPaginated->hasPages())
                        <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500" x-show="tab==='weekly'">
                            <div>
                                @if($structureWeeklyPaginated->total())
                                    Showing {{ $structureWeeklyPaginated->firstItem() }} to {{ $structureWeeklyPaginated->lastItem() }} of {{ $structureWeeklyPaginated->total() }} weeks
                                @else
                                    Showing 0 records
                                @endif
                            </div>
                            <x-simple-pagination :paginator="$structureWeeklyPaginated" />
                        </div>
                    @endif
                    @if($structureMonthlyPaginated->hasPages())
                        <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500" x-show="tab==='monthly'">
                            <div>
                                @if($structureMonthlyPaginated->total())
                                    Showing {{ $structureMonthlyPaginated->firstItem() }} to {{ $structureMonthlyPaginated->lastItem() }} of {{ $structureMonthlyPaginated->total() }} months
                                @else
                                    Showing 0 records
                                @endif
                            </div>
                            <x-simple-pagination :paginator="$structureMonthlyPaginated" />
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl bg-white shadow-sm ring-1 ring-slate-100 flex flex-col h-full" x-data="{ tab: 'daily' }">
                    <div class="px-6 py-5 border-b border-slate-50 flex items-center justify-between bg-white rounded-t-3xl">
                        <h3 class="font-bold text-slate-800 text-lg">Room Consumption</h3>
                        <div class="bg-slate-100 p-1 rounded-xl flex gap-1">
                            <button @click="tab='daily'" :class="tab==='daily' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all">Daily</button>
                            <button @click="tab='weekly'" :class="tab==='weekly' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all">Weekly</button>
                            <button @click="tab='monthly'" :class="tab==='monthly' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all">Monthly</button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-hidden relative">
                        <div class="overflow-x-auto custom-scrollbar max-h-[350px]">
                            <table class="min-w-full divide-y divide-slate-50">
                                <thead class="bg-slate-50/50 sticky top-0 backdrop-blur-sm z-10">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Room</th>
                                        <th class="px-6 py-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider">Kg</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-50" x-show="tab==='daily'">
                                    @forelse($roomDailyPaginated as $date => $rows)
                                        @php $periodTotal = collect($rows)->sum('total'); @endphp
                                        @foreach($rows as $row)
                                            <tr class="hover:bg-emerald-50/30 transition-colors group">
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 font-medium">{{ $date }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm font-semibold text-slate-700 group-hover:text-emerald-700 transition-colors">{{ $row['room_name'] }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-bold text-slate-800">{{ number_format($row['total'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-emerald-50/40 text-emerald-900 font-semibold">
                                            <td class="px-6 py-2 text-xs uppercase tracking-wide">Total for {{ $date }}</td>
                                            <td class="px-6 py-2 text-xs text-right" colspan="2">{{ number_format($periodTotal, 2) }} kg</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-6 text-sm text-center text-slate-400">No daily data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tbody class="bg-white divide-y divide-slate-50" x-show="tab==='weekly'">
                                    @forelse($roomWeeklyPaginated as $week => $rows)
                                        @php $periodTotal = collect($rows)->sum('total'); @endphp
                                        @foreach($rows as $row)
                                            <tr class="hover:bg-emerald-50/30 transition-colors group">
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 font-medium">{{ $week }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm font-semibold text-slate-700 group-hover:text-emerald-700 transition-colors">{{ $row['room_name']  }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-bold text-slate-800">{{ number_format($row['total'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-emerald-50/40 text-emerald-900 font-semibold">
                                            <td class="px-6 py-2 text-xs uppercase tracking-wide">Total for {{ $week }}</td>
                                            <td class="px-6 py-2 text-xs text-right" colspan="2">{{ number_format($periodTotal, 2) }} kg</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-6 text-sm text-center text-slate-400">No weekly data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tbody class="bg-white divide-y divide-slate-50" x-show="tab==='monthly'">
                                    @forelse($roomMonthlyPaginated as $month => $rows)
                                        @php $periodTotal = collect($rows)->sum('total'); @endphp
                                        @foreach($rows as $row)
                                            <tr class="hover:bg-emerald-50/30 transition-colors group">
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 font-medium">{{ $month }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm font-semibold text-slate-700 group-hover:text-emerald-700 transition-colors">{{ $row['room_name'] }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-bold text-slate-800">{{ number_format($row['total'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-emerald-50/40 text-emerald-900 font-semibold">
                                            <td class="px-6 py-2 text-xs uppercase tracking-wide">Total for {{ $month }}</td>
                                            <td class="px-6 py-2 text-xs text-right" colspan="2">{{ number_format($periodTotal, 2) }} kg</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-6 text-sm text-center text-slate-400">No monthly data.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if($roomDailyPaginated->hasPages())
                        <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500" x-show="tab==='daily'">
                            <div>
                                @if($roomDailyPaginated->total())
                                    Showing {{ $roomDailyPaginated->firstItem() }} to {{ $roomDailyPaginated->lastItem() }} of {{ $roomDailyPaginated->total() }} days
                                @else
                                    Showing 0 records
                                @endif
                            </div>
                            <x-simple-pagination :paginator="$roomDailyPaginated" />
                        </div>
                    @endif
                    @if($roomWeeklyPaginated->hasPages())
                        <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500" x-show="tab==='weekly'">
                            <div>
                                @if($roomWeeklyPaginated->total())
                                    Showing {{ $roomWeeklyPaginated->firstItem() }} to {{ $roomWeeklyPaginated->lastItem() }} of {{ $roomWeeklyPaginated->total() }} weeks
                                @else
                                    Showing 0 records
                                @endif
                            </div>
                            <x-simple-pagination :paginator="$roomWeeklyPaginated" />
                        </div>
                    @endif
                    @if($roomMonthlyPaginated->hasPages())
                        <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500" x-show="tab==='monthly'">
                            <div>
                                @if($roomMonthlyPaginated->total())
                                    Showing {{ $roomMonthlyPaginated->firstItem() }} to {{ $roomMonthlyPaginated->lastItem() }} of {{ $roomMonthlyPaginated->total() }} months
                                @else
                                    Showing 0 records
                                @endif
                            </div>
                            <x-simple-pagination :paginator="$roomMonthlyPaginated" />
                        </div>
                    @endif
                </div>

            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                <div class="mb-8">
                    <h3 class="font-bold text-slate-800 text-lg">Consumption Trends vs. Growth</h3>
                    <p class="text-sm text-slate-500">Analysis of intake efficiency over time.</p>
                </div>
                <div class="h-80 w-full">
                    <canvas id="feedWeightChart"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <div class="rounded-3xl bg-white shadow-sm ring-1 ring-slate-100 flex flex-col overflow-hidden">
                    <div class="px-6 py-5 bg-slate-50/80 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800">Feed Type Breakdown</h3>
                    </div>
                    <div class="flex-1 overflow-x-auto custom-scrollbar">
                        <table class="min-w-full divide-y divide-slate-50">
                            <thead>
                                <tr class="bg-white">
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-center text-xs font-bold text-slate-400 uppercase tracking-wider">Count</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider">Total (kg)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse($feedTypePaginated as $row)
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-3">
                                                <div class="h-2.5 w-2.5 rounded-full bg-indigo-500 shadow-sm shadow-indigo-200"></div>
                                                <span class="text-sm font-semibold text-slate-700">{{ $row['name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center justify-center h-6 min-w-[24px] px-1.5 rounded-md bg-slate-100 text-xs font-bold text-slate-600">{{ $row['count'] }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-black text-slate-800">{{ number_format($row['total_amount'], 1) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-6 py-6 text-center text-sm text-slate-400">No feed type data.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($feedTypePaginated->hasPages())
                        <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                @if($feedTypePaginated->total())
                                    Showing {{ $feedTypePaginated->firstItem() }} to {{ $feedTypePaginated->lastItem() }} of {{ $feedTypePaginated->total() }} feed types
                                @else
                                    Showing 0 results
                                @endif
                            </div>
                            <x-simple-pagination :paginator="$feedTypePaginated" />
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl bg-white shadow-sm ring-1 ring-slate-100 flex flex-col overflow-hidden">
                    <div class="px-6 py-5 bg-slate-50/80 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800">Nutritional Breakdown</h3>
                    </div>
                    <div class="flex-1 overflow-x-auto custom-scrollbar">
                        <table class="min-w-full divide-y divide-slate-50">
                            <thead>
                                <tr class="bg-white">
                                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Item</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider">Used</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider">Protein</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider">Energy</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse($ingredientPaginated as $name => $details)
                                    <tr class="hover:bg-emerald-50/20 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-700">{{ $name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-emerald-600">{{ number_format($details['total_used'], 1) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-500">{{ number_format($details['protein_percentage'], 1) }}%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-500">{{ number_format($details['energy_kcal'], 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-6 text-center text-sm text-slate-400">No ingredient data.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($ingredientPaginated->hasPages())
                        <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                @if($ingredientPaginated->total())
                                    Showing {{ $ingredientPaginated->firstItem() }} to {{ $ingredientPaginated->lastItem() }} of {{ $ingredientPaginated->total() }} ingredients
                                @else
                                    Showing 0 results
                                @endif
                            </div>
                            <x-simple-pagination :paginator="$ingredientPaginated" />
                        </div>
                    @endif
                </div>
            </div>

            @php
                $dailyPage = request()->input('feed_daily_page', 1);
                $dailyPaginate = collect($dailyIdealFeed)->slice(($dailyPage-1)*10, 10);
                $totalDays = count($dailyIdealFeed);
                $maxPage = ceil($totalDays/10);

                $weeklyPage = request()->input('feed_weekly_page', 1);
                $weeklyPaginate = collect($weeklyIdealTotals)->slice(($weeklyPage-1)*10, 10);
                $totalWeeks = count($weeklyIdealTotals);
                $maxWeeklyPage = ceil($totalWeeks/10);

                $monthlyPage = request()->input('feed_monthly_page', 1);
                $monthlyPaginate = collect($monthlyIdealTotals)->slice(($monthlyPage-1)*10, 10);
                $totalMonths = count($monthlyIdealTotals);
                $maxMonthlyPage = ceil($totalMonths/10);
            @endphp

            <div class="bg-white rounded-2xl shadow border border-green-200 p-6 mt-12" id="feed-ideal-table">
  <h3 class="text-lg font-extrabold text-green-900 mb-2">Ideal Feed Requirement — Based on Animal Population</h3>
  <p class="text-xs text-green-700 mb-2">Feed (kg) calculated per live animal category per day using the actual stage population and your setup settings. <span title="Feed/day set on Settings > Feed Setup">&#9432;</span></p>
  <ul class="flex flex-wrap gap-6 items-end mb-4">
    @foreach($feedSetup as $stage=>$kgDay)
      @if(str_ends_with($stage,'kg_day'))
      <li class="flex flex-col mr-2">
        <span class="font-bold text-sm uppercase">{{ Str::title(str_replace(['_kg_day','_'],['', ' '], $stage)) }}</span>
        <span class="font-mono text-emerald-900 text-lg">{{ $kgDay }}</span>
        <span class="text-xs text-slate-400">kg/animal/day</span>
      </li>
      @endif
    @endforeach
  </ul>

  <div class="overflow-x-auto custom-scrollbar mb-6 max-h-[430px]">
    <h4 class="font-bold text-green-900 text-xs uppercase mb-1">Daily Requirement</h4>
    <table class="min-w-full text-xs border mb-2">
      <thead class="bg-green-50 sticky top-0 z-20">
        <tr>
          <th class="px-3 py-2 font-bold text-left uppercase sticky left-0 bg-green-50">Date</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Piglets (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Weaners (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Growers (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Finishers (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Boars (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Preg. Sows (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Other Sows (kg)</th>
          <th class="px-2 py-2 font-bold text-right uppercase text-green-900">Total (kg)</th>
        </tr>
      </thead>
      <tbody>
        @foreach($dailyFeedPaginator as $date => $info)
        <tr class="hover:bg-green-200/5 border-b">
          <td class="px-3 py-1 text-slate-600">{{ $date }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($info['needed']['piglets'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($info['needed']['weaners'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($info['needed']['growers'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($info['needed']['finishers'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($info['needed']['boars'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($info['needed']['pregnant_sows'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($info['needed']['other_sows'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-right font-bold text-green-900">{{ number_format($info['needed']['total'] ?? 0, 1) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div class="flex items-center justify-between mt-3 text-xs">
      <span>
        Showing {{ $dailyFeedPaginator->firstItem() }} to {{ $dailyFeedPaginator->lastItem() }} of {{ $dailyFeedPaginator->total() }} days
      </span>
      <x-simple-pagination :paginator="$dailyFeedPaginator" />
    </div>
  </div>

  <div class="overflow-x-auto custom-scrollbar mb-6 max-h-[430px] mt-6">
    <h4 class="font-bold text-green-900 text-xs uppercase mb-1">Weekly Requirement</h4>
    <table class="min-w-full text-xs border mb-2">
      <thead class="bg-green-50 sticky top-0 z-20">
        <tr>
          <th class="px-3 py-2 font-bold text-left uppercase sticky left-0 bg-green-50">Week</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Piglets (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Weaners (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Growers (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Finishers (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Boars (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Preg. Sows (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Other Sows (kg)</th>
          <th class="px-2 py-2 font-bold text-right uppercase text-green-900">Total (kg)</th>
        </tr>
      </thead>
      <tbody>
        @foreach($weeklyFeedPaginator as $week => $totals)
        <tr class="hover:bg-green-200/5 border-b">
          <td class="px-3 py-1 text-slate-600">{{ $week }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['piglets'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['weaners'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['growers'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['finishers'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['boars'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['pregnant_sows'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['other_sows'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-right font-bold text-green-900">{{ number_format($totals['total'] ?? 0, 1) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div class="flex items-center justify-between mt-3 text-xs">
      <span>
        Showing {{ $weeklyFeedPaginator->firstItem() }} to {{ $weeklyFeedPaginator->lastItem() }} of {{ $weeklyFeedPaginator->total() }} weeks
      </span>
      <x-simple-pagination :paginator="$weeklyFeedPaginator" />
    </div>
  </div>

  <div class="overflow-x-auto custom-scrollbar mb-6 max-h-[430px] mt-6">
    <h4 class="font-bold text-green-900 text-xs uppercase mb-1">Monthly Requirement</h4>
    <table class="min-w-full text-xs border mb-2">
      <thead class="bg-green-50 sticky top-0 z-20">
        <tr>
          <th class="px-3 py-2 font-bold text-left uppercase sticky left-0 bg-green-50">Month</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Piglets (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Weaners (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Growers (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Finishers (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Boars (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Preg. Sows (kg)</th>
          <th class="px-2 py-2 font-bold text-center uppercase">Other Sows (kg)</th>
          <th class="px-2 py-2 font-bold text-right uppercase text-green-900">Total (kg)</th>
        </tr>
      </thead>
      <tbody>
        @foreach($monthlyFeedPaginator as $month => $totals)
        <tr class="hover:bg-green-200/5 border-b">
          <td class="px-3 py-1 text-slate-600">{{ $month }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['piglets'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['weaners'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['growers'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['finishers'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['boars'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['pregnant_sows'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-center">{{ number_format($totals['other_sows'] ?? 0, 1) }}</td>
          <td class="px-2 py-1 text-right font-bold text-green-900">{{ number_format($totals['total'] ?? 0, 1) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div class="flex items-center justify-between mt-3 text-xs">
      <span>
        Showing {{ $monthlyFeedPaginator->firstItem() }} to {{ $monthlyFeedPaginator->lastItem() }} of {{ $monthlyFeedPaginator->total() }} months
      </span>
      <x-simple-pagination :paginator="$monthlyFeedPaginator" />
    </div>
  </div>
</div>

        </div>
    </div>
    <x-copyright-minimal />

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // High Quality Font Settings
        Chart.defaults.font.family = '"Inter", "system-ui", sans-serif';
        Chart.defaults.color = '#64748b';
        Chart.defaults.font.size = 11;

        // 1. Feed by Type (Bar)
        new Chart(document.getElementById('feedTypeChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json(($byType ?? [])->pluck('name')),
                datasets: [{
                    label: 'Total Amount (kg)',
                    data: @json(($byType ?? [])->pluck('total_amount')),
                    backgroundColor: '#6366f1', // Indigo-500
                    hoverBackgroundColor: '#4f46e5',
                    borderRadius: 6,
                    barThickness: 40,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 13 },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        grid: { color: '#f1f5f9', borderDash: [4, 4] },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });

        // 2. Ingredient Usage (Doughnut)
        new Chart(document.getElementById('ingredientChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: @json(array_keys($ingredientDetails ?? [])),
                datasets: [{
                    label: 'Usage (kg)',
                    data: @json(array_column($ingredientDetails ?? [], 'total_used')),
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'],
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 6 } },
                }
            }
        });

        // 3. Trends (Line)
        const feedWeightCtx = document.getElementById('feedWeightChart').getContext('2d');
        const gradientFeed = feedWeightCtx.createLinearGradient(0, 0, 0, 300);
        gradientFeed.addColorStop(0, 'rgba(99, 102, 241, 0.15)'); // Indigo low opacity
        gradientFeed.addColorStop(1, 'rgba(99, 102, 241, 0)');

        new Chart(feedWeightCtx, {
            type: 'line',
            data: {
                labels: @json(($feedByDate ?? [])->keys()),
                datasets: [
                    {
                        label: 'Feed (kg)',
                        data: @json(($feedByDate ?? [])->values()),
                        yAxisID: 'y',
                        borderColor: '#6366f1',
                        backgroundColor: gradientFeed,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#6366f1',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Avg Weight (kg)',
                        data: @json(($avgWeightByDate ?? [])->values()),
                        yAxisID: 'y1',
                        borderColor: '#10b981',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 6],
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#fff',
                        fill: false,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { autoSkip: true, maxTicksLimit: 8 }
                    },
                    y: {
                        position: 'left',
                        title: { display: true, text: 'Feed Consumed (kg)' },
                        grid: { color: '#f1f5f9', borderDash: [4, 4] },
                        border: { display: false }
                    },
                    y1: {
                        position: 'right',
                        grid: { display: false },
                        border: { display: false },
                        title: { display: true, text: 'Pig Weight (kg)' }
                    }
                }
            }
        });
    </script>
</x-app-layout>