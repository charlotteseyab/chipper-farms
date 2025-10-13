<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('pig-detail') }}"
                           class="text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">{{ $pig->ear_tag_number }}</h1>
                            <p class="text-gray-500">{{ ucfirst($pig->stock_type) }} Stock • {{ $pig->breed }} • {{ ucfirst($pig->gender === 'M' ? 'Male' : 'Female') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        @if(!$pig->deaths()->exists())
                            <a href="{{ route('pigs.death-form', $pig->id) }}" class="inline-flex items-center px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition"
                               onclick="return confirm('Record death for {{ $pig->ear_tag_number }}?');">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m2 8H7a2 2 0 01-2-2V7a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Record Death
                            </a>
                        @else
                            <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 text-sm font-medium rounded-md">Deceased</span>
                        @endif
                        @if($pig->stock_type === 'standard' && in_array($breedingReadiness['age_status'], ['minimum_met', 'optimal']) && $breedingReadiness['factors']['weight']['status'] !== 'too_light')
                            <form action="{{ route('pigs.promote-to-breeding', $pig->id) }}" method="POST" onsubmit="return confirm('Promote {{ $pig->ear_tag_number }} to breeding stock?');">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                    </svg>
                                    Promote to Breeding
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('breeding-stocks.edit', $pig->id) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                        </a>
                        <form action="{{ route('breeding-stocks.destroy', $pig->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete {{ $pig->ear_tag_number }}? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Basic Info Card -->
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Age</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ floor($pig->current_age_in_days / 30) }}m</p>
                            <p class="text-xs text-gray-500">{{ $pig->current_age_in_days }} days</p>
                        </div>
                    </div>
                </div>

                <!-- Weight Card -->
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Weight</p>
                            @if($pig->latestWeight)
                                <p class="text-2xl font-semibold text-gray-900">{{ $pig->latestWeight->formatted_weight }}</p>
                                <p class="text-xs text-gray-500">{{ $pig->latestWeight->days_ago }} days ago</p>
                            @else
                                <p class="text-2xl font-semibold text-gray-400">No data</p>
                                <p class="text-xs text-gray-500">Never weighed</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Breeding Status Card -->
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Breeding Ready</p>
                            <p class="text-lg font-semibold {{ $breedingReadiness['ready'] ? 'text-green-600' : 'text-red-600' }}">
                                {{ $breedingReadiness['ready'] ? 'Yes' : 'No' }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $breedingReadiness['score'] }}% score</p>
                        </div>
                    </div>
                </div>

                <!-- Matings Card -->
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-100 rounded-lg">
                            <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Matings</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_matings'] }}</p>
                            <p class="text-xs text-gray-500">{{ $stats['successful_matings'] }} successful</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column - Basic Information -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Basic Information -->
                    <div class="bg-white rounded-lg shadow border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Basic Information</h2>
                        </div>
                        <div class="p-6">
                            <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-900">Ear Tag Number</dt>
                                    <dd class="mt-1 text-sm text-gray-700 font-mono">{{ $pig->ear_tag_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-900">Pig Code</dt>
                                    <dd class="mt-1 text-sm text-gray-700 font-mono">{{ $pig->code }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-900">Breed</dt>
                                    <dd class="mt-1 text-sm text-gray-700">{{ $pig->breed }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-900">Gender</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $pig->gender === 'M' ? 'Male' : 'Female' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-900">Date of Birth</dt>
                                    <dd class="mt-1 text-sm text-gray-700">{{ $pig->date_of_birth ? $pig->date_of_birth->format('M d, Y') : 'Not set' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-900">Stock Type</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pig->stock_type === 'breeding' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ ucfirst($pig->stock_type) }} Stock
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-900">Location</dt>
                                    <dd class="mt-1 text-sm text-gray-700">
                                        @if($pig->room)
                                            <span class="font-medium">{{ $pig->room->structure->name }}</span>
                                            <span class="text-gray-400">/</span>
                                            <span>{{ $pig->room->name }}</span>
                                        @elseif($pig->pen)
                                            <span>{{ $pig->pen->name }}</span>
                                        @else
                                            <span class="text-gray-400">Not assigned</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Breeding Readiness Details -->
                    <div class="bg-white rounded-lg shadow border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Breeding Readiness Assessment</h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <!-- Overall Status -->
                                <div class="flex items-center justify-between p-4 {{ $breedingReadiness['ready'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }} rounded-lg">
                                    <div>
                                        <h3 class="font-medium {{ $breedingReadiness['ready'] ? 'text-green-800' : 'text-red-800' }}">
                                            Overall Status: {{ $breedingReadiness['ready'] ? 'Ready for Breeding' : 'Not Ready for Breeding' }}
                                        </h3>
                                        <p class="text-sm {{ $breedingReadiness['ready'] ? 'text-green-600' : 'text-red-600' }}">
                                            Readiness Score: {{ $breedingReadiness['score'] }}%
                                        </p>
                                    </div>
                                    <div class="text-2xl">
                                        {{ $breedingReadiness['ready'] ? '✅' : '❌' }}
                                    </div>
                                </div>

                                <!-- Age Factor -->
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-medium text-gray-900">Age Requirements</h4>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $pig->breeding_readiness_badge }}">
                                            @if($breedingReadiness['age_status'] === 'optimal')
                                                ✓ Optimal
                                            @elseif($breedingReadiness['age_status'] === 'minimum_met')
                                                ⚠ Ready
                                            @elseif($breedingReadiness['age_status'] === 'too_young')
                                                ⏳ Too Young
                                            @else
                                                🛑 Too Old
                                            @endif
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3">{{ $breedingReadiness['factors']['age']['message'] }}</p>
                                    <div class="grid grid-cols-3 gap-4 text-xs">
                                        <div>
                                            <span class="text-gray-500">Current:</span>
                                            <span class="font-medium">{{ $breedingReadiness['age_in_days'] }} days</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Minimum:</span>
                                            <span class="font-medium">{{ $breedingReadiness['minimum_age'] }} days</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Optimal:</span>
                                            <span class="font-medium">{{ $breedingReadiness['optimal_age'] }} days</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Weight Factor -->
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-medium text-gray-900">Weight Requirements</h4>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                            {{ $breedingReadiness['factors']['weight']['status'] === 'optimal' ? 'bg-green-100 text-green-800' : 
                                               ($breedingReadiness['factors']['weight']['status'] === 'minimum_met' ? 'bg-yellow-100 text-yellow-800' : 
                                               ($breedingReadiness['factors']['weight']['status'] === 'too_light' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                            @if($breedingReadiness['factors']['weight']['status'] === 'optimal')
                                                ✓ Optimal
                                            @elseif($breedingReadiness['factors']['weight']['status'] === 'minimum_met')
                                                ⚠ Adequate
                                            @elseif($breedingReadiness['factors']['weight']['status'] === 'too_light')
                                                ✗ Too Light
                                            @else
                                                ? No Data
                                            @endif
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3">{{ $breedingReadiness['factors']['weight']['message'] }}</p>
                                    @if($breedingReadiness['factors']['weight']['current_weight'])
                                        <div class="grid grid-cols-3 gap-4 text-xs">
                                            <div>
                                                <span class="text-gray-500">Current:</span>
                                                <span class="font-medium">{{ $breedingReadiness['factors']['weight']['current_weight'] }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Minimum:</span>
                                                <span class="font-medium">{{ $breedingReadiness['factors']['weight']['minimum_required'] }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Optimal:</span>
                                                <span class="font-medium">{{ $breedingReadiness['factors']['weight']['optimal_weight'] }}</span>
                                            </div>
                                        </div>
                                        @if($breedingReadiness['factors']['weight']['last_weighed'])
                                            <p class="text-xs text-gray-500 mt-2">Last weighed: {{ $breedingReadiness['factors']['weight']['last_weighed'] }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Additional Information -->
                <div class="space-y-8">
                    <!-- Parents -->
                    @if($pig->father || $pig->mother)
                        <div class="bg-white rounded-lg shadow border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-900">Parents</h2>
                            </div>
                            <div class="p-6 space-y-4">
                                @if($pig->father)
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Father (Sire)</p>
                                            <p class="text-sm text-gray-600">{{ $pig->father->ear_tag_number }} - {{ $pig->father->code }}</p>
                                        </div>
                                        <a href="{{ route('pigs.show', $pig->father->id) }}" class="text-blue-600 hover:text-blue-800 text-sm">View</a>
                                    </div>
                                @endif
                                @if($pig->mother)
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">Mother (Dam)</p>
                                            <p class="text-sm text-gray-600">{{ $pig->mother->ear_tag_number }} - {{ $pig->mother->code }}</p>
                                        </div>
                                        <a href="{{ route('pigs.show', $pig->mother->id) }}" class="text-blue-600 hover:text-blue-800 text-sm">View</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Quick Stats -->
                    <div class="bg-white rounded-lg shadow border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Quick Statistics</h2>
                        </div>
                        <div class="p-6">
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Total Matings</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['total_matings'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Successful Matings</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['successful_matings'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Offspring</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['total_offspring'] }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Weight Records</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $stats['weight_records'] }}</dd>
                                </div>
                                @if($stats['days_since_last_weight'])
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Last Weighed</dt>
                                        <dd class="text-sm font-medium text-gray-900">{{ $stats['days_since_last_weight'] }} days ago</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <!-- Availability Status -->
                    <div class="bg-white rounded-lg shadow border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Availability Status</h2>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center space-x-3">
                                <span class="text-2xl">{{ $availabilityStatus['icon'] }}</span>
                                <div>
                                    <p class="font-medium text-gray-900">{{ ucfirst($availabilityStatus['status']) }}</p>
                                    <p class="text-sm text-gray-600">{{ $availabilityStatus['reason'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


                    <!-- Weight History -->
                    @if($pig->weights->count() > 0)
                        <div id="weight" class="bg-white rounded-lg shadow border border-gray-200 mt-8">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-900">Recent Weight History</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Body Condition</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($pig->weights as $weight)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $weight->weigh_date ? $weight->weigh_date->format('M d, Y') : 'Not set' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $weight->formatted_weight }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ">
                                                        {{ $weight->purpose_icon }} {{ ucfirst(str_replace('_', ' ', $weight->purpose ?? 'routine')) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    @if($weight->health_status)
                                                        @php
                                                            $healthConfig = [
                                                                'excellent' => ['color' => 'text-black','icon' => '🟢'],
                                                                'good' => ['color' => 'text-black', 'icon' => '🔵'],
                                                                'fair' => ['color' => 'text-black','icon' => '🟡'],
                                                                'poor' => ['color' => 'text-black','icon' => '🔴']
                                                            ];
                                                            $config = $healthConfig[$weight->health_status] ?? $healthConfig['good'];
                                                        @endphp
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $config['color'] }}">
                                                            {{ $config['icon'] }} {{ ucfirst($weight->health_status) }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    @if($weight->body_condition_score)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                            {{ $weight->body_condition_score <= 2 ? 'bg-red-100 text-red-800' : 
                                                            ($weight->body_condition_score == 3 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                                            {{ $weight->body_condition_score }}/5
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $weight->age_at_weighing }} days
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    {{ $weight->notes ?? '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

            <!-- Additional Sections -->
            @if($pig->weights->count() > 0 || $pig->maleMatings->count() > 0 || $pig->femaleMatings->count() > 0 || $offspring->count() > 0)
                <div class="mt-8 space-y-8">
                    @if(!empty($analytics) && !empty($analytics['growth_periods']))
                        <div class="bg-white rounded-lg shadow border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-900">Growth Periods Analysis</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight Range</th>
                                            <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phase</th> -->
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Gain</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Gain</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($analytics['growth_periods'] as $period)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $period['from_date']->format('M d') }} - {{ $period['to_date']->format('M d, Y') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $period['days'] }} days
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ number_format($period['from_weight'], 1) }} → {{ number_format($period['to_weight'], 1) }} kg
                                                </td>
                                                <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    @php
                                                        $endWeight = $period['to_weight'];
                                                        if ($endWeight < 25) {
                                                            $phase = 'Starter';
                                                            $badge = 'bg-blue-100 text-blue-800';
                                                        } elseif ($endWeight < 60) {
                                                            $phase = 'Grower';
                                                            $badge = 'bg-yellow-100 text-yellow-800';
                                                        } else {
                                                            $phase = 'Finisher';
                                                            $badge = 'bg-green-100 text-green-800';
                                                        }
                                                    @endphp
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ $phase }}</span>
                                                </td> -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="{{ $period['weight_gain'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ $period['weight_gain'] >= 0 ? '+' : '' }}{{ number_format($period['weight_gain'], 1) }} kg
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ number_format($period['daily_gain'], 3) }} kg/day
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($period['daily_gain'] >= 0.5)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Excellent</span>
                                                    @elseif($period['daily_gain'] >= 0.3)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Good</span>
                                                    @elseif($period['daily_gain'] >= 0.1)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Average</span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Poor</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Mating History -->
                    @if($pig->maleMatings->count() > 0 || $pig->femaleMatings->count() > 0)
                        <div class="bg-white rounded-lg shadow border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-900">Mating History</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($pig->maleMatings as $mating)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $mating->mating_date ? $mating->mating_date->format('M d, Y') : 'Not set' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <a href="{{ route('pigs.show', $mating->female->id) }}" class="text-blue-600 hover:text-blue-800">
                                                        {{ $mating->female->ear_tag_number }}
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Sire</td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                        {{ $mating->status === 'completed' ? 'bg-green-100 text-green-800' :
                                                           ($mating->status === 'planned' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                                                        {{ ucfirst($mating->status) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $mating->expected_farrowing_date ? $mating->expected_farrowing_date->format('M d, Y') : '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        @foreach($pig->femaleMatings as $mating)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $mating->mating_date ? $mating->mating_date->format('M d, Y') : 'Not set' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <a href="{{ route('pigs.show', $mating->male->id) }}" class="text-blue-600 hover:text-blue-800">
                                                        {{ $mating->male->ear_tag_number }}
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Dam</td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                        {{ $mating->status === 'completed' ? 'bg-green-100 text-green-800' :
                                                           ($mating->status === 'planned' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                                                        {{ ucfirst($mating->status) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $mating->expected_farrowing_date ? $mating->expected_farrowing_date->format('M d, Y') : '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Offspring -->
                    @if($offspring->count() > 0)
                        <div class="bg-white rounded-lg shadow border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-900">Offspring ({{ $offspring->count() }})</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ear Tag</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birth Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($offspring as $child)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $child->ear_tag_number }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $child->ear_tag_number }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $child->gender === 'M' ? 'Male' : 'Female' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $child->date_of_birth ? $child->date_of_birth->format('M d, Y') : 'Not set' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $child->current_age_in_days }} days
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                        {{ $child->stock_type === 'breeding' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                                        {{ ucfirst($child->stock_type) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600">
                                                    <a href="{{ route('pigs.show', $child->id) }}" class="hover:text-blue-800">View</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Siblings -->
                    @if($siblings->count() > 0)
                        <div class="bg-white rounded-lg shadow border border-gray-200">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-900">Siblings ({{ $siblings->count() }})</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ear Tag</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($siblings as $sibling)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        {{ $sibling->ear_tag_number }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $sibling->ear_tag_number }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $sibling->gender === 'M' ? 'Male' : 'Female' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $sibling->current_age_in_days }} days
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                        {{ $sibling->stock_type === 'breeding' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                                        {{ ucfirst($sibling->stock_type) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600">
                                                    <a href="{{ route('pigs.show', $sibling->id) }}" class="hover:text-blue-800">View</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </main>
    </div>
    <x-copyright-minimal />
</x-app-layout>
