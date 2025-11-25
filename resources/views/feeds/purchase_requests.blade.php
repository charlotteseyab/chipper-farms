<x-app-layout>
    <div class="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8">

        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-5">
            <div class="mb-3 sm:mb-0">
                <h1 class="text-2xl font-bold text-gray-900 leading-tight">
                    Procurement Requests 
                </h1>
                <p class="text-sm text-gray-500 mt-1">View and manage all purchase requests for the farm.</p>
            </div>
            
            <a href="{{ route('feed-purchase-requests.create') }}" 
               class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-lg text-white bg-indigo-600 hover:bg-indigo-700 transition duration-300 ease-in-out">
               <svg class="w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
               New Purchase Request
            </a>
        </div>

        <div class="bg-white overflow-hidden shadow-xl rounded-lg border border-gray-100">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item / Category</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cost (GHS)</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment Made</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created At</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Needed By</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Requested By</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($requests as $req)
                        <tr class="hover:bg-gray-50 transition duration-100">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $req->item_name ?? $req->feed_type ?? 'N/A' }}</div>
                                <div class="mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-indigo-700 bg-indigo-100">
                                        {{ ucfirst($req->category ?? 'feed') }}
                                    </span>
                                </div>
                            </td>
                            
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-700">
                                {{ number_format($req->quantity, 2) }} {{ $req->unit }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-base font-bold text-green-600">
                                ₵{{ number_format($req->cost_ghc ?? 0, 2) }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $statusClasses = match($req->status) {
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $statusClasses }}">
                                    {{ strtoupper($req->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                @if($req->status === 'approved')
                                    @if($req->is_paid)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                            Paid
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-200 text-gray-600">
                                            Unpaid
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-400">
                                        N/A
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                {{ $req->created_at->format('Y-m-d') }}
                            </td>
                            
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ $req->needed_by ? $req->needed_by->format('Y-m-d') : '-' }}
                            </td>
                            
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ $req->requestedBy->name ?? 'N/A' }}
                            </td>
                            
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('feed-purchase-requests.show', ['request' => $req->getKey()]) }}" 
                                       class="inline-flex items-center justify-center text-indigo-600 hover:text-indigo-900 px-2 py-1 rounded-full bg-indigo-50 hover:bg-indigo-100 transition text-xs font-semibold">
                                        View
                                    </a>
                                    <a href="{{ route('feed-purchase-requests.edit', $req) }}" 
                                       class="inline-flex items-center justify-center text-indigo-600 hover:text-white px-2 py-1 rounded-full border border-indigo-200 hover:bg-indigo-600 transition text-xs font-semibold">
                                        Edit
                                    </a>
                                    @if(auth()->user() && auth()->user()->isSuperuser())
                                        <form action="{{ route('feed-purchase-requests.destroy', $req) }}" method="POST" class="inline-flex" onsubmit="return confirm('Are you sure you want to delete this request?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center text-red-600 hover:text-white px-2 py-1 rounded-full border border-red-200 hover:bg-red-600 transition text-xs font-semibold" title="Delete">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center bg-gray-50">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2m-9 0V3h4v2m-4 0h4"></path></svg>
                                    <span class="text-gray-500 text-lg font-semibold">
                                        No Procurement Requests Yet
                                    </span>
                                    <p class="text-sm text-gray-500 mt-1">
                                        All purchase requests, including feed and other farm supplies, will appear here.
                                    </p>
                                    <a href="{{ route('feed-purchase-requests.create') }}" 
                                       class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        Create First Request
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Pagination (embedded, simple style) -->
            @if($requests->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 bg-white">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $requests->firstItem() }} to {{ $requests->lastItem() }} of {{ $requests->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$requests" />
                            </div>
                        </div>
                    </div>
                @endif
        </div>
        
    </div>
    <x-copyright-minimal />
</x-app-layout>
