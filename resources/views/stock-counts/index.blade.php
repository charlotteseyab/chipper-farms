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

        <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Counted</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-right">Items</th>
                        <th class="px-4 py-3 text-right">Differences</th>
                        <th class="px-4 py-3 text-left">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($counts as $count)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3"><a href="{{ route('stock-counts.show', $count) }}" class="font-semibold text-emerald-700 hover:underline">{{ $count->counted_at->format('j M Y, H:i') }}</a></td>
                            <td class="px-4 py-3 text-slate-600">{{ $count->reference ?: '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $count->lines_count }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $count->differences_count ? 'font-semibold text-amber-700' : 'text-emerald-700' }}">{{ $count->differences_count ?: 'None' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $count->counter?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No stock counts yet. A regular count shows feed lost to spillage, spoilage or theft.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $counts->links() }}</div>
    </div>
</x-app-layout>
