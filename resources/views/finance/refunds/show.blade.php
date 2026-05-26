<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.refunds.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Refunds</a>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        @if($refund->isReversed())
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>Reversed.</strong>
                @if($refund->reversal_reason)<p class="mt-1 italic">{{ $refund->reversal_reason }}</p>@endif
            </div>
        @endif

        {{-- Header card --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold">Refund</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ $refund->refund_number }}</h1>
                    <div class="mt-2 flex flex-wrap gap-2 items-center text-xs">
                        @php $statusClasses = ['draft' => 'bg-amber-100 text-amber-800', 'posted' => 'bg-emerald-100 text-emerald-800', 'reversed' => 'bg-slate-100 text-slate-700']; @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium {{ $statusClasses[$refund->status] }}">{{ $refund->status }}</span>
                        @if($refund->journalBatch)
                            <span class="text-slate-500">→ <a href="{{ route('finance.journals.show', $refund->journalBatch) }}" class="hover:underline font-mono">{{ $refund->journalBatch->batch_number }}</a></span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col gap-2 min-w-[200px]">
                    @if($refund->canPost() && auth()->user()?->hasPermission('approve_refund'))
                        <form method="POST" action="{{ route('finance.refunds.post', $refund) }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Approve & Post</button>
                        </form>
                    @endif

                    @if($refund->canCancel() && auth()->user()?->hasPermission('create_refund'))
                        <form method="POST" action="{{ route('finance.refunds.cancel', $refund) }}" onsubmit="return confirm('Cancel this draft refund? This cannot be undone.')">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Cancel Draft</button>
                        </form>
                    @endif

                    @if($refund->canReverse() && auth()->user()?->hasPermission('reverse_journal'))
                        <button type="button" onclick="confirmReverse()" class="w-full px-4 py-2 bg-amber-100 hover:bg-amber-200 text-amber-800 text-sm font-medium rounded-xl">Reverse Refund</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Customer + amount --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Customer</h3>
                <p class="font-medium text-slate-800">{{ $refund->counterparty?->name }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ $refund->counterparty?->code }}</p>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Refund Details</h3>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Date</dt>
                    <dd class="text-slate-800">{{ $refund->refund_date?->format('Y-m-d') }}</dd>
                    <dt class="text-slate-500">Method</dt>
                    <dd class="text-slate-800">{{ $refund->paymentMethod?->name ?? '—' }}</dd>
                    @if($refund->external_reference)
                        <dt class="text-slate-500">Reference</dt>
                        <dd class="text-slate-800 font-mono text-xs">{{ $refund->external_reference }}</dd>
                    @endif
                    <dt class="text-slate-500">Amount</dt>
                    <dd class="font-bold text-rose-700">{{ $refund->currency_code }} {{ number_format((float) $refund->amount, 2) }}</dd>
                    @if($refund->sourceReceipt)
                        <dt class="text-slate-500">From Receipt</dt>
                        <dd><a href="{{ route('finance.receipts.show', $refund->sourceReceipt) }}" class="text-emerald-700 hover:underline font-mono text-xs">{{ $refund->sourceReceipt->receipt_number }}</a></dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Reason --}}
        @if($refund->reason)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5 mb-4">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">Reason</h3>
                <p class="text-sm text-slate-700">{{ $refund->reason }}</p>
                @if($refund->description)
                    <p class="text-sm text-slate-500 mt-2">{{ $refund->description }}</p>
                @endif
            </div>
        @endif

        {{-- Attachments --}}
        @include('finance.partials.attachments-block', ['attachable' => $refund, 'parentType' => 'refunds'])

        {{-- Audit --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Audit</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-600">
                @if($refund->createdBy)
                    <div><span class="text-slate-400">Created:</span> {{ $refund->createdBy->name }} <span class="text-slate-400">— {{ $refund->created_at?->format('Y-m-d H:i') }}</span></div>
                @endif
                @if($refund->approvedBy)
                    <div><span class="text-slate-400">Approved:</span> {{ $refund->approvedBy->name }} <span class="text-slate-400">— {{ $refund->approved_at?->format('Y-m-d H:i') }}</span></div>
                @endif
                @if($refund->reversedBy)
                    <div><span class="text-slate-400">Reversed:</span> {{ $refund->reversedBy->name }} <span class="text-slate-400">— {{ $refund->reversed_at?->format('Y-m-d H:i') }}</span></div>
                @endif
            </div>
        </div>

        @if($refund->canReverse() && auth()->user()?->hasPermission('reverse_journal'))
            <form id="reverse-form" method="POST" action="{{ route('finance.refunds.reverse', $refund) }}" class="hidden">
                @csrf
                <input type="hidden" name="reason" value="">
            </form>
        @endif
    </div>

    <script>
        function confirmReverse() {
            const reason = prompt('Reason for reversing this refund?');
            if (!reason || reason.trim() === '') return;
            const form = document.getElementById('reverse-form');
            form.querySelector('input[name="reason"]').value = reason.trim();
            form.submit();
        }
    </script>
</x-app-layout>
