<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-4 flex items-center justify-between">
            <a href="{{ route('finance.customers.show', $customer) }}" class="text-sm text-slate-500 hover:underline">← Back to {{ $customer->name }}</a>

            <div class="flex gap-2">
                <a href="{{ route('finance.statements.download', [$customer, 'inline' => 1, 'start_date' => $statement->startDate->toDateString(), 'end_date' => $statement->endDate->toDateString()]) }}"
                   target="_blank"
                   class="inline-flex items-center px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">
                    View PDF
                </a>
                <a href="{{ route('finance.statements.download', [$customer, 'start_date' => $statement->startDate->toDateString(), 'end_date' => $statement->endDate->toDateString()]) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl">
                    Download PDF
                </a>
            </div>
        </div>

        {{-- Date range form --}}
        <form method="GET" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                    <input type="date" name="start_date" value="{{ $statement->startDate->toDateString() }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                    <input type="date" name="end_date" value="{{ $statement->endDate->toDateString() }}" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl">Update period</button>
                </div>
            </div>
        </form>

        {{-- Statement header --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Customer Statement</h1>
                    <p class="text-sm text-slate-500">{{ $statement->startDate->format('Y-m-d') }} to {{ $statement->endDate->format('Y-m-d') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-500">Generated</p>
                    <p class="text-sm text-slate-700">{{ $statement->generatedAt->format('Y-m-d H:i') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                <div>
                    <p class="text-xs uppercase text-slate-500 font-semibold tracking-wide">To</p>
                    <p class="text-base font-semibold text-slate-800 mt-1">{{ $customer->name }}</p>
                    <p class="text-xs text-slate-400 font-mono">{{ $customer->code }}</p>
                    @if($customer->billing_address_line1)
                        <p class="text-xs text-slate-600 mt-1">{{ $customer->billing_address_line1 }}</p>
                        @if($customer->billing_city)<p class="text-xs text-slate-600">{{ $customer->billing_city }}</p>@endif
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl border border-slate-100 p-3">
                        <p class="text-xs text-slate-500 uppercase">Opening</p>
                        <p class="text-lg font-bold {{ (float) $statement->openingBalance > 0.01 ? 'text-red-600' : 'text-slate-700' }}">
                            {{ number_format((float) $statement->openingBalance, 2) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-100 p-3">
                        <p class="text-xs text-slate-500 uppercase">Activity</p>
                        <p class="text-xs text-slate-600 mt-1">{{ $statement->invoicesCount }} inv / {{ $statement->receiptsCount }} rcpt</p>
                    </div>
                    <div class="rounded-xl border-2 {{ (float) $statement->closingBalance > 0.01 ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50' }} p-3">
                        <p class="text-xs uppercase {{ (float) $statement->closingBalance > 0.01 ? 'text-red-700' : 'text-emerald-700' }} font-semibold">Closing</p>
                        <p class="text-lg font-bold {{ (float) $statement->closingBalance > 0.01 ? 'text-red-700' : 'text-emerald-700' }}">
                            {{ number_format((float) $statement->closingBalance, 2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Activity --}}
        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden mb-4">
            <h3 class="px-5 py-3 border-b border-slate-100 text-sm font-semibold text-slate-700 uppercase tracking-wide">Activity in Period</h3>

            @if(empty($statement->entries))
                <p class="px-5 py-8 text-center text-sm text-slate-500">No activity in this period.</p>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Reference</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-right">Debit</th>
                            <th class="px-4 py-3 text-right">Credit</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="bg-slate-50 italic">
                            <td colspan="6" class="px-4 py-2 text-xs text-slate-600">Opening balance</td>
                            <td class="px-4 py-2 text-right text-sm font-medium {{ (float) $statement->openingBalance > 0.01 ? 'text-red-600' : 'text-slate-700' }}">{{ number_format((float) $statement->openingBalance, 2) }}</td>
                        </tr>
                        @foreach($statement->entries as $entry)
                            <tr>
                                <td class="px-4 py-3 text-slate-700">{{ $entry->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    @if($entry->type === 'invoice')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Invoice</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Receipt</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    @if($entry->type === 'invoice')
                                        <a href="{{ route('finance.invoices.show', $entry->relatedId) }}" class="text-emerald-700 hover:underline">{{ $entry->reference }}</a>
                                    @else
                                        <a href="{{ route('finance.receipts.show', $entry->relatedId) }}" class="text-emerald-700 hover:underline">{{ $entry->reference }}</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600 text-xs">{{ \Illuminate\Support\Str::limit($entry->description, 60) }}</td>
                                <td class="px-4 py-3 text-right">@if(bccomp($entry->debit, '0', 2) > 0){{ number_format((float) $entry->debit, 2) }}@endif</td>
                                <td class="px-4 py-3 text-right text-emerald-700">@if(bccomp($entry->credit, '0', 2) > 0){{ number_format((float) $entry->credit, 2) }}@endif</td>
                                <td class="px-4 py-3 text-right font-medium {{ (float) $entry->runningBalance() > 0.01 ? 'text-red-600' : 'text-slate-700' }}">{{ number_format((float) $entry->runningBalance(), 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-slate-100 font-semibold border-t-2 border-slate-300">
                            <td colspan="4" class="px-4 py-2 text-xs text-slate-700 uppercase">Period totals</td>
                            <td class="px-4 py-2 text-right text-sm">{{ number_format((float) $statement->invoicesTotal, 2) }}</td>
                            <td class="px-4 py-2 text-right text-sm text-emerald-700">{{ number_format((float) $statement->receiptsTotal, 2) }}</td>
                            <td class="px-4 py-2 text-right text-sm {{ (float) $statement->closingBalance > 0.01 ? 'text-red-700' : 'text-emerald-700' }}">{{ number_format((float) $statement->closingBalance, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Open invoices --}}
        @if($statement->openInvoices->isNotEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 shadow-sm overflow-hidden">
                <h3 class="px-5 py-3 border-b border-amber-200 text-sm font-semibold text-amber-800 uppercase tracking-wide">Outstanding Invoices ({{ $statement->openInvoices->count() }})</h3>
                <table class="min-w-full text-sm">
                    <thead class="bg-amber-100 text-xs uppercase tracking-wide text-amber-800">
                        <tr>
                            <th class="px-4 py-2 text-left">Invoice</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Due</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2 text-right">Paid</th>
                            <th class="px-4 py-2 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-100">
                        @foreach($statement->openInvoices as $inv)
                            <tr class="hover:bg-amber-100">
                                <td class="px-4 py-2 font-mono text-xs">
                                    <a href="{{ route('finance.invoices.show', $inv) }}" class="text-emerald-700 hover:underline">{{ $inv->invoice_number }}</a>
                                </td>
                                <td class="px-4 py-2 text-slate-700">{{ $inv->invoice_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-2 text-slate-700">
                                    {{ $inv->due_date?->format('Y-m-d') ?? '—' }}
                                    @if($inv->due_date && $inv->due_date->isPast())
                                        <span class="ml-1 text-xs text-red-600">overdue</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">{{ number_format((float) $inv->total, 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format((float) $inv->amount_paid, 2) }}</td>
                                <td class="px-4 py-2 text-right font-medium text-red-700">{{ number_format((float) $inv->balance_due, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
