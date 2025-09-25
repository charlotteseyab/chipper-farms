<x-app-layout>
<style>
    #feedTypeChart, #ingredientChart {
        max-height: 350px; /* keeps them compact */
    }
</style>

    <div class="max-w-7xl mx-auto px-4 py-8 space-y-8">
        <!-- Header + Filter -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <form method="GET" class="flex flex-wrap items-end gap-4 bg-white p-4 rounded-lg shadow border">
                <div>
                    <label class="block text-xs font-medium text-gray-500">From</label>
                    <input type="date" 
                           name="start_date" 
                           value="{{ $from ?? now()->subDays(30)->toDateString() }}" 
                           class="mt-1 border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200 w-40">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">To</label>
                    <input type="date" 
                           name="end_date" 
                           value="{{ $to ?? now()->toDateString() }}" 
                           class="mt-1 border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200 w-40">
                </div>
                <button class="h-10 px-4 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">
                    Filter
                </button>
            </form>

            <a href="{{ route('feeds.reports.export.pdf', ['start_date' => $from, 'end_date' => $to]) }}" 
               class="h-10 px-4 flex items-center justify-center bg-red-600 text-white rounded-lg text-sm font-medium shadow hover:bg-red-700 transition">
               📄 Export PDF
            </a>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-xl shadow border">
                <div class="text-sm text-gray-500">Total Scheduled Feed</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">
                    {{ number_format($totalScheduledKg ?? 0, 2) }} <span class="text-lg font-medium">kg</span>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border">
                <div class="text-sm text-gray-500">Feed Types Scheduled</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">
                    {{ isset($byType) ? $byType->count() : 0 }}
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border">
                <div class="text-sm text-gray-500">Total Ingredients Used</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">
                    {{ isset($ingredientDetails) ? count($ingredientDetails) : 0 }}
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Feed by Type Chart -->
            <div class="bg-white rounded-xl shadow border">
                <div class="px-6 py-4 border-b text-base font-semibold text-gray-800">Feed by Type (Chart)</div>
                <div class="p-6">
                    <canvas id="feedTypeChart" height="150"></canvas>
                </div>
            </div>

            <!-- Ingredient Usage Chart -->
            <div class="bg-white rounded-xl shadow border">
                <div class="px-6 py-4 border-b text-base font-semibold text-gray-800">Estimated Ingredient Usage (Chart)</div>
                <div class="p-6">
                    <canvas id="ingredientChart" height="100"></canvas>
                </div>
                </div>
        </div>

        <!-- Feed vs Pig Weights -->
        <div class="bg-white rounded-xl shadow border">
            <div class="px-6 py-4 border-b text-base font-semibold text-gray-800">Feed vs Pig Weights</div>
            <div class="p-6">
                <canvas id="feedWeightChart" height="100"></canvas>
            </div>
        </div>

        <!-- Tables -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Feed by Type Table -->
            <div class="bg-white rounded-xl shadow border">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Feed Types Summary
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Detailed breakdown of feed types and their usage</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Feed Type</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Events</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Avg per Event</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach(($byType ?? []) as $row)
                                <tr class="hover:bg-blue-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                                            <span class="text-sm font-medium text-gray-900">{{ $row['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $row['count'] }} events
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-semibold text-gray-900">{{ number_format($row['total_amount'], 2) }} kg</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600">{{ number_format($row['total_amount'] / max($row['count'], 1), 2) }} kg</span>
                                    </td>
                                </tr>
                            @endforeach
                            @if(empty($byType) || $byType->isEmpty())
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <span class="text-gray-400 text-sm">No feed type data available for the selected period</span>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Ingredient Usage Table -->
            <div class="bg-white rounded-xl shadow border">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Ingredients Analysis
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Detailed nutritional and usage information for all ingredients</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-green-50 to-emerald-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ingredient</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Used (kg)</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Protein %</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Energy (kcal/kg)</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Feed Types</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Cost (₵/kg)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach(($ingredientDetails ?? []) as $name => $details)
                                <tr class="hover:bg-green-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                            <span class="text-sm font-medium text-gray-900">{{ $name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ number_format($details['total_used'], 2) }} kg
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600">{{ number_format($details['protein_percentage'], 1) }}%</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600">{{ number_format($details['energy_kcal'], 0) }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($details['feed_types'] as $feedType)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ $feedType }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-semibold text-gray-900">₵{{ number_format($details['cost_per_kg'], 2) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            @if(empty($ingredientDetails))
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                            </svg>
                                            <span class="text-gray-400 text-sm">No ingredient data available for the selected period</span>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Feed by Type Chart
        new Chart(document.getElementById('feedTypeChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json(($byType ?? [])->pluck('name')),
                datasets: [{
                    label: 'Total Amount (kg)',
                    data: @json(($byType ?? [])->pluck('total_amount')),
                    backgroundColor: '#2563eb'
                }]
            }
        });

        // Ingredient Usage Chart
        new Chart(document.getElementById('ingredientChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: @json(array_keys($ingredientDetails ?? [])),
                datasets: [{
                    label: 'Usage (kg)',
                    data: @json(array_column($ingredientDetails ?? [], 'total_used')),
                    backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#9333ea', '#06b6d4', '#ec4899', '#84cc16']
                }]
            }
        });

        // Feed vs Pig Weights Chart (Dual-Axis, by Date)
        const feedWeightCtx = document.getElementById('feedWeightChart').getContext('2d');
        new Chart(feedWeightCtx, {
            type: 'line',
            data: {
                labels: @json(($feedByDate ?? [])->keys()),
                datasets: [
                    {
                        label: 'Feed (kg)',
                        data: @json(($feedByDate ?? [])->values()),
                        yAxisID: 'y',
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.15)',
                        borderWidth: 2,
                        pointRadius: 2,
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Avg Weight (kg)',
                        data: @json(($avgWeightByDate ?? [])->values()),
                        yAxisID: 'y1',
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.15)',
                        borderWidth: 2,
                        pointRadius: 2,
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                stacked: false,
                plugins: {
                    tooltip: { enabled: true },
                    legend: { position: 'top' },
                    title: {
                        display: true,
                        text: 'Feed vs Average Weight by Date'
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Date' },
                        ticks: { autoSkip: true, maxTicksLimit: 8 }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Feed (kg)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Avg Weight (kg)' }
                    }
                }
            }
        });

    

    </script>
</x-app-layout>
