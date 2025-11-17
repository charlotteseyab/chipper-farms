<x-app-layout>
    
    <!-- FULL-WIDTH Header Section: Subtle, Clean Bar -->
    <!-- Uses a light gray background with dark text for reliable contrast -->
    <div class="bg-gray-100 border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-800">Death Record Details</h1>
                <p class="text-gray-500 mt-1 font-medium">Detailed information on the animal's demise and final handling.</p>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex items-center space-x-3">
                @if(!$death->pig->sales()->exists())
                    <a href="{{ route('pigs.sale-form', $death->pig->id) }}" 
                       class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 text-sm font-semibold rounded-lg transition duration-200 shadow-md flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                        </svg>
                        Record Sale
                    </a>
                @else
                    <!-- Updated badge style for light background -->
                    <span class="bg-yellow-100 text-yellow-800 px-5 py-2.5 text-sm font-semibold rounded-lg flex items-center shadow-md">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Already Sold
                    </span>
                @endif
                <!-- Primary "Back" button style for light background -->
                <a href="{{ route('deaths.index') }}" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-5 py-2.5 text-sm font-medium rounded-lg transition duration-200 flex items-center shadow-md">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Death List
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content Container (below the fixed header) -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <!-- Key Information Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- Pig Information Card (Neutral/Blue Accent) -->
            <div class="bg-white rounded-xl p-6 shadow-xl border-t-4 border-blue-500 transition duration-300 hover:shadow-2xl">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Animal Details</h3>
                        <p class="text-gray-500 text-sm">Pig Information</p>
                    </div>
                </div>
                <div class="space-y-3 pt-2">
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="text-gray-600 font-medium">Code:</span>
                        <span class="text-gray-900 font-extrabold">{{ $death->pig->code ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="text-gray-600 font-medium">Ear Tag:</span>
                        <span class="text-gray-900">{{ $death->pig->ear_tag_number ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="text-gray-600 font-medium">Breed:</span>
                        <span class="text-gray-900">{{ $death->pig->breed ?? 'N/A' }}</span>
                    </div>
                    @if($death->age_at_death)
                        <div class="flex justify-between">
                            <span class="text-gray-600 font-medium">Age at Death:</span>
                            <span class="text-gray-900 font-semibold">{{ $death->age_at_death_display }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Death Information Card (Red/Warning Accent) -->
            <div class="bg-white rounded-xl p-6 shadow-xl border-t-4 border-red-500 transition duration-300 hover:shadow-2xl">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Incident Details</h3>
                        <p class="text-gray-500 text-sm">Death Information</p>
                    </div>
                </div>
                <div class="space-y-3 pt-2">
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="text-gray-600 font-medium">Death Date:</span>
                        <span class="text-red-600 font-extrabold">{{ $death->death_date?->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="text-gray-600 font-medium">Cause:</span>
                        <span class="text-red-700 font-semibold">{{ $death->death_cause_display }}</span>
                    </div>
                    @if($death->final_weight)
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600 font-medium">Final Weight:</span>
                            <span class="text-gray-900">{{ number_format($death->final_weight, 2) }} kg</span>
                        </div>
                    @endif
                    @if($death->death_location)
                        <div class="flex justify-between">
                            <span class="text-gray-600 font-medium">Location:</span>
                            <span class="text-gray-900">{{ $death->death_location }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Disposal Information Card (Gray/Neutral Accent) -->
            <div class="bg-white rounded-xl p-6 shadow-xl border-t-4 border-gray-500 transition duration-300 hover:shadow-2xl">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Final Handling</h3>
                        <p class="text-gray-500 text-sm">Disposal Information</p>
                    </div>
                </div>
                <div class="space-y-3 pt-2">
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <span class="text-gray-600 font-medium">Method:</span>
                        <span class="text-gray-900 font-semibold">{{ $death->disposal_method_display }}</span>
                    </div>
                    @if($death->disposal_date)
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600 font-medium">Disposal Date:</span>
                            <span class="text-gray-900">{{ $death->disposal_date->format('M d, Y') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-600 font-medium">Recorded By:</span>
                        <span class="text-gray-900 font-semibold">{{ $death->recordedBy->name ?? 'System' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            @if($death->veterinary_notes)
                <div class="bg-green-50 border border-green-300 rounded-xl p-6 shadow-md">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 bg-green-200 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-green-900">Veterinary Notes</h3>
                    </div>
                    <div class="text-green-800 whitespace-pre-line leading-relaxed">{{ $death->veterinary_notes }}</div>
                </div>
            @endif

            @if($death->death_notes)
                <div class="bg-yellow-50 border border-yellow-300 rounded-xl p-6 shadow-md">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 bg-yellow-200 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-yellow-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-yellow-900">Additional Notes</h3>
                    </div>
                    <div class="text-yellow-800 whitespace-pre-line leading-relaxed">{{ $death->death_notes }}</div>
                </div>
            @endif
        </div>
    </div>
    <x-copyright-minimal />
</x-app-layout>