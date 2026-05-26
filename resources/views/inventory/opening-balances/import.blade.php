<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('inventory.opening-balances.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Upload Opening Balance CSV</h1>
            <p class="text-sm text-slate-500">Bulk-import opening balances from a CSV.</p>
        </div>

        @if(session('error'))<div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.opening-balances.import') }}"
              enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Kind</label>
                    <select name="item_kind" required class="w-full rounded-lg border-slate-200">
                        <option value="raw_material" @selected($kind === 'raw_material')>Raw materials</option>
                        <option value="finished_feed" @selected($kind === 'finished_feed')>Finished feed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Cutover date</label>
                    <input type="date" name="cutover_date" required
                           value="{{ old('cutover_date', now()->subDay()->toDateString()) }}"
                           max="{{ now()->toDateString() }}"
                           class="w-full rounded-lg border-slate-200">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">CSV file</label>
                <input type="file" name="file" accept=".csv,text/csv,application/csv" required
                       class="w-full rounded-lg border-slate-200 text-sm">
                <p class="mt-1 text-xs text-slate-500">
                    Headers (case-insensitive, exact order):
                    <code class="text-slate-700">raw_material_id,quantity,unit_cost</code>
                    or
                    <code class="text-slate-700">feed_type_id,quantity,unit_cost</code>.
                    Use <code class="text-slate-700">.</code> as the decimal separator; no thousands separators.
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Notes</label>
                <textarea name="notes" rows="2" maxlength="2000"
                          class="w-full rounded-lg border-slate-200">{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                Upload and import
            </button>
        </form>
    </div>
</x-app-layout>
