<x-app-layout>
    <div class="max-w-7xl mx-auto py-2 px-4">
        <!-- <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Procurement Requests</h1>
            <p class="text-gray-500">View and manage all purchase requests for the farm.</p>
        </div>
        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded shadow">{{ session('success') }}</div>
        @endif -->
        <div class="mb-6">
            <a href="{{ route('feed-purchase-requests.create') }}" class="bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700 font-semibold transition">New Purchase Request</a>
        </div>
        <div>
            <table class="min-w-full bg-white border rounded shadow text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Category</th>
                        <th class="px-4 py-2 text-left">Item</th>
                        <th class="px-4 py-2 text-left">Quantity</th>
                        <th class="px-4 py-2 text-left">Unit</th>
                        <th class="px-4 py-2 text-left">Cost (GHS)</th>
                        <th class="px-4 py-2 text-left">Needed By</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Requested By</th>
                        <th class="px-4 py-2 text-left">Created At</th>
                        <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                    <tr>
                        <td class="px-4 py-2 text-left">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-gray-800 bg-gray-100">{{ ucfirst($req->category ?? 'feed') }}</span>
                        </td>
                        <td class="px-4 py-2 text-left">
                            {{ $req->item_name ?? $req->feed_type ?? 'N/A' }}
                        </td>
                        <td class="px-4 py-2 font-semibold text-left">{{ number_format($req->quantity, 2) }}</td>
                        <td class="px-4 py-2 text-left">{{ $req->unit }}</td>
                        <td class="px-4 py-2 font-semibold text-green-700 text-left">₵{{ number_format($req->cost_ghc ?? 0, 2) }}</td>
                        <td class="px-4 py-2 text-left">{{ $req->needed_by ? $req->needed_by->format('Y-m-d') : '-' }}</td>
                        <td class="px-4 py-2 text-left">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{
                                match($req->status) {
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-800'
                                }
                            }}">
                                {{ ucfirst($req->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-left">{{ $req->requestedBy->name ?? 'N/A' }}</td>
                        <td class="px-4 py-2 text-left">{{ $req->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2 text-left">
                            <a href="{{ route('feed-purchase-requests.show', ['request' => $req->getKey()]) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $requests->links() }}</div>
        </div>
    </div>
</x-app-layout>
