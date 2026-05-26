<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.coa.show', $account) }}" class="text-sm text-slate-500 hover:underline">← Back to Account</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">Edit Account</h1>
        <p class="text-sm text-slate-500 mb-6 font-mono">{{ $account->code }} · {{ $account->account_type }} · {{ $account->currency_code }}</p>
        <p class="text-xs text-amber-700 mb-4 italic">Code, type, and currency cannot be changed once an account exists — they would invalidate historical journal references.</p>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.coa.update', $account) }}" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input type="text" name="name" required value="{{ old('name', $account->name) }}" maxlength="200"
                       class="w-full rounded-xl border-slate-200 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('description', $account->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Subtype</label>
                <input type="text" name="account_subtype" value="{{ old('account_subtype', $account->account_subtype) }}"
                       class="w-full rounded-xl border-slate-200 text-sm">
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="allow_manual_journal" value="1" @checked(old('allow_manual_journal', $account->allow_manual_journal)) class="rounded border-slate-300">
                Allow manual journal entries on this account
            </label>

            <div class="flex gap-3 justify-end pt-2">
                <a href="{{ route('finance.coa.show', $account) }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Save Changes</button>
            </div>
        </form>
    </div>
</x-app-layout>
