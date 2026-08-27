<x-app-layout>
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Expected Weights</h1>
            <p class="text-gray-600 mt-1">Set expected weights for each pig type and age - system will automatically check weekly weights</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('weights.audit.index', ['tab' => 'standards']) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                Change History
            </a>
            <a href="{{ route('weight-standards.create') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                Add Add Expected Weight
            </a>
        </div>
    </div>

    <!-- Simple Instructions -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">How it works:</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <p>1. Set expected weight ranges for each pig type (breed + gender) at different ages</p>
                    <p>2. When you record weekly weights, system automatically compares against these standards</p>
                    <p>3. See instant alerts: <span class="text-red-600">Under Weight</span> | <span class="text-green-600">Normal</span> | <span class="text-black">Over Weight</span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Search Filters -->
    <form method="GET" action="{{ route('weight-standards.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Breed</label>
                <select name="breed" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" data-searchable-select>
                    <option value="">All Breeds</option>
                    @foreach($animalBreeds as $code => $name)
                        <option value="{{ $code }}" @selected(request('breed') === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                <select name="gender" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" data-searchable-select>
                    <option value="">All Genders</option>
                    <option value="M" {{ request('gender') === 'M' ? 'selected' : '' }}>Male</option>
                    <option value="F" {{ request('gender') === 'F' ? 'selected' : '' }}>Female</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stock Type</label>
                <select name="stock_type" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" data-searchable-select>
                    <option value="">All Types</option>
                    <option value="standard" {{ request('stock_type') === 'standard' ? 'selected' : '' }}>Standard</option>
                    <option value="breeding" {{ request('stock_type') === 'breeding' ? 'selected' : '' }}>Breeding</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Week From</label>
                <input type="number" name="week_from" min="1" max="200" value="{{ request('week_from') }}"
                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="1">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Week To</label>
                <input type="number" name="week_to" min="1" max="200" value="{{ request('week_to') }}"
                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="52">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Apply
                </button>

                <a href="{{ route('weight-standards.index') }}" class="w-full text-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Clear
                </a>
            </div>
        </div>
    </form>

        <!-- Filter Status -->
        <div id="filter-status" class="mt-4 pt-4 border-t border-gray-200 hidden">
            <div class="flex items-center justify-between">
                <span id="filter-count" class="text-sm text-gray-600"></span>
                <span class="text-xs text-gray-500">Filters applied - results update automatically</span>
            </div>
        </div>
    </div>

    <!-- Standards Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        @if($standards->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age (Weeks)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Weight Range</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($standards as $standard)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $standard->breed_display }} {{ $standard->gender_display }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $standard->stock_type_display }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900">Week {{ $standard->week_number }}</span>
                                    <div class="text-xs text-gray-500">{{ $standard->week_number * 7 }} days old</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <span class="font-medium">{{ number_format($standard->expected_min_weight, 1) }}kg</span>
                                        <span class="text-gray-500">to</span>
                                        <span class="font-medium">{{ number_format($standard->expected_max_weight, 1) }}kg</span>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Optimal: {{ number_format($standard->optimal_weight, 1) }}kg
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $standard->creator->name ?? 'Unknown' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('weight-standards.show', $standard) }}" class="text-blue-600 hover:text-blue-900">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ route('weight-standards.edit', $standard) }}" class="text-indigo-600 hover:text-indigo-900">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <form method="POST" action="{{ route('weight-standards.destroy', $standard) }}" class="inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this weight standard?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
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
                        Showing {{ $standards->firstItem() }} to {{ $standards->lastItem() }} of {{ $standards->total() }} results
                    </div>
                    <div class="flex items-center space-x-2">
                        <x-simple-pagination :paginator="$standards" />
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No weight standards found</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating your first weight standard.</p>
                <div class="mt-6">
                    <a href="{{ route('weight-standards.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Weight Standard
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const breedFilter = document.getElementById('breed-filter');
    const genderFilter = document.getElementById('gender-filter');
    const stockTypeFilter = document.getElementById('stock-type-filter');
    const weekFromFilter = document.getElementById('week-from-filter');
    const weekToFilter = document.getElementById('week-to-filter');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const filterStatus = document.getElementById('filter-status');
    const filterCount = document.getElementById('filter-count');

    const tableBody = document.querySelector('tbody');
    const tableRows = tableBody ? Array.from(tableBody.querySelectorAll('tr')) : [];

    let noResultsRow = document.getElementById('no-results-row');

    if (tableBody && !noResultsRow) {
        noResultsRow = document.createElement('tr');
        noResultsRow.id = 'no-results-row';
        noResultsRow.style.display = 'none';
        noResultsRow.innerHTML = `
            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                No weight standards match your current filters.
            </td>
        `;
        tableBody.appendChild(noResultsRow);
    }

    function applyFilters() {
        const filters = {
            breed: (breedFilter.value || '').toLowerCase().trim(),
            gender: (genderFilter.value || '').toLowerCase().trim(),
            stockType: (stockTypeFilter.value || '').toLowerCase().trim(),
            weekFrom: weekFromFilter.value ? parseInt(weekFromFilter.value, 10) : null,
            weekTo: weekToFilter.value ? parseInt(weekToFilter.value, 10) : null
        };

        let visibleCount = 0;
        let activeFilters = 0;

        if (filters.breed) activeFilters++;
        if (filters.gender) activeFilters++;
        if (filters.stockType) activeFilters++;
        if (filters.weekFrom !== null) activeFilters++;
        if (filters.weekTo !== null) activeFilters++;

        tableRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length < 5) return;

            const pigTypeCell = (cells[0]?.textContent || '').toLowerCase();
            const ageCell = (cells[1]?.textContent || '').toLowerCase();

            let shouldShow = true;

            if (filters.breed && !pigTypeCell.includes(filters.breed)) {
                shouldShow = false;
            }

            if (filters.gender) {
                const genderText = filters.gender === 'm' ? 'male' : 'female';
                if (!pigTypeCell.includes(genderText)) {
                    shouldShow = false;
                }
            }

            if (filters.stockType && !pigTypeCell.includes(filters.stockType)) {
                shouldShow = false;
            }

            const weekMatch = ageCell.match(/week\s+(\d+)/i);
            const weekNumber = weekMatch ? parseInt(weekMatch[1], 10) : null;

            if (filters.weekFrom !== null) {
                if (weekNumber === null || weekNumber < filters.weekFrom) {
                    shouldShow = false;
                }
            }

            if (filters.weekTo !== null) {
                if (weekNumber === null || weekNumber > filters.weekTo) {
                    shouldShow = false;
                }
            }

            row.style.display = shouldShow ? '' : 'none';

            if (shouldShow) {
                visibleCount++;
            }
        });

        if (filterStatus) {
            if (activeFilters > 0) {
                filterStatus.classList.remove('hidden');
                filterCount.textContent = `Showing ${visibleCount} standard${visibleCount === 1 ? '' : 's'} (${activeFilters} filter${activeFilters === 1 ? '' : 's'} applied)`;
            } else {
                filterStatus.classList.add('hidden');
            }
        }

        if (noResultsRow) {
            noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
        }
    }

    function clearAllFilters() {
        breedFilter.value = '';
        genderFilter.value = '';
        stockTypeFilter.value = '';
        weekFromFilter.value = '';
        weekToFilter.value = '';
        applyFilters();
    }

    breedFilter.addEventListener('change', applyFilters);
    genderFilter.addEventListener('change', applyFilters);
    stockTypeFilter.addEventListener('change', applyFilters);
    weekFromFilter.addEventListener('input', applyFilters);
    weekToFilter.addEventListener('input', applyFilters);
    clearFiltersBtn.addEventListener('click', clearAllFilters);

    applyFilters();
});
</script>
</x-app-layout>
