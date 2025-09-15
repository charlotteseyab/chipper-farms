<x-app-layout>
    <div class="max-w-7xl mx-auto py-8 px-4">
        <!-- <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Feed Purchase Requests</h1>
            <p class="text-gray-500">View and manage all feed purchase requests for the farm.</p>
        </div>
        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded shadow">{{ session('success') }}</div>
        @endif -->
        <div class="mb-6">
            <a href="{{ route('feed-purchase-requests.create') }}" class="bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700 font-semibold transition">New Purchase Request</a>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6 border">
            <table class="min-w-full bg-white border rounded shadow text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2">Feed Type</th>
                        <th class="px-4 py-2">Quantity</th>
                        <th class="px-4 py-2">Unit</th>
                        <th class="px-4 py-2">Cost (GHS)</th>
                        <th class="px-4 py-2">Needed By</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Requested By</th>
                        <th class="px-4 py-2">Created At</th>
                        <th class="px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                    <tr>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ ucfirst($req->feed_type_id) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 font-semibold">{{ number_format($req->quantity, 2) }}</td>
                        <td class="px-4 py-2">{{ $req->unit }}</td>
                        <td class="px-4 py-2 font-semibold text-green-700">₵{{ number_format($req->cost_ghc, 2) }}</td>
                        <td class="px-4 py-2">{{ $req->needed_by ? $req->needed_by->format('Y-m-d') : '-' }}</td>
                        <td class="px-4 py-2">
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
                        <td class="px-4 py-2">{{ $req->requestedBy->name ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $req->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('feed-purchase-requests.show', $req->id) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $requests->links() }}</div>
        </div>
    </div>
</x-app-layout>
