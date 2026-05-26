<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">

        <div class="mb-4">
            <a href="{{ route('finance.payments.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                <i class="fa-solid fa-arrow-left mr-1"></i> All Payments
            </a>
        </div>

        @if($payment->isReversed())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <strong>Reversed</strong> on {{ $payment->reversed_at?->format('Y-m-d H:i') }} by {{ $payment->reversedBy?->name }}.
                @if($payment->reversal_reason) <div class="mt-1 italic">"{{ $payment->reversal_reason }}"</div> @endif
            </div>
        @elseif($payment->isCancelled())
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <strong>Cancelled</strong> on {{ $payment->cancelled_at?->format('Y-m-d H:i') }} by {{ $payment->cancelledBy?->name }}.
                @if($payment->cancellation_reason) <div class="mt-1 italic">"{{ $payment->cancellation_reason }}"</div> @endif
            </div>
        @endif

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        <!-- Header card -->
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-slate-800 font-mono">{{ $payment->payment_number }}</h1>
                        @php $statusClasses = [
                            'draft'     => 'bg-slate-100 text-slate-700',
                            'posted'    => 'bg-emerald-100 text-emerald-800',
                            'cancelled' => 'bg-slate-100 text-slate-500',
                            'reversed'  => 'bg-rose-100 text-rose-800',
                        ]; @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $statusClasses[$payment->status] ?? '' }}">{{ strtoupper($payment->status) }}</span>
                        @if($payment->is_backfilled)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">backfilled</span>
                        @endif
                    </div>
                    <div class="mt-2 text-sm text-slate-600 space-y-0.5">
                        <div>Date: <span class="font-medium">{{ $payment->payment_date?->format('Y-m-d') }}</span></div>
                        @if($payment->paymentRun)
                            <div>Run: <a href="{{ route('finance.payment-runs.show', $payment->paymentRun) }}" class="font-mono text-emerald-700 hover:underline">{{ $payment->paymentRun->run_number }}</a></div>
                        @endif
                        @if($payment->journal_batch_id)
                            <div>Cash journal: <a href="{{ route('finance.journals.show', $payment->journal_batch_id) }}" class="font-mono text-emerald-700 hover:underline">#{{ $payment->journal_batch_id }}</a></div>
                        @endif
                        @if($payment->reversed_by_journal_batch_id)
                            <div>Reversal batch: <a href="{{ route('finance.journals.show', $payment->reversed_by_journal_batch_id) }}" class="font-mono text-rose-700 hover:underline">#{{ $payment->reversed_by_journal_batch_id }}</a></div>
                        @endif
                        @if($payment->external_reference)
                            <div>Reference: <span class="font-mono">{{ $payment->external_reference }}</span> @if($payment->reference_type)<span class="text-xs text-slate-500">({{ $payment->reference_type }})</span>@endif</div>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($payment->canReverse() && auth()->user()?->hasPermission('reverse_journal'))
                        <button type="button" onclick="reversePayment()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Reverse
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Supplier / method / source cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Supplier</p>
                <h3 class="mt-1 text-base font-semibold text-slate-800">{{ $payment->counterparty?->name }}</h3>
                <div class="mt-1 text-xs text-slate-500 font-mono">{{ $payment->counterparty?->code }}</div>
                @if($payment->counterparty?->supplierCategory)
                    <div class="mt-2 text-xs text-slate-500">Category: {{ $payment->counterparty->supplierCategory->name }}</div>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Method</p>
                <h3 class="mt-1 text-base font-semibold text-slate-800">{{ $payment->paymentMethod?->name }}</h3>
                <div class="mt-1 text-xs text-slate-500 font-mono">{{ $payment->paymentMethod?->code }}</div>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Source GL Account</p>
                @if($payment->sourceAccount)
                    <h3 class="mt-1 text-base font-semibold text-slate-800">{{ $payment->sourceAccount->code }} — {{ $payment->sourceAccount->name }}</h3>
                @else
                    <h3 class="mt-1 text-sm text-slate-500 italic">Resolved from method hint</h3>
                @endif
            </div>
        </div>

        <!-- Amounts grid -->
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <p class="text-xs text-slate-500 uppercase tracking-wide mb-4">Amounts ({{ $payment->currency_code }})</p>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                <div>
                    <p class="text-xs text-slate-500">Cash leaving bank</p>
                    <p class="text-lg font-bold text-slate-800">{{ number_format((float) $payment->amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">AP relieved (gross)</p>
                    <p class="text-lg font-bold text-slate-700">{{ number_format((float) $payment->allocated_amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Net allocated</p>
                    <p class="text-lg font-bold text-slate-700">{{ number_format((float) $payment->net_amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">In suspense</p>
                    <p class="text-lg font-bold {{ $payment->hasUnallocatedAmount() ? 'text-amber-700' : 'text-slate-400' }}">
                        {{ number_format((float) $payment->unallocatedAmount(), 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">WHT recognised</p>
                    <p class="text-lg font-bold text-slate-700">{{ number_format((float) $payment->withholding_amount, 2) }}</p>
                </div>
            </div>
            @if($payment->hasUnallocatedAmount())
                <p class="mt-3 text-xs text-amber-700 italic">
                    Cash sitting in Suspense Payment (2910) until allocated to specific vendor invoices.
                </p>
            @elseif(bccomp((string) $payment->withholding_amount, '0', 2) > 0)
                <p class="mt-3 text-xs text-slate-500 italic">
                    AP relieved by gross amount ({{ number_format((float) $payment->allocated_amount, 2) }}); cash drawn from suspense ({{ number_format((float) $payment->net_amount, 2) }}) matches bank statement; WHT ({{ number_format((float) $payment->withholding_amount, 2) }}) sits on 2250 for quarterly GRA remittance.
                </p>
            @endif
        </div>

        <!-- Allocations -->
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-slate-800">Allocations</h2>
                @if($payment->isPosted() && $payment->hasUnallocatedAmount() && auth()->user()?->hasPermission('create_payment'))
                    <form method="POST" action="{{ route('finance.payments.auto-allocate', $payment) }}">
                        @csrf
                        <button type="submit" onclick="return confirm('Auto-allocate FIFO across this supplier\'s open invoices?');" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white text-xs font-semibold rounded-lg">
                            <i class="fa-solid fa-shuffle mr-1"></i> Auto-allocate FIFO
                        </button>
                    </form>
                @endif
            </div>

            @if($payment->allAllocations->isEmpty())
                <p class="text-sm text-slate-400 italic">No allocations yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr class="border-b border-slate-100">
                                <th class="px-2 py-2 text-left">VI #</th>
                                <th class="px-2 py-2 text-left">Date</th>
                                <th class="px-2 py-2 text-right">Amount</th>
                                <th class="px-2 py-2 text-right">WHT</th>
                                <th class="px-2 py-2 text-left">Journal</th>
                                <th class="px-2 py-2 text-left">Status</th>
                                <th class="px-2 py-2 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($payment->allAllocations as $alloc)
                                <tr class="{{ $alloc->isReversed() ? 'opacity-60' : '' }}">
                                    <td class="px-2 py-2 font-mono text-xs">
                                        <a href="{{ route('finance.vendor-invoices.show', $alloc->vendorInvoice) }}" class="hover:underline">{{ $alloc->vendorInvoice?->vendor_invoice_number }}</a>
                                    </td>
                                    <td class="px-2 py-2 text-xs">{{ $alloc->allocation_date?->format('Y-m-d') }}</td>
                                    <td class="px-2 py-2 text-right">{{ number_format((float) $alloc->amount, 2) }}</td>
                                    <td class="px-2 py-2 text-right">
                                        @if(bccomp((string) $alloc->withholding_amount, '0', 2) > 0)
                                            {{ number_format((float) $alloc->withholding_amount, 2) }}
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-xs">
                                        @if($alloc->withholding_journal_batch_id)
                                            <a href="{{ route('finance.journals.show', $alloc->withholding_journal_batch_id) }}" class="font-mono text-emerald-700 hover:underline">#{{ $alloc->withholding_journal_batch_id }}</a>
                                            @if($alloc->reversed_by_journal_batch_id)
                                                <span class="text-rose-600">→</span>
                                                <a href="{{ route('finance.journals.show', $alloc->reversed_by_journal_batch_id) }}" class="font-mono text-rose-700 hover:underline">#{{ $alloc->reversed_by_journal_batch_id }}</a>
                                            @endif
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $alloc->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ $alloc->status }}</span>
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        @if($alloc->isActive() && $payment->isPosted() && auth()->user()?->hasPermission('create_payment'))
                                            <button type="button" onclick="reverseAllocation({{ $alloc->id }})" class="text-xs text-rose-600 hover:underline">reverse</button>
                                            <form id="reverse-alloc-{{ $alloc->id }}" method="POST" action="{{ route('finance.payments.reverse-allocation', $alloc) }}" class="hidden">
                                                @csrf
                                                <input type="hidden" name="reason" value="">
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Allocation panel for unallocated funds -->
        @if($payment->isPosted() && $payment->hasUnallocatedAmount() && auth()->user()?->hasPermission('create_payment') && $openInvoices->isNotEmpty())
            <div class="rounded-2xl border border-amber-100 bg-amber-50/40 shadow-sm p-6 mb-4">
                <h2 class="text-base font-semibold text-slate-800 mb-3">Allocate to open invoices</h2>
                <p class="text-xs text-slate-500 mb-3">Unallocated: <strong>{{ number_format((float) $payment->unallocatedAmount(), 2) }}</strong> {{ $payment->currency_code }}</p>
                <div class="space-y-2">
                    @foreach($openInvoices as $vi)
                        <form method="POST" action="{{ route('finance.payments.allocate', $payment) }}" class="flex items-center gap-2 bg-white border border-slate-100 rounded-xl p-3">
                            @csrf
                            <input type="hidden" name="vendor_invoice_id" value="{{ $vi->id }}">
                            <div class="flex-1">
                                <a href="{{ route('finance.vendor-invoices.show', $vi) }}" class="font-mono text-xs text-emerald-700 hover:underline">{{ $vi->vendor_invoice_number }}</a>
                                <span class="text-xs text-slate-500 ml-2">{{ $vi->invoice_date?->format('Y-m-d') }}</span>
                                <span class="text-xs text-slate-500 ml-2">balance due: <strong>{{ number_format((float) $vi->balance_due, 2) }}</strong></span>
                            </div>
                            <input type="number" step="0.01" min="0.01" name="amount"
                                   value="{{ min((float) $payment->unallocatedAmount(), (float) $vi->balance_due) }}"
                                   max="{{ min((float) $payment->unallocatedAmount(), (float) $vi->balance_due) }}"
                                   class="w-32 rounded-lg border-slate-200 text-sm">
                            <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg">Allocate</button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Reconciliation -->
        @if($payment->isPosted())
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
                <h2 class="text-base font-semibold text-slate-800 mb-3">Bank Reconciliation</h2>
                @if($payment->isReconciled())
                    <div class="text-sm text-slate-700">
                        <p>
                            <i class="fa-solid fa-check-circle text-emerald-600 mr-1"></i>
                            Reconciled on <strong>{{ $payment->reconciled_at?->format('Y-m-d H:i') }}</strong>
                            by {{ $payment->reconciledBy?->name }}.
                        </p>
                        @if($payment->reconciliation_reference)
                            <p class="mt-1 text-xs">Reference: <span class="font-mono">{{ $payment->reconciliation_reference }}</span></p>
                        @endif
                        @if(auth()->user()?->hasPermission('create_payment'))
                            <form method="POST" action="{{ route('finance.payments.unreconcile', $payment) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="text-xs text-rose-600 hover:underline">unreconcile</button>
                            </form>
                        @endif
                    </div>
                @elseif(auth()->user()?->hasPermission('create_payment'))
                    <form method="POST" action="{{ route('finance.payments.reconcile', $payment) }}" class="flex items-end gap-2">
                        @csrf
                        <div class="flex-1">
                            <label class="block text-xs text-slate-600 mb-1">Bank statement reference (optional)</label>
                            <input type="text" name="reconciliation_reference" maxlength="100" class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. STMT-2026-12-15-LINE-42">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Mark Reconciled</button>
                    </form>
                @else
                    <p class="text-sm text-slate-500 italic">Not reconciled.</p>
                @endif
            </div>
        @endif

        <!-- Audit -->
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
            <p class="text-xs text-slate-500 uppercase tracking-wide mb-2">Audit</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs text-slate-600">
                <div>Created: <strong>{{ $payment->createdBy?->name ?? 'system' }}</strong> on {{ $payment->created_at?->format('Y-m-d H:i') }}</div>
                @if($payment->reversedBy)
                    <div>Reversed: <strong>{{ $payment->reversedBy->name }}</strong> on {{ $payment->reversed_at?->format('Y-m-d H:i') }}</div>
                @endif
                @if($payment->cancelledBy)
                    <div>Cancelled: <strong>{{ $payment->cancelledBy->name }}</strong> on {{ $payment->cancelled_at?->format('Y-m-d H:i') }}</div>
                @endif
            </div>
        </div>

        <!-- Hidden reverse form -->
        <form id="reverse-payment-form" method="POST" action="{{ route('finance.payments.reverse', $payment) }}" class="hidden">
            @csrf
            <input type="hidden" name="reason" value="">
        </form>
    </div>

    <script>
    function reversePayment() {
        const reason = prompt('Reverse payment {{ $payment->payment_number }}? Provide a reason. This reverses the cash journal AND all active allocation WHT journals.');
        if (! reason || reason.trim() === '') return;
        const f = document.getElementById('reverse-payment-form');
        f.querySelector('input[name="reason"]').value = reason;
        f.submit();
    }
    function reverseAllocation(id) {
        const reason = prompt('Reverse this allocation? Provide a reason. The associated WHT slice journal (if any) will also be reversed.');
        if (! reason || reason.trim() === '') return;
        const f = document.getElementById('reverse-alloc-' + id);
        f.querySelector('input[name="reason"]').value = reason;
        f.submit();
    }
    </script>
</x-app-layout>
