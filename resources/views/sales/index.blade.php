<x-app-layout>
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Sales Management</h1>
                        <p class="text-gray-600 mt-1">Track and manage all pig sales</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('sales') }}" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back
                        </a> -->
                        <a href="{{ route('sales.export.csv', request()->query()) }}" 
                           class="inline-flex items-center px-4 py-2 border border-green-300 rounded-lg text-green-700 hover:bg-green-50 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Download CSV
                        </a>
                        <a href="{{ route('sales.export.pdf', request()->query()) }}" 
                           class="inline-flex items-center px-4 py-2 border border-red-300 rounded-lg text-red-700 hover:bg-red-50 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Download PDF
                        </a>
                        <a href="{{ route('standard-stocks.index') }}" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                            Record New Sale
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Sales</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_sales'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Value</p>
                            <p class="text-2xl font-semibold text-gray-900">₵{{ number_format($stats['total_value'] ?? 0, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending Payments</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['pending_payments'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Average Price</p>
                            <p class="text-2xl font-semibold text-gray-900">₵{{ number_format($stats['average_price'] ?? 0, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Filter Sales</h2>
                </div>
                <form method="GET" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Buyer Name</label>
                            <input type="text" name="buyer_name" value="{{ request('buyer_name') }}" 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sale Purpose</label>
                            <select name="sale_purpose" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Purposes</option>
                                <option value="market" {{ request('sale_purpose') == 'market' ? 'selected' : '' }}>Market</option>
                                <option value="breeding" {{ request('sale_purpose') == 'breeding' ? 'selected' : '' }}>Breeding</option>
                                <option value="slaughter" {{ request('sale_purpose') == 'slaughter' ? 'selected' : '' }}>Slaughter</option>
                                <option value="export" {{ request('sale_purpose') == 'export' ? 'selected' : '' }}>Export</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                            <select name="payment_status" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Status</option>
                                <option value="pending" {{ request('payment_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Partial</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Sales Table -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Sales Records</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sale Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buyer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($sales ?? [] as $sale)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ $sale->pig->code ?? 'N/A' }}</div>
                                                <div class="text-sm text-gray-500">{{ $sale->pig->ear_tag_number ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $sale->sale_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $sale->buyer_name }}</div>
                                        @if($sale->buyer_contact)
                                            <div class="text-sm text-gray-500">{{ $sale->buyer_contact }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($sale->sale_weight)
                                            <div class="text-sm font-medium text-gray-900">{{ number_format($sale->sale_weight, 1) }} kg</div>
                                            <div class="text-sm text-gray-500">{{ ucfirst($sale->weight_type ?? 'live') }} weight</div>
                                        @else
                                            <div class="text-sm text-gray-500">Not recorded</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">₵{{ $sale->formatted_sale_price }}</div>
                                        @if($sale->price_per_kg)
                                            <div class="text-sm text-gray-500">₵{{ number_format($sale->price_per_kg, 2) }}/kg</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $sale->sale_purpose_badge }}">
                                            {{ $sale->sale_purpose_icon }} {{ ucfirst($sale->sale_purpose) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $sale->payment_status_badge }}">
                                            {{ ucfirst($sale->payment_status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600">
                                        <a href="{{ route('sales.show', $sale->id) }}" class="hover:text-blue-800">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                            </svg>
                                            <h3 class="text-lg font-medium text-gray-900 mb-2">No sales found</h3>
                                            <p class="text-gray-500 mb-4">
                                                @if(request()->hasAny(['buyer_name', 'sale_purpose', 'payment_status', 'date_from']))
                                                    No sales match your current filter criteria.
                                                @else
                                                    There are no sales records in the system yet.
                                                @endif
                                            </p>
                                            <a href="{{ route('standard-stocks.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700">
                                                Record First Sale
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if(isset($sales) && $sales->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $sales->links() }}
                    </div>
                @endif
            </div>
        </main>
    </div>
</x-app-layout>
