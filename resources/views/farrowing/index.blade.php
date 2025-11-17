<x-app-layout>

<div class="container mx-auto px-6 py-6">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Farrowing Records</h1>
            <p class="text-gray-600 mt-2">Complete farrowing history and birth records</p>
        </div>
    </div>

    @php
        $avgLitterSize = \App\Models\Farrowing::avg('live_births');
        $totalLive = \App\Models\Farrowing::sum('live_births');
        $totalBorn = \App\Models\Farrowing::sum('total_piglets_born');
        $survivalRate = $totalBorn > 0 ? round($totalLive / $totalBorn * 100, 1) : 0;
        $weaningPct = $totalLive > 0 ? round(\App\Models\Weaning::count() / $totalLive * 100, 1) : 0;
        $avgStillborns = \App\Models\Farrowing::avg('stillbirths');
    @endphp
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
        <!-- Table Header with Search Bar -->
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Farrowing Records ({{ $farrowingRecords->total() }})</h2>
                <p class="text-sm text-gray-600 mt-1">Complete birth and farrowing history</p>
            </div>
            <!-- Search Box (Top Right) -->
            <!-- <div class="w-full sm:w-64">
                <label for="farrowing-search" class="sr-only">Search</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <input type="text" id="farrowing-search" onkeyup="filterFarrowingTable()" class="block w-full pl-10 pr-4 py-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Search sow, date, type...">
                </div>
            </div> -->
            <!-- End Search Box -->
        </div>

        @if($farrowingRecords->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="farrowing-records-table">
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
                    <tbody class="bg-white divide-y divide-gray-200" id="table-body">
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
                                            <span class="text-green-600 font-medium">{{ $farrowing->current_litter_size }}</span>
                                            <span class="text-gray-400">live</span>
                                            @if($farrowing->total_piglet_deaths > 0)
                                                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-700">-{{ $farrowing->total_piglet_deaths }}</span>
                                            @endif
                                        </div>
                                        @if($farrowing->stillbirths > 0)
                                            <div class="flex items-center space-x-2">
                                                <span class="text-red-600 font-medium">{{ $farrowing->stillbirths }}</span>
                                                <span class="text-gray-400">still</span>
                                            </div>
                                        @endif
                                        <div class="text-xs text-gray-500">
                                            {{ number_format(($farrowing->total_piglets_born > 0) ? ($farrowing->current_litter_size / $farrowing->total_piglets_born) * 100 : 0, 1) }}% survival
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
                                    <div x-data="{ open: false }">
                                    <div class="flex items-center space-x-3">
                                        <a href="{{ route('farrowing.show', $farrowing->id) }}" class="text-blue-600 hover:text-blue-900">
                                            View Details
                                        </a>
                                        <a href="{{ route('farrowing.edit', $farrowing->id) }}" class="text-green-600 hover:text-green-900">
                                            Edit
                                        </a>
                                            <button type="button" @click="open = true" class="text-red-600 hover:text-red-800">
                                                Record Piglet Death
                                            </button>
                                        </div>

                                        <!-- Modal -->
                                        <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black bg-opacity-40" @click="open = false"></div>
    <!-- Dialog -->
    <div class="relative bg-white rounded-lg shadow-lg w-full max-w-lg mx-4">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Record Piglet Death</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" @click="open = false">✕</button>
        </div>
        <div class="p-5">
            <form method="POST" action="{{ route('farrowing.piglet-deaths.record', $farrowing) }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf
                <div>
                    <label class="block text-xs text-gray-600">Date</label>
                    <input type="date" name="death_date" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-600">Count</label>
                    <input type="number" min="1" name="count" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" value="1" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-600">Cause (optional)</label>
                    <input type="text" name="cause" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" placeholder="e.g., crushing">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-600">Notes (optional)</label>
                    <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" placeholder="Additional details"></textarea>
                </div>
                <div class="md:col-span-2 flex items-center justify-end space-x-2">
                    <button type="button" @click="open = false" class="px-3 py-1.5 rounded-md border text-xs text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700 text-xs">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
                                        <!-- End Modal -->
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        <!-- Search No Results State (Initially hidden) -->
                        <tr id="no-search-results-row" style="display: none;">
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No results found</h3>
                                    <p class="mt-1 text-sm text-gray-500">Try adjusting your search term.</p>
                                </div>
                            </td>
                        </tr>
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
            <!-- Initial Empty State -->
            <div class="px-6 py-12 text-center" id="initial-empty-state">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No farrowing records</h3>
                <p class="mt-1 text-sm text-gray-500">Farrowing records will appear here once births are recorded.</p>
            </div>
        @endif
    </div>
</div>
<x-copyright-minimal />

</x-app-layout>

<!-- <script>
    /**
     * Implements the live search functionality for the Farrowing Records table.
     * It hides rows that do not contain the search input text and manages the 
     * visibility of the 'No results found' message.
     */
    function filterFarrowingTable() {
        const input = document.getElementById("farrowing-search");
        const filter = input.value.toUpperCase().trim();
        
        const tableBody = document.getElementById("table-body");
        
        // If the table body element doesn't exist (i.e., no initial data), stop execution
        if (!tableBody) return;

        // Get all data rows (<tr>) in the tbody
        const tr = tableBody.getElementsByTagName("tr");
        
        const noResultsRow = document.getElementById("no-search-results-row");
        
        let foundMatch = false;
        
        // Iterate through all rows in the table body
        for (let i = 0; i < tr.length; i++) {
            const row = tr[i];
            
            // Skip the dedicated status row from the filter check, but keep it hidden for now
            if (row.id === "no-search-results-row") {
                row.style.display = "none";
                continue;
            }

            // Check if the row content includes the filter text
            const rowText = row.innerText.toUpperCase();
            
            if (rowText.includes(filter)) {
                row.style.display = ""; // Show the row
                foundMatch = true;
            } else {
                row.style.display = "none"; // Hide the row
            }
        }
        
        // Logic for displaying the correct status message
        if (filter.length > 0) {
            // User is actively searching
            if (foundMatch) {
                if (noResultsRow) noResultsRow.style.display = "none";
            } else {
                if (noResultsRow) noResultsRow.style.display = ""; // Show "No results found"
            }
        } else {
            // Search field is empty, show all data rows
            if (noResultsRow) noResultsRow.style.display = "none";
            
            // Show all data rows again
            for (let i = 0; i < tr.length; i++) {
                 // Ensure we don't accidentally display the hidden 'no-search-results-row'
                 if (tr[i].id !== "no-search-results-row") {
                     tr[i].style.display = "";
                 }
            }
        }
    }
</script> -->
