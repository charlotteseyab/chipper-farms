<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4" x-data="openingPoolForm({{ json_encode(['groups' => $groups->all()]) }})">
        <div class="mb-6">
            <a href="{{ route('finance.costing.opening-pig-inventory.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back</a>
            <h1 class="text-2xl font-bold text-slate-800 mt-2">Open Pig Pools at Cutover</h1>
            <p class="text-sm text-slate-500">Declare the starting carrying value per existing group. Each row plants an opening movement and stamps the pool as open.</p>
        </div>

        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.costing.opening-pig-inventory.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Cutover date</label>
                    <input type="date" name="cutover_date" required
                           value="{{ old('cutover_date', now()->subDay()->toDateString()) }}"
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
                    <h2 class="font-bold text-slate-800">Group pool entries</h2>
                    <button type="button" @click="addRow()"
                            class="px-3 py-1.5 text-sm bg-slate-100 hover:bg-slate-200 rounded-lg">
                        + Add row
                    </button>
                </div>

                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="text-left py-2 w-2/6">Group</th>
                            <th class="text-right py-2 w-1/6">Pig count</th>
                            <th class="text-right py-2 w-1/6">Total weight (kg)</th>
                            <th class="text-right py-2 w-1/6">Carrying value</th>
                            <th class="py-2 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, idx) in rows" :key="idx">
                            <tr class="border-t border-slate-100">
                                <td class="py-2 pr-2">
                                    <select :name="'rows[' + idx + '][group_id]'" x-model="row.group_id"
                                            required class="w-full rounded-lg border-slate-200">
                                        <option value="">— select group —</option>
                                        @foreach($groups as $g)
                                            <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->type }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2 px-1">
                                    <input type="number" step="1" min="0" required
                                           :name="'rows[' + idx + '][pig_count]'" x-model="row.pig_count"
                                           class="w-full rounded-lg border-slate-200 text-right font-mono">
                                </td>
                                <td class="py-2 px-1">
                                    <input type="number" step="0.0001" min="0" required
                                           :name="'rows[' + idx + '][total_weight_kg]'" x-model="row.total_weight_kg"
                                           class="w-full rounded-lg border-slate-200 text-right font-mono">
                                </td>
                                <td class="py-2 px-1">
                                    <input type="number" step="0.0001" min="0" required
                                           :name="'rows[' + idx + '][total_value]'" x-model="row.total_value"
                                           class="w-full rounded-lg border-slate-200 text-right font-mono">
                                </td>
                                <td class="py-2 pl-2 text-right">
                                    <button type="button" @click="removeRow(idx)"
                                            class="text-red-500 hover:text-red-700 text-sm" x-show="rows.length > 1">x</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <button type="submit"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                Open pools
            </button>
        </form>
    </div>

    <script>
        function openingPoolForm(init) {
            return {
                groups: init.groups,
                rows: [{ group_id: '', pig_count: 0, total_weight_kg: 0, total_value: 0 }],
                addRow() {
                    this.rows.push({ group_id: '', pig_count: 0, total_weight_kg: 0, total_value: 0 });
                },
                removeRow(i) {
                    this.rows.splice(i, 1);
                },
            };
        }
    </script>
</x-app-layout>
