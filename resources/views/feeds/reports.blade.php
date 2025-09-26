<x-app-layout>
<style>
    /* Custom style for chart containers */
    .chart-container {
        /* Sets a fixed height for consistency and medium size */
        height: 300px; 
        min-height: 250px;
        position: relative;
    }
    #feedTypeChart, #ingredientChart {
        /* Ensure canvas fills its container */
        height: 100% !important;
        width: 100% !important;
    }
</style>

    <div class="max-w-7xl mx-auto px-4 py-2 sm:px-6 lg:px-8 space-y-6 mb-6">
        
        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">
                Feed Usage Analytics Report 
            </h1>
            <!-- <p class="text-gray-500 text-sm mt-1">
                Detailed analysis of feed consumption and ingredient usage from {{ $from ?? now()->subDays(30)->toDateString() }} to {{ $to ?? now()->toDateString() }}.
            </p> -->
        </div>

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-4 rounded-lg shadow-md border mb-6">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div class="flex-grow">
                    <label class="block text-xs font-medium text-gray-500">From Date</label>
                    <input type="date" 
                           name="start_date" 
                           value="{{ $from ?? now()->subDays(30)->toDateString() }}" 
                           class="mt-1 border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 w-full md:w-40"     >
                </div>
                <div class="flex-grow">
                    <label class="block text-xs font-medium text-gray-500">To Date</label>
                    <input type="date" 
                           name="end_date" 
                           value="{{ $to ?? now()->toDateString() }}" 
                           class="mt-1 border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 w-full md:w-40">
                </div>
                <button class="h-9 px-4 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition shadow-md">
                    Apply Filter
                </button>
            </form>

            <a href="{{ route('feeds.reports.export.pdf', ['start_date' => $from, 'end_date' => $to]) }}" 
               class="h-9 px-4 flex items-center justify-center bg-red-600 text-white rounded-lg text-sm font-medium shadow-md hover:bg-red-700 transition">
               <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm0 0l-1.5-3m1.5 3l1.5-3"></path></svg>
               Export PDF
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-white p-5 rounded-xl shadow-lg border border-indigo-100">
                <div class="text-sm font-medium text-gray-500">Total Scheduled Feed</div>
                <div class="mt-1 text-3xl font-bold text-indigo-600">
                    {{ number_format($totalScheduledKg ?? 0, 2) }} <span class="text-lg font-medium text-gray-700">kg</span>
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-lg border border-indigo-100">
                <div class="text-sm font-medium text-gray-500">Feed Types Scheduled</div>
                <div class="mt-1 text-3xl font-bold text-gray-900">
                    {{ isset($byType) ? $byType->count() : 0 }}
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-lg border border-indigo-100">
                <div class="text-sm font-medium text-gray-500">Total Ingredients Used</div>
                <div class="mt-1 text-3xl font-bold text-green-600">
                    {{ isset($ingredientDetails) ? count($ingredientDetails) : 0 }}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            
            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="px-5 py-3 border-b border-gray-100 text-lg font-semibold text-gray-800">Feed by Type Usage (kg)</div>
                <div class="p-5 chart-container">
                    <canvas id="feedTypeChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="px-5 py-3 border-b border-gray-100 text-lg font-semibold text-gray-800">Ingredient Usage Distribution</div>
                <div class="p-5 chart-container">
                    <canvas id="ingredientChart"></canvas>
                </div>
                </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border border-gray-100">
            <div class="px-5 py-3 border-b border-gray-100 text-lg font-semibold text-gray-800">Feed Consumption vs. Average Pig Weights</div>
            <div class="p-5">
                <canvas id="feedWeightChart" height="100"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="px-5 py-3 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-indigo-700 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Feed Types Summary
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Detailed breakdown of scheduled feed types and usage counts.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-indigo-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Feed Type</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Events</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Total Amount</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Avg/Event</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach(($byType ?? []) as $row)
                                <tr class="hover:bg-indigo-50 transition-colors duration-150">
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 bg-indigo-500 rounded-full mr-3"></div>
                                            <span class="text-sm font-medium text-gray-900">{{ $row['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                            {{ $row['count'] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm font-bold text-gray-900">{{ number_format($row['total_amount'], 2) }} kg</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-600">{{ number_format($row['total_amount'] / max($row['count'], 1), 2) }} kg</td>
                                </tr>
                            @endforeach
                            @if(empty($byType) || $byType->isEmpty())
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center bg-gray-50">
                                        <span class="text-gray-400 text-sm">No feed type data available for the selected period.</span>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg border border-gray-100">
                <div class="px-5 py-3 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-green-700 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Ingredients Analysis
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Nutritional details and estimated usage based on feed recipes.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-green-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Ingredient</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Used (kg)</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Protein %</th>
                                <th class="px-5 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Energy</th>
                                <!-- <th class="px-5 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Cost (₵/kg)</th> -->
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach(($ingredientDetails ?? []) as $name => $details)
                                <tr class="hover:bg-green-50 transition-colors duration-150">
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                            <span class="text-sm font-medium text-gray-900">{{ $name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            {{ number_format($details['total_used'], 2) }} kg
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-600">{{ number_format($details['protein_percentage'], 1) }}%</td>
                                    <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-600">{{ number_format($details['energy_kcal'], 0) }}</td>
                                    <!-- <td class="px-5 py-3 whitespace-nowrap text-sm font-bold text-gray-900">₵{{ number_format($details['cost_per_kg'], 2) }}</td> -->
                                </tr>
                            @endforeach
                            @if(empty($ingredientDetails))
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center bg-gray-50">
                                        <span class="text-gray-400 text-sm">No ingredient data available for the selected period.</span>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>

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
                    backgroundColor: '#4f46e5', // Indigo-600
                    borderColor: '#4f46e5',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Important for fixed height container
                plugins: {
                    legend: { position: 'top' },
                    title: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Ingredient Usage Chart
        new Chart(document.getElementById('ingredientChart').getContext('2d'), {
            type: 'doughnut', // Changed to Doughnut for a modern look
            data: {
                labels: @json(array_keys($ingredientDetails ?? [])),
                datasets: [{
                    label: 'Usage (kg)',
                    data: @json(array_column($ingredientDetails ?? [], 'total_used')),
                    // Adjusted colors for a clean palette
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#34d399', '#6366f1', '#fbbf24']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Important for fixed height container
                plugins: {
                    legend: { position: 'right' }, // Move legend to the side
                    title: { display: false }
                }
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
                        borderColor: '#4f46e5', // Indigo
                        backgroundColor: 'rgba(79, 70, 229, 0.2)',
                        borderWidth: 2,
                        pointRadius: 3,
                        fill: true,
                        tension: 0.4 // Smoother lines
                    },
                    {
                        label: 'Avg Weight (kg)',
                        data: @json(($avgWeightByDate ?? [])->values()),
                        yAxisID: 'y1',
                        borderColor: '#10b981', // Emerald/Green
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderWidth: 2,
                        pointRadius: 3,
                        fill: false, // No fill on secondary line
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    title: { display: false }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Date' },
                        ticks: { autoSkip: true, maxTicksLimit: 10 }
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