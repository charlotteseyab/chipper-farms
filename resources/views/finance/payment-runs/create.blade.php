<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.payment-runs.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                <i class="fa-solid fa-arrow-left mr-1"></i> All Payment Runs
            </a>
        </div>

        <h1 class="text-2xl font-bold text-slate-800 mb-2">New Payment Run</h1>
        <p class="text-sm text-slate-500 mb-6">Creates a draft run. Add invoices as lines, then execute to materialise payments.</p>

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

        <form method="POST" action="{{ route('finance.payment-runs.store') }}" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Run date *</label>
                    <input type="date" name="run_date" value="{{ old('run_date', now()->toDateString()) }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Currency *</label>
                    <input type="text" name="currency_code" value="{{ old('currency_code', 'GHS') }}" required maxlength="3" class="w-full rounded-xl border-slate-200 text-sm font-mono uppercase">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Default payment method</label>
                    <select name="default_payment_method_id" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">— none —</option>
                        @foreach($methods as $m)
                            <option value="{{ $m->id }}" @selected(old('default_payment_method_id') == $m->id)>{{ $m->name }} ({{ $m->code }})</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Lines without a per-line override use this method.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Default source GL account</label>
                    <select name="default_source_account_id" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Use method hint</option>
                        @foreach($cashAccounts as $a)
                            <option value="{{ $a->id }}" @selected(old('default_source_account_id') == $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <input type="text" name="description" maxlength="500" value="{{ old('description') }}" class="w-full rounded-xl border-slate-200 text-sm" placeholder="e.g. Friday feed-supplier payments — week 49">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" maxlength="2000" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-200">
                <a href="{{ route('finance.payment-runs.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl">Create Draft Run</button>
            </div>
        </form>
    </div>
</x-app-layout>
