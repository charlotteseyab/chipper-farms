<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @php
            $statusBadge = match ($cert->status) {
                'draft'  => 'bg-slate-200 text-slate-800',
                'issued' => 'bg-emerald-100 text-emerald-800',
                'void'   => 'bg-slate-300 text-slate-600',
                default  => 'bg-slate-100 text-slate-700',
            };
            $rows = $cert->lines_snapshot['allocations'] ?? [];
            $supplierTinDisplay = $cert->isIssued()
                ? ($cert->supplier_tin_snapshot ?: '—')
                : ($cert->counterparty->tax_id ?: 'Warning none on file');
        @endphp

        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ $cert->certificate_number ?: 'Draft Certificate #' . $cert->id }}
                    </h1>
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                        {{ strtoupper($cert->status) }}
                    </span>
                </div>
                <p class="text-sm text-slate-600">{{ $cert->counterparty->name }} <span class="text-slate-400">({{ $cert->counterparty->code }})</span></p>
                <p class="text-xs text-slate-400 mt-1">{{ $cert->periodLabel() }} · {{ $cert->period_from->format('Y-m-d') }} → {{ $cert->period_to->format('Y-m-d') }}</p>
            </div>
            <a href="{{ route('finance.wht-certificates.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← Back</a>
        </div>

        {{-- Identity card --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <h3 class="text-xs uppercase tracking-wide text-slate-500 font-semibold mb-2">Recipient (Supplier)</h3>
                <p class="font-semibold text-slate-800">{{ $cert->isIssued() ? $cert->supplier_name_snapshot : $cert->counterparty->name }}</p>
                <p class="text-sm text-slate-600 mt-1">TIN: <span class="font-mono">{{ $supplierTinDisplay }}</span></p>
                @if($cert->isIssued() && $cert->supplier_address_snapshot)
                    <p class="text-xs text-slate-500 mt-1">{{ $cert->supplier_address_snapshot }}</p>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <h3 class="text-xs uppercase tracking-wide text-slate-500 font-semibold mb-2">Issuer (Us)</h3>
                <p class="font-semibold text-slate-800">{{ $cert->issuer_name_snapshot ?: config('finance.statements.company_name', config('app.name')) }}</p>
                <p class="text-sm text-slate-600 mt-1">TIN: <span class="font-mono">{{ $cert->issuer_tin_snapshot ?: (config('finance.statements.company_tin') ?: 'Warning not configured') }}</span></p>
            </div>
        </div>

        {{-- Totals --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Gross Paid (allocations)</p>
                <p class="text-2xl font-bold text-slate-700 mt-1">{{ number_format((float) $cert->total_amount_paid, 2) }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ $cert->currency_code }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Tax Withheld</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format((float) $cert->total_withheld, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Allocations</p>
                <p class="text-2xl font-bold text-slate-700 mt-1">{{ $cert->allocation_count }}</p>
                <p class="text-xs text-slate-400 mt-1">across {{ $cert->payment_count }} payment(s)</p>
            </div>
        </div>

        {{-- Action bar --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4 flex flex-wrap gap-2">
            @if($cert->canRefresh())
                <form method="POST" action="{{ route('finance.wht-certificates.refresh', $cert) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl">
                        <i class="fa-solid fa-arrows-rotate mr-2"></i> Refresh totals
                    </button>
                </form>
            @endif

            @if($cert->canIssue() && auth()->user()?->hasPermission('issue_wht_certificate'))
                <form method="POST" action="{{ route('finance.wht-certificates.issue', $cert) }}"
                      onsubmit="return confirm('Issue this certificate? This locks underlying allocations from reversal until the certificate is voided.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                        <i class="fa-solid fa-stamp mr-2"></i> Issue Certificate
                    </button>
                </form>
            @elseif($cert->canIssue() && ! auth()->user()?->hasPermission('issue_wht_certificate'))
                <div class="text-xs text-slate-500 italic px-3 py-2">
                    Issuance requires issue_wht_certificate permission (treasurer/admin only).
                </div>
            @endif

            @if($cert->canVoid())
                <form method="POST" action="{{ route('finance.wht-certificates.void', $cert) }}"
                      onsubmit="this.querySelector('input[name=reason]').value = (prompt('Reason for voiding this certificate? (Required, min 5 chars)') || ''); return this.querySelector('input[name=reason]').value.length >= 5 && confirm('Void this certificate? This unlocks underlying allocations.');">
                    @csrf
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl">
                        <i class="fa-solid fa-ban mr-2"></i> Void
                    </button>
                </form>
            @endif

            @if($cert->isIssued() && $cert->pdf_path)
                <a href="{{ route('finance.wht-certificates.pdf', $cert) }}"
                   class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 text-sm font-semibold rounded-xl">
                    <i class="fa-solid fa-file-pdf mr-2"></i> Download PDF
                </a>
            @endif
        </div>

        @if(! $cert->isIssued() && bccomp((string) $cert->total_withheld, '0', 2) <= 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 mb-4">
                <i class="fa-solid fa-circle-info mr-1"></i>
                No withholding contributing in this period. Check that:
                <ul class="list-disc pl-5 mt-1 text-xs">
                    <li>Posted, non-backfilled payments to this supplier exist within {{ $cert->period_from->format('Y-m-d') }} → {{ $cert->period_to->format('Y-m-d') }}</li>
                    <li>Allocations on those payments have <code>withholding_amount &gt; 0</code></li>
                    <li>Allocations are still <code>active</code> (not reversed)</li>
                </ul>
            </div>
        @endif

        {{-- Contributing allocations --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">
                    Contributing Allocations
                    @if($cert->isIssued())
                        <span class="ml-2 text-xs text-slate-500 font-normal">(snapshot at issue, frozen)</span>
                    @else
                        <span class="ml-2 text-xs text-slate-500 font-normal">(live data)</span>
                    @endif
                </h2>
            </div>
            @if(empty($rows))
                <div class="p-6 text-center text-sm text-slate-500">No contributing allocations yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">Payment Date</th>
                                <th class="px-3 py-2 text-left">Payment #</th>
                                <th class="px-3 py-2 text-left">Our VI #</th>
                                <th class="px-3 py-2 text-left">Their Inv #</th>
                                <th class="px-3 py-2 text-right">Allocated (Gross)</th>
                                <th class="px-3 py-2 text-right">Withheld</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($rows as $r)
                                <tr>
                                    <td class="px-3 py-2 text-xs">{{ $r['payment_date'] ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">
                                        @if(! empty($r['payment_id']) && \App\Support\Features\FinanceHubs::canOpen('finance.payments.show', auth()->user()))
                                            <a href="{{ route('finance.payments.show', $r['payment_id']) }}" class="text-emerald-700 hover:underline">{{ $r['payment_number'] ?? ('#' . $r['payment_id']) }}</a>
                                        @else
                                            {{ $r['payment_number'] ?? ('#' . ($r['payment_id'] ?? '')) }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs">
                                        @if(\Illuminate\Support\Facades\Route::has('finance.vendor-invoices.show') && ! empty($r['vendor_invoice_id']))
                                            <a href="{{ route('finance.vendor-invoices.show', $r['vendor_invoice_id']) }}" class="text-emerald-700 hover:underline">{{ $r['vendor_invoice_number'] ?? ('#' . $r['vendor_invoice_id']) }}</a>
                                        @else
                                            {{ $r['vendor_invoice_number'] ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $r['supplier_invoice_number'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) ($r['amount'] ?? 0), 2) }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-emerald-700">{{ number_format((float) ($r['withholding_amount'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-100 font-semibold">
                                <td colspan="4" class="px-3 py-2 text-right text-xs uppercase">Total</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $cert->total_amount_paid, 2) }}</td>
                                <td class="px-3 py-2 text-right text-emerald-700">{{ number_format((float) $cert->total_withheld, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Audit trail --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 text-sm space-y-1">
            <div class="text-xs text-slate-400">
                Created by {{ optional($cert->createdBy)->name ?: '—' }} on {{ $cert->created_at?->format('Y-m-d H:i') }}
            </div>
            @if($cert->issued_at)
                <div class="text-xs text-emerald-700">
                    Issued by {{ optional($cert->issuedBy)->name ?: '—' }} on {{ $cert->issued_at->format('Y-m-d H:i') }}
                </div>
            @endif
            @if($cert->voided_at)
                <div class="text-xs text-red-700">
                    Voided by {{ optional($cert->voidedBy)->name ?: '—' }} on {{ $cert->voided_at->format('Y-m-d H:i') }}
                    @if($cert->void_reason)
                        — {{ $cert->void_reason }}
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
