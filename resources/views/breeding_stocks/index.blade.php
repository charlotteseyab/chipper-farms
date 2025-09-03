<x-app-layout>
    <main class="flex-1 p-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Breeding Stock Management</h2>
                <!-- <a href="{{ route('breeding-stocks.create') }}" class="px-4 py-2 bg-[#1C6C51] text-white rounded-lg font-semibold hover:bg-green-700">Add New</a> -->
            </div>

            <div class="bg-white shadow-xl rounded-lg">
                <!-- Filters and Export Buttons integrated with table -->
                <div class="p-6 border-b border-gray-200">
                    <form method="GET" action="{{ route('breeding-stocks.index') }}" id="filterForm" class="flex flex-wrap items-end gap-4 mb-4">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Breed</label>
                            <select name="breed" id="breedFilter" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                                <option value="" disabled selected>All Breeds</option>
                                <option value="LW" {{ request('breed') == 'LW' ? 'selected' : '' }}>Large White</option>
                                <option value="DR" {{ request('breed') == 'DR' ? 'selected' : '' }}>Duroc</option>
                                <option value="LWDR" {{ request('breed') == 'LWDR' ? 'selected' : '' }}>Large White Duroc</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[150px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                            <select name="gender" id="genderFilter" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                                <option value="" disabled selected>All Genders</option>
                                <option value="M" {{ request('gender') == 'M' ? 'selected' : '' }}>Male</option>
                                <option value="F" {{ request('gender') == 'F' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[150px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Min Age (days)</label>
                            <input type="number" name="age" id="ageFilter" value="{{ request('age') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]" placeholder="e.g. 365">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-[#1C6C51] text-white rounded-lg hover:bg-green-700 font-medium">Filter</button>
                            <a href="{{ route('breeding-stocks.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 font-medium">Clear</a>
                        </div>
                    </form>

                    <!-- Export Buttons -->
                    <div class="flex gap-2">
                        <a href="{{ route('breeding-stocks.export.csv', request()->query()) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2 font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export CSV
                        </a>
                        <a href="{{ route('breeding-stocks.export.pdf', request()->query()) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2 font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export PDF
                        </a>
                    </div>
                </div>

                <!-- Results Summary -->
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            @if($pigmanagement->total() > 0)
                                Total: {{ $pigmanagement->total() }} pig{{ $pigmanagement->total() !== 1 ? 's' : '' }}
                                @if(request()->hasAny(['breed', 'gender', 'age']))
                                    (filtered)
                                @endif
                            @else
                                No pigs found
                            @endif
                        </div>
                        @if($pigmanagement->hasPages())
                        <div class="text-sm text-gray-600">
                            Page {{ $pigmanagement->currentPage() }} of {{ $pigmanagement->lastPage() }}
                        </div>
                        @endif
                    </div>
                </div>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ear tag</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Breed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DOB</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parents</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($pigmanagement as $pig)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $pig->code }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $pig->ear_tag_number }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $pig->breed }}</td>
                            <td class="px-6 py-4 whitespace-nowrap capitalize">{{ $pig->gender }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $pig->date_of_birth->format('Y-m-d') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap" title="{{ $pig->current_age_in_days }} days">
                                {{ $pig->age_compact }}
                                @if($pig->current_age_in_days < 0)
                                    <span class="text-red-500 text-xs">(Invalid)</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $father = $pig->father_id ? $pig->father->ear_tag_number ?? 'N/A' : 'N/A';
                                    $mother = $pig->mother_id ? $pig->mother->ear_tag_number ?? 'N/A' : 'N/A';
                                @endphp
                                F: {{ $father }}<br>M: {{ $mother }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1C6C51]">
                                        Actions
                                        <svg class="ml-2 -mr-0.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    
                                    <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                        <div class="py-1">
                                            @php
                                                $hasActiveMating = $pig->maleMatings()->whereIn('status', ['planned', 'completed'])->exists() ||
                                                                  $pig->femaleMatings()->whereIn('status', ['planned', 'completed'])->exists();
                                            @endphp
                                            
                                            <!-- View -->
                                            <a href="{{ route('pigs.show', $pig->id) }}" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <svg class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                View Details
                                            </a>
                                            
                                            <!-- Edit -->
                                            <a href="{{ route('breeding-stocks.edit', $pig) }}" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <svg class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                            
                                            <!-- Demote to Standard -->
                                            @if(!$hasActiveMating)
                                                <form action="{{ route('pigs.demote-to-standard', $pig->id) }}" method="POST" onsubmit="return confirm('Demote {{ $pig->code }} to standard stock?');" class="block">
                                                    @csrf
                                                    <button type="submit" class="group flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 text-left">
                                                        <svg class="mr-3 h-4 w-4 text-orange-500 group-hover:text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                                        </svg>
                                                        Demote to Standard
                                                    </button>
                                                </form>
                                            @else
                                                <div class="px-4 py-2 text-sm text-gray-400 cursor-not-allowed" title="Cannot demote: pig has active matings">
                                                    <div class="flex items-center">
                                                        <svg class="mr-3 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                                        </svg>
                                                        Cannot Demote (Active Matings)
                                                    </div>
                                                </div>
                                            @endif
                                            
                                            <!-- Divider -->
                                            <div class="border-t border-gray-100 my-1"></div>
                                            
                                            <!-- Delete -->
                                            <form action="{{ route('breeding-stocks.destroy', $pig) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this breeding stock?');" class="block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="group flex items-center w-full px-4 py-2 text-sm text-red-700 hover:bg-red-50 text-left">
                                                    <svg class="mr-3 h-4 w-4 text-red-400 group-hover:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.562M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.636 6.636a9 9 0 000 12.728"></path>
                                    </svg>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No breeding stock found</h3>
                                    <p class="text-gray-500 mb-4">
                                        @if(request()->hasAny(['breed', 'gender', 'age']))
                                            No pigs match your current filter criteria. Try adjusting your filters or
                                            <a href="{{ route('breeding-stocks.index') }}" class="text-[#1C6C51] hover:underline font-medium">clear all filters</a>.
                                        @else
                                            There are no breeding stock records in the system yet.
                                        @endif
                                    </p>
                                    @if(!request()->hasAny(['breed', 'gender', 'age']))
                                    <div class="space-y-3">
                                        <p class="text-sm text-gray-600">Pigs are promoted to breeding stock from standard stock when they meet age and weight requirements.</p>
                                        <!-- <a href="{{ route('standard-stocks.index') }}" class="inline-flex items-center px-4 py-2 bg-[#1C6C51] text-white rounded-lg font-semibold hover:bg-green-700">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                            </svg>
                                            View Standard Stock
                                        </a> -->
                                    </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                <!-- Pagination -->
                @if($pigmanagement->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ $pigmanagement->firstItem() }} to {{ $pigmanagement->lastItem() }} of {{ $pigmanagement->total() }} results
                        </div>
                        <div class="flex items-center space-x-2">
                            <x-simple-pagination :paginator="$pigmanagement" />
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('filterForm');
            const breedFilter = document.getElementById('breedFilter');
            const genderFilter = document.getElementById('genderFilter');
            const ageFilter = document.getElementById('ageFilter');

            // Add event listeners for live search
            breedFilter.addEventListener('change', function() {
                form.submit();
            });

            genderFilter.addEventListener('change', function() {
                form.submit();
            });

            // For age input, use a debounced approach to avoid too many requests
            let ageTimeout;
            ageFilter.addEventListener('input', function() {
                clearTimeout(ageTimeout);
                ageTimeout = setTimeout(function() {
                    form.submit();
                }, 500); // Wait 500ms after user stops typing
            });

            // Also submit on Enter key for age input
            ageFilter.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(ageTimeout);
                    form.submit();
                }
            });
        });
    </script>
</x-app-layout>
