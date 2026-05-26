<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.payment-runs.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                <i class="fa-solid fa-arrow-left mr-1"></i> All Payment Runs
            </a>
        </div>

        @if($run->isCancelled())
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <strong>Cancelled</strong> on {{ $run->cancelled_at?->format('Y-m-d H:i') }} by {{ $run->cancelledBy?->name }}.
                @if($run->cancellation_reason) <div class="mt-1 italic">"{{ $run->cancellation_reason }}"</div> @endif
            </div>
        @elseif($run->isReversed())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <strong>Reversed</strong> on {{ $run->reversed_at?->format('Y-m-d H:i') }} by {{ $run->reversedBy?->name }}.
                @if($run->reversal_reason) <div class="mt-1 italic">"{{ $run->reversal_reason }}"</div> @endif
            </div>
        @endif

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        <!-- Header -->
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-slate-800 font-mono">{{ $run->run_number }}</h1>
                        @php $statusClasses = [
                            'draft'     => 'bg-slate-100 text-slate-700',
                            'executed'  => 'bg-emerald-100 text-emerald-800',
                            'cancelled' => 'bg-slate-100 text-slate-500',
                            'reversed'  => 'bg-rose-100 text-rose-800',
                        ]; @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $statusClasses[$run->status] ?? '' }}">{{ strtoupper($run->status) }}</span>
                    </div>
                    <div class="mt-2 text-sm text-slate-600 space-y-0.5">
                        <div>Run date: <strong>{{ $run->run_date?->format('Y-m-d') }}</strong></div>
                        <div>Currency: <strong class="font-mono">{{ $run->currency_code }}</strong></div>
                        @if($run->defaultPaymentMethod)
                            <div>Default method: {{ $run->defaultPaymentMethod->name }}</div>
                        @endif
                        @if($run->defaultSourceAccount)
                            <div>Default source: {{ $run->defaultSourceAccount->code }} — {{ $run->defaultSourceAccount->name }}</div>
                        @endif
                        @if($run->description) <div class="italic text-slate-500">"{{ $run->description }}"</div> @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($run->canExecute() && auth()->user()?->hasPermission('create_payment'))
                        <button type="button" onclick="executeRun()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                            <i class="fa-solid fa-play mr-1"></i> Execute
                        </button>
                    @endif
                    @if($run->canCancel() && auth()->user()?->hasPermission('create_payment'))
                        <button type="button" onclick="cancelRun()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">Cancel</button>
                    @endif
                    @if($run->canReverse() && auth()->user()?->hasPermission('reverse_journal'))
                        <button type="button" onclick="reverseRun()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Reverse
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Aggregates -->
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <p class="text-xs text-slate-500 uppercase tracking-wide mb-4">Totals ({{ $run->currency_code }})</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-xs text-slate-500">Distinct payments</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $run->payment_count }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Gross</p>
                    <p class="text-2xl font-bold text-slate-800">{{ number_format((float) $run->total_gross, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Withholding</p>
                    <p class="text-2xl font-bold text-slate-700">{{ number_format((float) $run->total_withholding, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Net (cash out)</p>
                    <p class="text-2xl font-bold text-emerald-700">{{ number_format((float) $run->total_net, 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Lines -->
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Lines</h2>
            @if($run->lines->isEmpty())
                <p class="text-sm text-slate-400 italic">No lines yet — add some below.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr class="border-b border-slate-100">
                                <th class="px-2 py-2 text-left">VI #</th>
                                <th class="px-2 py-2 text-left">Supplier</th>
                                <th class="px-2 py-2 text-right">Amount</th>
                                <th class="px-2 py-2 text-right">WHT</th>
                                <th class="px-2 py-2 text-left">Method (override)</th>
                                <th class="px-2 py-2 text-left">Source (override)</th>
                                <th class="px-2 py-2 text-left">After exec</th>
                                <th class="px-2 py-2 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($run->lines as $line)
                                <tr>
                                    <td class="px-2 py-2 font-mono text-xs">
                                        <a href="{{ route('finance.vendor-invoices.show', $line->vendorInvoice) }}" class="hover:underline">{{ $line->vendorInvoice?->vendor_invoice_number }}</a>
                                    </td>
                                    <td class="px-2 py-2 text-xs">{{ $line->counterparty?->name }}</td>
                                    <td class="px-2 py-2 text-right">{{ number_format((float) $line->amount, 2) }}</td>
                                    <td class="px-2 py-2 text-right text-xs">
                                        @if(bccomp((string) $line->withholding_amount, '0', 2) > 0)
                                            {{ number_format((float) $line->withholding_amount, 2) }}
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-xs">{{ $line->paymentMethod?->name ?? '—' }}</td>
                                    <td class="px-2 py-2 text-xs">{{ $line->sourceAccount ? $line->sourceAccount->code : '—' }}</td>
                                    <td class="px-2 py-2 text-xs">
                                        @if($line->payment)
                                            <a href="{{ route('finance.payments.show', $line->payment) }}" class="font-mono text-emerald-700 hover:underline">{{ $line->payment->payment_number }}</a>
                                        @else
                                            <span class="text-slate-300">pending</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        @if($run->isDraft() && ! $line->isExecuted() && auth()->user()?->hasPermission('create_payment'))
                                            <form method="POST" action="{{ route('finance.payment-runs.remove-line', $line) }}" onsubmit="return confirm('Remove this line?');">
                                                @csrf
                                                <button type="submit" class="text-xs text-rose-600 hover:underline">remove</button>
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

        <!-- Add line block -->
        @if($run->isDraft() && auth()->user()?->hasPermission('create_payment'))
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
                <h2 class="text-base font-semibold text-slate-800 mb-3">Add line</h2>
                @if($availableInvoices->isEmpty())
                    <p class="text-sm text-slate-400 italic">No open vendor invoices in {{ $run->currency_code }}.</p>
                @else
                    <form method="POST" action="{{ route('finance.payment-runs.add-line', $run) }}" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-2">
                                <label class="block text-xs text-slate-600 mb-1">Vendor invoice</label>
                                <select name="vendor_invoice_id" required class="w-full rounded-xl border-slate-200 text-sm">
                                    <option value="">— select —</option>
                                    @foreach($availableInvoices as $vi)
                                        <option value="{{ $vi->id }}">
                                            {{ $vi->vendor_invoice_number }} — {{ $vi->counterparty->name }} — {{ $vi->invoice_date?->format('Y-m-d') }} — balance {{ number_format((float) $vi->balance_due, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-600 mb-1">Amount (default: balance due)</label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="w-full rounded-xl border-slate-200 text-sm" placeholder="leave blank for full balance">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-600 mb-1">Method override (optional)</label>
                                <select name="payment_method_id" class="w-full rounded-xl border-slate-200 text-sm">
                                    <option value="">Use run default</option>
                                </select>
                                <p class="text-xs text-slate-400 mt-1">Lines without an override join the run's default method group.</p>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-600 mb-1">Source GL override (optional)</label>
                                <select name="source_account_id" class="w-full rounded-xl border-slate-200 text-sm">
                                    <option value="">Use run default</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Add Line</button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        <!-- Hidden forms -->
        @if($run->isDraft())
            <form id="execute-form" method="POST" action="{{ route('finance.payment-runs.execute', $run) }}" class="hidden">
                @csrf
            </form>
            <form id="cancel-form" method="POST" action="{{ route('finance.payment-runs.cancel', $run) }}" class="hidden">
                @csrf
                <input type="hidden" name="reason" value="">
            </form>
        @endif
        @if($run->canReverse())
            <form id="reverse-form" method="POST" action="{{ route('finance.payment-runs.reverse', $run) }}" class="hidden">
                @csrf
                <input type="hidden" name="reason" value="">
            </form>
        @endif
    </div>

    <script>
    function executeRun() {
        const lineCount = {{ $run->lines->count() }};
        const distinctSuppliers = {{ $run->lines->pluck('counterparty_id')->unique()->count() }};
        const total = '{{ number_format((float) $run->total_gross, 2) }}';
        const net = '{{ number_format((float) $run->total_net, 2) }}';
        const cur = '{{ $run->currency_code }}';
        const ok = confirm(
            'Execute this payment run?\n\n' +
            lineCount + ' line(s) → grouped into payments per (supplier, method, source).\n' +
            'Will create up to ' + distinctSuppliers + ' payment(s).\n' +
            'Gross: ' + cur + ' ' + total + '\n' +
            'Net cash out: ' + cur + ' ' + net + '\n\n' +
            'This posts journals and CANNOT be partially undone.'
        );
        if (ok) document.getElementById('execute-form').submit();
    }
    function cancelRun() {
        const reason = prompt('Cancel this draft run? Provide a reason.');
        if (! reason || reason.trim() === '') return;
        const f = document.getElementById('cancel-form');
        f.querySelector('input[name="reason"]').value = reason;
        f.submit();
    }
    function reverseRun() {
        const reason = prompt('Reverse this executed run? Provide a reason. This reverses ALL child payments AND their WHT slice journals.');
        if (! reason || reason.trim() === '') return;
        const f = document.getElementById('reverse-form');
        f.querySelector('input[name="reason"]').value = reason;
        f.submit();
    }
    </script>
</x-app-layout>
