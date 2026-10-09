<x-app-layout>
    <x-feed-hub hub="store" />
    @php
        $statusClasses = match($purchaseRequest->status) {
            'approved' => 'bg-green-100 text-green-800',
            'paid' => 'bg-emerald-100 text-emerald-800',
            'completed' => 'bg-emerald-100 text-emerald-900',
            'declined' => 'bg-red-100 text-red-800',
            'pending' => 'bg-blue-100 text-blue-800',
            default => 'bg-yellow-100 text-yellow-800',
        };
        $hasRawMaterialItems = $purchaseRequest->items->contains(fn ($item) => filled($item->raw_material_id));
        $receiptComplete = ! $hasRawMaterialItems || $purchaseRequest->items->whereNotNull('raw_material_id')->every(fn ($item) => $item->is_receipt_closed);
        $activeInvoices = $purchaseRequest->vendorInvoices->whereNotIn('status', ['cancelled', 'reversed']);
        $postedInvoices = $activeInvoices->where('status', 'posted');
        $paidAmount = (float) $purchaseRequest->payments->sum('amount');
        $requiredAdvance = $purchaseRequest->payment_arrangement === 'deposit_balance'
            ? (float) $purchaseRequest->total_amount * (float) $purchaseRequest->deposit_percent / 100
            : (float) $purchaseRequest->total_amount;
        $advanceSatisfied = ! $purchaseRequest->requiresAdvancePayment() || $paidAmount + 0.005 >= $requiredAdvance;
        $invoicesSettled = $postedInvoices->isNotEmpty() && $postedInvoices->every(fn ($invoice) => (float) $invoice->balance_due <= 0.005);
        $readyToComplete = $receiptComplete && $invoicesSettled;
    @endphp

    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Purchase Request Details</h1>
                <p class="text-sm text-gray-500">View purchase request information and requested items.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if($purchaseRequest->status === 'pending')
                    @can('approve', $purchaseRequest)
                        <form action="{{ route('purchase-requests.approve', $purchaseRequest) }}" method="POST">@csrf
                            <button type="submit" class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700">Approve Request</button>
                        </form>
                    @endcan
                    @can('decline', $purchaseRequest)
                        <form action="{{ route('purchase-requests.decline', $purchaseRequest) }}" method="POST" class="flex flex-wrap items-center gap-2">@csrf
                            <input type="text" name="decline_reason" value="{{ old('decline_reason') }}" placeholder="Reason for decline" required class="w-56 rounded-lg border-gray-300 text-sm">
                            <button type="submit" class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Decline Request</button>
                        </form>
                    @endcan
                @endif

                @if(in_array($purchaseRequest->status, ['approved', 'paid'], true) && $purchaseRequest->requiresAdvancePayment() && ! $advanceSatisfied && auth()->user()?->hasPermission('create_payment'))
                    <a href="{{ route('finance.payments.create', ['purchase_request_id' => $purchaseRequest->id]) }}" class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">
                        {{ $purchaseRequest->payment_arrangement === 'deposit_balance' ? 'Record Deposit' : 'Record Advance Payment' }}
                    </a>
                @endif

                @if(in_array($purchaseRequest->status, ['approved', 'paid'], true) && $advanceSatisfied && ! $receiptComplete && auth()->user()?->hasPermission('receive_against_pr'))
                    <a href="{{ route('finance.procurement.receive-against-pr.create', $purchaseRequest) }}" class="inline-flex items-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700">Receive Goods</a>
                @endif

                @if(in_array($purchaseRequest->status, ['approved', 'paid'], true) && $activeInvoices->isEmpty() && auth()->user()?->hasAnyPermission(['create_vendor_invoice', 'create_purchase_request_vendor_invoice']))
                    <a href="{{ route('finance.vendor-invoices.create', ['purchase_request_id' => $purchaseRequest->id]) }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Create Vendor Invoice</a>
                @endif

                @foreach($purchaseRequest->vendorInvoices as $vendorInvoice)
                    <a href="{{ route('finance.vendor-invoices.show', $vendorInvoice) }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">View {{ $vendorInvoice->vendor_invoice_number }}</a>
                @endforeach

                @php $invoiceToPay = $postedInvoices->first(fn ($invoice) => (float) $invoice->balance_due > 0.005); @endphp
                @if($invoiceToPay && auth()->user()?->hasPermission('create_payment'))
                    <a href="{{ route('finance.payments.create', ['purchase_request_id' => $purchaseRequest->id, 'vendor_invoice_id' => $invoiceToPay->id]) }}" class="inline-flex items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">{{ $paidAmount > 0 ? 'Pay Balance' : 'Record Payment' }}</a>
                @endif

                @foreach($purchaseRequest->payments as $payment)
                    <a href="{{ route('finance.payments.show', $payment) }}" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800">View {{ $payment->payment_number }}</a>
                @endforeach

                @if($readyToComplete)
                    @can('complete', $purchaseRequest)
                        <form method="POST" action="{{ route('purchase-requests.complete', $purchaseRequest) }}">@csrf
                            <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Complete Purchase</button>
                        </form>
                    @endcan
                @endif

                @can('update', $purchaseRequest)
                    <a href="{{ route('purchase-requests.edit', $purchaseRequest) }}"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                        Edit Request
                    </a>
                @endcan

                @can('delete', $purchaseRequest)
                    <form action="{{ route('purchase-requests.destroy', $purchaseRequest) }}" method="POST"
                          onsubmit="return confirm('Are you sure you want to delete this purchase request?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                            Delete Request
                        </button>
                    </form>
                @endcan

                <a href="{{ route('purchase-requests.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-semibold rounded-lg transition">
                    Back
                </a>
            </div>
        </div>

        @if(! in_array($purchaseRequest->status, ['pending', 'declined'], true))
            <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Arrangement</p><p class="mt-2 font-bold text-slate-900">{{ $purchaseRequest->paymentArrangementLabel() }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Delivery</p><p class="mt-2 font-bold {{ $receiptComplete ? 'text-emerald-700' : 'text-amber-700' }}">{{ $receiptComplete ? 'Fully received' : ($advanceSatisfied ? 'Awaiting goods' : 'Waiting for advance') }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Invoice</p><p class="mt-2 font-bold text-slate-900">{{ $postedInvoices->isNotEmpty() ? 'Posted' : ($activeInvoices->isNotEmpty() ? 'In progress' : 'Not recorded') }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Payment</p><p class="mt-2 font-bold text-slate-900">GHS {{ number_format($paidAmount, 2) }} recorded</p><p class="mt-1 text-xs text-slate-500">Request total: GHS {{ number_format((float) $purchaseRequest->total_amount, 2) }}</p></div>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden mb-6">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800">Request Information</h2>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-gray-500 mb-1">Request No</p>
                    <p class="text-base font-semibold text-gray-800">{{ $purchaseRequest->request_no }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 mb-1">Request Type</p>
                    <p class="inline-flex px-3 py-1 rounded-full text-sm font-semibold {{ ($purchaseRequest->purchase_type ?? 'other') === 'feed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                        {{ ($purchaseRequest->purchase_type ?? 'other') === 'feed' ? 'Feed Purchase' : 'Other Purchase' }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 mb-1">Request Date</p>
                    <p class="text-base font-semibold text-gray-800">
                        {{ optional($purchaseRequest->request_date)->format('M d, Y') ?? $purchaseRequest->request_date }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 mb-1">Supplier</p>
                    @if($purchaseRequest->supplier)
                        <p class="text-base font-semibold text-gray-800">{{ $purchaseRequest->supplier->name }}</p>
                    @else
                        <p class="text-base font-semibold {{ $hasRawMaterialItems ? 'text-amber-700' : 'text-gray-500' }}">
                            Not assigned
                        </p>
                        @if($hasRawMaterialItems)
                            <p class="mt-1 text-xs font-medium text-amber-700">
                                Required before approval because this request includes raw-material-linked items.
                            </p>
                        @endif
                    @endif
                </div>

                <div>
                    <p class="text-sm text-gray-500 mb-1">Requested By</p>
                    <p class="text-base font-semibold text-gray-800">
                        {{ trim(($purchaseRequest->requester->first_name ?? '') . ' ' . ($purchaseRequest->requester->last_name ?? '')) ?: ($purchaseRequest->requester->name ?? 'N/A') }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 mb-1">Status</p>
                    <p class="inline-flex px-3 py-1 rounded-full text-sm font-semibold {{ $statusClasses }}">
                        {{ ucfirst($purchaseRequest->status) }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 mb-1">Approved By</p>
                    <p class="text-base font-semibold text-gray-800">
                        {{ trim(($purchaseRequest->approver->first_name ?? '') . ' ' . ($purchaseRequest->approver->last_name ?? '')) ?: ($purchaseRequest->approver->name ?? 'Not approved yet') }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 mb-1">Approved At</p>
                    <p class="text-base font-semibold text-gray-800">
                        {{ $purchaseRequest->approved_at ? \Carbon\Carbon::parse($purchaseRequest->approved_at)->format('M d, Y h:i A') : '—' }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-sm text-gray-500 mb-1">Purpose</p>
                    <p class="text-base text-gray-800">{{ $purchaseRequest->purpose ?: '—' }}</p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-sm text-gray-500 mb-1">Reason for Request</p>
                    <p class="text-base text-gray-800 whitespace-pre-line">{{ $purchaseRequest->reason ?: '—' }}</p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-sm text-gray-500 mb-1">Notes</p>
                    <p class="text-base text-gray-800">{{ $purchaseRequest->notes ?: '—' }}</p>
                </div>

                @if($purchaseRequest->evidence_image_path)
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500 mb-2">Attachments</p>
                        <a href="{{ asset('storage/'.$purchaseRequest->evidence_image_path) }}" target="_blank" class="inline-block rounded-2xl border border-gray-200 bg-gray-50 p-3 hover:bg-gray-100 transition">
                            <img src="{{ asset('storage/'.$purchaseRequest->evidence_image_path) }}" alt="Purchase request evidence" class="max-h-64 rounded-xl object-contain">
                            <span class="mt-2 block text-sm font-semibold text-blue-700">Open full image</span>
                        </a>
                    </div>
                @endif

                @if($purchaseRequest->status === 'declined')
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500 mb-1">Decline Reason</p>
                        <p class="text-base text-red-700 font-medium">
                            {{ $purchaseRequest->decline_reason ?: 'No reason provided.' }}
                        </p>
                    </div>
                @endif

                @if($purchaseRequest->status === 'paid')
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500 mb-1">Payment Status</p>
                        <p class="text-base text-emerald-700 font-semibold">
                            This purchase request has been paid.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Request Items</h2>
                <div class="text-sm text-gray-500">
                    Total Amount:
                    <span class="font-bold text-gray-800">{{ number_format($purchaseRequest->total_amount, 2) }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Item Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Raw Material</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Unit</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Quantity</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Unit Price</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($purchaseRequest->items as $item)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-800">{{ $item->item_name }}</td>
                                <td class="px-4 py-3">
                                    @if($item->rawMaterial)
                                        <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700 ring-1 ring-green-100">
                                            {{ $item->rawMaterial->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-500">Free text</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $item->description ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $item->unit ?: '—' }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ number_format($item->quantity, 2) }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-4 py-3 font-semibold">{{ number_format($item->total_price, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                    No items found for this purchase request.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
