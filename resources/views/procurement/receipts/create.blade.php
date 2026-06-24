<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('purchase-requests.show', $purchaseRequest) }}" class="text-sm text-slate-500 hover:text-slate-700">← Back to PR</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Receive against {{ $purchaseRequest->request_no }}</h1>
            <p class="text-sm text-slate-500">
                Supplier: <strong>{{ optional($purchaseRequest->supplier)->name ?? '(none — assign one on the PR)' }}</strong>
            </p>
        </div>

        @if(session('success'))<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if($items->isEmpty())
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 text-center text-slate-500">
                <p>No open raw-material-linked lines on this PR.</p>
                <p class="mt-1 text-xs">Either all lines are closed, or none of them link to a catalogued raw material.</p>
            </div>
        @else
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-6">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Line</th>
                            <th class="px-3 py-2 text-left">Raw material</th>
                            <th class="px-3 py-2 text-right">Ordered</th>
                            <th class="px-3 py-2 text-right">Received</th>
                            <th class="px-3 py-2 text-right">Outstanding</th>
                            <th class="px-3 py-2 text-right">Unit price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($items as $item)
                            <tr>
                                <td class="px-3 py-2 text-slate-600">#{{ $item->id }}</td>
                                <td class="px-3 py-2 font-medium">
                                    {{ optional($item->rawMaterial)->name ?? $item->description }}
                                    <p class="text-xs text-slate-500">{{ $item->description }}</p>
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit ?? optional($item->rawMaterial)->unit }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $item->quantity_received, 2) }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold">{{ number_format($item->quantityOutstanding(), 2) }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $item->unit_price, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('finance.procurement.receive-against-pr.store', $purchaseRequest) }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">PR line</label>
                        <select name="purchase_request_item_id" required class="w-full rounded-lg border-slate-200">
                            <option value="">— select a line —</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-unit-price="{{ $item->unit_price }}">
                                    Line #{{ $item->id }} — {{ optional($item->rawMaterial)->name }}
                                    (open: {{ number_format($item->quantityOutstanding(), 2) }} {{ $item->unit ?? optional($item->rawMaterial)->unit }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Quantity received</label>
                        <input type="number" step="0.0001" min="0.0001" name="quantity" required
                               value="{{ old('quantity') }}" class="w-full rounded-lg border-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Unit cost (GH₵)</label>
                        <input type="number" step="0.000001" min="0" name="unit_cost" required
                               value="{{ old('unit_cost') }}" class="w-full rounded-lg border-slate-200">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Reference (GRN no.)</label>
                        <input type="text" name="reference" maxlength="200" value="{{ old('reference') }}"
                               class="w-full rounded-lg border-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Notes</label>
                        <input type="text" name="notes" maxlength="2000" value="{{ old('notes') }}"
                               class="w-full rounded-lg border-slate-200">
                    </div>

                    @if(auth()->user()?->hasPermission('override_match_variance'))
                        <div class="sm:col-span-2">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="allow_over_receipt" value="1">
                                <span class="text-sm text-slate-700">Allow over-receipt (override)</span>
                            </label>
                        </div>
                    @endif
                </div>

                <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                    Record receipt
                </button>
            </form>
        @endif
    </div>
</x-app-layout>
