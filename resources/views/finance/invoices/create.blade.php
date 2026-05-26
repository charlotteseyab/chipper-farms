<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.invoices.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Invoices</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">New Invoice</h1>
        <p class="text-sm text-gray-500 mb-6">Add at least one line. Header totals compute from lines automatically. Saves as draft — post later from the show page.</p>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('finance.invoices.store') }}" x-data="invoiceForm()" id="invoice-form">
            @csrf

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Header</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer <span class="text-red-500">*</span></label>
                        <select name="counterparty_id" required class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">Select a customer...</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-currency="{{ $c->currency_code }}" @selected(old('counterparty_id') == $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency <span class="text-red-500">*</span></label>
                        <input type="text" name="currency_code" value="{{ old('currency_code', 'GHS') }}" maxlength="3" required class="w-full rounded-xl border-slate-200 text-sm uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Invoice Date <span class="text-red-500">*</span></label>
                        <input type="date" name="invoice_date" required value="{{ old('invoice_date', now()->toDateString()) }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Due Date</label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}" class="w-full rounded-xl border-slate-200 text-sm">
                        <p class="text-xs text-slate-400 mt-1">Blank → uses customer's payment terms.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method (cash invoices only)</label>
                        <select name="payment_method_id" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">— Credit invoice (AR) —</option>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->id }}" @selected(old('payment_method_id') == $pm->id)>{{ $pm->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer Reference</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" placeholder="PO number, etc." class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded-xl border-slate-200 text-sm">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Lines</h2>
                    <button type="button" @click="addLine()" class="text-sm text-emerald-700 hover:underline font-medium">+ Add line</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-2 py-2 text-left">Description</th>
                                <th class="px-2 py-2 text-right w-24">Qty</th>
                                <th class="px-2 py-2 text-left w-20">UOM</th>
                                <th class="px-2 py-2 text-right w-28">Unit Price</th>
                                <th class="px-2 py-2 text-right w-20">Disc %</th>
                                <th class="px-2 py-2 text-right w-20">Tax %</th>
                                <th class="px-2 py-2 text-right w-32">Line Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, idx) in lines" :key="idx">
                                <tr>
                                    <td class="py-2 pr-2">
                                        <input type="text" :name="`lines[${idx}][description]`" x-model="line.description" required class="w-full rounded-lg border-slate-200 text-sm">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" step="0.0001" min="0.0001" :name="`lines[${idx}][quantity]`" x-model="line.quantity" required class="w-full rounded-lg border-slate-200 text-sm text-right">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="text" :name="`lines[${idx}][unit_of_measure]`" x-model="line.unit_of_measure" maxlength="20" class="w-full rounded-lg border-slate-200 text-sm">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" step="0.0001" min="0" :name="`lines[${idx}][unit_price]`" x-model="line.unit_price" required class="w-full rounded-lg border-slate-200 text-sm text-right">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" step="0.01" min="0" max="100" :name="`lines[${idx}][discount_percent]`" x-model="line.discount_percent" class="w-full rounded-lg border-slate-200 text-sm text-right">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" step="0.01" min="0" :name="`lines[${idx}][tax_rate_percent]`" x-model="line.tax_rate_percent" class="w-full rounded-lg border-slate-200 text-sm text-right">
                                    </td>
                                    <td class="py-2 pr-2 text-right font-medium" x-text="lineTotal(line).toFixed(2)"></td>
                                    <td class="py-2 text-right">
                                        <button type="button" @click="removeLine(idx)" :disabled="lines.length <= 1" class="text-red-500 hover:text-red-700 disabled:text-slate-300 text-xs">Remove</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-slate-50 text-sm">
                            <tr>
                                <td colspan="6" class="px-2 py-2 text-right text-slate-600">Subtotal</td>
                                <td class="px-2 py-2 text-right" x-text="subtotal().toFixed(2)"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="6" class="px-2 py-2 text-right text-slate-600">Discount</td>
                                <td class="px-2 py-2 text-right text-slate-500" x-text="totalDiscount().toFixed(2)"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="6" class="px-2 py-2 text-right text-slate-600">Tax</td>
                                <td class="px-2 py-2 text-right text-slate-500" x-text="totalTax().toFixed(2)"></td>
                                <td></td>
                            </tr>
                            <tr class="bg-slate-100 font-semibold text-base">
                                <td colspan="6" class="px-2 py-3 text-right">Total Due</td>
                                <td class="px-2 py-3 text-right" x-text="grandTotal().toFixed(2)"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex gap-3 justify-end pt-2">
                <a href="{{ route('finance.invoices.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">Save Draft</button>
            </div>
        </form>

        <script>
            function invoiceForm() {
                return {
                    lines: [
                        { description: '', quantity: '1', unit_of_measure: 'unit', unit_price: '', discount_percent: '0', tax_rate_percent: '0' },
                    ],
                    addLine() {
                        this.lines.push({ description: '', quantity: '1', unit_of_measure: 'unit', unit_price: '', discount_percent: '0', tax_rate_percent: '0' });
                    },
                    removeLine(idx) {
                        if (this.lines.length > 1) this.lines.splice(idx, 1);
                    },
                    lineSubtotal(line) {
                        const q = parseFloat(line.quantity) || 0;
                        const p = parseFloat(line.unit_price) || 0;
                        return Math.round(q * p * 100) / 100;
                    },
                    lineDiscount(line) {
                        const sub = this.lineSubtotal(line);
                        const pct = parseFloat(line.discount_percent) || 0;
                        return Math.round(sub * pct) / 100;
                    },
                    lineTax(line) {
                        const sub = this.lineSubtotal(line);
                        const disc = this.lineDiscount(line);
                        const pct = parseFloat(line.tax_rate_percent) || 0;
                        return Math.round((sub - disc) * pct) / 100;
                    },
                    lineTotal(line) {
                        return this.lineSubtotal(line) - this.lineDiscount(line) + this.lineTax(line);
                    },
                    subtotal() {
                        return this.lines.reduce((s, l) => s + this.lineSubtotal(l), 0);
                    },
                    totalDiscount() {
                        return this.lines.reduce((s, l) => s + this.lineDiscount(l), 0);
                    },
                    totalTax() {
                        return this.lines.reduce((s, l) => s + this.lineTax(l), 0);
                    },
                    grandTotal() {
                        return this.subtotal() - this.totalDiscount() + this.totalTax();
                    },
                };
            }
        </script>
    </div>
</x-app-layout>
