<x-app-layout>
    <div class="max-w-6xl mx-auto py-8 px-4 bg-white rounded-xl shadow p-6 border">
        <div class="mb-6 flex items-center justify-between">
            <a href="{{ route('feed-purchase-requests.index') }}" class="text-blue-600 hover:underline">← Back to Requests</a>
            @if(Auth::user() && (Auth::user()->isAdmin() || Auth::user()->isSuperuser()))
                <div class="flex items-center space-x-3">
                    <form method="POST" action="{{ route('feed-purchase-requests.approve', $request->id) }}">
                        @csrf
                        <input type="hidden" name="decision_reason" value="">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('feed-purchase-requests.reject', $request->id) }}" class="flex items-center space-x-2">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Reject</button>
                        <input type="text" name="decision_reason" placeholder="Reason (required)" class="border rounded px-3 py-2 text-sm w-64" required>
                    </form>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow p-6 border border-gray-200">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Procurement Request</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-gray-500">Category</div>
                    <div class="font-semibold text-gray-900">{{ ucfirst($request->category ?? 'feed') }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Item</div>
                    <div class="font-semibold text-gray-900">{{ $request->item_name ?? $request->feed_type }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Quantity</div>
                    <div class="font-semibold text-gray-900">{{ number_format($request->quantity, 2) }} {{ $request->unit }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Cost (GHS)</div>
                    <div class="font-semibold text-gray-900">₵{{ number_format($request->cost_ghc, 2) }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Needed By</div>
                    <div class="font-semibold text-gray-900">{{ $request->needed_by ? $request->needed_by->format('Y-m-d') : '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Status</div>
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{
                            match($request->status) {
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800'
                            }
                        }}">
                            {{ ucfirst($request->status) }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">Requested By</div>
                    <div class="font-semibold text-gray-900">{{ $request->requestedBy->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Created At</div>
                    <div class="font-semibold text-gray-900">{{ $request->created_at->format('Y-m-d H:i') }}</div>
                </div>
                @if($request->decided_by)
                <div>
                    <div class="text-gray-500">Decided By</div>
                    <div class="font-semibold text-gray-900">{{ optional($request->decider)->name ?? $request->decided_by }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Decided At</div>
                    <div class="font-semibold text-gray-900">{{ optional($request->decided_at)->format('Y-m-d H:i') }}</div>
                </div>
                @endif
            </div>

            @if($request->decision_reason)
                <div class="mt-6">
                    <div class="text-gray-500 text-sm mb-1">Decision Reason</div>
                    <div class="p-3 bg-gray-50 rounded border text-sm text-gray-800">{{ $request->decision_reason }}</div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
