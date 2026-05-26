<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.coa.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Chart of Accounts</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-6">New Account</h1>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.coa.store') }}" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Code</label>
                    <input type="text" name="code" required value="{{ old('code') }}" placeholder="e.g. 1015"
                           class="w-full rounded-xl border-slate-200 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Account Type</label>
                    <select name="account_type" required class="w-full rounded-xl border-slate-200 text-sm">
                        @foreach(['asset', 'liability', 'equity', 'revenue', 'expense'] as $t)
                            <option value="{{ $t }}" @selected(old('account_type') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input type="text" name="name" required value="{{ old('name') }}" maxlength="200"
                       class="w-full rounded-xl border-slate-200 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description (optional)</label>
                <textarea name="description" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Subtype (optional)</label>
                    <input type="text" name="account_subtype" value="{{ old('account_subtype') }}" placeholder="e.g. current_asset"
                           class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Currency Code</label>
                    <input type="text" name="currency_code" required value="{{ old('currency_code', 'GHS') }}" maxlength="3"
                           class="w-full rounded-xl border-slate-200 text-sm uppercase">
                </div>
            </div>

            <div class="space-y-2">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_control" value="1" @checked(old('is_control')) class="rounded border-slate-300">
                    Mark as control account (for AR/AP/Inventory subledger reconciliation)
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="allow_manual_journal" value="1" @checked(old('allow_manual_journal', true)) class="rounded border-slate-300">
                    Allow manual journal entries on this account
                </label>
            </div>

            <div class="flex gap-3 justify-end pt-2">
                <a href="{{ route('finance.coa.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Create Account</button>
            </div>
        </form>
    </div>
</x-app-layout>
