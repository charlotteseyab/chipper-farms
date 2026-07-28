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
                            <div class="text-sm font-medium text-gray-900">{{ $sale->buyer_name ?: 'N/A' }}</div>
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
                            <div class="text-sm font-medium text-gray-900">GHS {{ $sale->formatted_sale_price }}</div>
                            @if($sale->price_per_kg)
                                <div class="text-sm text-gray-500">GHS {{ number_format($sale->price_per_kg, 2) }}/kg</div>
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
