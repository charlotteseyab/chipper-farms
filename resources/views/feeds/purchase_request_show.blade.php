<x-app-layout>
    {{-- CORRECTED: x-data placed here to scope the button and the modal --}}
    <div x-data="{ rejectModal: false }" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 p-6 sm:p-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 border-b pb-4">
                
                <a href="{{ route('feed-purchase-requests.index') }}" 
                   class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 transition mb-3 sm:mb-0">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to All Requests
                </a>

                @if(Auth::user() && (Auth::user()->isAdmin() || Auth::user()->isSuperuser()))
                    
                    {{-- Logic to determine disabled state --}}
                    @php
                        $isPending = $request->status === 'pending';
                        $disabledAttributes = $isPending ? '' : 'disabled class="opacity-50 cursor-not-allowed"';
                        // Add disabled styling to the class for pending buttons to ensure it overrides active hover states
                        $approveClass = 'w-full md:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-sm text-white bg-green-600 ' . ($isPending ? 'hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition' : 'opacity-50 cursor-not-allowed');
                        $rejectClass = 'w-full md:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-sm text-white bg-red-600 ' . ($isPending ? 'hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition' : 'opacity-50 cursor-not-allowed');
                    @endphp

                    <div class="flex flex-col md:flex-row items-stretch md:items-center space-y-3 md:space-y-0 md:space-x-4">
                        
                        {{-- APPROVE BUTTON --}}
                        <form method="POST" action="{{ route('feed-purchase-requests.approve', $request->id) }}">
                            @csrf
                            <input type="hidden" name="decision_reason" value="">
                            <button type="submit" 
                                    {{-- Apply disabled attribute --}}
                                    {{ $isPending ? '' : 'disabled' }}
                                    class="{{ $approveClass }}">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Approve
                            </button>
                        </form>
                        
                        {{-- REJECT BUTTON (Opens Modal via Alpine.js) --}}
                        <button type="button" 
                                {{-- Only allow the click event if pending --}}
                                @click="{{ $isPending ? 'rejectModal = true' : '' }}" 
                                {{-- Apply disabled attribute --}}
                                {{ $isPending ? '' : 'disabled' }}
                                class="{{ $rejectClass }}">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            Reject
                        </button>
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
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Reason for rejecting</h3>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 text-sm text-gray-700 italic">
                        {{ $request->decision_reason }}
                    </div>
                </div>
            @endif
            
        </div>

        {{-- REJECT REASON MODAL (Only visible/functional if $isPending is true) --}}
        
        {{-- Modal Backdrop --}}
        <div x-show="rejectModal" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0"
             style="display: none;" 
             class="fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-75 transition-opacity" 
             aria-labelledby="modal-title" role="dialog" aria-modal="true">
        </div>

        {{-- Modal Panel --}}
        <div x-show="rejectModal" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             style="display: none;"
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4">
            
            <div class="bg-white rounded-lg px-4 pt-5 pb-4 shadow-xl transform transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6" 
                 @click.away="rejectModal = false">
                
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.394 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Reject Purchase Request
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Please provide a reason for rejecting this purchase request.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Rejection Form inside the modal --}}
                <form method="POST" action="{{ route('feed-purchase-requests.reject', $request->id) }}" class="mt-5">
                    @csrf
                    <div class="mb-4">
                        <label for="decision_reason" class="block text-sm font-medium text-gray-700">Rejection Reason</label>
                        <textarea name="decision_reason" id="decision_reason" rows="3" 
                                  placeholder="Explain why the request is being rejected..." 
                                  class="mt-1 block w-full border-gray-300 focus:border-red-500 focus:ring-red-500 rounded-md shadow-sm text-sm" 
                                  required></textarea>
                    </div>

                    <div class="mt-5 sm:mt-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition">
                            Confirm Rejection
                        </button>
                        <button type="button" 
                                @click="rejectModal = false" 
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm transition">
                            Cancel
                        </button>
                    </div>
                </form>

            </div>
        </div>
        
    </div>
    <x-copyright-minimal />
</x-app-layout>