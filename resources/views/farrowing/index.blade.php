<x-app-layout>

<div class="container mx-auto px-6 py-6">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Farrowing Records</h1>
            <p class="text-gray-600 mt-2">Complete farrowing history and birth records</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Total Farrowings</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['total_farrowings'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4m-4 8a4 4 0 11-8 0v-1a4 4 0 014-4h4a4 4 0 014 4v1a4 4 0 11-8 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">This Month</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['farrowings_this_month'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Avg Litter Size</p>
                    <p class="text-xl font-semibold text-gray-900">
                        @if($stats['average_litter_size'])
                            {{ number_format($stats['average_litter_size'], 1) }}
                        @else
                            N/A
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Survival Rate</p>
                    <p class="text-xl font-semibold text-gray-900">
                        @if($stats['average_survival_rate'])
                            {{ number_format($stats['average_survival_rate'], 1) }}%
                        @else
                            N/A
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Statistics Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Farrowing Types -->
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">📊 Farrowing Types</h3>
            <div class="space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Natural</span>
                    <span class="text-sm font-medium text-green-600">{{ $stats['natural_farrowings'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Assisted</span>
                    <span class="text-sm font-medium text-yellow-600">{{ $stats['assisted_farrowings'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Cesarean</span>
                    <span class="text-sm font-medium text-red-600">{{ $stats['cesarean_farrowings'] }}</span>
                </div>
            </div>
        </div>

        <!-- Sow Conditions -->
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">🏥 Sow Conditions</h3>
            <div class="space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Excellent</span>
                    <span class="text-sm font-medium text-green-600">{{ $stats['excellent_condition'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Good</span>
                    <span class="text-sm font-medium text-blue-600">{{ $stats['good_condition'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Fair</span>
                    <span class="text-sm font-medium text-yellow-600">{{ $stats['fair_condition'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Poor</span>
                    <span class="text-sm font-medium text-red-600">{{ $stats['poor_condition'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Farrowing Records Table -->
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Farrowing Records ({{ $farrowingRecords->total() }})</h2>
            <p class="text-sm text-gray-600 mt-1">Complete birth and farrowing history</p>
        </div>

        @if($farrowingRecords->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sow</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Farrowing Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Litter Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Difficulty</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sow Condition</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($farrowingRecords as $farrowing)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-pink-100 flex items-center justify-center">
                                                <span class="text-sm font-medium text-pink-700">🐷</span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm text-gray-500">{{ $farrowing->sow->ear_tag_number }}</div>
                                            <div class="text-sm font-medium text-gray-900">{{ $farrowing->sow->code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>
                                        <div class="font-medium">{{ $farrowing->farrowing_date->format('M d, Y') }}</div>
                                        @if($farrowing->farrowing_time)
                                            <div class="text-xs text-gray-500">{{ $farrowing->farrowing_time->format('H:i') }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="space-y-1">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-green-600 font-medium">{{ $farrowing->live_births }}</span>
                                            <span class="text-gray-400">live</span>
                                        </div>
                                        @if($farrowing->stillbirths > 0)
                                            <div class="flex items-center space-x-2">
                                                <span class="text-red-600 font-medium">{{ $farrowing->stillbirths }}</span>
                                                <span class="text-gray-400">still</span>
                                            </div>
                                        @endif
                                        <div class="text-xs text-gray-500">
                                            {{ $farrowing->survival_rate }}% survival
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $farrowing->farrowing_type_badge }}">
                                        {{ ucfirst($farrowing->farrowing_type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $farrowing->farrowing_difficulty_badge }}">
                                        {{ ucfirst($farrowing->farrowing_difficulty) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $farrowing->sow_condition_badge }}">
                                        {{ ucfirst($farrowing->sow_condition) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-3">
                                        <a href="{{ route('farrowing.show', $farrowing->id) }}" class="text-blue-600 hover:text-blue-900">
                                            View Details
                                        </a>
                                        <a href="{{ route('farrowing.edit', $farrowing->id) }}" class="text-green-600 hover:text-green-900">
                                            Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing {{ $farrowingRecords->firstItem() }} to {{ $farrowingRecords->lastItem() }} of {{ $farrowingRecords->total() }} results
                    </div>
                    <div class="flex items-center space-x-2">
                        <x-simple-pagination :paginator="$farrowingRecords" />
                    </div>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No farrowing records</h3>
                <p class="mt-1 text-sm text-gray-500">Farrowing records will appear here once births are recorded.</p>
            </div>
        @endif
    </div>
</div>
</x-app-layout>
