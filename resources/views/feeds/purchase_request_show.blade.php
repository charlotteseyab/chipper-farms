<x-app-layout>
    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 p-6 sm:p-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 border-b pb-4">
                
                <a href="{{ route('feed-purchase-requests.index') }}" 
                   class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 transition mb-3 sm:mb-0">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to All Requests
                </a>

                @if(Auth::user() && (Auth::user()->isAdmin() || Auth::user()->isSuperuser()))
                    <div class="flex flex-col md:flex-row items-stretch md:items-center space-y-3 md:space-y-0 md:space-x-4">
                        
                        <form method="POST" action="{{ route('feed-purchase-requests.approve', $request->id) }}">
                            @csrf
                            <input type="hidden" name="decision_reason" value="">
                            <button type="submit" class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Approve
                            </button>
                        </form>
                        
                        <form method="POST" action="{{ route('feed-purchase-requests.reject', $request->id) }}" class="flex flex-col md:flex-row items-stretch md:items-center space-y-2 md:space-y-0 md:space-x-2">
                            @csrf
                            <input type="text" name="decision_reason" placeholder="Reason (required)" class="w-full md:w-64 border-gray-300 focus:border-red-500 focus:ring-red-500 rounded-lg px-3 py-2 text-sm shadow-sm" required>
                            <button type="submit" class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Reject
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <h2 class="text-xl font-bold text-gray-900 mb-5">Request Details #{{ $request->id }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6 text-sm">
                
                <div>
                    <div class="text-gray-500 mb-1">Item Requested</div>
                    <div class="font-semibold text-gray-900 text-base">{{ $request->item_name ?? $request->feed_type }}</div>
                </div>
                <div>
                    <div class="text-gray-500 mb-1">Category</div>
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium text-indigo-700 bg-indigo-100">
                        {{ ucfirst($request->category ?? 'feed') }}
                    </span>
                </div>

                <div class="border-t pt-4">
                    <div class="text-gray-500 mb-1">Quantity</div>
                    <div class="font-bold text-lg text-gray-900">{{ number_format($request->quantity, 2) }} {{ $request->unit }}</div>
                </div>
                <div class="border-t pt-4">
                    <div class="text-gray-500 mb-1">Estimated Cost (GHS)</div>
                    <div class="font-bold text-lg text-green-600">₵{{ number_format($request->cost_ghc, 2) }}</div>
                </div>

                <div class="border-t pt-4">
                    <div class="text-gray-500 mb-1">Current Status</div>
                    <div>
                        @php
                            $statusClasses = match($request->status) {
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800'
                            };
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold {{ $statusClasses }}">
                            {{ strtoupper($request->status) }}
                        </span>
                    </div>
                </div>
                <div class="border-t pt-4">
                    <div class="text-gray-500 mb-1">Needed By Date</div>
                    <div class="font-semibold text-gray-900 text-base">{{ $request->needed_by ? $request->needed_by->format('Y-m-d') : 'N/A' }}</div>
                </div>

                <div class="border-t pt-4">
                    <div class="text-gray-500 mb-1">Requested By</div>
                    <div class="font-semibold text-gray-900">{{ $request->requestedBy->name ?? 'N/A' }}</div>
                </div>
                <div class="border-t pt-4">
                    <div class="text-gray-500 mb-1">Requested On</div>
                    <div class="font-semibold text-gray-900">{{ $request->created_at->format('Y-m-d H:i') }}</div>
                </div>

                @if($request->decided_by)
                <div class="border-t pt-4">
                    <div class="text-gray-500 mb-1">Decided By</div>
                    <div class="font-semibold text-gray-900">{{ optional($request->decider)->name ?? $request->decided_by }}</div>
                </div>
                <div class="border-t pt-4">
                    <div class="text-gray-500 mb-1">Decided At</div>
                    <div class="font-semibold text-gray-900">{{ optional($request->decided_at)->format('Y-m-d H:i') }}</div>
                </div>
                @endif
            </div>

            @if($request->decision_reason)
                <div class="mt-8 border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Decision Reason</h3>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 text-sm text-gray-700 italic">
                        {{ $request->decision_reason }}
                    </div>
                </div>
            @endif
            
        </div>
    </div>
</x-app-layout>