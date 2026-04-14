<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Edit Purchase Request</h1>
                <p class="text-sm text-gray-500">Update this pending purchase request.</p>
            </div>

            <a href="{{ route('purchase-requests.show', $purchaseRequest) }}"
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

        <form action="{{ route('purchase-requests.update', $purchaseRequest) }}" method="POST" x-data="purchaseRequestEditForm()">
            @csrf
            @method('PUT')

            <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Request Details</h2>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Request Date</label>
                        <input type="date"
                               name="request_date"
                               value="{{ old('request_date', optional($purchaseRequest->request_date)->format('Y-m-d')) }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Purpose</label>
                        <input type="text"
                               name="purpose"
                               value="{{ old('purpose', $purchaseRequest->purpose) }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes"
                                  rows="3"
                                  class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">{{ old('notes', $purchaseRequest->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden mt-6">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">Request Items</h2>

                    <button type="button"
                            @click="addItem()"
                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition">
                        + Add Item
                    </button>
                </div>

                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Description</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Quantity</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Unit Price</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Total</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-t border-gray-200">
                                    <td class="px-4 py-3">
                                        <input type="text"
                                               :name="`items[${index}][description]`"
                                               x-model="item.description"
                                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number"
                                               step="0.01"
                                               min="0.01"
                                               :name="`items[${index}][quantity]`"
                                               x-model="item.quantity"
                                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               :name="`items[${index}][unit_price]`"
                                               x-model="item.unit_price"
                                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-700">
                                        <span x-text="formatMoney((parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)))"></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button"
                                                @click="removeItem(index)"
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
                <a href="{{ route('purchase-requests.show', $purchaseRequest) }}"
                   class="inline-flex items-center px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-semibold rounded-lg transition">
                    Cancel
                </a>

                <button type="submit"
                        class="inline-flex items-center px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                    Update Request
                </button>
            </div>
        </form>
    </div>

    <script>
        function purchaseRequestEditForm() {
            return {
                items: [
                    @foreach(old('items', $purchaseRequest->items->map(fn($item) => [
                        'description' => $item->description,
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                    ])->values()->all()) as $item)
                    {
                        description: @js($item['description']),
                        quantity: @js($item['quantity']),
                        unit_price: @js($item['unit_price']),
                    },
                    @endforeach
                ],

                addItem() {
                    this.items.push({ description: '', quantity: 1, unit_price: 0 });
                },

                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    }
                },

                grandTotal() {
                    return this.items.reduce((total, item) => {
                        return total + ((parseFloat(item.quantity || 0)) * (parseFloat(item.unit_price || 0)));
                    }, 0);
                },

                formatMoney(value) {
                    return new Intl.NumberFormat('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(value || 0);
                }
            }
        }
    </script>
</x-app-layout>