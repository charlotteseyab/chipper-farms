<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.invoices.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Invoices</a>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if($invoice->isReversed())
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <strong>Reversed.</strong> This invoice was reversed via journal batch
                <a href="{{ route('finance.journals.show', $invoice->reversed_by_journal_batch_id) }}" class="font-mono underline">#{{ $invoice->reversed_by_journal_batch_id }}</a>.
            </div>
        @endif

        @if($invoice->isCancelled())
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <strong>Cancelled.</strong>
                @if($invoice->cancellation_reason)<span> — {{ $invoice->cancellation_reason }}</span>@endif
            </div>
        @endif

        {{-- Header card --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold">{{ $invoice->is_cash_invoice ? 'Cash Invoice' : 'Credit Invoice' }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ $invoice->invoice_number }}</h1>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        @php
                            $statusClasses = [
                                'draft' => 'bg-amber-100 text-amber-800',
                                'posted' => 'bg-emerald-100 text-emerald-800',
                                'cancelled' => 'bg-slate-200 text-slate-700',
                                'reversed' => 'bg-slate-100 text-slate-700',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium {{ $statusClasses[$invoice->status] }}">{{ $invoice->status }}</span>
                        @if($invoice->journalBatch)
                            <span class="text-slate-500">→ <a href="{{ route('finance.journals.show', $invoice->journalBatch) }}" class="hover:underline font-mono">{{ $invoice->journalBatch->batch_number }}</a></span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col gap-2 min-w-[200px]">
                    @if($invoice->canPost() && auth()->user()?->hasPermission('post_invoice'))
                        <form method="POST" action="{{ route('finance.invoices.post', $invoice) }}" onsubmit="return confirm('Post this invoice to the GL? This is reversible but does not undo automatically.')">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Post to GL</button>
                        </form>
                    @endif

                    @if($invoice->canCancel() && auth()->user()?->hasPermission('create_invoice'))
                        <button type="button" onclick="document.getElementById('cancel-form').classList.toggle('hidden')"
                                class="w-full px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Cancel Draft</button>
                    @endif

                    @if($invoice->canReverse() && auth()->user()?->hasPermission('reverse_journal'))
                        <button type="button" onclick="document.getElementById('reverse-form').classList.toggle('hidden')"
                                class="w-full px-4 py-2 bg-amber-100 hover:bg-amber-200 text-amber-800 text-sm font-medium rounded-xl">Reverse</button>
                    @endif
                </div>
            </div>

            @if($invoice->canCancel() && auth()->user()?->hasPermission('create_invoice'))
                <form id="cancel-form" method="POST" action="{{ route('finance.invoices.cancel', $invoice) }}" class="mt-4 hidden">
                    @csrf
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cancellation Reason</label>
                    <textarea name="reason" required rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Why is this draft being cancelled?"></textarea>
                    <button type="submit" class="mt-2 px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl">Confirm Cancel</button>
                </form>
            @endif

            @if($invoice->canReverse() && auth()->user()?->hasPermission('reverse_journal'))
                <form id="reverse-form" method="POST" action="{{ route('finance.invoices.reverse', $invoice) }}" class="mt-4 hidden">
                    @csrf
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason for Reversal</label>
                    <textarea name="reason" required rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Why is this posted invoice being reversed?"></textarea>
                    <button type="submit" class="mt-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl">Confirm Reversal</button>
                </form>
            @endif
        </div>

        {{-- Customer + dates --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Customer</h3>
                <p class="font-medium text-slate-800">{{ $invoice->counterparty?->name }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ $invoice->counterparty?->code }}</p>
                @if($invoice->counterparty?->category)
                    <p class="text-xs text-slate-500 mt-1">{{ $invoice->counterparty->category->name }}</p>
                @endif
                @if($invoice->billing_address_snapshot)
                    <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ $invoice->billing_address_snapshot }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Dates &amp; Reference</h3>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Invoice Date</dt>
                    <dd class="text-slate-800">{{ $invoice->invoice_date?->format('Y-m-d') }}</dd>
                    <dt class="text-slate-500">Due Date</dt>
                    <dd class="text-slate-800">{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="text-slate-500">Reference</dt>
                    <dd class="text-slate-800">{{ $invoice->reference ?? '—' }}</dd>
                    <dt class="text-slate-500">Currency</dt>
                    <dd class="text-slate-800">{{ $invoice->currency_code }}</dd>
                    @if($invoice->paymentMethod)
                        <dt class="text-slate-500">Payment Method</dt>
                        <dd class="text-slate-800">{{ $invoice->paymentMethod->name }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Lines --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Lines</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Unit Price</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3 text-right">Discount</th>
                            <th class="px-4 py-3 text-right">Tax</th>
                            <th class="px-4 py-3 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($invoice->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-xs text-slate-400">{{ $line->line_number }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-700">{{ $line->description }}</p>
                                    @if($line->item_code)<p class="text-xs text-slate-400 font-mono">{{ $line->item_code }}</p>@endif
                                    @if($line->revenueAccount)<p class="text-xs text-slate-400">→ {{ $line->revenueAccount->code }} {{ $line->revenueAccount->name }}</p>@endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim($line->quantity, '0'), '.') }} {{ $line->unit_of_measure }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim($line->unit_price, '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $line->line_subtotal, 2) }}</td>
                                <td class="px-4 py-3 text-right text-slate-500">{{ (float) $line->line_discount_amount > 0 ? '−' . number_format((float) $line->line_discount_amount, 2) : '' }}</td>
                                <td class="px-4 py-3 text-right text-slate-500">{{ (float) $line->line_tax_amount > 0 ? number_format((float) $line->line_tax_amount, 2) : '' }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 text-sm">
                        <tr>
                            <td colspan="7" class="px-4 py-2 text-right text-slate-600">Subtotal</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                        </tr>
                        @if((float) $invoice->discount_total > 0)
                            <tr>
                                <td colspan="7" class="px-4 py-2 text-right text-slate-600">Total Discount</td>
                                <td class="px-4 py-2 text-right text-slate-600">−{{ number_format((float) $invoice->discount_total, 2) }}</td>
                            </tr>
                        @endif
                        @if((float) $invoice->tax_total > 0)
                            <tr>
                                <td colspan="7" class="px-4 py-2 text-right text-slate-600">Tax</td>
                                <td class="px-4 py-2 text-right text-slate-600">{{ number_format((float) $invoice->tax_total, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="bg-slate-100 font-bold text-lg">
                            <td colspan="7" class="px-4 py-3 text-right">Total Due</td>
                            <td class="px-4 py-3 text-right">{{ $invoice->currency_code }} {{ number_format((float) $invoice->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Audit trail --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Audit</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-600">
                @if($invoice->createdBy)
                    <div><span class="text-slate-400">Created by:</span> {{ $invoice->createdBy->name }} <span class="text-slate-400">— {{ $invoice->created_at?->format('Y-m-d H:i') }}</span></div>
                @endif
                @if($invoice->postedBy)
                    <div><span class="text-slate-400">Posted by:</span> {{ $invoice->postedBy->name }} <span class="text-slate-400">— {{ $invoice->posted_at?->format('Y-m-d H:i') }}</span></div>
                @endif
                @if($invoice->cancelledBy)
                    <div><span class="text-slate-400">Cancelled by:</span> {{ $invoice->cancelledBy->name }} <span class="text-slate-400">— {{ $invoice->cancelled_at?->format('Y-m-d H:i') }}</span></div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
