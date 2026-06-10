<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.costing.opening-pig-inventory.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Opening Import #{{ $import->id }}</h1>
            <p class="text-sm text-slate-500">cutover {{ $import->cutover_date?->format('Y-m-d') }} · {{ $import->import_mode }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Status</p>
                @php
                    $badge = match($import->status) {
                        'completed' => 'bg-emerald-50 text-emerald-700',
                        'pending'   => 'bg-amber-50 text-amber-700',
                        'failed'    => 'bg-red-50 text-red-700',
                        'archived'  => 'bg-slate-100 text-slate-500',
                    };
                @endphp
                <p class="mt-1"><span class="px-2 py-0.5 text-sm font-semibold rounded-full {{ $badge }}">{{ $import->status }}</span></p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Declared rows</p>
                <p class="mt-1 text-2xl font-bold text-slate-800">{{ $import->row_count }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">Persisted rows</p>
                <p class="mt-1 text-2xl font-bold {{ $import->persistedRowCount() === $import->row_count ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $import->persistedRowCount() }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500">By</p>
                <p class="mt-1 text-sm font-medium text-slate-800">{{ optional($import->performedBy)->name ?? '—' }}</p>
            </div>
        </div>

        @if($import->error_message)
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
                <p class="font-semibold mb-1">Import failed</p>
                <p>{{ $import->error_message }}</p>
            </div>
        @endif

        @if($import->notes)
            <div class="mb-4 px-4 py-3 rounded-xl bg-slate-50 text-slate-700 text-sm">{{ $import->notes }}</div>
        @endif

        <h2 class="text-lg font-bold text-slate-800 mb-3">Opening movements</h2>
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Group</th>
                        <th class="px-3 py-2 text-right">Pigs</th>
                        <th class="px-3 py-2 text-right">Weight</th>
                        <th class="px-3 py-2 text-right">Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($import->movements as $m)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $m->group?->name ?? 'group#'.$m->group_id }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ $m->pig_count_delta }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->weight_delta, 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $m->value_delta, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No movements recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
