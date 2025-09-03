<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 rounded-t-xl shadow-lg">
            <div class="px-8 py-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white">Sale Details</h1>
                    <p class="text-green-100 mt-1">Complete sale information and transaction details</p>
                </div>
                <a href="{{ route('sales.index') }}" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg transition-all duration-200 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Sales
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="bg-white shadow-xl rounded-b-xl border border-gray-200">
            <div class="p-8">
                <!-- Key Information Cards -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Pig Information Card -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-blue-900">Pig Information</h3>
                                <p class="text-blue-600 text-sm">Animal Details</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-blue-700 font-medium">Code:</span>
                                <span class="text-blue-900 font-semibold">{{ $sale->pig->code ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-blue-700 font-medium">Ear Tag:</span>
                                <span class="text-blue-900">{{ $sale->pig->ear_tag_number ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-blue-700 font-medium">Breed:</span>
                                <span class="text-blue-900">{{ $sale->pig->breed ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sale Information Card -->
                    <div class="bg-green-50 border border-green-200 rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-green-900">Sale Information</h3>
                                <p class="text-green-600 text-sm">Transaction Details</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-green-700 font-medium">Sale Date:</span>
                                <span class="text-green-900 font-semibold">{{ $sale->sale_date?->format('M d, Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-green-700 font-medium">Purpose:</span>
                                <span class="text-green-900">{{ ucfirst($sale->sale_purpose) }}</span>
                            </div>
                            @if($sale->sold_by)
                                <div class="flex justify-between">
                                    <span class="text-green-700 font-medium">Sold By:</span>
                                    <span class="text-green-900 font-semibold">{{ $sale->sold_by }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Payment Information Card -->
                    <div class="bg-purple-50 border border-purple-200 rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-purple-900">Payment Details</h3>
                                <p class="text-purple-600 text-sm">Financial Information</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-purple-700 font-medium">Total Price:</span>
                                <span class="text-purple-900 font-bold text-lg">{{ $sale->formatted_sale_price }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-purple-700 font-medium">Method:</span>
                                <span class="text-purple-900">{{ ucfirst($sale->payment_method ?? 'n/a') }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-purple-700 font-medium">Status:</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $sale->payment_status_badge }}">
                                    {{ ucfirst($sale->payment_status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Information Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Buyer Information -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Buyer Information</h3>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <span class="text-gray-600 text-sm">Name:</span>
                                <div class="text-gray-900 font-medium">{{ $sale->buyer_name }}</div>
                            </div>
                            @if($sale->buyer_contact)
                                <div>
                                    <span class="text-gray-600 text-sm">Contact:</span>
                                    <div class="text-gray-900">{{ $sale->buyer_contact }}</div>
                                </div>
                            @endif
                            @if($sale->buyer_address)
                                <div>
                                    <span class="text-gray-600 text-sm">Address:</span>
                                    <div class="text-gray-900">{{ $sale->buyer_address }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Weight & Transport Information -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Weight & Transport</h3>
                        </div>
                        <div class="space-y-3">
                            @if($sale->sale_weight)
                                <div>
                                    <span class="text-gray-600 text-sm">Sale Weight:</span>
                                    <div class="text-gray-900 font-medium">{{ number_format($sale->sale_weight, 2) }} kg</div>
                                    <div class="text-gray-600 text-sm">{{ $sale->formatted_price_per_kg }}</div>
                                </div>
                            @endif
                            @if($sale->transport_cost)
                                <div>
                                    <span class="text-gray-600 text-sm">Transport Cost:</span>
                                    <div class="text-gray-900 font-medium">₵{{ number_format($sale->transport_cost, 2) }}</div>
                                    @if($sale->transport_method)
                                        <div class="text-gray-600 text-sm">Method: {{ ucfirst($sale->transport_method) }}</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                @if($sale->notes)
                    <div class="mt-8">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-yellow-900">Additional Notes</h3>
                            </div>
                            <div class="text-yellow-800 whitespace-pre-line">{{ $sale->notes }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>


