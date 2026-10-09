<x-app-layout>
    <x-feed-hub hub="store" />
    @php
        $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 4), '0'), '.');
        $kinds = ['raw_material' => 'Raw materials', 'finished_feed' => 'Finished feed'];
    @endphp
    <div class="max-w-5xl mx-auto px-4 pt-6 pb-24">
        <div class="mb-6">
            <a href="{{ route('stock-counts.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back to stock counts</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Stock count #{{ $count->id }}</h1>
            <p class="text-sm text-slate-500">
                {{ $count->counted_at->format('j M Y, H:i') }} · by {{ $count->counter?->name ?? '—' }}@if($count->reference) · {{ $count->reference }}@endif
            </p>
        </div>

        @foreach($count->lines->groupBy('item_kind') as $kind => $lines)
            <section class="mb-5 overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm">
                <h2 class="border-b border-slate-100 px-4 py-3 font-bold text-slate-900">{{ $kinds[$kind] ?? $kind }}</h2>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Item</th>
                            <th class="px-4 py-2 text-right">System</th>
                            <th class="px-4 py-2 text-right">Counted</th>
                            <th class="px-4 py-2 text-right">Difference</th>
                            @if($showValues)
                                <th class="px-4 py-2 text-right">Value (GH₵)</th>
                                <th class="px-4 py-2 text-left">Posting</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($lines as $line)
                            @php $diff = (float) $line->difference_qty; @endphp
                            <tr>
                                <td class="px-4 py-2 font-medium text-slate-900">{{ $line->item_name }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ $qty($line->system_qty) }} {{ $line->unit }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ $qty($line->counted_qty) }} {{ $line->unit }}</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold {{ $diff == 0 ? 'text-emerald-700' : ($diff < 0 ? 'text-red-700' : 'text-amber-700') }}">
                                    {{ $diff == 0 ? 'Matches' : ($diff > 0 ? '+' : '').$qty($diff).' '.$line->unit }}
                                </td>
                                @if($showValues)
                                    <td class="px-4 py-2 text-right font-mono">{{ $diff == 0 ? '—' : number_format((float) $line->value_delta, 2) }}</td>
                                    <td class="px-4 py-2 text-xs text-slate-500">{{ $diff == 0 ? '—' : str_replace('_', ' ', $line->effective_posting_status) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach

        @if($count->notes)
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="mb-1 text-xs uppercase text-slate-500">Notes</p>
                <p class="text-sm text-slate-800">{{ $count->notes }}</p>
            </div>
        @endif
    </div>
</x-app-layout>
