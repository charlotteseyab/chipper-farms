<x-app-layout>
    <div class="max-w-2xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.loans.index') }}" class="text-sm text-blue-600 hover:underline">← Loans</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">New Loan</h1>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.loans.store') }}" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Reference</label>
                    <input name="reference" value="{{ old('reference') }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Type</label>
                    <select name="loan_type" class="w-full rounded-xl border-slate-200 text-sm">
                        @foreach($types as $key => $cfg)
                            <option value="{{ $key }}">{{ $cfg['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-600 mb-1">Lender name</label>
                <input name="lender_name" value="{{ old('lender_name') }}" required class="w-full rounded-xl border-slate-200 text-sm">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Principal (GHS)</label>
                    <input name="principal_amount" type="number" step="0.01" min="0.01" value="{{ old('principal_amount') }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Annual rate (e.g. 0.24 = 24%)</label>
                    <input name="annual_interest_rate" type="number" step="0.000001" min="0" value="{{ old('annual_interest_rate') }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Term (months)</label>
                    <input name="term_months" type="number" min="1" value="{{ old('term_months') }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Amortisation method</label>
                    <select name="amortisation_method" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="reducing_balance">Reducing balance (equal instalment)</option>
                        <option value="straight_line">Straight line (equal principal)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Origination fee (GHS)</label>
                    <input name="origination_fee" type="number" step="0.01" min="0" value="{{ old('origination_fee', '0') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Disbursement date</label>
                    <input name="disbursement_date" type="date" value="{{ old('disbursement_date') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">First payment date</label>
                    <input name="first_payment_date" type="date" value="{{ old('first_payment_date') }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-600 mb-1">Disburse into (bank account)</label>
                <select name="bank_account_id" class="w-full rounded-xl border-slate-200 text-sm">
                    <option value="">— default operating account —</option>
                    @foreach($bankAccounts as $ba)
                        <option value="{{ $ba->id }}">{{ $ba->label ?? $ba->bank_name }} ({{ $ba->account_number }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-slate-600 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="pt-2">
                <button class="px-5 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-medium">Create draft + generate schedule</button>
                <p class="mt-2 text-xs text-slate-400">Creating a loan generates its amortisation schedule. No money moves until you disburse.</p>
            </div>
        </form>
    </div>
</x-app-layout>
