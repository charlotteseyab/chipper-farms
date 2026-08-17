<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.receipts.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Receipts</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">New Receipt</h1>
        <p class="text-sm text-gray-500 mb-6">Records a customer payment. The receipt posts to the GL immediately. Allocate to specific invoices below or pick auto-allocate (FIFO).</p>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.receipts.store') }}" id="receipt-form">
            @csrf

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Receipt Details</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer <span class="text-red-500">*</span></label>
                        <select name="counterparty_id" required class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">Select a customer...</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-currency="{{ $c->currency_code }}"
                                    @selected(old('counterparty_id', $preselectedCustomerId) == $c->id)>
                                    {{ $c->code }} — {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency <span class="text-red-500">*</span></label>
                        <input type="text" name="currency_code" value="{{ old('currency_code', 'GHS') }}" maxlength="3" required class="w-full rounded-xl border-slate-200 text-sm uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Receipt Date <span class="text-red-500">*</span></label>
                        <input type="date" name="receipt_date" value="{{ old('receipt_date', now()->toDateString()) }}" required class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="w-full rounded-xl border-slate-200 text-sm text-right">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Received into <span class="text-red-500">*</span></label>
                        <select name="money_account_id" required class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">Choose a money account...</option>
                            @foreach($moneyAccounts as $account)
                                <option value="{{ $account->id }}" @selected((int) old('money_account_id') === $account->id)>
                                    {{ $account->displayName() }} · {{ $account->account?->code }}
                                </option>
                            @endforeach
                        </select>
                        @if($moneyAccounts->isEmpty())
                            <p class="mt-1 text-xs text-slate-500">Set up a money account under Cash, Bank & MoMo before posting receipts.</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">External Reference</label>
                        <input type="text" name="external_reference" value="{{ old('external_reference') }}"
                               placeholder="MoMo TXID, cheque number, bank reference..." class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('description') }}</textarea>
                </div>
            </div>

            {{-- Allocation block --}}
            @if($openInvoices->isNotEmpty())
                <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Allocate to Open Invoices</h2>

                    <label class="flex items-center gap-2 mb-4 text-sm">
                        <input type="checkbox" name="auto_allocate" value="1" {{ old('auto_allocate') ? 'checked' : '' }} class="rounded border-slate-300">
                        <span>Auto-allocate to oldest invoices first (FIFO)</span>
                    </label>

                    <p class="text-xs text-slate-500 mb-3">Or specify per-invoice amounts below. Leave amounts blank to skip an invoice.</p>

                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-2 py-2 text-left">Invoice</th>
                                <th class="px-2 py-2 text-left">Date</th>
                                <th class="px-2 py-2 text-right">Balance</th>
                                <th class="px-2 py-2 text-right w-32">Allocate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($openInvoices as $idx => $inv)
                                <tr>
                                    <td class="px-2 py-2 font-mono text-xs">{{ $inv->invoice_number }}</td>
                                    <td class="px-2 py-2 text-slate-600 text-xs">{{ $inv->invoice_date?->format('Y-m-d') }}</td>
                                    <td class="px-2 py-2 text-right">{{ number_format((float) $inv->balance_due, 2) }}</td>
                                    <td class="px-2 py-2 text-right">
                                        <input type="hidden" name="allocations[{{ $idx }}][invoice_id]" value="{{ $inv->id }}">
                                        <input type="number" step="0.01" min="0" max="{{ $inv->balance_due }}"
                                               name="allocations[{{ $idx }}][amount]" placeholder="0"
                                               class="w-full rounded-lg border-slate-200 text-sm text-right">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
                    <p class="text-sm text-slate-500">
                        @if($preselectedCustomerId)
                            No open invoices for this customer. The receipt will be posted with full amount as customer credit.
                        @else
                            Select a customer above to see their open invoices for allocation.
                        @endif
                    </p>
                </div>
            @endif

            <div class="flex gap-3 justify-end pt-2">
                <a href="{{ route('finance.receipts.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Post Receipt</button>
            </div>
        </form>
    </div>
</x-app-layout>
