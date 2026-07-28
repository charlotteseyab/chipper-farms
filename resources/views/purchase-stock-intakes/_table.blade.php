<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Batch</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Supplier</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Category</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ordered</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Received</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Purchase Date</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($intakes as $intake)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $intake->batch_number }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $intake->supplier_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $categories[$intake->animal_category] ?? $intake->animal_category }}</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">{{ number_format($intake->quantity_ordered) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-slate-700">{{ $intake->quantity_received === null ? '-' : number_format($intake->quantity_received) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $statuses[$intake->status] ?? $intake->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ optional($intake->purchase_date)->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('purchase-stock-intakes.show', $intake) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center">
                            <div class="text-sm font-semibold text-slate-700">No purchased stock intakes found.</div>
                            <a href="{{ route('purchase-stock-intakes.create') }}" class="mt-3 inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold" style="background:#14532d;color:#ffffff;border:1px solid #14532d;box-shadow:0 8px 18px rgba(20,83,45,.22);">
                                Add Purchased Stock
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $intakes->links() }}</div>
