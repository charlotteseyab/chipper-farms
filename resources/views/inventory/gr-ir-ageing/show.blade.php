<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('inventory.gr-ir-ageing.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">{{ $supplier->name }}</h1>
            <p class="text-sm text-slate-500">Outstanding GR/IR entries.</p>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Received</th>
                        <th class="px-3 py-2 text-left">PR</th>
                        <th class="px-3 py-2 text-left">Raw material</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Unit cost</th>
                        <th class="px-3 py-2 text-right">Amount (GH₵)</th>
                        <th class="px-3 py-2 text-right">Age (days)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($entries as $entry)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-600">{{ $entry->occurred_at?->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-xs">
                                @if($entry->purchaseRequestItem?->purchaseRequest)
                                    {{ $entry->purchaseRequestItem->purchaseRequest->request_no }}
                                @else — @endif
                            </td>
                            <td class="px-3 py-2">{{ optional($entry->rawMaterial)->name }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $entry->quantity, 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $entry->receipt_unit_cost, 6) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-semibold">{{ number_format((float) $entry->receipt_amount, 2) }}</td>
                            <td class="px-3 py-2 text-right">
                                @php $age = $entry->ageDays(); @endphp
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                                    @if($age >= 90) bg-red-50 text-red-700
                                    @elseif($age >= 60) bg-amber-50 text-amber-700
                                    @else bg-slate-100 text-slate-600 @endif">
                                    {{ $age }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
