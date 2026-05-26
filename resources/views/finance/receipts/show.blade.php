<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.receipts.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Receipts</a>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if($receipt->is_backfilled)
            <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                <strong>Backfilled receipt.</strong> Synthesised from a legacy pig sale that was already paid before Phase 2c was deployed. The cash side already hit GL via the original Phase 1c posting — this row exists for ledger continuity. No journal batch is linked.
            </div>
        @endif

        @if($receipt->isReversed())
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>Reversed.</strong>
                @if($receipt->reversed_by_journal_batch_id)
                    Cash reversal posted via journal batch
                    <a href="{{ route('finance.journals.show', $receipt->reversed_by_journal_batch_id) }}" class="font-mono underline">#{{ $receipt->reversed_by_journal_batch_id }}</a>.
                @endif
                @if($receipt->reversal_reason)<p class="mt-1 italic">{{ $receipt->reversal_reason }}</p>@endif
            </div>
        @endif

        {{-- Header card --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold">Receipt</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ $receipt->receipt_number }}</h1>
                    <div class="mt-2 flex flex-wrap gap-2 items-center text-xs">
                        @php $statusClasses = ['posted' => 'bg-emerald-100 text-emerald-800', 'reversed' => 'bg-slate-100 text-slate-700']; @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium {{ $statusClasses[$receipt->status] }}">{{ $receipt->status }}</span>
                        @if($receipt->journalBatch)
                            <span class="text-slate-500">→ <a href="{{ route('finance.journals.show', $receipt->journalBatch) }}" class="hover:underline font-mono">{{ $receipt->journalBatch->batch_number }}</a></span>
                        @endif
                        @if($receipt->is_backfilled)<span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-medium">backfilled</span>@endif
                    </div>
                </div>

                @if($receipt->canReverse() && auth()->user()?->hasPermission('reverse_journal'))
                    <div class="flex flex-col gap-2 min-w-[200px]">
                        <button type="button" onclick="confirmReverse()"
                                class="w-full px-4 py-2 bg-amber-100 hover:bg-amber-200 text-amber-800 text-sm font-medium rounded-xl">Reverse Receipt</button>
                        <p class="text-xs text-slate-400 text-center">Cascades to allocations</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Customer + amount --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Customer</h3>
                <p class="font-medium text-slate-800">{{ $receipt->counterparty?->name }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ $receipt->counterparty?->code }}</p>
                @if($receipt->counterparty?->category)
                    <p class="text-xs text-slate-500 mt-1">{{ $receipt->counterparty->category->name }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Amounts &amp; Method</h3>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Receipt Date</dt>
                    <dd class="text-slate-800">{{ $receipt->receipt_date?->format('Y-m-d') }}</dd>
                    <dt class="text-slate-500">Method</dt>
                    <dd class="text-slate-800">{{ $receipt->paymentMethod?->name ?? '—' }}</dd>
                    @if($receipt->external_reference)
                        <dt class="text-slate-500">Reference</dt>
                        <dd class="text-slate-800 font-mono text-xs">{{ $receipt->external_reference }}</dd>
                    @endif
                    <dt class="text-slate-500">Received</dt>
                    <dd class="font-semibold text-slate-800">{{ $receipt->currency_code }} {{ number_format((float) $receipt->amount, 2) }}</dd>
                    <dt class="text-slate-500">Allocated</dt>
                    <dd class="text-slate-700">{{ number_format((float) $receipt->allocated_amount, 2) }}</dd>
                    <dt class="text-slate-500">Unallocated</dt>
                    <dd class="font-semibold {{ (float) $receipt->unallocatedAmount() > 0.005 ? 'text-amber-700' : 'text-emerald-700' }}">
                        {{ number_format((float) $receipt->unallocatedAmount(), 2) }}
                    </dd>
                </dl>
            </div>
        </div>

        {{-- Allocations --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Allocations</h3>
                @if($receipt->isPosted() && (float) $receipt->unallocatedAmount() > 0.005 && auth()->user()?->hasPermission('create_receipt') && $openInvoices->isNotEmpty())
                    <button type="button" onclick="document.getElementById('allocate-form').classList.toggle('hidden')"
                            class="text-sm text-emerald-700 hover:underline font-medium">+ Allocate to invoice</button>
                @endif
            </div>

            @if($receipt->allocations->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-slate-500">No allocations yet — receipt sits as customer credit.</div>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Invoice</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">By</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($receipt->allocations as $alloc)
                            <tr>
                                <td class="px-4 py-3 text-slate-700">{{ $alloc->allocation_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('finance.invoices.show', $alloc->invoice_id) }}" class="text-emerald-700 hover:underline font-mono text-xs">{{ $alloc->invoice?->invoice_number }}</a>
                                    @if($alloc->invoice?->isFullyPaid())<span class="ml-1 text-xs text-emerald-600">paid</span>@endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $alloc->amount, 2) }}</td>
                                <td class="px-4 py-3">
                                    @if($alloc->isActive())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">active</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">reversed</span>
                                        @if($alloc->reversal_reason)<p class="text-xs text-slate-500 mt-0.5">{{ $alloc->reversal_reason }}</p>@endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $alloc->createdBy?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($alloc->isActive() && $receipt->isPosted() && auth()->user()?->hasPermission('create_receipt'))
                                        <form method="POST" action="{{ route('finance.receipts.allocations.reverse', $alloc) }}" class="inline" onsubmit="return confirmAllocReverse(this)">
                                            @csrf
                                            <input type="hidden" name="reason" value="">
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Reverse</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Inline allocate form --}}
            @if($receipt->isPosted() && (float) $receipt->unallocatedAmount() > 0.005 && auth()->user()?->hasPermission('create_receipt') && $openInvoices->isNotEmpty())
                <form id="allocate-form" method="POST" action="{{ route('finance.receipts.allocations.store', $receipt) }}" class="hidden border-t border-slate-100 p-5 bg-slate-50">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Open Invoice</label>
                            <select name="invoice_id" required class="w-full rounded-xl border-slate-200 text-sm">
                                @foreach($openInvoices as $inv)
                                    <option value="{{ $inv->id }}" data-balance="{{ $inv->balance_due }}">
                                        {{ $inv->invoice_number }} — {{ $inv->invoice_date->format('Y-m-d') }} — bal {{ $inv->currency_code }} {{ number_format((float) $inv->balance_due, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Amount</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $receipt->unallocatedAmount() }}" name="amount" required
                                   value="{{ $receipt->unallocatedAmount() }}" class="w-full rounded-xl border-slate-200 text-sm">
                        </div>
                    </div>
                    <button type="submit" class="mt-3 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Allocate</button>
                </form>
            @endif
        </div>

        {{-- Reverse form (hidden until button click) --}}
        @if($receipt->canReverse() && auth()->user()?->hasPermission('reverse_journal'))
            <form id="reverse-form" method="POST" action="{{ route('finance.receipts.reverse', $receipt) }}" class="hidden">
                @csrf
                <input type="hidden" name="reason" value="">
            </form>
        @endif

        {{-- Audit --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Audit</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-600">
                @if($receipt->createdBy)
                    <div><span class="text-slate-400">Created by:</span> {{ $receipt->createdBy->name }} <span class="text-slate-400">— {{ $receipt->created_at?->format('Y-m-d H:i') }}</span></div>
                @endif
                @if($receipt->reversedBy)
                    <div><span class="text-slate-400">Reversed by:</span> {{ $receipt->reversedBy->name }} <span class="text-slate-400">— {{ $receipt->reversed_at?->format('Y-m-d H:i') }}</span></div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function confirmReverse() {
            const reason = prompt('Reason for reversing this receipt? (cascades to all allocations)');
            if (! reason || reason.trim() === '') return;
            const form = document.getElementById('reverse-form');
            form.querySelector('input[name="reason"]').value = reason.trim();
            form.submit();
        }
        function confirmAllocReverse(form) {
            const reason = prompt('Reason for reversing this allocation?');
            if (! reason || reason.trim() === '') return false;
            form.querySelector('input[name="reason"]').value = reason.trim();
            return true;
        }
    </script>
</x-app-layout>
