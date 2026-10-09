<x-app-layout>
    @php
        $activeReturn = $sale->salesReturns
            ->where('status', '!=', \App\Models\SalesReturn::STATUS_FAILED)
            ->sortByDesc('id')
            ->first();
        $latestReturn = $sale->salesReturns->sortByDesc('id')->first();
    @endphp
    
    <!-- FULL-WIDTH Header Section: Subtle, Clean Bar -->
    <!-- Switched to a light gray background with a subtle shadow and dark text for reliable visibility -->
    <div class="bg-gray-100 border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-800">Sale Transaction Details</h1>
                <p class="text-gray-500 mt-1 font-medium">Complete record of the sale and financial transaction.</p>
            </div>
            
            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('sales.audit', $sale->id) }}" class="flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-indigo-700">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Change History
                </a>
                <a href="{{ route('sales.index') }}" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-5 py-2.5 text-sm font-semibold rounded-lg transition duration-200 flex items-center shadow-md">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Sales List
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content Container (below the fixed header) -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <!-- Main Content Grid -->
        <div>
            <!-- Key Information Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Pig Information Card (Neutral/Blue Accent) -->
                <div class="bg-white rounded-xl p-6 shadow-xl border-t-4 border-blue-500 transition duration-300 hover:shadow-2xl">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Animal Details</h3>
                            <p class="text-gray-500 text-sm">Pig Information</p>
                        </div>
                    </div>
                    <div class="space-y-3 pt-2">
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600 font-medium">Code:</span>
                            <span class="text-gray-900 font-extrabold">{{ $sale->pig->code ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600 font-medium">Ear Tag:</span>
                            <span class="text-gray-900">{{ $sale->pig?->identityAt($sale->sale_date) ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 font-medium">Breed:</span>
                            <span class="text-gray-900">{{ $sale->pig->breed ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Sale Information Card (Green Accent) -->
                <div class="bg-white rounded-xl p-6 shadow-xl border-t-4 border-green-600 transition duration-300 hover:shadow-2xl">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Transaction Details</h3>
                            <p class="text-gray-500 text-sm">Sale Information</p>
                        </div>
                    </div>
                    <div class="space-y-3 pt-2">
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600 font-medium">Sale Date:</span>
                            <span class="text-green-700 font-extrabold">{{ $sale->sale_date?->format('M d, Y') }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600 font-medium">Purpose:</span>
                            <span class="text-gray-900 font-semibold">{{ ucfirst($sale->sale_purpose) }}</span>
                        </div>
                        @if($sale->display_sold_by)
                            <div class="flex justify-between">
                                <span class="text-gray-600 font-medium">Sold By:</span>
                                <span class="text-gray-900 font-semibold">{{ $sale->display_sold_by }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Payment Information Card (Purple Accent) -->
                <div class="bg-white rounded-xl p-6 shadow-xl border-t-4 border-purple-600 transition duration-300 hover:shadow-2xl">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Payment Summary</h3>
                            <p class="text-gray-500 text-sm">Financial Information</p>
                        </div>
                    </div>
                    <div class="space-y-3 pt-2">
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600 font-medium">Total Price:</span>
                            <span class="text-purple-700 font-extrabold text-xl">{{ $sale->formatted_sale_price }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600 font-medium">Method:</span>
                            <span class="text-gray-900">{{ ucfirst($sale->payment_method ?? 'n/a') }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 font-medium">Status:</span>
                            <!-- Applying a better looking badge for status -->
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold leading-4 {{ $sale->payment_status === 'paid' ? 'bg-green-100 text-green-800' : ($sale->payment_status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ ucfirst($sale->payment_status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative z-10 mb-8 overflow-visible rounded-2xl border border-slate-200 bg-white shadow-lg">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-200 bg-slate-50 px-6 py-5 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Transaction Controls</h2>
                        <p class="mt-1 text-sm text-slate-500">Update collection status or initiate a full reversal of this sale.</p>
                    </div>
                    @if($latestReturn)
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide {{ $latestReturn->status === 'posted' ? 'bg-emerald-100 text-emerald-800' : ($latestReturn->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                            Reversal {{ $latestReturn->status }}
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-2">
                    @can('updatePayment', $sale)
                        <form id="payment-status-form" method="POST" action="{{ route('sales.update-payment-status', $sale->id) }}" data-original-status="{{ $sale->payment_status }}" class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-5">
                            @csrf
                            <h3 class="font-black text-indigo-950">Update Payment Status</h3>
                            <p class="mt-1 text-sm text-indigo-700">Record the latest collection state without changing the sale amount.</p>
                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="payment_status" class="mb-1 block text-sm font-bold text-slate-700">Payment Status</label>
                                    <select id="payment_status" name="payment_status" required class="w-full rounded-lg border-slate-300">
                                        @foreach(['pending' => 'Pending', 'partial' => 'Partially Paid', 'paid' => 'Paid'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('payment_status', $sale->payment_status) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="payment_notes" class="mb-1 block text-sm font-bold text-slate-700">Update Note <span id="payment-note-required" class="hidden text-red-600">*</span></label>
                                    <input id="payment_notes" name="notes" value="{{ old('notes') }}" maxlength="1000" class="w-full rounded-lg border-slate-300" placeholder="Optional payment note">
                                    <p id="payment-note-help" class="mt-1 hidden text-xs font-medium text-red-700">Explain why this paid sale is being corrected.</p>
                                </div>
                            </div>
                            <button type="submit" @disabled($activeReturn) class="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300">Save Payment Status</button>
                        </form>
                    @endcan

                    @can('reverse', $sale)
                        <div class="rounded-xl border border-red-100 bg-red-50/60 p-5">
                            <h3 class="font-black text-red-950">Abort Transaction</h3>
                            <p class="mt-1 text-sm leading-6 text-red-700">Creates a full sales return, reverses financial postings, and restores the pig to stock when processing succeeds.</p>
                            <button type="button" id="open-abort-sale-modal" @disabled($activeReturn) class="mt-4 rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                                {{ $activeReturn ? 'Reversal Already Initiated' : 'Abort Sale Transaction' }}
                            </button>
                        </div>
                    @endcan
                </div>
            </div>

            <!-- Detailed Information Section -->
            <h2 class="text-2xl font-bold text-gray-700 mb-4 border-b pb-2">Contact & Logistics</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Buyer Information -->
                <div class="bg-white rounded-xl p-6 shadow-md border border-gray-100">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Buyer Information</h3>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <span class="text-gray-500 text-sm block">Name:</span>
                            <div class="text-gray-900 font-medium">{{ $sale->buyer_name ?: 'N/A' }}</div>
                        </div>
                        @if($sale->buyer_contact)
                            <div>
                                <span class="text-gray-500 text-sm block">Contact:</span>
                                <div class="text-gray-900">{{ $sale->buyer_contact }}</div>
                            </div>
                        @endif
                        @if($sale->buyer_address)
                            <div>
                                <span class="text-gray-500 text-sm block">Address:</span>
                                <div class="text-gray-900 whitespace-pre-line">{{ $sale->buyer_address }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Weight & Transport Information -->
                <div class="bg-white rounded-xl p-6 shadow-md border border-gray-100">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Weight & Transport</h3>
                    </div>
                    <div class="space-y-3">
                        @if($sale->sale_weight)
                            <div>
                                <span class="text-gray-500 text-sm block">Sale Weight:</span>
                                <div class="text-gray-900 font-medium">{{ number_format($sale->sale_weight, 2) }} kg</div>
                                <div class="text-gray-500 text-sm">{{ $sale->formatted_price_per_kg }}</div>
                            </div>
                        @endif
                        @if($sale->transport_cost)
                            <div>
                                <span class="text-gray-500 text-sm block">Transport Cost:</span>
                                <div class="text-gray-900 font-medium">₵{{ number_format($sale->transport_cost, 2) }}</div>
                                @if($sale->transport_method)
                                    <div class="text-gray-500 text-sm">Method: {{ ucfirst($sale->transport_method) }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            @if($sale->notes)
                <div class="mt-8">
                    <h2 class="text-2xl font-bold text-gray-700 mb-4 border-b pb-2">Additional Notes</h2>
                    <div class="bg-yellow-50 border border-yellow-300 rounded-xl p-6 shadow-md">
                        <div class="text-yellow-800 whitespace-pre-line leading-relaxed">{{ $sale->notes }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @can('reverse', $sale)
        @if(!$activeReturn)
            <div id="abort-sale-modal" class="fixed inset-0 z-[100] hidden" role="dialog" aria-modal="true" aria-labelledby="abort-sale-title">
                <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" data-close-abort-sale></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div class="bg-gradient-to-r from-red-700 to-rose-600 px-6 py-5 text-white">
                            <h2 id="abort-sale-title" class="text-2xl font-black">Abort Sale Transaction</h2>
                            <p class="mt-1 text-sm text-red-100">Sale #{{ $sale->id }} · {{ $sale->pig?->code ?? $sale->pig?->ear_tag_number }}</p>
                        </div>
                        <form method="POST" action="{{ route('sales.abort', $sale->id) }}" class="p-6">
                            @csrf
                            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800">
                                This is a compensating transaction, not a deletion. It creates permanent reversal records and may generate reversing journals and a credit note.
                            </div>
                            <div class="mt-5">
                                <label for="abort_reason" class="mb-1 block text-sm font-bold text-slate-700">Reason for aborting <span class="text-red-600">*</span></label>
                                <textarea id="abort_reason" name="reason" required minlength="10" maxlength="500" rows="4" class="w-full rounded-lg border-slate-300" placeholder="Explain why this sale must be reversed...">{{ old('reason') }}</textarea>
                            </div>
                            <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                                <input type="checkbox" name="confirm_abort" value="1" required class="mt-1 rounded border-slate-300 text-red-600 focus:ring-red-500">
                                <span class="text-sm text-slate-700">I understand this restores the pig to active stock and initiates financial reversal processing.</span>
                            </label>
                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" data-close-abort-sale class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-red-600 px-5 py-2 text-sm font-bold text-white hover:bg-red-700">Confirm Abort</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const modal = document.getElementById('abort-sale-modal');
                    const opener = document.getElementById('open-abort-sale-modal');
                    if (!modal || !opener) return;
                    const open = () => { modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); setTimeout(() => document.getElementById('abort_reason')?.focus(), 50); };
                    const close = () => { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); opener.focus(); };
                    opener.addEventListener('click', open);
                    modal.querySelectorAll('[data-close-abort-sale]').forEach(element => element.addEventListener('click', close));
                    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
                    @if($errors->has('reason') || $errors->has('confirm_abort') || $errors->has('abort')) open(); @endif
                });
            </script>
        @endif
    @endcan
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('payment-status-form');
            if (!form) return;
            const status = document.getElementById('payment_status');
            const note = document.getElementById('payment_notes');
            const marker = document.getElementById('payment-note-required');
            const help = document.getElementById('payment-note-help');
            const syncCorrectionRequirement = () => {
                const required = form.dataset.originalStatus === 'paid' && status.value !== 'paid';
                note.required = required;
                marker.classList.toggle('hidden', !required);
                help.classList.toggle('hidden', !required);
                note.placeholder = required ? 'Required correction reason' : 'Optional payment note';
            };
            status.addEventListener('change', syncCorrectionRequirement);
            syncCorrectionRequirement();
        });
    </script>
</x-app-layout>
