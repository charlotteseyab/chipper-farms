<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <!-- RESTORED TITLE: Gives the user immediate context -->
        <h1 class="text-3xl font-extrabold text-gray-900 mb-8">Medication Administration Log</h1>

        <!-- Filter and Action Card -->
        <div class="bg-white p-6 md:p-8 shadow-xl rounded-xl mb-8 border border-gray-100">
            <form method="get">
                <!-- Reorganized Grid: Added Pig ID and changed Purpose to a select -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-5 md:gap-4">
                    
                    <!-- NEW: Pig ID/Code Search (1/5 column width) -->
                    <div class="col-span-1 sm:col-span-1">
                        <label for="pig_q" class="block text-sm font-medium text-gray-700">Pig ID</label>
                        <input id="pig_q" type="text" name="pig_q" value="{{ request('pig_q') }}" placeholder="e.g., P001, 2345" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" />
                    </div>

                    <!-- Medication Name (2/5 column width) -->
                    <div class="col-span-1 sm:col-span-2">
                        <label for="q" class="block text-sm font-medium text-gray-700">Medication Name</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="e.g., Penicillin, Vitamin E" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" />
                    </div>

                    <!-- Purpose (1/5 column width, now a SELECT for data integrity) -->
                    <div class="col-span-1 sm:col-span-1">
                        <label for="purpose" class="block text-sm font-medium text-gray-700">Purpose</label>
                        <select id="purpose" name="purpose" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            <option value="">Any Purpose</option>
                            <option value="Treatment" @selected(request('purpose') === 'Treatment')>Treatment</option>
                            <option value="Prevention" @selected(request('purpose') === 'Prevention')>Prevention</option>
                            <option value="Vitamin" @selected(request('purpose') === 'Vitamin')>Vitamin/Supplement</option>
                            <!-- Add more common purposes here -->
                        </select>
                    </div>

                    <!-- Date Range (1/5 column width) -->
                    <div class="col-span-1 sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <div class="flex gap-3">
                            <input type="date" name="from" value="{{ request('from') }}" class="w-1/2 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" />
                            <input type="date" name="to" value="{{ request('to') }}" class="w-1/2 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" />
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex flex-col sm:flex-row justify-between gap-3 pt-4 border-t border-gray-100">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <!-- FIX: Changed route to a non-parameterized 'medications.create' route -->
                        <a href="{{ route('health.create-medication') }}" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition duration-150 shadow-md text-center">
                            <svg class="inline-block w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Add Medication Record
                        </a>
                        <!-- FIX: Changed route to a non-parameterized 'health.create' route -->
                        <!-- <a href="{{ route('health.create') }}" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition duration-150 shadow-md text-center">
                            <svg class="inline-block w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Add Health Record
                        </a> -->
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('medications.index') }}" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition duration-150 text-center">
                            Reset Filters
                        </a>
                        <button type="submit" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition duration-150 shadow-md">
                            <svg class="inline-block w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707v7l-4 4v-7a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                            Apply Filter
                        </button>
                        <a href="{{ route('medications.export', request()->query()) }}" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition duration-150 shadow-md text-center">
                            Export CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Medication Table Card -->
        <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        @php
                            $sortBy = request('sort_by', 'administration_date'); // Default sort column
                            $sortDir = request('sort_dir', 'desc'); // Default sort direction
                            
                            // Helper function to generate the next sort direction and link parameters
                            $sortLink = function ($column) use ($sortBy, $sortDir) {
                                $isCurrentColumn = ($sortBy === $column);
                                $nextSortDir = $isCurrentColumn && $sortDir === 'asc' ? 'desc' : 'asc';
                                $params = array_merge(request()->query(), ['sort_by' => $column, 'sort_dir' => $nextSortDir]);
                                $link = route('medications.index', $params);

                                return [
                                    'link' => $link,
                                    'isCurrent' => $isCurrentColumn,
                                    'nextDir' => $nextSortDir,
                                    'iconClasses' => $isCurrentColumn && $sortDir === 'desc' ? 'rotate-180' : ''
                                ];
                            };
                        @endphp
                        <tr>
                            <!-- Date Sorting -->
                            @php($dateSort = $sortLink('administration_date'))
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <a href="{{ $dateSort['link'] }}" class="flex items-center group hover:text-gray-900 transition duration-150">
                                    Date
                                    <svg class="w-4 h-4 ml-1 transition-transform duration-200 {{ $dateSort['iconClasses'] }} {{ $dateSort['isCurrent'] ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                </a>
                            </th>

                            <!-- Pig ID Sorting (assuming a column like 'pig_code' or 'pig_tag' is used for sorting) -->
                            @php($pigIdSort = $sortLink('pig_code'))
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <a href="{{ $pigIdSort['link'] }}" class="flex items-center group hover:text-gray-900 transition duration-150">
                                    Ear Tag Number
                                    <svg class="w-4 h-4 ml-1 transition-transform duration-200 {{ $pigIdSort['iconClasses'] }} {{ $pigIdSort['isCurrent'] ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                </a>
                            </th>

                            <!-- Medication Name Sorting -->
                            @php($medNameSort = $sortLink('medication_name'))
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <a href="{{ $medNameSort['link'] }}" class="flex items-center group hover:text-gray-900 transition duration-150">
                                    Medication Name
                                    <svg class="w-4 h-4 ml-1 transition-transform duration-200 {{ $medNameSort['iconClasses'] }} {{ $medNameSort['isCurrent'] ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                </a>
                            </th>

                            <!-- Dosage Sorting -->
                            @php($dosageSort = $sortLink('dosage'))
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                <a href="{{ $dosageSort['link'] }}" class="flex items-center group hover:text-gray-900 transition duration-150">
                                    Dosage
                                    <svg class="w-4 h-4 ml-1 transition-transform duration-200 {{ $dosageSort['iconClasses'] }} {{ $dosageSort['isCurrent'] ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                </a>
                            </th>

                            <!-- Purpose (Not clickable, too many non-sortable options typically) -->
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Purpose</th>

                            <!-- Route (Not clickable) -->
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Route</th>
                            
                            <!-- Actions (Not clickable) -->
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($medications as $m)
                        <tr class="hover:bg-indigo-50/50 transition duration-100">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-700">{{ $m->administration_date->format('M d, Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
    @if($m->pig)
        <a href="{{ route('health.show', $m->pig->id) }}" class="text-indigo-600 hover:text-indigo-800 font-semibold transition duration-150">
            {{ $m->pig->code ?? $m->pig->ear_tag_number ?? 'Pig #' . $m->pig->id }}
        </a>
    @else
        <span class="text-gray-400 italic">No pig linked</span>
    @endif
</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $m->medication_name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $m->dosage }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $m->purpose ?? 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $m->route ?? 'N/A' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                                <div class="relative inline-block text-left" x-data="{ open: false }" @click.away="open = false">
                                    <!-- Dropdown Toggle Button (Kebab Icon) -->
                                    <button type="button" @click="open = !open" class="text-gray-500 hover:text-gray-700 p-1 rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-150">
                                        {{-- Kebab Icon (Three vertical dots) --}}
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>
                                    <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 focus:outline-none z-10">
                                        <div class="py-1">
                                            <a href="{{ route('medications.show', $m) }}" class="block px-4 py-2 text-sm text-blue-600 hover:bg-indigo-50 hover:text-indigo-600 w-full text-left" role="menuitem">View Record</a>
                                            <a href="{{ route('medications.edit', $m) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 w-full text-left" role="menuitem">Edit Record</a>
                                            <form method="POST" action="{{ route('medications.destroy', $m) }}" onsubmit="return confirm('Are you sure you want to delete this medication record?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 w-full text-left" role="menuitem">
                                                    Delete Record
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-lg text-gray-500 bg-gray-50">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p class="mt-1">No medication records found matching your filters.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Pagination (embedded, simple style) -->
            @if($medications->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 bg-white">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $medications->firstItem() }} to {{ $medications->lastItem() }} of {{ $medications->total() }} results
                            </div>
                            <!-- IMPORTANT: Ensure your pagination component retains all query parameters, including sort_by and sort_dir -->
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$medications" />
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>                               
    </div>
</x-app-layout>
