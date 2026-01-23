<x-app-layout>
    <div class="min-h-screen bg-slate-50/50 p-4 lg:p-8 font-sans antialiased text-slate-900">
        <div class="max-w-[1400px] mx-auto">
            
            <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-800">Farm Manager</h1>
                    <!-- <p class="text-sm font-medium text-slate-500">Real-time herd analytics and priorities</p> -->
                </div>

                <div class="flex items-center gap-2 bg-white p-1.5 rounded-2xl shadow-sm border border-slate-200/60">
                    <div class="px-4 py-2 border-r border-slate-100 text-center">
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-tighter">Total Stock</span>
                        <span class="text-lg font-black text-emerald-600">{{ $totalStockActive }}</span>
                    </div>
                    <div class="px-4 py-2 border-r border-slate-100 text-center">
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-tighter">Breeding</span>
                        <span class="text-lg font-black text-blue-600">{{ $breedingStockTotal }}</span>
                    </div>
                    <div class="px-4 py-2 text-center group cursor-pointer" onclick="window.location='{{ route('weight-alerts.dashboard') }}'">
                        <span class="block text-xs font-bold text-rose-400 uppercase tracking-tighter">Alerts</span>
                        <span class="text-lg font-black text-rose-600">{{ $criticalAlerts }}</span>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-8 space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/50">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-bold text-slate-500 uppercase">Stock Composition</h3>
                                <div class="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></div>
                            </div>
                            <div class="h-48"><canvas id="stockDistributionChart"></canvas></div>
                        </div>

                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/50">
                            <h3 class="text-sm font-bold text-slate-500 uppercase mb-4">Weight Velocity</h3>
                            <div class="h-48"><canvas id="weightTrendsChart"></canvas></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-white p-5 rounded-3xl border border-slate-200/50 text-center">
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Fertility</span>
                            <span class="text-xl font-black text-slate-800">{{ number_format($fertilityRate, 1) }}%</span>
                        </div>
                        <div class="bg-white p-5 rounded-3xl border border-slate-200/50 text-center">
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Accuracy</span>
                            <span class="text-xl font-black text-slate-800">{{ $breedingStats['data_completeness'] }}%</span>
                        </div>
                        <div class="bg-white p-5 rounded-3xl border border-slate-200/50 text-center">
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Mortality</span>
                            <span class="text-xl font-black text-rose-500">{{ number_format($mortalityRate, 1) }}%</span>
                        </div>
                        <div class="bg-amber-50/50 p-5 rounded-3xl border border-amber-100 text-center">
                            <span class="block text-[10px] font-black text-amber-600 uppercase tracking-widest mb-1">Overdue</span>
                            <span class="text-xl font-black text-amber-700">{{ $overdueForWeighing }}</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/50">
                        <h3 class="text-sm font-bold text-slate-800 mb-6">Breed Performance Mix</h3>
                        <div class="grid grid-cols-3 gap-8">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-12 bg-blue-500 rounded-full"></div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">LW</p>
                                    <p class="text-lg font-black">{{ $breedCounts['lw'] }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-12 bg-emerald-500 rounded-full"></div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Duroc</p>
                                    <p class="text-lg font-black">{{ $breedCounts['dr'] }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-12 bg-purple-500 rounded-full"></div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">LWDR</p>
                                    <p class="text-lg font-black">{{ $breedCounts['lwdr'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4 flex flex-col gap-6">
                    
                    <div class="bg-slate-900 rounded-3xl p-6 text-white shadow-xl">
                        <h3 class="text-sm font-bold text-emerald-400 uppercase tracking-widest mb-4">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="{{ route('all-stocks.index') }}" class="flex flex-col items-center justify-center p-4 bg-white/5 hover:bg-white/10 rounded-2xl transition-all border border-white/10 group">
                                <svg class="w-5 h-5 mb-2 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                <span class="text-[10px] font-bold uppercase tracking-tighter">Add Stock</span>
                            </a>
                            <a href="{{ route('weight-alerts.dashboard') }}" class="flex flex-col items-center justify-center p-4 bg-white/5 hover:bg-white/10 rounded-2xl transition-all border border-white/10 group">
                                <svg class="w-5 h-5 mb-2 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <span class="text-[10px] font-bold uppercase tracking-tighter">View Alerts</span>
                            </a>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/50 flex-1">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-widest">Tasks</h3>
                            <span class="text-[10px] font-black text-slate-400 px-2 py-1 bg-slate-50 rounded-lg">Upcoming</span>
                        </div>
                        <div class="space-y-5">
                            @forelse ($upcomingEvents as $event)
                                <div class="relative pl-6 border-l-2 border-slate-100 group">
                                    <div class="absolute -left-[6px] top-1 w-2.5 h-2.5 rounded-full bg-{{ $event['color'] }}-500 ring-4 ring-white"></div>
                                    <h4 class="text-xs font-bold text-slate-900 group-hover:text-blue-600 transition-colors leading-none mb-1">{{ $event['title'] }}</h4>
                                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-tighter">{{ optional($event['date'])->format('M d') }} • {{ $event['days_until'] }} Days left</p>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 italic text-center py-8">No tasks found</p>
                            @endforelse
                        </div>
                    </div>

                </div>
            </div>
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