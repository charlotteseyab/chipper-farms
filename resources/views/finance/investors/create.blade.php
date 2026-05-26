<x-app-layout>
    <div class="max-w-2xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.investors.index') }}" class="text-sm text-blue-600 hover:underline">← Investors</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">New Investor</h1>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.investors.store') }}" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Name</label>
                    <input name="name" value="{{ old('name') }}" required class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Reference</label>
                    <input name="reference" value="{{ old('reference') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Class</label>
                    <select name="investor_class" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="equity">Equity</option>
                        <option value="preferred">Preferred share</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Ownership fraction (0–1, e.g. 0.25)</label>
                    <input name="ownership_percentage" type="number" step="0.000001" min="0" max="1" value="{{ old('ownership_percentage', '0') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Preferred capital (if preferred)</label>
                    <input name="preferred_share_capital" type="number" step="0.01" min="0" value="{{ old('preferred_share_capital', '0') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Preferred return rate (annual fraction)</label>
                    <input name="preferred_return_rate" type="number" step="0.000001" min="0" value="{{ old('preferred_return_rate', '0') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Contact email</label>
                    <input name="contact_email" type="email" value="{{ old('contact_email') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Contact phone</label>
                    <input name="contact_phone" value="{{ old('contact_phone') }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-600 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="pt-2">
                <button class="px-5 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-medium">Create investor</button>
                <p class="mt-2 text-xs text-slate-400">Creating an investor opens a capital account. No money moves until you record a contribution.</p>
            </div>
        </form>
    </div>
</x-app-layout>
