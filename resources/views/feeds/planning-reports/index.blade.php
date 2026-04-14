<x-app-layout>
    @php
        $statusClasses = [
            'enough' => 'bg-green-100 text-green-800 border border-green-200',
            'shortage' => 'bg-red-100 text-red-800 border border-red-200',
        ];
    @endphp

    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Planning & Reports</h1>
                <p class="text-sm text-gray-500">Project feed needs, ingredient shortages, bags to buy, and estimated purchase cost.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('planning-reports.index') }}" class="mb-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-end">
                <div class="w-full md:w-72">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Projection Period</label>
                    <select name="period"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <option value="weekly" {{ $period === 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="bi-weekly" {{ $period === 'bi-weekly' ? 'selected' : '' }}>Bi-weekly</option>
                        <option value="monthly" {{ $period === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                        Apply
                    </button>

                    <a href="{{ route('planning-reports.index') }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-semibold rounded-xl transition">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-blue-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Projection Window</p>
                <h3 class="mt-2 text-3xl font-bold text-blue-700">{{ $days }}</h3>
                <p class="mt-1 text-xs text-gray-400">Days in selected period</p>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Total Feed Needed</p>
                <h3 class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($stats['total_feed_needed'] ?? 0, 2) }} kg</h3>
                <p class="mt-1 text-xs text-gray-400">Projected total feed requirement</p>
            </div>

            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Ingredients Needed</p>
                <h3 class="mt-2 text-3xl font-bold text-amber-700">{{ $stats['ingredients_needed'] ?? 0 }}</h3>
                <p class="mt-1 text-xs text-gray-400">Materials required for the projection</p>
            </div>

            <div class="rounded-2xl border border-red-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Shortages / Purchase Cost</p>
                <div class="mt-2">
                    <div class="text-3xl font-bold text-red-700">{{ $stats['shortages'] ?? 0 }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-700">{{ number_format($stats['total_purchase_cost'] ?? 0, 2) }}</div>
                </div>
                <p class="mt-1 text-xs text-gray-400">Shortage count and estimated cost to buy missing stock</p>
            </div>
        </div>

        @if(!empty($missingRecipes))
            <div class="mb-6 overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 shadow-sm">
                <div class="border-b border-amber-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-amber-900">Missing Recipe Alerts</h2>
                    <p class="text-sm text-amber-700 mt-1">These feed types are being used in the projection but do not yet have recipes configured.</p>
                </div>

                <div class="px-6 py-5">
                    <div class="flex flex-wrap gap-2">
                        @foreach(array_unique($missingRecipes) as $missingRecipe)
                            <span class="inline-flex items-center rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-amber-800 border border-amber-200">
                                {{ $missingRecipe }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if(count($shortageRows ?? []))
            <div class="mb-6 overflow-hidden rounded-2xl border border-red-200 bg-red-50 shadow-sm">
                <div class="border-b border-red-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-red-900">Shortage Alerts</h2>
                    <p class="text-sm text-red-700 mt-1">These materials are short for the selected projection period and may need to be purchased.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 p-6">
                    @foreach($shortageRows as $row)
                        <div class="rounded-xl border border-red-200 bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $row['name'] }}</h3>
                                    <p class="mt-1 text-xs text-gray-500">{{ $row['category'] ?: 'No category' }} • Unit: {{ $row['unit'] }}</p>
                                </div>

                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                                    Shortage
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
                                <div class="rounded-lg bg-gray-50 px-3 py-2 border border-gray-100">
                                    <p class="text-gray-500">Required</p>
                                    <p class="font-semibold text-gray-800">{{ number_format($row['required_quantity'], 2) }}</p>
                                </div>

                                <div class="rounded-lg bg-gray-50 px-3 py-2 border border-gray-100">
                                    <p class="text-gray-500">Available</p>
                                    <p class="font-semibold text-gray-800">{{ number_format($row['available_quantity'], 2) }}</p>
                                </div>

                                <div class="rounded-lg bg-red-50 px-3 py-2 border border-red-100">
                                    <p class="text-red-500">Short</p>
                                    <p class="font-semibold text-red-700">{{ number_format($row['shortage_quantity'], 2) }}</p>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg bg-gray-50 px-3 py-2 border border-gray-100">
                                    <p class="text-gray-500">Bag Weight</p>
                                    <p class="font-semibold text-gray-800">{{ number_format($row['bag_weight_kg'], 2) }} kg</p>
                                </div>

                                <div class="rounded-lg bg-emerald-50 px-3 py-2 border border-emerald-100">
                                    <p class="text-emerald-600">Bags to Buy</p>
                                    <p class="font-semibold text-emerald-700">{{ number_format($row['bags_to_buy']) }} bag(s)</p>
                                </div>
                            </div>

                            <div class="mt-4 text-sm text-gray-600">
                                Estimated purchase cost:
                                <span class="font-semibold text-gray-800">{{ number_format($row['purchase_cost'], 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Projected Feed Requirement</h2>
                    <p class="text-sm text-gray-500 mt-1">Feed needed by stage for the selected period.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Stage</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Feed Type</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Animals</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kg/Day</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Feed Needed</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($feedProjectionRows as $row)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-800">{{ $row['stage'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ $row['feed_type_name'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ number_format($row['animal_count']) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ number_format($row['kg_per_animal_per_day'], 2) }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-800">{{ number_format($row['feed_needed'], 2) }} kg</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">
                                        No projection rows available.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Projected Ingredient Requirement</h2>
                    <p class="text-sm text-gray-500 mt-1">Ingredients needed from recipes compared against available stock.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Material</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Required (kg)</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Available (kg)</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Shortage (kg)</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Bag Weight</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Bags to Buy</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($ingredientProjectionRows as $row)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-800">{{ $row['name'] }}</div>
                                        <div class="text-xs text-gray-400 mt-1">{{ $row['category'] ?: 'No category' }} • {{ $row['unit'] }}</div>
                                    </td>

                                    <td class="px-6 py-4 text-sm font-semibold text-gray-800">
                                        {{ number_format($row['required_quantity'], 2) }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        {{ number_format($row['available_quantity'], 2) }}
                                    </td>

                                    <td class="px-6 py-4 text-sm {{ $row['shortage_quantity'] > 0 ? 'font-semibold text-red-700' : 'text-gray-700' }}">
                                        {{ number_format($row['shortage_quantity'], 2) }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        {{ number_format($row['bag_weight_kg'], 2) }} kg
                                    </td>

                                    <td class="px-6 py-4 text-sm font-semibold text-emerald-700">
                                        {{ number_format($row['bags_to_buy']) }} bags
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$row['status']] ?? 'bg-gray-100 text-gray-700 border border-gray-200' }}">
                                            {{ ucfirst($row['status']) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                                        No ingredient projection data available.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-800">Estimated Purchase Cost</h2>
                <p class="text-sm text-gray-500 mt-1">Estimated cost of only the quantities you are short of and may need to buy.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Material</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Shortage (kg)</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Bag Weight</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Bags to Buy</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Unit Cost</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Purchase Cost</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($ingredientProjectionRows as $row)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-800">{{ $row['name'] }}</div>
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ number_format($row['shortage_quantity'], 2) }} kg
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ number_format($row['bag_weight_kg'], 2) }} kg
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-emerald-700">
                                    {{ number_format($row['bags_to_buy']) }} bags
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ number_format($row['unit_cost'], 2) }}
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-gray-800">
                                    {{ number_format($row['purchase_cost'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                                    No purchase cost data available.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>