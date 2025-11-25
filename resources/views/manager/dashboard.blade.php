<x-app-layout>
    <div class="min-h-screen bg-slate-50 font-sans text-slate-600">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Farm Overview</h1>
                    <p class="mt-1 text-sm text-slate-500">Welcome back. Here is your herd's performance today.</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('weight-alerts.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <svg class="h-4 w-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        Alerts <span class="ml-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600">{{ $criticalAlerts + $overdueForWeighing }}</span>
                    </a>
                    <a href="{{ route('all-stocks.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Manage Stock
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <dt>
                        <div class="absolute rounded-md bg-emerald-50 p-3">
                            <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <p class="ml-16 truncate text-sm font-medium text-slate-500">Total Stock</p>
                    </dt>
                    <dd class="ml-16 flex items-baseline">
                        <p class="text-2xl font-semibold text-slate-900">{{ $totalStockActive }}</p>
                    </dd>
                    <div class="mt-4 flex items-center text-xs text-slate-500">
                         <span class="font-medium text-emerald-600">{{ $activePigletsAdjusted }} piglets</span>&nbsp;active
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <dt>
                        <div class="absolute rounded-md bg-pink-50 p-3">
                            <svg class="h-6 w-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        <p class="ml-16 truncate text-sm font-medium text-slate-500">Breeding Herd</p>
                    </dt>
                    <dd class="ml-16 flex items-baseline">
                        <p class="text-2xl font-semibold text-slate-900">{{ $breedingStockTotal }}</p>
                    </dd>
                    <div class="mt-4 flex items-center text-xs text-slate-500">
                        {{ $breedingFemales }} females / {{ $breedingMales }} males
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-xl border border-rose-200 bg-rose-50/50 p-5 shadow-sm">
                    <dt>
                        <div class="absolute rounded-md bg-white p-3 shadow-sm">
                            <svg class="h-6 w-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        </div>
                        <p class="ml-16 truncate text-sm font-medium text-rose-800">Critical Alerts</p>
                    </dt>
                    <dd class="ml-16 flex items-baseline">
                        <p class="text-2xl font-bold text-rose-700">{{ $criticalAlerts }}</p>
                    </dd>
                    <div class="mt-4 flex items-center text-xs text-rose-600 font-medium">
                        Action required immediately
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <dt>
                        <div class="absolute rounded-md bg-amber-50 p-3">
                            <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                        </div>
                        <p class="ml-16 truncate text-sm font-medium text-slate-500">Overdue Weighing</p>
                    </dt>
                    <dd class="ml-16 flex items-baseline">
                        <p class="text-2xl font-semibold text-slate-900">{{ $overdueForWeighing }}</p>
                    </dd>
                    <div class="mt-4 flex items-center text-xs text-slate-500">
                        <a href="{{ route('weight-alerts.index') }}" class="hover:text-amber-600 hover:underline">View pending list &rarr;</a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                
                <div class="lg:col-span-2 space-y-8">
                    
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-slate-900">Weekly Weight Activity</h3>
                            <a href="{{ route('weights.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-500">View History</a>
                        </div>
                        <div class="h-72 w-full">
                            <canvas id="weightTrendsChart"></canvas>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col">
                            <h3 class="text-base font-semibold text-slate-900 mb-2">Stock Distribution</h3>
                            <div class="flex-1 flex flex-col justify-center">
                                <div class="h-48 w-full">
                                    <canvas id="stockDistributionChart"></canvas>
                                </div>
                                <div class="mt-4 flex justify-center gap-4 text-xs text-slate-500">
                                    <div class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Breeding</div>
                                    <div class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-blue-500"></span>Standard</div>
                                </div>
                            </div>
                        </div>

                         <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col">
                            <h3 class="text-base font-semibold text-slate-900 mb-2">Breed Composition</h3>
                            <div class="flex-1 flex flex-col justify-center">
                                <div class="h-48 w-full">
                                    <canvas id="breedDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900 mb-6">Herd Health Indicators</h3>
                        <div class="space-y-6">
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-600">Fertility Rate</span>
                                    <span class="font-medium text-slate-900">{{ number_format($fertilityRate, 1) }}%</span>
                                </div>
                                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-purple-500" style="width: {{ $fertilityRate }}%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-600">Breeding Stock Ratio</span>
                                    <span class="font-medium text-slate-900">{{ $breedingStats['breeding_stock_ratio'] }}%</span>
                                </div>
                                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $breedingStats['breeding_stock_ratio'] }}%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-600">Data Completeness</span>
                                    <span class="font-medium text-slate-900">{{ $breedingStats['data_completeness'] }}%</span>
                                </div>
                                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-blue-500" style="width: {{ $breedingStats['data_completeness'] }}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 border-t border-slate-100 pt-4 grid grid-cols-2 gap-4">
                            <div class="text-center">
                                <p class="text-xs text-slate-400 uppercase tracking-wide">Mortality Rate</p>
                                <p class="text-xl font-bold text-slate-900">{{ number_format($mortalityRate, 1) }}%</p>
                            </div>
                            <div class="text-center border-l border-slate-100">
                                <p class="text-xs text-slate-400 uppercase tracking-wide">Total Deaths</p>
                                <p class="text-xl font-bold text-slate-900">{{ $totalDeaths }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <a href="{{ route('gestation.index') }}" class="col-span-1 flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm hover:border-pink-300 hover:shadow-md transition">
                            <div class="mb-2 rounded-full bg-pink-50 p-2 text-pink-500">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-slate-700">Gestation</span>
                        </a>
                        <a href="{{ route('all-stocks.index') }}" class="col-span-1 flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm hover:border-blue-300 hover:shadow-md transition">
                            <div class="mb-2 rounded-full bg-blue-50 p-2 text-blue-500">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </div>
                            <span class="text-xs font-semibold text-slate-700">Inventory</span>
                        </a>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-slate-900">Upcoming Priorities</h3>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">{{ count($upcomingEvents) }}</span>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @forelse ($upcomingEvents as $event)
                                <div class="p-4 hover:bg-slate-50 transition">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-{{ $event['color'] }}-100 text-{{ $event['color'] }}-600">
                                                @if ($event['icon'] === 'scale')
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                                                @elseif ($event['icon'] === 'heart')
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                                @else
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-slate-900">{{ $event['title'] }}</p>
                                            <p class="text-xs text-slate-500">{{ optional($event['date'])->format('M j') }}</p>
                                        </div>
                                        <div>
                                            @if ($event['days_until'] <= 0)
                                                 <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">Overdue</span>
                                            @else
                                                <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">{{ $event['days_until'] }}d</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-8 text-center">
                                    <p class="text-sm text-slate-500">No upcoming tasks.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900 mb-4">Alert Severity</h3>
                        <div class="h-48">
                            <canvas id="weightAlertsChart"></canvas>
                        </div>
                        <div class="mt-4 space-y-2">
                             <div class="flex items-center justify-between text-xs">
                                <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-rose-500"></span>High</span>
                                <span class="font-bold text-slate-700">{{ $highAlerts }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Medium</span>
                                <span class="font-bold text-slate-700">{{ $mediumAlerts }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Low</span>
                                <span class="font-bold text-slate-700">{{ $lowAlerts }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Chart.defaults.font.family = '"Inter", system-ui, sans-serif';
            Chart.defaults.color = '#64748b'; // Slate 500
            
            // Clean Grid Lines
            const gridOptions = {
                color: '#f1f5f9',
                drawBorder: false,
            };

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
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.1)'); // Blue-500 low opacity
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

            // Dummy data generation for trends (replace with real if available in array)
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
                    scales: {
                        y: { beginAtZero: true, grid: gridOptions },
                        x: { grid: { display: false } }
                    },
                    interaction: { intersect: false, mode: 'index' },
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
                        backgroundColor: ['#f43f5e', '#f59e0b', '#10b981'], // Rose, Amber, Emerald
                        borderRadius: 4,
                        barThickness: 24
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { display: false, beginAtZero: true },
                        x: { grid: { display: false } }
                    }
                }
            });
        });
    </script>
</x-app-layout>