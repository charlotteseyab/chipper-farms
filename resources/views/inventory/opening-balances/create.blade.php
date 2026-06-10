<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4" x-data="openingForm({{ json_encode(['kind' => $kind]) }})">
        <div class="mb-6">
            <a href="{{ route('finance.inventory.opening-balances.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Manual Opening Balance Entry</h1>
            <p class="text-sm text-slate-500">Use this for a small number of items. For bulk, upload a CSV.</p>
        </div>

        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.inventory.opening-balances.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Kind</label>
                    <select name="item_kind" x-model="kind" required class="w-full rounded-lg border-slate-200">
                        <option value="raw_material">Raw materials</option>
                        <option value="finished_feed">Finished feed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Cutover date</label>
                    <input type="date" name="cutover_date" required value="{{ old('cutover_date', now()->subDay()->toDateString()) }}"
                           max="{{ now()->toDateString() }}"
                           class="w-full rounded-lg border-slate-200">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Notes</label>
                    <input type="text" name="notes" maxlength="2000" value="{{ old('notes') }}"
                           class="w-full rounded-lg border-slate-200" placeholder="Optional">
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-bold text-slate-800">Rows</h2>
                    <button type="button" @click="addRow()"
                            class="px-3 py-1.5 text-sm bg-slate-100 hover:bg-slate-200 rounded-lg">
                        + Add row
                    </button>
                </div>

                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="text-left py-2 w-1/2">Item</th>
                            <th class="text-right py-2 w-1/6">Quantity</th>
                            <th class="text-right py-2 w-1/6">Unit cost (GH₵)</th>
                            <th class="py-2 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, idx) in rows" :key="idx">
                            <tr class="border-t border-slate-100">
                                <td class="py-2 pr-2">
                                    <template x-if="kind === 'raw_material'">
                                        <select :name="'rows[' + idx + '][raw_material_id]'" x-model="row.raw_material_id"
                                                required class="w-full rounded-lg border-slate-200">
                                            <option value="">— select raw material —</option>
                                            @foreach($materials as $m)
                                                <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->unit }})</option>
                                            @endforeach
                                        </select>
                                    </template>
                                    <template x-if="kind === 'finished_feed'">
                                        <select :name="'rows[' + idx + '][feed_type_id]'" x-model="row.feed_type_id"
                                                required class="w-full rounded-lg border-slate-200">
                                            <option value="">— select feed type —</option>
                                            @foreach($feedTypes as $ft)
                                                <option value="{{ $ft->id }}">{{ $ft->name }}</option>
                                            @endforeach
                                        </select>
                                    </template>
                                </td>
                                <td class="py-2 px-1">
                                    <input type="number" step="0.0001" min="0" required
                                           :name="'rows[' + idx + '][quantity]'" x-model="row.quantity"
                                           class="w-full rounded-lg border-slate-200 text-right font-mono">
                                </td>
                                <td class="py-2 px-1">
                                    <input type="number" step="0.000001" min="0" required
                                           :name="'rows[' + idx + '][unit_cost]'" x-model="row.unit_cost"
                                           class="w-full rounded-lg border-slate-200 text-right font-mono">
                                </td>
                                <td class="py-2 pl-2 text-right">
                                    <button type="button" @click="removeRow(idx)"
                                            class="text-red-500 hover:text-red-700 text-sm" x-show="rows.length > 1">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <button type="submit"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                Save opening balances
            </button>
        </form>
    </div>

    <script>
        function openingForm(init) {
            return {
                kind: init.kind,
                rows: [{ raw_material_id: '', feed_type_id: '', quantity: '', unit_cost: '' }],
                addRow() {
                    this.rows.push({ raw_material_id: '', feed_type_id: '', quantity: '', unit_cost: '' });
                },
                removeRow(i) {
                    this.rows.splice(i, 1);
                },
            };
        }
    </script>
</x-app-layout>
