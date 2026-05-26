<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">New Bank Reconciliation</h1>
            <p class="text-sm text-slate-500">Create a draft reconciliation for a bank account and period.</p>
        </div>

        @if (session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.bank-reconciliation.store') }}" class="space-y-4">
            @csrf
            <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Bank Account</label>
                    <select name="bank_account_id" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">— Pick a bank account —</option>
                        @foreach ($bankAccounts as $ba)
                            <option value="{{ $ba->id }}" {{ ($bankAccount?->id ?? old('bank_account_id')) == $ba->id ? 'selected' : '' }}>
                                {{ $ba->displayName() }} ({{ $ba->currency_code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Period From</label>
                        <input name="period_from" type="date" required value="{{ old('period_from') }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Period To</label>
                        <input name="period_to" type="date" required value="{{ old('period_to') }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Opening Balance per Bank <span class="text-xs text-slate-400">(optional, will carry forward)</span></label>
                        <input name="opening_balance_per_bank" type="number" step="0.01" value="{{ old('opening_balance_per_bank') }}" class="w-full rounded-lg border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Closing Balance per Bank <span class="text-red-500">*</span></label>
                        <input name="closing_balance_per_bank" type="number" step="0.01" required value="{{ old('closing_balance_per_bank') }}" class="w-full rounded-lg border-slate-200 text-sm">
                        <p class="text-xs text-slate-500 mt-1">From your bank's official period-end statement.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('finance.bank-reconciliation.index') }}" class="px-4 py-2 text-sm rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg">Create Reconciliation</button>
            </div>
        </form>
    </div>
</x-app-layout>
