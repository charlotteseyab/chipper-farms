<x-app-layout>

    <div class="min-h-screen py-2 mb-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header with Export Options -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Comprehensive System Reports</h1>
                            <p class="text-gray-600 mt-1">Complete overview of your farm management system</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="{{ route('settings.reports.export.pdf', request()->query()) }}" 
                               class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Export PDF
                            </a>
                            <!-- <a href="{{ route('settings.reports.export.csv', request()->query()) }}" 
                               class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Export CSV
                            </a> -->
                        </div>
                    </div>
                    <form method="GET" class="mt-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">From</label>
                                <input type="date" name="from" value="{{ request('from') }}" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">To</label>
                                <input type="date" name="to" value="{{ request('to') }}" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex items-end space-x-2">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Apply</button>
                                <a href="{{ route('settings.reports') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Executive Summary -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Executive Summary</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-blue-600">Total Pigs</p>
                                    <p class="text-2xl font-bold text-blue-900">{{ number_format($summary['total_pigs']) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-green-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-green-600">Active Pigs</p>
                                    <p class="text-2xl font-bold text-green-900">{{ number_format($summary['active_pigs']) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-purple-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-purple-600">Total Revenue</p>
                                    <p class="text-2xl font-bold text-purple-900">₵{{ number_format($summary['total_sales_revenue'], 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-orange-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-orange-600">Weights Recorded</p>
                                    <p class="text-2xl font-bold text-orange-900">{{ number_format($summary['total_weights_recorded']) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-indigo-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-indigo-600">Structures</p>
                                    <p class="text-2xl font-bold text-indigo-900">{{ number_format($summary['total_structures']) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Pig Distribution -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Pig Distribution</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">By Stock Type</h4>
                                <div class="space-y-2">
                                    @foreach($pigDistribution['by_stock_type'] as $type => $count)
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600 capitalize">{{ $type }}</span>
                                            <span class="text-sm font-semibold text-gray-900">{{ number_format($count) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">By Gender</h4>
                                <div class="space-y-2">
                                    @foreach($pigDistribution['by_gender'] as $gender => $count)
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600 capitalize">{{ $gender }}</span>
                                            <span class="text-sm font-semibold text-gray-900">{{ number_format($count) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">By Breed</h4>
                                <div class="space-y-2">
                                    @foreach($pigDistribution['by_breed'] as $breed => $count)
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600">{{ $breed }}</span>
                                            <span class="text-sm font-semibold text-gray-900">{{ number_format($count) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Overview -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Financial Overview</h3>
                        
                        <div class="space-y-4">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Total Revenue</span>
                                    <span class="text-lg font-bold text-gray-900">₵{{ number_format($financialOverview['total_revenue'], 2) }}</span>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Average Sale Price</span>
                                    <span class="text-lg font-bold text-gray-900">₵{{ number_format($financialOverview['average_price_per_kg'], 2) }}</span>
                                </div>
                            </div>

                            @if($financialOverview['sales_by_month']->count() > 0)
                                <div>
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Monthly Sales</h4>
                                    <div class="space-y-2 max-h-40 overflow-y-auto">
                                        @foreach($financialOverview['sales_by_month'] as $month)
                                            <div class="flex justify-between items-center text-sm">
                                                <span class="text-gray-600">{{ \Carbon\Carbon::createFromFormat('Y-m', $month->month)->format('M Y') }}</span>
                                                <span class="font-semibold text-gray-900">₵{{ number_format($month->revenue, 2) }} ({{ $month->count }} sales)</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Statistics Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Breeding Statistics -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Breeding Statistics</h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Matings</span>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($breedingStats['total_matings']) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Farrowings</span>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($breedingStats['total_farrowings']) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Weanings</span>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($breedingStats['total_weanings']) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Average Litter Size</span>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($breedingStats['average_litter_size'], 1) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Pregnancy Rate</span>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($breedingStats['pregnancy_rate'], 1) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Health & Mortality -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Health & Mortality</h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Deaths</span>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($healthStats['total_deaths']) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Mortality Rate</span>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($healthStats['mortality_rate'], 2) }}%</span>
                            </div>
                            
                            @if($healthStats['deaths_by_cause']->count() > 0)
                                <div class="mt-4">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Deaths by Cause</h4>
                                    <div class="space-y-1 max-h-32 overflow-y-auto">
                                        @foreach($healthStats['deaths_by_cause'] as $cause => $count)
                                            <div class="flex justify-between items-center text-xs">
                                                <span class="text-gray-600">{{ $cause }}</span>
                                                <span class="font-semibold text-gray-900">{{ $count }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Weight Analytics -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Weight Analytics</h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Average Weight</span>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($weightAnalytics['average_weight'], 1) }} kg</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Records</span>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($weightAnalytics['total_weight_records']) }}</span>
                            </div>
                            
                            @if($weightAnalytics['weights_by_month']->count() > 0)
                                <div class="mt-4">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Monthly Averages</h4>
                                    <div class="space-y-1 max-h-32 overflow-y-auto">
                                        @foreach($weightAnalytics['weights_by_month']->take(6) as $month)
                                            <div class="flex justify-between items-center text-xs">
                                                <span class="text-gray-600">{{ \Carbon\Carbon::createFromFormat('Y-m', $month->month)->format('M Y') }}</span>
                                                <span class="font-semibold text-gray-900">{{ number_format($month->avg_weight, 1) }} kg</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Recent Activity (Last 30 Days)</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-blue-900">{{ number_format($recentActivity['new_pigs']) }}</div>
                            <div class="text-sm text-blue-600">New Pigs</div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-green-900">{{ number_format($recentActivity['weights_recorded']) }}</div>
                            <div class="text-sm text-green-600">Weights Recorded</div>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-purple-900">{{ number_format($recentActivity['pigs_sold']) }}</div>
                            <div class="text-sm text-purple-600">Pigs Sold</div>
                        </div>
                        <div class="bg-red-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-red-900">{{ number_format($recentActivity['pigs_died']) }}</div>
                            <div class="text-sm text-red-600">Pigs Died</div>
                        </div>
                        <div class="bg-pink-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-pink-900">{{ number_format($recentActivity['matings_planned']) }}</div>
                            <div class="text-sm text-pink-600">Matings Planned</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Structure & Room Utilization -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Structure & Room Utilization</h3>
                    
                    @php $firstStructureId = optional($structureStats['structures_by_status']->first())->id; @endphp
                    <div x-data="{ selectedStructureId: {{ $firstStructureId ?? 'null' }} }" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Structures by Status</h4>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                @foreach($structureStats['structures_by_status'] as $structure)
                                    <button type="button" @click="selectedStructureId = {{ $structure->id }}"
                                        class="w-full flex justify-between items-center px-3 py-2 rounded-md text-left transition"
                                        :class="selectedStructureId === {{ $structure->id }} ? 'bg-blue-50' : 'hover:bg-gray-50'">
                                        <span class="text-sm text-gray-700" :class="selectedStructureId === {{ $structure->id }} ? 'font-semibold text-blue-900' : ''">{{ $structure->name }}</span>
                                        <span class="text-xs inline-flex items-center px-2.5 py-0.5 rounded-full font-medium 
                                            {{ match($structure->status) {
                                                'active' => 'bg-green-100 text-green-800',
                                                'inactive' => 'bg-gray-100 text-gray-800',
                                                'under_maintenance' => 'bg-yellow-100 text-yellow-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            } }}">
                                            {{ ucfirst(str_replace('_', ' ', $structure->status)) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Room Utilization</h4>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                @foreach($structureStats['room_utilization'] as $room)
                                    <div class="flex justify-between items-center" x-show="selectedStructureId === {{ (int)optional($room->structure)->id }}">
                                        <span class="text-sm text-gray-600">{{ $room->name }}</span>
                                        <span class="text-sm font-semibold text-gray-900">{{ $room->pigs_count }} pigs</span>
                                    </div>
                                @endforeach
                                @if($structureStats['room_utilization']->isEmpty())
                                    <div class="text-xs text-gray-500 text-center">No rooms found.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Structure Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Structure Details & Capacity</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Structure Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Rooms</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Available</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilization</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($structureStats['detailed_structures'] as $structure)
                                    @php
                                        $capacity = $structure->capacity ?? 'Unlimited';
                                        $currentRooms = $structure->rooms_count;
                                        $available = $structure->available_capacity;
                                        $utilization = $structure->capacity ? round(($currentRooms / $structure->capacity) * 100, 1) : 'N/A';
                                        
                                        // Status color
                                        $statusColor = match($structure->status) {
                                            'active' => 'bg-green-100 text-green-800',
                                            'inactive' => 'bg-gray-100 text-gray-800',
                                            'under_maintenance' => 'bg-yellow-100 text-yellow-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        
                                        // Utilization color
                                        $utilizationColor = 'text-gray-600';
                                        if ($utilization !== 'N/A') {
                                            if ($utilization >= 90) $utilizationColor = 'text-red-600 font-semibold';
                                            elseif ($utilization >= 75) $utilizationColor = 'text-yellow-600 font-semibold';
                                            elseif ($utilization >= 50) $utilizationColor = 'text-blue-600';
                                        }
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $structure->name }}</div>
                                            @if($structure->description)
                                                <div class="text-sm text-gray-500">{{ Str::limit($structure->description, 50) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                                {{ ucfirst(str_replace('_', ' ', $structure->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $capacity }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $currentRooms }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($available === -1)
                                                <span class="text-gray-500">Unlimited</span>
                                            @else
                                                {{ $available }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm {{ $utilizationColor }}">
                                            @if($utilization === 'N/A')
                                                <span class="text-gray-500">N/A</span>
                                            @else
                                                {{ $utilization }}%
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-copyright-minimal />
</x-app-layout>


