<x-app-layout>
    <div class="max-w-2xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.investors.index') }}" class="text-sm text-blue-600 hover:underline">← Investors</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Allocate Profit</h1>
            <p class="text-sm text-gray-500">Distribute a profit pool to equity investors by ownership %.</p>
        </div>

        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
            <strong>Run this AFTER the year-end close.</strong> Allocation moves profit already swept into
            Retained Earnings (3200) into investor capital (3100). Running it before the close, or twice for the
            same year, is blocked.
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <form method="POST" action="{{ route('finance.investors.allocate') }}" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs text-slate-600 mb-1">Fiscal year</label>
                <select name="fiscal_year_id" required class="w-full rounded-xl border-slate-200 text-sm">
                    @foreach($years as $y)
                        <option value="{{ $y->id }}">{{ $y->name ?? ('FY' . $y->id) }} ({{ $y->start_date?->toDateString() }} – {{ $y->end_date?->toDateString() }})</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Method</label>
                    <select name="method" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="ownership_pct">By ownership %</option>
                        <option value="fixed_schedule">Fixed schedule (advanced)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Profit pool (GHS)</label>
                    <input name="profit_pool" type="number" step="0.01" min="0" value="{{ old('profit_pool') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    <p class="text-xs text-slate-400 mt-1">For ownership-% method. Typically the year's net profit (or a board-declared portion).</p>
                </div>
            </div>
            <div>
                <label class="block text-xs text-slate-600 mb-1">Allocation date</label>
                <input name="allocation_date" type="date" value="{{ old('allocation_date') }}" class="w-full rounded-xl border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-600 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea>
            </div>
            <div class="pt-2">
                <button class="px-5 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-medium" onclick="return confirm('Post the profit allocation to the GL?')">Allocate profit</button>
            </div>
        </form>
    </div>
</x-app-layout>
