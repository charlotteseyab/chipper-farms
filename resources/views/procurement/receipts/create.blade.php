<x-app-layout>
    <style>
        .receipt-page{max-width:1120px;margin:0 auto;padding:28px 18px 48px}.receipt-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:22px}.receipt-title{margin-top:7px;font-size:1.65rem;line-height:1.2;font-weight:900;color:#0f172a}.receipt-sub{margin-top:6px;font-size:.84rem;color:#64748b}.receipt-badge{display:inline-flex;align-items:center;border-radius:999px;padding:7px 12px;background:#ecfdf5;color:#047857;font-size:.72rem;font-weight:900}.delivery-card,.receipt-lines{border:1px solid #e2e8f0;border-radius:20px;background:#fff;box-shadow:0 7px 22px rgba(15,23,42,.05)}.delivery-card{padding:20px;margin-bottom:18px}.delivery-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.receipt-label{display:block;margin-bottom:6px;font-size:.69rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#64748b}.receipt-input{width:100%;min-height:44px;border:1px solid #cbd5e1;border-radius:11px;font-size:.84rem;color:#0f172a}.receipt-lines{overflow:hidden}.receipt-lines-head{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:18px 20px;border-bottom:1px solid #e2e8f0;background:#f8fafc}.receipt-lines-title{font-size:1rem;font-weight:900;color:#0f172a}.receipt-lines-copy{margin-top:4px;font-size:.76rem;color:#64748b}.receipt-select-all{font-size:.76rem;font-weight:900;color:#047857}.receipt-list{padding:10px}.receipt-row{padding:16px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;transition:.2s ease}.receipt-row+.receipt-row{margin-top:10px}.receipt-row.is-selected{border-color:#86efac;background:#f7fef9;box-shadow:0 0 0 2px rgba(34,197,94,.08)}.receipt-row-head{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:12px;align-items:start}.receipt-check{width:20px;height:20px;margin-top:2px;border-radius:6px;color:#059669}.receipt-material{font-size:.92rem;font-weight:900;color:#0f172a}.receipt-description{margin-top:3px;font-size:.74rem;color:#64748b}.receipt-status{border-radius:999px;padding:5px 9px;background:#fff7ed;color:#c2410c;font-size:.68rem;font-weight:900;white-space:nowrap}.receipt-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:14px 0}.receipt-metric{padding:10px 11px;border-radius:11px;background:#f8fafc}.receipt-metric-label{font-size:.63rem;font-weight:800;text-transform:uppercase;color:#94a3b8}.receipt-metric-value{margin-top:3px;font-size:.8rem;font-weight:800;color:#334155}.receipt-entry{display:grid;grid-template-columns:1fr 1fr 1.25fr;gap:12px;padding-top:13px;border-top:1px solid #e2e8f0}.receipt-entry input:disabled{cursor:not-allowed;background:#f1f5f9;color:#94a3b8}.receipt-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:18px}.receipt-help{max-width:620px;font-size:.76rem;line-height:1.6;color:#64748b}.receipt-submit{min-height:44px;border-radius:12px;padding:0 19px;background:#047857;color:#fff;font-size:.82rem;font-weight:900;box-shadow:0 8px 18px rgba(4,120,87,.2)}@media(max-width:700px){.receipt-page{padding:18px 12px 40px}.receipt-head{display:block}.receipt-badge{margin-top:12px}.delivery-grid,.receipt-entry{grid-template-columns:1fr}.receipt-metrics{grid-template-columns:1fr}.receipt-lines-head,.receipt-footer{align-items:stretch;flex-direction:column}.receipt-submit{width:100%}.receipt-row-head{grid-template-columns:auto minmax(0,1fr)}.receipt-status{grid-column:2;justify-self:start}}
    </style>

    <div class="receipt-page">
        <header class="receipt-head">
            <div>
                <a href="{{ route('purchase-requests.show', $purchaseRequest) }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800">&larr; Back to purchase request</a>
                <h1 class="receipt-title">Receive delivery for {{ $purchaseRequest->request_no }}</h1>
                <p class="receipt-sub">Supplier: <strong class="text-slate-800">{{ optional($purchaseRequest->supplier)->name ?? 'Not assigned' }}</strong></p>
            </div>
            <span class="receipt-badge">{{ $items->count() }} open {{ \Illuminate\Support\Str::plural('material', $items->count()) }}</span>
        </header>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><p class="font-bold">Check the delivery details:</p><ul class="mt-2 list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        @if($items->isEmpty())
            <div class="delivery-card text-center text-slate-500"><p class="font-bold text-slate-800">No materials are outstanding.</p><p class="mt-2 text-sm">Every linked material has been received, or this request has no catalogued raw-material lines.</p></div>
        @else
            <form method="POST" action="{{ route('finance.procurement.receive-against-pr.store', $purchaseRequest) }}" id="multi-receipt-form">
                @csrf

                <section class="delivery-card">
                    <div class="delivery-grid">
                        <div><label class="receipt-label" for="delivery-reference">GRN or delivery-note number</label><input id="delivery-reference" class="receipt-input" type="text" name="reference" maxlength="200" value="{{ old('reference') }}" placeholder="e.g. GRN-2026-0042"></div>
                        <div><label class="receipt-label" for="delivery-notes">Delivery notes</label><input id="delivery-notes" class="receipt-input" type="text" name="notes" maxlength="2000" value="{{ old('notes') }}" placeholder="Optional note applying to this delivery"></div>
                    </div>
                </section>

                <section class="receipt-lines">
                    <div class="receipt-lines-head">
                        <div><h2 class="receipt-lines-title">Materials received in this delivery</h2><p class="receipt-lines-copy">Select every material that arrived, then confirm its actual quantity and unit cost.</p></div>
                        <button type="button" id="receipt-select-all" class="receipt-select-all">Select all outstanding</button>
                    </div>

                    <div class="receipt-list">
                        @foreach($items as $index => $item)
                            @php
                                $unit = $item->unit ?? optional($item->rawMaterial)->unit;
                                $oldLine = old("receipts.{$index}", []);
                                $selected = (bool) ($oldLine['selected'] ?? ($items->count() === 1));
                            @endphp
                            <article class="receipt-row {{ $selected ? 'is-selected' : '' }}" data-receipt-row>
                                <div class="receipt-row-head">
                                    <input type="hidden" name="receipts[{{ $index }}][purchase_request_item_id]" value="{{ $item->id }}">
                                    <input class="receipt-check" type="checkbox" name="receipts[{{ $index }}][selected]" value="1" data-receipt-check @checked($selected) aria-label="Receive {{ optional($item->rawMaterial)->name ?? $item->description }}">
                                    <div><h3 class="receipt-material">{{ optional($item->rawMaterial)->name ?? $item->description }}</h3><p class="receipt-description">{{ $item->description ?: 'Purchase request material' }} · {{ $unit }}</p></div>
                                    <span class="receipt-status">{{ number_format($item->quantityOutstanding(), 2) }} {{ $unit }} outstanding</span>
                                </div>

                                <div class="receipt-metrics">
                                    <div class="receipt-metric"><div class="receipt-metric-label">Ordered</div><div class="receipt-metric-value">{{ number_format((float) $item->quantity, 2) }} {{ $unit }}</div></div>
                                    <div class="receipt-metric"><div class="receipt-metric-label">Previously received</div><div class="receipt-metric-value">{{ number_format((float) $item->quantity_received, 2) }} {{ $unit }}</div></div>
                                    <div class="receipt-metric"><div class="receipt-metric-label">Expected unit cost</div><div class="receipt-metric-value">GH₵ {{ number_format((float) $item->unit_price, 2) }}</div></div>
                                </div>

                                <div class="receipt-entry">
                                    <div><label class="receipt-label" for="receipt-qty-{{ $item->id }}">Quantity received now ({{ $unit }})</label><input id="receipt-qty-{{ $item->id }}" class="receipt-input" type="number" step="0.0001" min="0.0001" name="receipts[{{ $index }}][quantity]" value="{{ $oldLine['quantity'] ?? number_format($item->quantityOutstanding(), 4, '.', '') }}" data-receipt-input @disabled(! $selected)></div>
                                    <div><label class="receipt-label" for="receipt-cost-{{ $item->id }}">Actual unit cost (GH₵)</label><input id="receipt-cost-{{ $item->id }}" class="receipt-input" type="number" step="0.000001" min="0" name="receipts[{{ $index }}][unit_cost]" value="{{ $oldLine['unit_cost'] ?? number_format((float) $item->unit_price, 6, '.', '') }}" data-receipt-input @disabled(! $selected)></div>
                                    <div><label class="receipt-label" for="receipt-notes-{{ $item->id }}">Line note</label><input id="receipt-notes-{{ $item->id }}" class="receipt-input" type="text" maxlength="500" name="receipts[{{ $index }}][notes]" value="{{ $oldLine['notes'] ?? '' }}" placeholder="Optional variance or condition note" data-receipt-input @disabled(! $selected)></div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                @if(auth()->user()?->hasPermission('override_match_variance'))
                    <label class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="allow_over_receipt" value="1" @checked(old('allow_over_receipt'))> Allow quantities above the configured receipt tolerance</label>
                @endif

                <div class="receipt-footer">
                    <p class="receipt-help">All selected materials are saved together. If any selected line fails validation, no stock quantities from this delivery will be changed.</p>
                    <button type="submit" class="receipt-submit">Receive selected materials</button>
                </div>
            </form>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = Array.from(document.querySelectorAll('[data-receipt-row]'));
            const updateRow = (row) => {
                const checked = row.querySelector('[data-receipt-check]').checked;
                row.classList.toggle('is-selected', checked);
                row.querySelectorAll('[data-receipt-input]').forEach((input) => input.disabled = !checked);
            };
            rows.forEach((row) => {
                updateRow(row);
                row.querySelector('[data-receipt-check]').addEventListener('change', () => updateRow(row));
            });
            document.getElementById('receipt-select-all')?.addEventListener('click', () => {
                const shouldSelect = rows.some((row) => !row.querySelector('[data-receipt-check]').checked);
                rows.forEach((row) => {
                    row.querySelector('[data-receipt-check]').checked = shouldSelect;
                    updateRow(row);
                });
            });
        });
    </script>
</x-app-layout>
