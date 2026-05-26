<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.refunds.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Refunds</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">New Refund</h1>
        <p class="text-sm text-gray-500 mb-6">
            Records money paid back to a customer. Created as draft — treasury must approve to post to GL.
        </p>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if($sourceReceipt)
            <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                <p class="font-semibold">Refunding from receipt {{ $sourceReceipt->receipt_number }}</p>
                <p class="mt-1 text-xs">
                    Receipt amount: {{ $sourceReceipt->currency_code }} {{ number_format((float) $sourceReceipt->amount, 2) }} ·
                    Unallocated balance available to refund: <strong>{{ number_format((float) $sourceReceipt->unallocatedAmount(), 2) }}</strong>
                </p>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.refunds.store') }}">
            @csrf
            @if($sourceReceipt)
                <input type="hidden" name="source_receipt_id" value="{{ $sourceReceipt->id }}">
            @endif

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Refund Details</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer <span class="text-red-500">*</span></label>
                        <select name="counterparty_id" required class="w-full rounded-xl border-slate-200 text-sm" {{ $sourceReceipt ? 'disabled' : '' }}>
                            <option value="">Select a customer...</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-currency="{{ $c->currency_code }}"
                                    @selected(old('counterparty_id', $sourceReceipt?->counterparty_id) == $c->id)>
                                    {{ $c->code }} — {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                        @if($sourceReceipt)
                            <input type="hidden" name="counterparty_id" value="{{ $sourceReceipt->counterparty_id }}">
                            <p class="text-xs text-slate-500 mt-1">Locked to source receipt's customer.</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency <span class="text-red-500">*</span></label>
                        <input type="text" name="currency_code" value="{{ old('currency_code', $sourceReceipt?->currency_code ?? 'GHS') }}" maxlength="3" required class="w-full rounded-xl border-slate-200 text-sm uppercase">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Refund Date <span class="text-red-500">*</span></label>
                        <input type="date" name="refund_date" value="{{ old('refund_date', now()->toDateString()) }}" required class="w-full rounded-xl border-slate-200 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Amount <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0.01"
                               max="{{ $sourceReceipt ? $sourceReceipt->unallocatedAmount() : '' }}"
                               name="amount" value="{{ old('amount', $sourceReceipt?->unallocatedAmount()) }}"
                               required class="w-full rounded-xl border-slate-200 text-sm text-right">
                        @if($sourceReceipt)
                            <p class="text-xs text-slate-500 mt-1">Max: {{ number_format((float) $sourceReceipt->unallocatedAmount(), 2) }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                        <select name="payment_method_id" required class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">Select method...</option>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->id }}" @selected(old('payment_method_id') == $pm->id)>{{ $pm->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">External Reference</label>
                        <input type="text" name="external_reference" value="{{ old('external_reference') }}"
                               placeholder="Cheque #, MoMo TXID, bank ref..." class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="2" required placeholder="e.g. customer overpaid, duplicate payment..." class="w-full rounded-xl border-slate-200 text-sm">{{ old('reason') }}</textarea>
                    <p class="text-xs text-slate-500 mt-1">Required for audit trail.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 mb-4 text-sm text-amber-800">
                <p class="font-semibold">Two-person rule</p>
                <p class="mt-1 text-xs">This refund will be created as a <strong>draft</strong>. A user with the
                <code class="px-1 bg-amber-100 rounded">approve_refund</code> permission must review and post it before
                any GL impact occurs.</p>
            </div>

            <div class="flex gap-3 justify-end">
                <a href="{{ route('finance.refunds.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl">Create Draft</button>
            </div>
        </form>
    </div>
</x-app-layout>
