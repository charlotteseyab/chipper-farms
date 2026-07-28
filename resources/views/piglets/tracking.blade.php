<x-app-layout>
    <div class="min-h-screen bg-gray-100">
        
        <!-- Header & Search -->
        <div class="bg-white shadow-md border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900">Piglet Tracking Dashboard</h1>
                        <p class="text-gray-500 mt-1">Comprehensive tracking of all recorded farrowing events in a single list.</p>
                    </div>
                    
                    <!-- Search Form -->
                    <form method="GET" action="{{ route('piglets.tracking') }}" class="mt-4 md:mt-0 flex items-center space-x-2 bg-gray-50 p-2 rounded-xl border border-gray-200">
                        <input 
                            type="text" 
                            name="search" 
                            id="pigletSearchInput"
                            value="{{ request('search') }}"
                            placeholder="Search by Sow Tag..." 
                            class="px-4 py-2 border-none rounded-lg focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-transparent"
                        >
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium transition shadow-md">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Search
                        </button>
                        @if(request('search'))
                            <a href="{{ route('piglets.tracking') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium transition">
                                Clear
                            </a>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Overall Statistics Grid -->
            <!-- <h2 class="text-xl font-bold text-gray-800 mb-4">Farm Summary Metrics</h2> -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <!-- Total Sows -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-5">
                    <p class="text-sm font-medium text-gray-600">Total Sows Tracked</p>
                    <p class="text-3xl font-extrabold text-gray-900 mt-1">{{ $overallStats['total_sows'] }}</p>
                </div>
                
                <!-- Total Farrowings -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-5">
                    <p class="text-sm font-medium text-gray-600">Total Farrowings</p>
                    <p class="text-3xl font-extrabold text-gray-900 mt-1">{{ $overallStats['total_farrowings'] }}</p>
                </div>

                <!-- Total Born -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-5">
                    <p class="text-sm font-medium text-gray-600">Total Piglets Born</p>
                    <p class="text-3xl font-extrabold text-blue-700 mt-1">{{ $overallStats['total_piglets_born'] }}</p>
                </div>
                
                <!-- Total Live Births -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-5">
                    <p class="text-sm font-medium text-gray-600">Total Live Births</p>
                    <p class="text-3xl font-extrabold text-green-700 mt-1">{{ $overallStats['total_live_births'] }}</p>
                </div>

                <!-- Stillbirths -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-5">
                    <p class="text-sm font-medium text-gray-600">Stillbirths</p>
                    <p class="text-3xl font-extrabold text-yellow-700 mt-1">{{ $overallStats['total_stillbirths'] }}</p>
                </div>
                
                <!-- Deaths -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-5">
                    <p class="text-sm font-medium text-gray-600">Total Deaths Recorded</p>
                    <p class="text-3xl font-extrabold text-red-700 mt-1">{{ $overallStats['total_deaths'] }}</p>
                </div>
                
                <!-- Current Surviving Piglets (Highlighted) -->
                <div class="bg-green-600 rounded-xl shadow-2xl p-5 col-span-2 lg:col-span-1 border border-green-700 transform scale-[1.02]">
                    <p class="text-sm font-medium text-green-100 flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Current Surviving Piglets
                    </p>
                    <p class="text-4xl font-extrabold text-white mt-1">{{ $overallStats['current_surviving'] ?? 0 }}</p>
                    <p class="text-xs text-green-200 mt-2">Active Piglets (under {{ config('breeding.cycle.birth_to_weaning_days', 45) }} days old)</p>
                </div>
                
                <!-- Total Piglets Ever Born (Live) -->
                <!-- <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-5 col-span-2 lg:col-span-1">
                    <p class="text-sm font-medium text-gray-600">Total Piglets (All Time)</p>
                    <p class="text-3xl font-extrabold text-blue-600 mt-1">{{ $overallStats['total_surviving'] }}</p>
                    <p class="text-xs text-gray-500 mt-2">Cumulative live piglets recorded — never decreases</p>
                </div> -->
            </div>

            <!-- Info Box -->
            <!-- <div class="bg-blue-50 border border-blue-300 rounded-xl p-4 mb-8">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-blue-900">Understanding Piglet Survival Counts</p>
                        <p class="text-sm text-blue-700 mt-1">
                            The **Current Surviving Piglets** count is dynamic, reflecting only piglets from active farrowings (less than **{{ config('breeding.cycle.birth_to_weaning_days', 45) }} days old**). The **Total Surviving (All Time)** includes all historical records.
                        </p>
                    </div>
                </div>
            </div> -->

            <!-- Single Comprehensive Farrowing Table -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-200">
                    <h2 class="text-xl font-bold text-gray-900">All Farrowing Events</h2>
                    <!-- <p class="text-sm text-gray-600">List of every recorded farrowing, sortable by any column.</p> -->
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Sow Tag</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Sow Code</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Farrowing Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Boar</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Litter Age</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Born</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Live</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Still</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Deaths</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Surviving</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100" id="pigletTrackingTableBody">
                            @php
                                $totalFarrowingCount = 0;
                            @endphp
                            @forelse($sowsWithStats as $sowData)
                                @php
                                    $sow = $sowData['sow'];
                                @endphp
                                @foreach($sowData['farrowings'] as $farrowingData)
                                    @php
                                        $farrowing = $farrowingData['farrowing'];
                                        $deathCount = $farrowingData['deaths'];
                                        $surviving = $farrowingData['surviving'];
                                        $liveBirths = $farrowingData['live_births'];
                                        $stillbirths = $farrowingData['stillbirths'];
                                        $totalBorn = $farrowingData['total_born'];
                                        $totalFarrowingCount++;
                                    @endphp
                                    <tr class="hover:bg-gray-50 transition duration-150">
                                        <!-- Sow Identification -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">
                                            <a href="{{ route('pigs.show', $sow->id) }}" class="text-indigo-600 hover:text-indigo-800">
                                                {{ $sow->ear_tag_number }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $sow->code }}</td>

                                        <!-- Farrowing Details -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <div class="font-medium">{{ $farrowing->farrowing_date->format('M d, Y') }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                            @if($farrowing->boar)
                                                <a href="{{ route('pigs.show', $farrowing->boar->id) }}" class="text-indigo-600 hover:text-indigo-800">
                                                    {{ $farrowing->boar->ear_tag_number }}
                                                </a>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        @php
                                            $weaningThreshold = (int) config('breeding.cycle.birth_to_weaning_days', 45);
                                            $weanedAtDate = $farrowing->date_weaned ? \Illuminate\Support\Carbon::parse($farrowing->date_weaned) : null;
                                            if ($farrowing->farrowing_date) {
                                                if ($weanedAtDate) {
                                                    $litterAgeDays = (int) $farrowing->farrowing_date->diffInDays($weanedAtDate);
                                                } else {
                                                    $litterAgeDays = (int) $farrowing->farrowing_date->diffInDays(now());
                                                }
                                            } else {
                                                $litterAgeDays = null;
                                            }
                                            $daysRemaining = $litterAgeDays !== null ? $weaningThreshold - $litterAgeDays : null;
                                        @endphp
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            @if(!is_null($litterAgeDays))
                                                <div class="font-semibold {{ $litterAgeDays >= $weaningThreshold ? 'text-red-600' : 'text-gray-900' }}">
                                                    {{ $litterAgeDays }} <span class="text-xs font-normal text-gray-500">days</span>
                                                </div>
                                            <div class="text-xs text-gray-500">
                                                @php
                                                    $litterWeaned = $farrowing->date_weaned || $farrowing->weanings()->exists() || $farrowing->status === 'completed';
                                                @endphp
                                                @if($litterWeaned)
                                                    Weaned @if($farrowing->date_weaned) on {{ \Illuminate\Support\Carbon::parse($farrowing->date_weaned)->format('M d, Y') }} @endif
                                                @elseif($litterAgeDays >= $weaningThreshold)
                                                        Ready to wean
                                                    @else
                                                    {{ max(0, (int) $daysRemaining) }} days to go
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        
                                        <!-- Counts -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-blue-700 text-center">{{ $totalBorn }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-green-700 text-center">{{ $liveBirths }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-yellow-700 text-center">{{ $stillbirths }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-red-700 text-center">
                                            @if($deathCount > 0)
                                                {{ $deathCount }}
                                                <span class="block text-xs text-gray-500 mt-1">
                                                    ({{ $farrowing->pigletDeaths->first()->cause ?? 'Unknown' }})
                                                </span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-lg font-extrabold text-green-600 text-center">{{ $surviving }}</td>
                                        
                                        <!-- Type and Actions -->
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $farrowing->farrowing_type_badge }}">
                                                {{ ucfirst($farrowing->farrowing_type) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-right">
                                            <a href="{{ route('farrowing.show', $farrowing->id) }}" class="text-indigo-600 hover:text-indigo-900 font-semibold transition">
                                                Details
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <!-- This message is handled below, as we iterate inside the table body -->
                            @endforelse
                            
                            @if ($totalFarrowingCount === 0)
                                <tr>
                                    <td colspan="12" class="px-4 py-12 text-center text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No farrowing records found</h3>
                                        <p class="mt-1 text-sm text-gray-500">
                                            @if(request('search'))
                                                No sows or farrowings match your search criteria.
                                            @else
                                                Start by recording farrowing events for your sows.
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('pigletSearchInput');
        const tbody = document.getElementById('pigletTrackingTableBody');
        if (!input || !tbody) return;
        input.addEventListener('input', function() {
            const val = this.value.trim().toLowerCase();
            const rows = tbody.querySelectorAll('tr');
            let anyVisible = false;
            rows.forEach(row => {
                // Only match on Sow Tag (first TD, anchor text)
                const tagCell = row.querySelector('td:first-child a');
                const tag = tagCell ? tagCell.textContent.trim().toLowerCase() : '';
                const match = tag.includes(val);
                row.style.display = match || val === '' ? '' : 'none';
                if (match) anyVisible = true;
            });
            // Optionally: show a no-results row here if needed
        });
    });
</script>
