<x-app-layout>
    <x-feed-hub hub="store" />
    <div class="max-w-5xl mx-auto px-4 pt-6 pb-24">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Stock Counts</h1>
                <p class="text-sm text-slate-500">Physical counts of raw materials and finished feed, and the stock changes they made.</p>
            </div>
            <a href="{{ route('stock-counts.create') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Count stock</a>
        </div>

        <x-glance :cards="$glance" class="mb-6" />

        <div class="data-table-wrap">
          <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Counted</th>
                        <th>Reference</th>
                        <th class="num">Items</th>
                        <th class="num">Differences</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($counts as $count)
                        <tr>
                            <td><a href="{{ route('stock-counts.show', $count) }}" class="font-semibold text-emerald-700 hover:underline">{{ $count->counted_at->format('j M Y, H:i') }}</a></td>
                            <td class="text-slate-600">{{ $count->reference ?: '—' }}</td>
                            <td class="text-right font-mono num">{{ $count->lines_count }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $count->differences_count ? 'font-semibold text-amber-700' : 'text-emerald-700' }}">{{ $count->differences_count ?: 'None' }}</td>
                            <td class="text-xs text-slate-500">{{ $count->counter?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No stock counts yet. A regular count shows feed lost to spillage, spoilage or theft.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
          <div class="data-table-footer"><x-pager :paginator="$counts" label="stock counts" /></div>
        </div>
    </div>
</x-app-layout>
