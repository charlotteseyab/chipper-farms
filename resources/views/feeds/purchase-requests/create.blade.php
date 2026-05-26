<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Create Purchase Request</h1>
                <p class="text-sm text-gray-500">Add items, optionally linking each one to a catalogued raw material so receipts can be tracked.</p>
            </div>

            <a href="{{ route('purchase-requests.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-semibold rounded-lg transition">
                Back
            </a>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-100 border border-red-200 text-red-700 px-4 py-3">
                <strong class="block mb-2">Please fix the following errors:</strong>
                <ul class="list-disc list-inside text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('purchase-requests.store') }}" method="POST"
              x-data="purchaseRequestForm({{ json_encode(['materials' => $rawMaterials->all()]) }})">
            @csrf

            <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Request Details</h2>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Request Date</label>
                        <input type="date" name="request_date"
                               value="{{ old('request_date', date('Y-m-d')) }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                        <select name="supplier_id" class="w-full rounded-lg border-gray-300 shadow-sm">
                            <option value="">— optional —</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" @selected(old('supplier_id') == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Required before approval if any line links to a raw material.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Purpose</label>
                        <input type="text" name="purpose" value="{{ old('purpose') }}"
                               placeholder="Enter purpose" class="w-full rounded-lg border-gray-300 shadow-sm">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes" rows="3" placeholder="Additional notes"
                                  class="w-full rounded-lg border-gray-300 shadow-sm">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden mt-6">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">Request Items</h2>

                    <button type="button" @click="addItem()"
                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition">
                        + Add Item
                    </button>
                </div>

                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Raw material (optional)</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Description</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Unit</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Qty</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Unit Price</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Total</th>
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-t border-gray-200">
                                    <td class="px-3 py-2">
                                        <select :name="`items[${index}][raw_material_id]`"
                                                x-model="item.raw_material_id"
                                                @change="onMaterialChange(index)"
                                                class="w-full rounded-lg border-gray-300 shadow-sm">
                                            <option value="">— free text —</option>
                                            @foreach($rawMaterials as $rm)
                                                <option value="{{ $rm->id }}">{{ $rm->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text" :name="`items[${index}][description]`"
                                               x-model="item.description"
                                               class="w-full rounded-lg border-gray-300 shadow-sm">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text" :name="`items[${index}][unit]`"
                                               x-model="item.unit" maxlength="20"
                                               class="w-20 rounded-lg border-gray-300 shadow-sm">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" step="0.01" min="0.01"
                                               :name="`items[${index}][quantity]`"
                                               x-model="item.quantity"
                                               class="w-24 rounded-lg border-gray-300 shadow-sm text-right font-mono">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" step="0.01" min="0"
                                               :name="`items[${index}][unit_price]`"
                                               x-model="item.unit_price"
                                               class="w-28 rounded-lg border-gray-300 shadow-sm text-right font-mono">
                                    </td>
                                    <td class="px-3 py-2 text-sm font-semibold text-gray-700">
                                        <span x-text="formatMoney((parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)))"></span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <button type="button" @click="removeItem(index)"
                                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div class="mt-6 flex justify-end">
                        <div class="bg-gray-50 rounded-xl px-5 py-4 border border-gray-200 min-w-[250px]">
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span>Total Amount</span>
                                <span class="font-bold text-lg text-gray-800" x-text="formatMoney(grandTotal())"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('purchase-requests.index') }}"
                   class="inline-flex items-center px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-semibold rounded-lg transition">
                    Cancel
                </a>

                <button type="submit"
                        class="inline-flex items-center px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                    Save Request
                </button>
            </div>
        </form>
    </div>

    <script>
        function purchaseRequestForm(init) {
            return {
                materials: init.materials,
                items: [
                    { raw_material_id: '', description: '', unit: '', quantity: 1, unit_price: 0 }
                ],
                onMaterialChange(index) {
                    const id = this.items[index].raw_material_id;
                    if (id) {
                        const m = this.materials.find(x => String(x.id) === String(id));
                        if (m) {
                            this.items[index].description = this.items[index].description || m.name;
                            this.items[index].unit = m.unit;
                        }
                    }
                },
                addItem() {
                    this.items.push({ raw_material_id: '', description: '', unit: '', quantity: 1, unit_price: 0 });
                },
                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    }
                },
                grandTotal() {
                    return this.items.reduce((total, item) =>
                        total + ((parseFloat(item.quantity || 0)) * (parseFloat(item.unit_price || 0))), 0);
                },
                formatMoney(value) {
                    return new Intl.NumberFormat('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(value || 0);
                }
            };
        }
    </script>
</x-app-layout>
