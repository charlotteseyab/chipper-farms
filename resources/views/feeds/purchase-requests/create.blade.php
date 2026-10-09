<x-app-layout>
    <x-feed-hub hub="store" />
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

        <form action="{{ route('purchase-requests.store') }}" method="POST" id="purchase-request-form">
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
                        <input type="text" name="purpose" value="{{ old('purpose', $prefillNotes ? 'Feed planning shortage' : '') }}"
                               placeholder="Enter purpose" class="w-full rounded-lg border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment arrangement</label>
                        <select name="payment_arrangement" id="payment-arrangement" required class="w-full rounded-lg border-gray-300 shadow-sm">
                            <option value="after_delivery" @selected(old('payment_arrangement', 'after_delivery') === 'after_delivery')>Pay after delivery</option>
                            <option value="before_delivery" @selected(old('payment_arrangement') === 'before_delivery')>Pay before delivery</option>
                            <option value="deposit_balance" @selected(old('payment_arrangement') === 'deposit_balance')>Deposit and balance</option>
                            <option value="supplier_credit" @selected(old('payment_arrangement') === 'supplier_credit')>Supplier credit</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">This controls whether payment or receiving comes next after approval.</p>
                    </div>

                    <div id="deposit-percent-field" class="{{ old('payment_arrangement') === 'deposit_balance' ? '' : 'hidden' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Deposit percentage</label>
                        <div class="relative"><input type="number" name="deposit_percent" value="{{ old('deposit_percent') }}" min="0.01" max="99.99" step="0.01" class="w-full rounded-lg border-gray-300 pr-10 shadow-sm"><span class="absolute right-3 top-2.5 text-slate-500">%</span></div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes" rows="3" placeholder="Additional notes"
                                  class="w-full rounded-lg border-gray-300 shadow-sm">{{ old('notes', $prefillNotes ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden mt-6">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">Request Items</h2>

                    <button type="button" id="add-purchase-item"
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
                        <tbody id="purchase-request-items">
                            @foreach(old('items', $prefillItems ?? [['raw_material_id' => '', 'description' => '', 'unit' => '', 'quantity' => 1, 'unit_price' => 0]]) as $index => $item)
                                @include('feeds.purchase-requests._item-row', ['index' => $index, 'item' => $item])
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-6 flex justify-end">
                        <div class="bg-gray-50 rounded-xl px-5 py-4 border border-gray-200 min-w-[250px]">
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span>Total Amount</span>
                                <span class="font-bold text-lg text-gray-800" id="purchase-request-total">0.00</span>
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

    <template id="purchase-request-item-template">
        @include('feeds.purchase-requests._item-row', [
            'index' => '__INDEX__',
            'item' => ['raw_material_id' => '', 'description' => '', 'unit' => '', 'quantity' => 1, 'unit_price' => 0],
        ])
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const body = document.getElementById('purchase-request-items');
            const template = document.getElementById('purchase-request-item-template');
            const totalOutput = document.getElementById('purchase-request-total');
            const materials = @json($rawMaterials->keyBy('id'));
            const arrangement = document.getElementById('payment-arrangement');
            const depositField = document.getElementById('deposit-percent-field');
            const money = new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            const rows = () => Array.from(body.querySelectorAll('[data-purchase-item-row]'));

            arrangement.addEventListener('change', () => {
                depositField.classList.toggle('hidden', arrangement.value !== 'deposit_balance');
            });

            function updateTotals() {
                let grandTotal = 0;
                rows().forEach((row) => {
                    const quantity = Number.parseFloat(row.querySelector('[data-item-quantity]').value) || 0;
                    const price = Number.parseFloat(row.querySelector('[data-item-price]').value) || 0;
                    const lineTotal = quantity * price;
                    row.querySelector('[data-item-total]').textContent = money.format(lineTotal);
                    grandTotal += lineTotal;
                });
                totalOutput.textContent = money.format(grandTotal);
            }

            function reindexRows() {
                rows().forEach((row, index) => {
                    row.querySelectorAll('[name]').forEach((field) => {
                        field.name = field.name.replace(/items\[[^\]]+\]/, `items[${index}]`);
                    });
                });
            }

            document.getElementById('add-purchase-item').addEventListener('click', () => {
                const index = rows().length;
                const wrapper = document.createElement('tbody');
                wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
                const row = wrapper.firstElementChild;
                body.appendChild(row);
                window.initialiseSearchableSelects?.(row);
                updateTotals();
            });

            body.addEventListener('change', (event) => {
                if (event.target.matches('[data-material-select]') && event.target.value) {
                    const row = event.target.closest('[data-purchase-item-row]');
                    const material = materials[event.target.value];
                    if (material) {
                        const description = row.querySelector('[data-item-description]');
                        if (!description.value.trim()) description.value = material.name;
                        row.querySelector('[data-item-unit]').value = material.unit || '';
                    }
                }
                updateTotals();
            });

            body.addEventListener('input', updateTotals);
            body.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-item]');
                if (!button) return;
                if (rows().length === 1) return;
                button.closest('[data-purchase-item-row]').remove();
                reindexRows();
                updateTotals();
            });

            updateTotals();
        });
    </script>
</x-app-layout>
