<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.vendor-invoices.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Vendor Invoices</a>
        </div>

        <h1 class="text-2xl font-bold text-gray-800 mb-2">New Vendor Invoice</h1>
        <p class="text-sm text-gray-500 mb-6">Records a bill from a supplier. Created in draft — submit for approval to post.</p>

        @if($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        @if($pr)
            <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                <strong>From PR {{ $pr->request_no }}</strong> — {{ $pr->purpose ?? 'no purpose' }}.
                Lines pre-populated from the {{ $prItems->count() }} PR item(s); adjust to match the supplier's actual bill.
            </div>
        @endif

        <form method="POST" action="{{ route('finance.vendor-invoices.store') }}" id="vi-form" class="space-y-4">
            @csrf
            @if($pr)<input type="hidden" name="purchase_request_id" value="{{ $pr->id }}">@endif

            {{-- Header --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Invoice Header</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                        <select name="counterparty_id" required class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="">Select supplier...</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}"
                                        data-currency="{{ $s->currency_code }}"
                                        data-wht="{{ $s->withholding_percent }}"
                                        data-wht-exempt="{{ $s->withholding_exempt ? '1' : '0' }}"
                                        @selected(old('counterparty_id', $pr?->supplier_id) == $s->id)>
                                    {{ $s->code }} — {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency <span class="text-red-500">*</span></label>
                        <input type="text" name="currency_code" value="{{ old('currency_code', 'GHS') }}" maxlength="3" required class="w-full rounded-xl border-slate-200 text-sm uppercase">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Invoice Date <span class="text-red-500">*</span></label>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" required class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Received Date</label>
                        <input type="date" name="received_at" value="{{ old('received_at') }}" class="w-full rounded-xl border-slate-200 text-sm">
                        <p class="text-xs text-slate-500 mt-0.5">When ops got the bill (optional)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Due Date</label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}" class="w-full rounded-xl border-slate-200 text-sm">
                        <p class="text-xs text-slate-500 mt-0.5">From supplier terms if blank</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Supplier's Invoice Number</label>
                        <input type="text" name="supplier_invoice_number" value="{{ old('supplier_invoice_number') }}" placeholder="e.g. INV-12345 (from supplier's bill)" class="w-full rounded-xl border-slate-200 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                        <input type="text" name="description" value="{{ old('description') }}" class="w-full rounded-xl border-slate-200 text-sm">
                    </div>
                </div>
            </div>

            {{-- Lines --}}
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Lines</h2>
                    <button type="button" onclick="addLine()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-xl">+ Add Line</button>
                </div>

                <div id="lines" class="space-y-3">
                    @php
                        $oldLines = old('lines');
                        if ($oldLines === null && $prItems && $prItems->count() > 0) {
                            // Pre-populate from PR
                            $oldLines = $prItems->map(fn($i) => [
                                'description' => $i->item_name . ($i->description ? ' — ' . $i->description : ''),
                                'quantity'    => $i->quantity,
                                'unit_cost'   => $i->unit_price,
                                'unit'        => $i->unit ?? $i->rawMaterial?->unit,
                                'raw_material_id' => $i->raw_material_id,
                                'purchase_request_item_id' => $i->id,
                                'tax_rate'    => 0,
                                'withholding_rate' => 0,
                            ])->toArray();
                        }
                        if (! $oldLines) {
                            $oldLines = [['description' => '', 'quantity' => 1, 'unit_cost' => 0]];
                        }
                    @endphp

                    @foreach($oldLines as $idx => $line)
                        <div class="line-row rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2">
                            <div class="grid grid-cols-12 gap-2">
                                <div class="col-span-12">
                                    <label class="text-xs text-slate-600">Description <span class="text-red-500">*</span></label>
                                    <input type="text" name="lines[{{ $idx }}][description]" value="{{ $line['description'] ?? '' }}" required class="w-full rounded-lg border-slate-200 text-sm">
                                </div>
                                @if(! empty($line['purchase_request_item_id']))
                                    <input type="hidden" name="lines[{{ $idx }}][purchase_request_item_id]" value="{{ $line['purchase_request_item_id'] }}">
                                @endif

                                <div class="col-span-3">
                                    <label class="text-xs text-slate-600">Item</label>
                                    <select name="lines[{{ $idx }}][raw_material_id]" class="w-full rounded-lg border-slate-200 text-sm">
                                        <option value="">— None —</option>
                                        @foreach($rawMaterials as $rm)
                                            <option value="{{ $rm->id }}" @selected(($line['raw_material_id'] ?? null) == $rm->id)>{{ $rm->name }} ({{ $rm->unit }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs text-slate-600">Qty <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.0001" min="0.0001" name="lines[{{ $idx }}][quantity]" value="{{ $line['quantity'] ?? 1 }}" required class="w-full rounded-lg border-slate-200 text-sm text-right qty">
                                </div>
                                <div class="col-span-1">
                                    <label class="text-xs text-slate-600">Unit</label>
                                    <input type="text" name="lines[{{ $idx }}][unit]" value="{{ $line['unit'] ?? '' }}" maxlength="30" class="w-full rounded-lg border-slate-200 text-sm">
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs text-slate-600">Unit Cost <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.0001" min="0" name="lines[{{ $idx }}][unit_cost]" value="{{ $line['unit_cost'] ?? 0 }}" required class="w-full rounded-lg border-slate-200 text-sm text-right cost">
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs text-slate-600">Tax</label>
                                    <select name="lines[{{ $idx }}][tax_code_id]" class="w-full rounded-lg border-slate-200 text-sm tax-select">
                                        <option value="">— None —</option>
                                        @foreach($taxCodes as $tc)
                                            <option value="{{ $tc->id }}" data-rate="{{ $tc->rate }}" @selected(($line['tax_code_id'] ?? null) == $tc->id)>
                                                {{ $tc->code }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="lines[{{ $idx }}][tax_rate]" value="{{ $line['tax_rate'] ?? 0 }}" class="tax-rate-input">
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs text-slate-600">WHT</label>
                                    <select name="lines[{{ $idx }}][withholding_tax_code_id]" class="w-full rounded-lg border-slate-200 text-sm wht-select">
                                        <option value="">— None —</option>
                                        @foreach($whtCodes as $wht)
                                            <option value="{{ $wht->id }}" data-rate="{{ $wht->rate }}" @selected(($line['withholding_tax_code_id'] ?? null) == $wht->id)>
                                                {{ $wht->code }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="lines[{{ $idx }}][withholding_rate]" value="{{ $line['withholding_rate'] ?? 0 }}" class="wht-rate-input">
                                </div>

                                <div class="col-span-12 flex items-center justify-between text-xs text-slate-500 pt-1">
                                    <span class="line-total italic">Line subtotal: 0.00</span>
                                    <button type="button" onclick="removeLine(this)" class="text-red-600 hover:underline text-xs">Remove</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Two-person rule</p>
                <p class="mt-1 text-xs">This invoice will be created as a <strong>draft</strong>. After review, it must be submitted for approval. A different user with <code class="px-1 bg-amber-100 rounded">approve_vendor_invoice</code> permission must approve and post it before any GL impact occurs.</p>
            </div>

            <div class="flex gap-3 justify-end">
                <a href="{{ route('finance.vendor-invoices.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl">Create Draft</button>
            </div>
        </form>
    </div>

    <script>
        let lineIndex = {{ count($oldLines) }};

        function addLine() {
            const container = document.getElementById('lines');
            const template = container.querySelector('.line-row').cloneNode(true);
            template.querySelectorAll('input, select').forEach(el => {
                el.name = el.name.replace(/lines\[\d+\]/, `lines[${lineIndex}]`);
                if (el.tagName === 'INPUT' && el.type !== 'hidden') {
                    el.value = el.classList.contains('qty') ? 1 : (el.classList.contains('cost') ? 0 : '');
                }
                if (el.tagName === 'SELECT') {
                    el.selectedIndex = 0;
                }
            });
            // Remove any PR item link from cloned line
            template.querySelectorAll('input[name*="purchase_request_item_id"]').forEach(el => el.remove());
            container.appendChild(template);
            lineIndex++;
            wireLine(template);
        }

        function removeLine(btn) {
            const container = document.getElementById('lines');
            if (container.querySelectorAll('.line-row').length <= 1) {
                alert('At least one line required.');
                return;
            }
            btn.closest('.line-row').remove();
            recompute();
        }

        function wireLine(row) {
            row.querySelector('.tax-select')?.addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                row.querySelector('.tax-rate-input').value = opt?.dataset.rate ?? 0;
                recompute();
            });
            row.querySelector('.wht-select')?.addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                row.querySelector('.wht-rate-input').value = opt?.dataset.rate ?? 0;
                recompute();
            });
            row.querySelector('.qty')?.addEventListener('input', recompute);
            row.querySelector('.cost')?.addEventListener('input', recompute);
        }

        function recompute() {
            document.querySelectorAll('.line-row').forEach(row => {
                const q = parseFloat(row.querySelector('.qty')?.value || 0);
                const c = parseFloat(row.querySelector('.cost')?.value || 0);
                const subtotal = (q * c).toFixed(2);
                const span = row.querySelector('.line-total');
                if (span) span.textContent = `Line subtotal: ${subtotal}`;
            });
        }

        document.querySelectorAll('.line-row').forEach(wireLine);
        recompute();
    </script>
</x-app-layout>
