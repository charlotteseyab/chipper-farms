<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4 flex items-center justify-between">
            <a href="{{ route('finance.vendor-invoices.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Vendor Invoices</a>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        @if($invoice->isReversed())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <strong>Reversed.</strong>
                @if($invoice->reversal_reason)<p class="mt-1 italic">{{ $invoice->reversal_reason }}</p>@endif
            </div>
        @elseif($invoice->isCancelled())
            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <strong>Cancelled.</strong>
                @if($invoice->cancellation_reason)<p class="mt-1 italic">{{ $invoice->cancellation_reason }}</p>@endif
            </div>
        @endif

        @if($invoice->hasMatchVariance())
            <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4">
                <h4 class="text-sm font-semibold text-amber-900 mb-2">⚠ Three-way matching variance</h4>
                <p class="text-xs text-amber-800 mb-2">The invoice deviates from the purchase request or goods receipt beyond tolerance. Soft-block: the invoice can still be approved, but the variance is recorded in the journal description.</p>
                <ul class="text-xs space-y-1 ml-4 list-disc text-amber-900">
                    @foreach($invoice->match_variances ?? [] as $v)
                        <li><strong>Line {{ $v['line_number'] }}</strong> — {{ $v['type'] }}: {{ $v['detail'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Header --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold">Vendor Invoice</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-800 font-mono">{{ $invoice->vendor_invoice_number }}</h1>
                    @if($invoice->supplier_invoice_number)
                        <p class="text-sm text-slate-600 mt-1">Supplier ref: <span class="font-mono">{{ $invoice->supplier_invoice_number }}</span></p>
                    @endif
                    <div class="mt-2 flex flex-wrap gap-2 items-center text-xs">
                        @php $statusClasses = [
                            'draft'            => 'bg-slate-100 text-slate-700',
                            'pending_approval' => 'bg-amber-100 text-amber-800',
                            'posted'           => 'bg-emerald-100 text-emerald-800',
                            'cancelled'        => 'bg-slate-100 text-slate-500',
                            'reversed'         => 'bg-rose-100 text-rose-800',
                        ]; @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium {{ $statusClasses[$invoice->status] }}">{{ str_replace('_', ' ', $invoice->status) }}</span>
                        @if($invoice->journalBatch)
                            <span class="text-slate-500">→ <a href="{{ route('finance.journals.show', $invoice->journalBatch) }}" class="hover:underline font-mono">{{ $invoice->journalBatch->batch_number }}</a></span>
                        @endif
                        @if($invoice->purchaseRequest)
                            <span class="text-slate-500">| PR: <span class="font-mono">{{ $invoice->purchaseRequest->request_no }}</span></span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col gap-2 min-w-[220px]">
                    @if($invoice->canSubmitForApproval() && auth()->user()?->hasPermission('create_vendor_invoice'))
                        <form method="POST" action="{{ route('finance.vendor-invoices.submit', $invoice) }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl">Submit for Approval</button>
                        </form>
                    @endif

                    @if($invoice->canApprove() && auth()->user()?->hasPermission('approve_vendor_invoice'))
                        <form method="POST" action="{{ route('finance.vendor-invoices.post', $invoice) }}"
                              onsubmit="return confirm('Approve and post this invoice to GL? This cannot be undone except by reversal.')">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Approve &amp; Post</button>
                        </form>
                    @endif

                    @if($invoice->canCancel() && auth()->user()?->hasPermission('create_vendor_invoice'))
                        <button type="button" onclick="confirmCancel()" class="w-full px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Cancel</button>
                    @endif

                    @if($invoice->canReverse() && auth()->user()?->hasPermission('reverse_journal'))
                        <button type="button" onclick="confirmReverse()" class="w-full px-4 py-2 bg-rose-100 hover:bg-rose-200 text-rose-800 text-sm font-medium rounded-xl">Reverse</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Supplier + Dates --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Supplier</h3>
                <p class="font-medium text-slate-800">
                    <a href="{{ route('finance.suppliers.show', $invoice->counterparty) }}" class="hover:underline">{{ $invoice->counterparty?->name }}</a>
                </p>
                <p class="text-xs text-slate-400 font-mono">{{ $invoice->counterparty?->code }}</p>
                @if($invoice->counterparty?->supplierCategory)
                    <p class="text-xs text-slate-500 mt-1">{{ $invoice->counterparty->supplierCategory->name }}</p>
                @endif
                @if($invoice->counterparty?->withholding_exempt)
                    <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">WHT exempt</span>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Dates</h3>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Invoice date</dt>
                    <dd class="text-slate-800">{{ $invoice->invoice_date?->format('Y-m-d') }}</dd>
                    @if($invoice->received_at)
                        <dt class="text-slate-500">Received</dt>
                        <dd class="text-slate-800">{{ $invoice->received_at->format('Y-m-d') }}</dd>
                    @endif
                    <dt class="text-slate-500">Due</dt>
                    <dd class="text-slate-800">
                        {{ $invoice->due_date?->format('Y-m-d') ?? '—' }}
                        @if($invoice->isPosted() && $invoice->due_date && $invoice->due_date->isPast() && (float) $invoice->balance_due > 0)
                            <span class="ml-1 text-xs text-red-600">overdue</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        {{-- Lines --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
            <h3 class="px-5 py-3 border-b border-slate-100 text-sm font-semibold text-slate-700 uppercase tracking-wide">Lines</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Description</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2 text-right">Unit Cost</th>
                            <th class="px-4 py-2 text-right">Subtotal</th>
                            <th class="px-4 py-2 text-right">Tax</th>
                            <th class="px-4 py-2 text-right">WHT</th>
                            <th class="px-4 py-2 text-left">Account</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($invoice->lines as $line)
                            <tr>
                                <td class="px-4 py-2 text-slate-500">{{ $line->line_number }}</td>
                                <td class="px-4 py-2 text-slate-800">
                                    {{ $line->description }}
                                    @if($line->purchaseRequestItem)
                                        <span class="ml-1 text-xs text-slate-400">(PR line)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }} {{ $line->unit }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format((float) $line->unit_cost, 4) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format((float) $line->line_subtotal, 2) }}</td>
                                <td class="px-4 py-2 text-right">
                                    @if(bccomp((string) $line->tax_amount, '0', 2) > 0)
                                        {{ number_format((float) $line->tax_amount, 2) }}
                                        <div class="text-xs text-slate-400">{{ rtrim(rtrim((string) $line->tax_rate, '0'), '.') }}%</div>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if(bccomp((string) $line->withholding_amount, '0', 2) > 0)
                                        {{ number_format((float) $line->withholding_amount, 2) }}
                                        <div class="text-xs text-slate-400">{{ rtrim(rtrim((string) $line->withholding_rate, '0'), '.') }}%</div>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    @if($line->account)
                                        <span class="font-mono">{{ $line->account->code }}</span>
                                        <div class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($line->account->name, 25) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right font-medium">{{ number_format((float) $line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 text-sm">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right text-slate-700 font-semibold uppercase text-xs">Subtotal</td>
                            <td class="px-4 py-2 text-right">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                            <td colspan="4" class="px-4 py-2"></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right text-slate-700 font-semibold uppercase text-xs">Tax</td>
                            <td colspan="2" class="px-4 py-2 text-right">{{ number_format((float) $invoice->tax_total, 2) }}</td>
                            <td colspan="3" class="px-4 py-2"></td>
                        </tr>
                        <tr class="border-t-2 border-slate-300">
                            <td colspan="4" class="px-4 py-2 text-right text-slate-800 font-bold uppercase text-xs">Total</td>
                            <td colspan="5" class="px-4 py-2"></td>
                            <td class="px-4 py-2 text-right text-slate-900 font-bold text-base">{{ $invoice->currency_code }} {{ number_format((float) $invoice->total, 2) }}</td>
                        </tr>
                        @if(bccomp((string) $invoice->withholding_total, '0', 2) > 0)
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-right text-slate-600 italic text-xs">Withholding (deducted at payment)</td>
                                <td colspan="2" class="px-4 py-2 text-right text-xs italic text-slate-500">- {{ number_format((float) $invoice->withholding_total, 2) }}</td>
                                <td colspan="4" class="px-4 py-2"></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-right text-slate-700 italic text-xs">Net payable when settled</td>
                                <td colspan="6" class="px-4 py-2 text-right text-sm italic">
                                    {{ $invoice->currency_code }} {{ number_format((float) $invoice->total - (float) $invoice->withholding_total, 2) }}
                                </td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Description --}}
        @if($invoice->description || $invoice->notes)
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5 mb-4">
                @if($invoice->description)
                    <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">Description</h3>
                    <p class="text-sm text-slate-700">{{ $invoice->description }}</p>
                @endif
                @if($invoice->notes)
                    <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mt-3 mb-2">Notes</h3>
                    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $invoice->notes }}</p>
                @endif
            </div>
        @endif

        {{-- Attachments (Phase 2d polymorphic block) --}}
        @include('finance.partials.attachments-block', ['attachable' => $invoice, 'parentType' => 'vendor-invoices'])

        {{-- Audit --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Audit</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-600">
                @if($invoice->createdBy)
                    <div><span class="text-slate-400">Created:</span> {{ $invoice->createdBy->name }} <span class="text-slate-400">— {{ $invoice->created_at?->format('Y-m-d H:i') }}</span></div>
                @endif
                @if($invoice->submittedForApprovalBy)
                    <div><span class="text-slate-400">Submitted:</span> {{ $invoice->submittedForApprovalBy->name }} <span class="text-slate-400">— {{ $invoice->submitted_for_approval_at?->format('Y-m-d H:i') }}</span></div>
                @endif
                @if($invoice->approvedBy)
                    <div><span class="text-slate-400">Approved:</span> {{ $invoice->approvedBy->name }} <span class="text-slate-400">— {{ $invoice->approved_at?->format('Y-m-d H:i') }}</span></div>
                @endif
                @if($invoice->cancelledBy)
                    <div><span class="text-slate-400">Cancelled:</span> {{ $invoice->cancelledBy->name }} <span class="text-slate-400">— {{ $invoice->cancelled_at?->format('Y-m-d H:i') }}</span></div>
                @endif
                @if($invoice->reversedBy)
                    <div><span class="text-slate-400">Reversed:</span> {{ $invoice->reversedBy->name }} <span class="text-slate-400">— {{ $invoice->reversed_at?->format('Y-m-d H:i') }}</span></div>
                @endif
            </div>
        </div>

        @if($invoice->canCancel())
            <form id="cancel-form" method="POST" action="{{ route('finance.vendor-invoices.cancel', $invoice) }}" class="hidden">
                @csrf
                <input type="hidden" name="reason" value="">
            </form>
        @endif
        @if($invoice->canReverse())
            <form id="reverse-form" method="POST" action="{{ route('finance.vendor-invoices.reverse', $invoice) }}" class="hidden">
                @csrf
                <input type="hidden" name="reason" value="">
            </form>
        @endif
    </div>

    <script>
        function confirmCancel() {
            const reason = prompt('Reason for cancelling this invoice?');
            if (!reason || reason.trim() === '') return;
            const f = document.getElementById('cancel-form');
            f.querySelector('input[name="reason"]').value = reason.trim();
            f.submit();
        }
        function confirmReverse() {
            const reason = prompt('Reason for reversing this invoice?');
            if (!reason || reason.trim() === '') return;
            const f = document.getElementById('reverse-form');
            f.querySelector('input[name="reason"]').value = reason.trim();
            f.submit();
        }
    </script>
</x-app-layout>
