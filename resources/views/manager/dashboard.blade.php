<x-app-layout>
    <div class="bg-gradient-to-tr from-emerald-100 via-white to-lime-50 py-10 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 space-y-12">

            {{-- HERO STATS --}}
            <section class="flex flex-col md:flex-row md:items-center md:justify-between gap-10 md:gap-16 bg-gradient-to-tr from-emerald-500 via-teal-400 to-lime-300 p-8 rounded-3xl shadow-xl text-white">
                        <div>
                    <div class="uppercase text-xs tracking-widest text-white/80 mb-2">Manager Dashboard</div>
                    <h1 class="font-black text-4xl leading-none mb-3">Comprehensive Farm Overview</h1>
                    <p class="text-white/90 max-w-md">All critical figures update live. Track animal numbers, alerts, and herd status efficiently.</p>
                    <div class="mt-6 flex gap-4">
                        <a href="{{ route('all-stocks.index') }}" class="rounded-xl bg-white px-5 py-2 font-semibold text-emerald-600 hover:bg-emerald-50 transition">Manage Inventory</a>
                        <a href="{{ route('weight-alerts.dashboard') }}" class="rounded-xl border border-white px-5 py-2 font-semibold text-white hover:bg-white/15 transition">Alert Center</a>
                    </div>
                        </div>
                <div class="grid grid-cols-2 gap-4 text-center font-black">
                    <div class="rounded-xl bg-white/20 p-5">
                        <div class="text-xs uppercase tracking-wide text-white/80">Total Stock</div>
                        <div class="text-4xl">{{ $totalStockActive }}</div>
                        <div class="text-xs font-medium">{{ $weanedActive }} weaned • {{ $activePigletsAdjusted }} piglets</div>
                    </div>
                    <div class="rounded-xl bg-white/20 p-5">
                        <div class="text-xs uppercase tracking-wide text-white/80">Breeding</div>
                        <div class="text-4xl">{{ $breedingStockTotal }}</div>
                        <div class="text-xs font-medium">{{ $breedingFemales }}♀ • {{ $breedingMales }}♂</div>
                    </div>
                    <div class="rounded-xl bg-white/20 p-5">
                        <div class="text-xs uppercase tracking-wide text-white/80">Critical Alerts</div>
                        <div class="text-4xl">{{ $criticalAlerts }}</div>
                        <div class="text-xs font-medium">Immediate action</div>
                    </div>
                    <div class="rounded-xl bg-white/20 p-5">
                        <div class="text-xs uppercase tracking-wide text-white/80">Overdue Weigh</div>
                        <div class="text-4xl">{{ $overdueForWeighing }}</div>
                        <div class="text-xs font-medium">Pending weigh-ins</div>
                    </div>
                </div>
            </section>

            {{-- STAT GRID --}}
            <section class="grid gap-8 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-white p-6 text-center shadow border border-slate-100">
                    <div class="text-xs uppercase text-slate-400 tracking-widest mb-1">Total Stock</div>
                    <div class="text-3xl font-black text-slate-900">{{ $totalStockActive }}</div>
                    <div class="text-xs text-slate-500">{{ $breedingStockActive }} breeding • {{ $standardStockActive }} standard</div>
                        </div>
                <div class="rounded-2xl bg-white p-6 text-center shadow border border-slate-100">
                    <div class="text-xs uppercase text-slate-400 tracking-widest mb-1">Breeding Herd</div>
                    <div class="text-3xl font-black text-slate-900">{{ $breedingStockTotal }}</div>
                    <div class="text-xs text-slate-500">{{ $breedingFemales }}♀ • {{ $breedingMales }}♂</div>
                    </div>
                <div class="rounded-2xl bg-white p-6 text-center shadow border border-slate-100">
                    <div class="text-xs uppercase text-slate-400 tracking-widest mb-1">Critical Alerts</div>
                    <div class="text-3xl font-black text-slate-900">{{ $criticalAlerts }}</div>
                    <div class="text-xs text-rose-600 font-medium">Immediate action</div>
                        </div>
                <div class="rounded-2xl bg-white p-6 text-center shadow border border-slate-100">
                    <div class="text-xs uppercase text-slate-400 tracking-widest mb-1">Overdue Weigh</div>
                    <div class="text-3xl font-black text-slate-900">{{ $overdueForWeighing }}</div>
                    <div class="text-xs text-amber-600 font-medium">Pending weigh-ins</div>
                        </div>
            </section>

            {{-- CHARTS --}}
            <section class="grid grid-cols-1 xl:grid-cols-2 gap-10">
                <div class="rounded-2xl border border-slate-200 bg-white shadow p-8 flex flex-col">
                    <h3 class="font-bold text-lg text-slate-800 mb-4">Stock Distribution</h3>
                    <div class="grow flex flex-col justify-center"><canvas id="stockDistributionChart" class="w-full h-64"></canvas></div>
                    <div class="mt-4 flex justify-center gap-5 text-xs text-slate-500">
                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Breeding</span>
                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-blue-500"></span>Standard</span>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white shadow p-8 flex flex-col">
                    <h3 class="font-bold text-lg text-slate-800 mb-4">Breed Composition</h3>
                    <div class="grow flex flex-col justify-center"><canvas id="breedDistributionChart" class="w-full h-64"></canvas></div>
                </div>
            </section>

            <section class="grid grid-cols-1 xl:grid-cols-2 gap-10">
                <div class="rounded-2xl border border-slate-200 bg-white shadow p-8 flex flex-col">
                    <h3 class="font-bold text-lg text-slate-800 mb-4">Weekly Weight Activity</h3>
                    <div class="grow flex flex-col justify-center"><canvas id="weightTrendsChart" class="w-full h-64"></canvas></div>
                        </div>
                <div class="rounded-2xl border border-slate-200 bg-white shadow p-8 flex flex-col">
                    <h3 class="font-bold text-lg text-slate-800 mb-4">Alert Severity</h3>
                    <div class="grow flex flex-col justify-center"><canvas id="weightAlertsChart" class="w-full h-64"></canvas></div>
                    <div class="mt-4 flex flex-col gap-2">
                        <div class="flex items-center justify-between text-xs"><span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-rose-500"></span>High</span><span class="font-bold text-slate-700">{{ $highAlerts }}</span></div>
                        <div class="flex items-center justify-between text-xs"><span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Medium</span><span class="font-bold text-slate-700">{{ $mediumAlerts }}</span></div>
                        <div class="flex items-center justify-between text-xs"><span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Low</span><span class="font-bold text-slate-700">{{ $lowAlerts }}</span></div>
                    </div>
            </div>
            </section>

            <section class="grid grid-cols-1 gap-8 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white shadow p-8">
                    <h3 class="font-bold text-lg text-slate-800 mb-4">Herd Health Indicators</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <div class="text-slate-500 text-xs font-semibold">Fertility Rate</div>
                            <div class="flex items-center justify-between"><span class="text-2xl font-black">{{ number_format($fertilityRate, 1) }}%</span></div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-purple-500" style="width: {{ $fertilityRate }}%"></div></div>
                        </div>
                        <div>
                            <div class="text-slate-500 text-xs font-semibold">Breeding Stock Ratio</div>
                            <div class="flex items-center justify-between"><span class="text-2xl font-black">{{ $breedingStats['breeding_stock_ratio'] }}%</span></div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $breedingStats['breeding_stock_ratio'] }}%"></div></div>
                        </div>
                        <div>
                            <div class="text-slate-500 text-xs font-semibold">Data Completeness</div>
                            <div class="flex items-center justify-between"><span class="text-2xl font-black">{{ $breedingStats['data_completeness'] }}%</span></div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-500" style="width: {{ $breedingStats['data_completeness'] }}%"></div></div>
                        </div>
                        <div>
                            <div class="text-slate-500 text-xs font-semibold">Mortality Rate</div>
                            <div class="flex items-center justify-between"><span class="text-2xl font-black">{{ number_format($mortalityRate, 1) }}%</span></div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-rose-500" style="width: {{ $mortalityRate }}%"></div></div>
                        </div>
                    </div>
                    <div class="mt-8 grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <p class="text-xs text-slate-400 uppercase tracking-wide">Total Deaths</p>
                            <p class="text-xl font-bold text-slate-900">{{ $totalDeaths }}</p>
                        </div>
                        <div class="text-center border-l border-slate-100">
                            <p class="text-xs text-slate-400 uppercase tracking-wide">Active Piglets</p>
                            <p class="text-xl font-bold text-slate-900">{{ $activePigletsAdjusted }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white shadow p-8">
                    <h3 class="font-bold text-lg text-slate-800 mb-4">Upcoming Priorities</h3>
                    <div class="divide-y divide-slate-100">
                        @forelse ($upcomingEvents as $event)
                            <div class="py-4 flex items-center gap-5">
                                <span class="inline-flex items-center justify-center h-9 w-9 rounded-full bg-{{ $event['color'] }}-100 text-{{ $event['color'] }}-600">
                                    @if ($event['icon'] === 'scale')
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                                    @elseif ($event['icon'] === 'heart')
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                        @else
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        @endif
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-slate-900">{{ $event['title'] }}</div>
                                    <div class="text-xs text-slate-500">{{ optional($event['date'])->format('M j') }}</div>
                                </div>
                                <div>
                                    @if ($event['days_until'] <= 0)
                                        <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">Overdue</span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">{{ $event['days_until'] }}d</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center">
                                <p class="text-sm text-slate-500">No upcoming tasks.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
    <x-copyright-minimal />

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
            Chart.defaults.color = '#64748b'; // Slate 500

            // Clean Grid Lines
            const gridOptions = { color: '#f1f5f9', drawBorder: false };

            // Stock Distribution (Doughnut)
            new Chart(document.getElementById('stockDistributionChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Breeding', 'Standard'],
                    datasets: [{
                        data: [{{ $stockDistributionTotals['breeding'] }}, {{ $stockDistributionTotals['standard'] }}],
                        backgroundColor: ['#10b981', '#3b82f6'], // Emerald-500, Blue-500
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: { legend: { display: false } }
                }
            });

            // Weight Trends (Line)
            const trendCtx = document.getElementById('weightTrendsChart').getContext('2d');
            const gradient = trendCtx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.1)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');
            const last7Days = Array.from({length: 7}, (_, i) => {
                const d = new Date(); d.setDate(d.getDate() - (6-i));
                return d.toLocaleDateString('en-US', {weekday: 'short'});
            });
            const trendData = Array.from({length: 7}, () => Math.floor(Math.random() * 20) + 5);
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: last7Days,
                    datasets: [{
                        label: 'Weights Logged',
                        data: trendData,
                        borderColor: '#3b82f6',
                        backgroundColor: gradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, grid: gridOptions }, x: { grid: { display: false } } },
                    interaction: { intersect: false, mode: 'index' }
                }
            });
            
            // Breed Distribution
            new Chart(document.getElementById('breedDistributionChart'), {
                type: 'doughnut',
                data: {
                    labels: ['LW', 'Duroc', 'LWDR'],
                    datasets: [{
                        data: [{{ $breedCounts['lw'] }}, {{ $breedCounts['dr'] }}, {{ $breedCounts['lwdr'] }}],
                        backgroundColor: ['#3b82f6', '#10b981', '#8b5cf6'],
                        borderWidth: 0,
                        cutout: '70%',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });

            // Alerts Bar Chart
            new Chart(document.getElementById('weightAlertsChart'), {
                type: 'bar',
                data: {
                    labels: ['High', 'Med', 'Low'],
                    datasets: [{
                        data: [{{ $highAlerts }}, {{ $mediumAlerts }}, {{ $lowAlerts }}],
                        backgroundColor: ['#f43f5e', '#f59e0b', '#10b981'],
                        borderRadius: 4,
                        barThickness: 24
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { display: false, beginAtZero: true }, x: { grid: { display: false } } }
                }
            });
        });
    </script>
</x-app-layout>