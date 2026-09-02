<x-app-layout>
    @php
        $tabs = [
            'all' => ['All activity', $purchaseRequests->total() + $directPurchases->total()],
            'requests' => ['Purchase requests', $purchaseRequests->total()],
            'direct' => ['Direct purchases', $directPurchases->total()],
            'invoices' => ['Supplier invoices', $vendorInvoices->total()],
            'payments' => ['Payments', $payments->total()],
        ];
        $statusClasses = [
            'pending' => 'bg-amber-50 text-amber-700 ring-amber-200', 'approved' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'paid' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'posted' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'pending_approval' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'draft' => 'bg-slate-100 text-slate-700 ring-slate-200', 'declined' => 'bg-rose-50 text-rose-700 ring-rose-200',
        ];
    @endphp

    <main class="min-h-screen bg-slate-50/80 py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-5 p-5 sm:p-7 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-[.16em] text-emerald-700"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Purchasing workspace</div>
                        <h1 class="text-3xl font-black tracking-tight text-slate-950">Purchases</h1>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Track requested, invoiced and paid procurement alongside purchases recorded directly in Finance.</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        @can('create', \App\Models\PurchaseRequest::class)
                            <a href="{{ route('purchase-requests.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-800 hover:bg-slate-50">New purchase request</a>
                        @endcan
                        @can('record_purchases')
                            <a href="{{ route('finance.purchases.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-bold text-white hover:bg-slate-800">Record direct purchase</a>
                        @endcan
                    </div>
                </div>
                <div class="grid border-t border-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach([
                        ['Procurement value', $summary['requestedTotal'], 'Approved, paid and completed requests', 'text-slate-950', true],
                        ['PR-linked payments', $summary['paidTotal'], 'Posted cash, bank and MoMo payments', 'text-emerald-700', true],
                        ['Open requests', $summary['openRequestCount'], 'Pending, approved or awaiting completion', 'text-amber-700', false],
                        ['Direct purchases', $summary['directTotal'], 'Purchases entered without a request', 'text-slate-950', true],
                    ] as [$label, $value, $help, $colour, $money])
                        <div class="border-b border-slate-200 p-5 sm:border-r xl:border-b-0 last:border-r-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-black {{ $colour }}">{{ $money ? 'GHS '.number_format($value, 2) : number_format($value) }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            @if (session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 pt-4 sm:px-6">
                    <nav class="flex gap-1 overflow-x-auto" aria-label="Purchase views">
                        @foreach($tabs as $key => [$label, $count])
                            <a href="{{ route('finance.purchases.index', ['tab' => $key]) }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-bold {{ $tab === $key ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">{{ $label }} <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $count }}</span></a>
                        @endforeach
                    </nav>
                </div>
                <form method="GET" class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div><h2 class="font-black text-slate-950">{{ $tabs[$tab][0] }}</h2><p class="mt-1 text-xs text-slate-500">Requests stay linked through supplier invoices and posted payments.</p></div>
                    <div class="flex w-full gap-2 sm:w-auto"><input name="search" value="{{ $search }}" placeholder="Search number or supplier" class="min-h-11 min-w-0 flex-1 rounded-xl border-slate-300 text-sm sm:w-72"><button class="min-h-11 rounded-xl bg-slate-900 px-4 text-sm font-bold text-white">Search</button></div>
                </form>

                @if(in_array($tab, ['all', 'requests'], true))
                    <div class="p-4 sm:p-6">
                        @if($tab === 'all')<h3 class="mb-4 text-sm font-black uppercase tracking-wide text-slate-500">Recent purchase requests</h3>@endif
                        <div class="space-y-3">
                            @forelse($purchaseRequests as $pr)
                                @php
                                    $paid = (float) ($pr->posted_payment_total ?? 0); $total = (float) $pr->total_amount;
                                    $received = $pr->items->count() > 0 && $pr->items->every(fn($item) => $item->is_receipt_closed || (float)$item->quantity_received >= (float)$item->quantity);
                                    $invoice = $pr->vendorInvoices->sortByDesc('id')->first();
                                @endphp
                                <article class="rounded-2xl border border-slate-200 p-4 transition hover:border-slate-300 sm:p-5">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">@can('view', $pr)<a href="{{ route('purchase-requests.show', $pr) }}" class="font-black text-slate-950 hover:text-emerald-700">{{ $pr->request_no }}</a>@else<span class="font-black text-slate-950">{{ $pr->request_no }}</span>@endcan<span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset {{ $statusClasses[$pr->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ str($pr->status)->replace('_', ' ')->title() }}</span></div>
                                            <p class="mt-1 truncate text-sm font-semibold text-slate-700">{{ $pr->supplier?->name ?? 'Supplier not assigned' }} · {{ $pr->purpose ?: 'No purpose provided' }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Requested {{ $pr->request_date?->format('j M Y') }} · {{ $pr->paymentArrangementLabel() }}</p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4 lg:min-w-[590px]">
                                            <div><p class="text-xs font-bold uppercase text-slate-400">Total</p><p class="mt-1 text-sm font-black text-slate-950">GHS {{ number_format($total, 2) }}</p></div>
                                            <div><p class="text-xs font-bold uppercase text-slate-400">Paid</p><p class="mt-1 text-sm font-black {{ $paid > $total + .005 ? 'text-rose-700' : 'text-emerald-700' }}">GHS {{ number_format($paid, 2) }}</p></div>
                                            <div><p class="text-xs font-bold uppercase text-slate-400">Goods</p><p class="mt-1 text-sm font-bold text-slate-700">{{ $received ? 'Received' : 'Outstanding' }}</p></div>
                                            <div><p class="text-xs font-bold uppercase text-slate-400">Invoice</p><p class="mt-1 text-sm font-bold text-slate-700">{{ $invoice ? str($invoice->status)->replace('_', ' ')->title() : 'Not created' }}</p></div>
                                        </div>
                                    </div>
                                    @if($paid > $total + .005)<p class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">Payment exceeds this request by GHS {{ number_format($paid - $total, 2) }}. Review the linked payments.</p>@endif
                                </article>
                            @empty<div class="rounded-2xl border border-dashed border-slate-300 px-5 py-10 text-center text-sm text-slate-500">No purchase requests match this view.</div>@endforelse
                        </div>
                        @if($tab === 'requests')<div class="mt-5">{{ $purchaseRequests->links() }}</div>@endif
                    </div>
                @endif

                @if($tab === 'all')<div class="border-t border-slate-200"></div>@endif
                @if(in_array($tab, ['all', 'direct'], true))
                    <div class="p-4 sm:p-6">
                        @if($tab === 'all')<h3 class="mb-4 text-sm font-black uppercase tracking-wide text-slate-500">Recent direct purchases</h3>@endif
                        <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200">
                            @forelse($directPurchases as $purchase)
                                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><p class="font-black text-slate-950">{{ $purchase->purchase_number }} · {{ $purchase->supplier?->name ?? 'No supplier recorded' }}</p><p class="mt-1 truncate text-sm text-slate-500">{{ $purchase->lines->pluck('description')->join(', ') ?: 'No description' }}</p></div><div class="shrink-0 sm:text-right"><p class="font-black text-slate-950">GHS {{ number_format((float)$purchase->total_amount, 2) }}</p><p class="mt-1 text-xs text-slate-500">{{ $purchase->purchase_date?->format('j M Y') }} · {{ $purchase->isPaidNow() ? 'Paid now' : 'Pay later' }}</p></div></div>
                            @empty<div class="p-10 text-center text-sm text-slate-500">No direct purchases recorded.</div>@endforelse
                        </div>
                        @if($tab === 'direct')<div class="mt-5">{{ $directPurchases->links() }}</div>@endif
                    </div>
                @endif

                @if($tab === 'invoices')
                    <div class="divide-y divide-slate-100">
                        @forelse($vendorInvoices as $invoice)
                            <a href="{{ route('finance.vendor-invoices.show', $invoice) }}" class="flex flex-col gap-3 p-5 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="font-black text-slate-950">{{ $invoice->vendor_invoice_number }} · {{ $invoice->purchaseRequest?->request_no }}</p><p class="mt-1 text-sm text-slate-500">{{ $invoice->supplier?->name ?? 'Supplier unavailable' }} · {{ $invoice->invoice_date?->format('j M Y') }}</p></div><div class="sm:text-right"><span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset {{ $statusClasses[$invoice->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ str($invoice->status)->replace('_', ' ')->title() }}</span><p class="mt-2 text-sm font-black text-slate-950">Balance GHS {{ number_format((float)$invoice->balance_due, 2) }}</p></div></a>
                        @empty<div class="p-10 text-center text-sm text-slate-500">No supplier invoices are linked to purchase requests.</div>@endforelse
                    </div><div class="p-5">{{ $vendorInvoices->links() }}</div>
                @endif

                @if($tab === 'payments')
                    <div class="divide-y divide-slate-100">
                        @forelse($payments as $payment)
                            <a href="{{ route('finance.payments.show', $payment) }}" class="flex flex-col gap-3 p-5 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="font-black text-slate-950">{{ $payment->payment_number }} · {{ $payment->purchaseRequest?->request_no }}</p><p class="mt-1 text-sm text-slate-500">{{ $payment->supplier?->name ?? 'Supplier unavailable' }} · {{ str($payment->purchase_payment_stage ?: 'payment')->replace('_', ' ')->title() }}</p></div><div class="sm:text-right"><p class="font-black text-emerald-700">GHS {{ number_format((float)$payment->amount, 2) }}</p><p class="mt-1 text-xs text-slate-500">Posted {{ $payment->payment_date?->format('j M Y') }}</p></div></a>
                        @empty<div class="p-10 text-center text-sm text-slate-500">No posted payments are linked to purchase requests.</div>@endforelse
                    </div><div class="p-5">{{ $payments->links() }}</div>
                @endif
            </section>
        </div>
    </main>
</x-app-layout>
