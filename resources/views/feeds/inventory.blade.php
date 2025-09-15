<x-app-layout>
    <div class="max-w-7xl mx-auto py-8 px-4">
        <!-- <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Feed Inventory</h1>
            <p class="text-gray-500">Track current stock, daily usage, and running balances for all feed types.</p>
        </div>
        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded shadow">{{ session('success') }}</div>
        @endif -->
        <div class="flex flex-col md:flex-row md:space-x-6 mb-8">
            <a href="{{ route('feed-inventory.create') }}" class="inline-block mb-2 md:mb-0 bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700 font-semibold transition">Add Feed Stock</a>
            <a href="{{ route('feed-inventory.analytics') }}" class="inline-block bg-purple-600 text-white px-6 py-2 rounded-lg shadow hover:bg-purple-700 font-semibold transition">Analytics</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
            <div class="bg-white rounded-xl shadow-lg p-6 border">
                <h2 class="text-xl font-semibold mb-4 flex items-center">
                    <svg class="w-6 h-6 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 3v4M8 3v4m-5 4h18"/></svg>
                    Current Stock
                </h2>
                <table class="min-w-full bg-white border rounded shadow text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2">Feed Type</th>
                            <th class="px-4 py-2">Quantity</th>
                            <th class="px-4 py-2">Unit</th>
                            <th class="px-4 py-2">Last Updated</th>
                            <th class="px-4 py-2">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inventories as $inv)
                        <tr>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{
                                    match($inv->feed_type) {
                                        'starter' => 'bg-blue-100 text-blue-800',
                                        'grower' => 'bg-green-100 text-green-800',
                                        'finisher' => 'bg-orange-100 text-orange-800',
                                        'breeding' => 'bg-purple-100 text-purple-800',
                                        'lactating' => 'bg-pink-100 text-pink-800',
                                        'maintenance' => 'bg-gray-100 text-gray-800',
                                        'supplement' => 'bg-yellow-100 text-yellow-800',
                                        'medicated' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    }
                                }}">
                                    {{ ucfirst($inv->feed_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 font-semibold">{{ number_format($inv->quantity, 2) }}</td>
                            <td class="px-4 py-2">{{ $inv->unit }}</td>
                            <td class="px-4 py-2">{{ $inv->last_updated ? $inv->last_updated->format('Y-m-d') : '-' }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center space-x-2">
                                    <span class="font-semibold">{{ isset($balances[$inv->feed_type]) ? number_format($balances[$inv->feed_type], 2) : '-' }}</span>
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-400 h-2 rounded-full" style="width: {{ isset($balances[$inv->feed_type]) && $inv->quantity > 0 ? min(100, max(0, ($balances[$inv->feed_type] / $inv->quantity) * 100)) : 0 }}%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 border">
                <h2 class="text-xl font-semibold mb-4 flex items-center">
                    <svg class="w-6 h-6 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 3v4M8 3v4m-5 4h18"/></svg>
                    Daily Usage (Last 14 Days)
                </h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border rounded shadow text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2">Date</th>
                                <th class="px-4 py-2">Feed Type</th>
                                <th class="px-4 py-2">Total Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usage as $row)
                            <tr>
                                <td class="px-4 py-2">{{ $row->feed_date }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{
                                        match($row->feed_type) {
                                            'starter' => 'bg-blue-100 text-blue-800',
                                            'grower' => 'bg-green-100 text-green-800',
                                            'finisher' => 'bg-orange-100 text-orange-800',
                                            'breeding' => 'bg-purple-100 text-purple-800',
                                            'lactating' => 'bg-pink-100 text-pink-800',
                                            'maintenance' => 'bg-gray-100 text-gray-800',
                                            'supplement' => 'bg-yellow-100 text-yellow-800',
                                            'medicated' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        }
                                    }}">
                                        {{ ucfirst($row->feed_type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-semibold">{{ number_format($row->total_used, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
