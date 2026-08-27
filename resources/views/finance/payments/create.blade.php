<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.payments.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                <i class="fa-solid fa-arrow-left mr-1"></i> All Payments
            </a>
        </div>

        <h1 class="text-2xl font-bold text-slate-800 mb-2">New Payment</h1>
        <p class="text-sm text-slate-500 mb-6">Records cash leaving the bank to a holding account; allocations later relieve AP and recognise withholding tax.<br><span class="text-xs">Enter the <strong>net cash</strong> that actually leaves the bank — for non-WHT suppliers this equals the bill amount; for WHT-bearing bills it's bill amount minus WHT.</span></p>

        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if($preselectPurchaseRequest)
            <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Purchase request payment</p>
                <p class="mt-1 font-bold text-slate-900">{{ $preselectPurchaseRequest->request_no }} · {{ $preselectPurchaseRequest->paymentArrangementLabel() }}</p>
                <p class="mt-1 text-sm text-slate-600">This payment will be linked to the request and remain traceable when the final supplier invoice is recorded.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.payments.store') }}" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
            @csrf
            @if($preselectPurchaseRequest)
                <input type="hidden" name="purchase_request_id" value="{{ $preselectPurchaseRequest->id }}">
                <input type="hidden" name="purchase_payment_stage" value="{{ $preselectVendorInvoice ? ($preselectPurchaseRequest->payment_progress === 'not_paid' ? 'full' : 'balance') : ($preselectPurchaseRequest->payment_arrangement === 'deposit_balance' ? 'deposit' : 'advance') }}">
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Supplier *</label>
                    <select name="counterparty_id" id="supplier-select" required class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">— select —</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" data-currency="{{ $s->currency_code }}"
                                @selected(old('counterparty_id', $preselectSupplierId) == $s->id)>
                                {{ $s->code }} — {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Currency *</label>
                    <input type="text" name="currency_code" id="currency-input" value="{{ old('currency_code', 'GHS') }}" required maxlength="3" class="w-full rounded-xl border-slate-200 text-sm font-mono uppercase">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Payment date *</label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Amount *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $preselectPurchaseRequest ? ($preselectPurchaseRequest->payment_arrangement === 'deposit_balance' ? round((float) $preselectPurchaseRequest->total_amount * (float) $preselectPurchaseRequest->deposit_percent / 100, 2) : $preselectPurchaseRequest->total_amount) : null) }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Pay from *</label>
                    <select name="money_account_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="">Choose a money account...</option>
                        @foreach($moneyAccounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('money_account_id') === $account->id)>
                                {{ $account->displayName() }} · {{ $account->account?->code }}
                            </option>
                        @endforeach
                    </select>
                    @if($moneyAccounts->isEmpty())
                        <p class="text-xs text-slate-500 mt-1">Set up a money account under Cash, Bank & MoMo before posting payments.</p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">External reference</label>
                    <input type="text" name="external_reference" maxlength="100" value="{{ old('external_reference') }}" class="w-full rounded-xl border-slate-200 text-sm" placeholder="cheque #, txn ID, MOMO ref">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <input type="text" name="description" maxlength="500" value="{{ old('description') }}" class="w-full rounded-xl border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" maxlength="2000" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea>
            </div>

            <hr class="border-slate-200">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Allocation</label>
                <div class="space-y-2 text-sm">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="allocate_to" value="none" @checked(old('allocate_to', $preselectVendorInvoice ? 'specific' : 'none') === 'none')>
                        Don't allocate now (allocate later from the Show page)
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="allocate_to" value="specific" @checked(old('allocate_to', $preselectVendorInvoice ? 'specific' : null) === 'specific')>
                        Allocate to a specific vendor invoice
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="allocate_to" value="fifo" @checked(old('allocate_to') === 'fifo')>
                        Auto-allocate FIFO across the supplier's open invoices
                    </label>
                </div>

                @if($preselectVendorInvoice)
                    <div class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50/50 p-3 text-sm">
                        <input type="hidden" name="vendor_invoice_id" value="{{ $preselectVendorInvoice->id }}">
                        <strong>{{ $preselectVendorInvoice->vendor_invoice_number }}</strong>
                        <span class="text-slate-600 text-xs ml-2">balance due: {{ number_format((float) $preselectVendorInvoice->balance_due, 2) }} {{ $preselectVendorInvoice->currency_code }}</span>
                        <div class="mt-2">
                            <label class="block text-xs text-slate-600 mb-1">Allocation amount (defaults to min of payment amount and invoice balance)</label>
                            <input type="number" step="0.01" min="0.01" name="allocation_amount" value="{{ old('allocation_amount', $preselectVendorInvoice->balance_due) }}" class="w-48 rounded-lg border-slate-200 text-sm">
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-200">
                <a href="{{ route('finance.payments.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl">Post Payment</button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const supplierSelect = document.getElementById('supplier-select');
        const currencyInput = document.getElementById('currency-input');
        function syncCurrency() {
            const opt = supplierSelect.options[supplierSelect.selectedIndex];
            const cur = opt?.dataset?.currency;
            if (cur) currencyInput.value = cur;
        }

        supplierSelect.addEventListener('change', syncCurrency);
    });
    </script>
</x-app-layout>
