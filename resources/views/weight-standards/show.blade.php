<x-app-layout>  
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Weight Standard Details</h1>
            <p class="text-gray-600 mt-1">{{ $weightStandard->breed_display }} {{ $weightStandard->gender_display }} - Week {{ $weightStandard->week_number }}</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('weight-standards.edit', $weightStandard) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Standard
            </a>
            <a href="{{ route('weight-standards.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Standards
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Standard Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Breed</label>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                <span class="text-xs font-medium text-blue-800">{{ $weightStandard->breed }}</span>
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ $weightStandard->breed_display }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Gender</label>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-8 w-8 bg-{{ $weightStandard->gender === 'M' ? 'blue' : 'pink' }}-100 rounded-full flex items-center justify-center mr-3">
                                <!-- <svg class="h-4 w-4 text-{{ $weightStandard->gender === 'M' ? 'blue' : 'pink' }}-600" fill="currentColor" viewBox="0 0 20 20">
                                    @if($weightStandard->gender === 'M')
                                        <path fill-rule="evenodd" d="M14.243 5.757a6 6 0 10-.986 9.284 1 1 0 111.087 1.678A8 8 0 1118 4a1 1 0 01-1 1h-2a1 1 0 110-2h2a3 3 0 013 3v2a1 1 0 11-2 0V6.757z" clip-rule="evenodd"/>
                                    @else
                                        <path fill-rule="evenodd" d="M10 2a6 6 0 100 12 6 6 0 000-12zM4 8a6 6 0 1112 0A6 6 0 014 8z" clip-rule="evenodd"/>
                                    @endif
                                </svg> -->
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ $weightStandard->gender_display }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Stock Type</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            {{ $weightStandard->stock_type === 'breeding' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $weightStandard->stock_type_display }}
                        </span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Week Number</label>
                        <div class="text-2xl font-bold text-blue-600">{{ $weightStandard->week_number }}</div>
                        <div class="text-xs text-gray-500">weeks old</div>
                    </div>
                </div>

                <!-- Weight Information -->
                <div class="mt-8">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Weight Specifications</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="text-sm font-medium text-red-800">Minimum Weight</div>
                            <div class="text-2xl font-bold text-red-600">{{ number_format($weightStandard->expected_min_weight, 1) }}</div>
                            <div class="text-xs text-red-600">kg</div>
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="text-sm font-medium text-green-800">Optimal Weight</div>
                            <div class="text-2xl font-bold text-green-600">{{ number_format($weightStandard->optimal_weight, 1) }}</div>
                            <div class="text-xs text-green-600">kg (target)</div>
                        </div>
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                            <div class="text-sm font-medium text-orange-800">Maximum Weight</div>
                            <div class="text-2xl font-bold text-orange-600">{{ number_format($weightStandard->expected_max_weight, 1) }}</div>
                            <div class="text-xs text-orange-600">kg</div>
                        </div>
                    </div>
                </div>

                <!-- Weight Range Visualization -->
                <div class="mt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Weight Range Visualization</h3>
                    <div class="relative">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                            <span>{{ number_format($weightStandard->expected_min_weight, 1) }}kg</span>
                            <span>{{ number_format($weightStandard->optimal_weight, 1) }}kg</span>
                            <span>{{ number_format($weightStandard->expected_max_weight, 1) }}kg</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4 relative">
                            <div class="bg-gradient-to-r from-red-400 via-green-400 to-orange-400 h-4 rounded-full" style="width: 100%"></div>
                            @php
                                $range = $weightStandard->expected_max_weight - $weightStandard->expected_min_weight;
                                $optimalPosition = (($weightStandard->optimal_weight - $weightStandard->expected_min_weight) / $range) * 100;
                            @endphp
                            <div class="absolute top-0 h-4 w-1 bg-white border-2 border-green-600 rounded" style="left: {{ $optimalPosition }}%"></div>
                        </div>
                        <div class="text-center mt-2">
                            <span class="text-xs text-gray-500">Tolerance: ±{{ number_format(($weightStandard->expected_max_weight - $weightStandard->expected_min_weight) / 2, 1) }}kg</span>
                        </div>
                    </div>
                </div>

                @if($weightStandard->notes)
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Notes</label>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                            <p class="text-sm text-gray-700">{{ $weightStandard->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Status</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Current Status</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            {{ $weightStandard->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $weightStandard->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Created By</span>
                        <span class="text-sm font-medium text-gray-900">{{ $weightStandard->creator->name ?? 'Unknown' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Created</span>
                        <span class="text-sm text-gray-900">{{ $weightStandard->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Last Updated</span>
                        <span class="text-sm text-gray-900">{{ $weightStandard->updated_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="{{ route('weight-standards.edit', $weightStandard) }}" 
                       class="w-full flex items-center justify-center px-4 py-2 border border-blue-300 rounded-lg text-blue-700 hover:bg-blue-50 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Standard
                    </a>
                    
                    <a href="{{ route('weight-standards.create') }}?breed={{ $weightStandard->breed }}&gender={{ $weightStandard->gender }}&stock_type={{ $weightStandard->stock_type }}" 
                       class="w-full flex items-center justify-center px-4 py-2 border border-green-300 rounded-lg text-green-700 hover:bg-green-50 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Create Similar
                    </a>
                    
                    <form method="POST" action="{{ route('weight-standards.destroy', $weightStandard) }}" 
                          onsubmit="return confirm('Are you sure you want to delete this weight standard? This action cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full flex items-center justify-center px-4 py-2 border border-red-300 rounded-lg text-red-700 hover:bg-red-50 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Standard
                        </button>
                    </form>
                </div>
            </div>

            <!-- Related Standards -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Related Standards</h3>
                <div class="text-sm text-gray-500">
                    <p>View other standards for:</p>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('weight-standards.index') }}?breed={{ $weightStandard->breed }}&gender={{ $weightStandard->gender }}&stock_type={{ $weightStandard->stock_type }}" 
                           class="block text-blue-600 hover:text-blue-800">
                            Same breed, gender & type
                        </a>
                        <a href="{{ route('weight-standards.index') }}?breed={{ $weightStandard->breed }}" 
                           class="block text-blue-600 hover:text-blue-800">
                            Same breed (all genders)
                        </a>
                        <a href="{{ route('weight-standards.index') }}?week_from={{ max(1, $weightStandard->week_number - 2) }}&week_to={{ $weightStandard->week_number + 2 }}" 
                           class="block text-blue-600 hover:text-blue-800">
                            Similar age range
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</x-app-layout>
