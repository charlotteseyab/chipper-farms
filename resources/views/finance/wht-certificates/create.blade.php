<x-app-layout>
    <div class="max-w-2xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">New WHT Certificate Draft</h1>
            <p class="text-sm text-slate-500">
                Aggregates withholding tax recognised on the supplier's payments inside a period.
                Re-running for the same (supplier, period) returns the existing draft (idempotent).
            </p>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.wht-certificates.store') }}"
              class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                <select name="counterparty_id" required class="w-full rounded-xl border-slate-200 text-sm">
                    <option value="">— Select supplier —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}"
                                @selected(old('counterparty_id', $preselectId) == $s->id)>
                            {{ $s->code }} — {{ $s->name }}
                            @if(! $s->tax_id) ⚠ no TIN @endif
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Suppliers without a TIN cannot be issued a certificate (only drafts).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Period Type</label>
                <select name="period_kind" class="w-full rounded-xl border-slate-200 text-sm">
                    <option value="quarter" @selected(old('period_kind', $defaultPeriod['kind']) === 'quarter')>Quarterly (Ghana GRA standard)</option>
                    <option value="month" @selected(old('period_kind') === 'month')>Monthly</option>
                    <option value="year" @selected(old('period_kind') === 'year')>Annual</option>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Year <span class="text-red-500">*</span></label>
                    <input type="number" name="period_year" required min="2000" max="2100"
                           value="{{ old('period_year', $defaultPeriod['year']) }}"
                           class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Period Index <span class="text-red-500">*</span></label>
                    <input type="number" name="period_index" required min="1" max="12"
                           value="{{ old('period_index', $defaultPeriod['index']) }}"
                           class="w-full rounded-xl border-slate-200 text-sm">
                    <p class="text-xs text-slate-400 mt-1">For quarterly: 1–4. Monthly: 1–12. Annual: 1.</p>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('finance.wht-certificates.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Generate Draft</button>
            </div>
        </form>
    </div>
</x-app-layout>
