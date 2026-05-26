<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">New Supplier Statement</h1>
            <p class="text-sm text-slate-500">Capture a supplier-issued statement for reconciliation against our AP detail.</p>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.supplier-statements.store') }}"
              class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                <select name="counterparty_id" required class="w-full rounded-xl border-slate-200 text-sm">
                    <option value="">— Select supplier —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}"
                                data-currency="{{ $s->currency_code }}"
                                @selected(old('counterparty_id', $preselectId) == $s->id)>
                            {{ $s->code }} — {{ $s->name }}@if(! $s->tax_id) (no TIN)@endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Statement Date <span class="text-red-500">*</span></label>
                    <input type="date" name="statement_date" required
                           value="{{ old('statement_date', now()->toDateString()) }}"
                           class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Period From <span class="text-red-500">*</span></label>
                    <input type="date" name="period_from" required
                           value="{{ old('period_from', now()->subMonth()->startOfMonth()->toDateString()) }}"
                           class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Period To <span class="text-red-500">*</span></label>
                    <input type="date" name="period_to" required
                           value="{{ old('period_to', now()->toDateString()) }}"
                           class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                    <input type="text" name="currency_code" maxlength="3"
                           value="{{ old('currency_code', 'GHS') }}"
                           class="w-full rounded-xl border-slate-200 text-sm uppercase">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Supplier-stated Balance <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="supplier_balance" required
                           value="{{ old('supplier_balance', '0.00') }}"
                           class="w-full rounded-xl border-slate-200 text-sm">
                    <p class="text-xs text-slate-400 mt-1">The closing balance shown on the supplier's statement at period_to. Negative for credit balance.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Supplier Reference</label>
                <input type="text" name="supplier_reference" maxlength="100"
                       value="{{ old('supplier_reference') }}"
                       class="w-full rounded-xl border-slate-200 text-sm"
                       placeholder="If the supplier prints a reference number on their statement.">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                <textarea name="notes" rows="3" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('finance.supplier-statements.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Create Draft</button>
            </div>
        </form>
    </div>
</x-app-layout>
