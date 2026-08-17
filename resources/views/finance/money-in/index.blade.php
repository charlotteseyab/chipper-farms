{{--
    Finance → Sales & Money In (Phase 7, blueprint §28; closes IMP-027).

    Two questions an owner asks about money coming in — who still owes me, and
    what has come in — and one button to answer the first.

    The full invoice and receipt screens are unchanged and linked from here.
--}}
@php $money = fn ($v) => number_format((float) $v, 2); @endphp

<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Sales &amp; Money In</h1>
                    <p class="mt-1 text-sm text-slate-600">Who still owes the farm, and what has come in.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('manage_invoices')
                        <a href="{{ route('finance.invoices.create') }}" class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm">New invoice</a>
                    @endcan
                    <a href="{{ route('finance.invoices.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm">All invoices</a>
                    <a href="{{ route('finance.receipts.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm">All receipts</a>
                </div>
            </div>

            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Customers owe</p>
                    <p class="mt-1 text-3xl font-bold text-amber-700">GHS {{ $money($owed) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Received this month</p>
                    <p class="mt-1 text-3xl font-bold text-emerald-700">GHS {{ $money($receivedThisMonth) }}</p>
                </div>
            </div>

            {{-- Awaiting payment, with the one button. --}}
            <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">Waiting to be paid</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Oldest first.</p>
                </div>

                <ul class="divide-y divide-slate-100">
                    @forelse($awaitingPayment as $invoice)
                        @php $overdue = $invoice->due_date && $invoice->due_date->isPast(); @endphp
                        <li class="px-5 py-4" x-data="{ paying: false }">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-800">
                                        {{ $invoice->counterparty?->name ?? 'Unknown customer' }}
                                        @if($overdue)
                                            <span class="ml-1 px-1.5 py-0.5 rounded text-xs bg-rose-50 text-rose-700">overdue</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        <a class="hover:underline" href="{{ route('finance.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a>
                                        @if($invoice->due_date) · due {{ $invoice->due_date->format('d M Y') }}@endif
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold tabular-nums text-slate-800">GHS {{ $money($invoice->balance_due) }}</p>
                                    @if($canRecord)
                                        <button type="button" x-on:click="paying = !paying"
                                                class="mt-1 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-medium">
                                            <span x-show="!paying">Record payment</span>
                                            <span x-show="paying" x-cloak>Cancel</span>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if($canRecord)
                                <form x-show="paying" x-cloak method="POST"
                                      action="{{ route('finance.money-in.record', $invoice) }}"
                                      class="mt-3 grid sm:grid-cols-12 gap-3 items-end border-t border-slate-100 pt-3">
                                    @csrf
                                    <label class="sm:col-span-3 block text-sm">
                                        <span class="text-slate-600">Amount (GHS)</span>
                                        <input type="number" step="0.01" min="0.01" name="amount" required
                                               max="{{ number_format((float) $invoice->balance_due, 2, '.', '') }}"
                                               value="{{ number_format((float) $invoice->balance_due, 2, '.', '') }}"
                                               class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                    </label>
                                    <label class="sm:col-span-3 block text-sm">
                                        <span class="text-slate-600">Paid by</span>
                                        <select name="payment_method_id" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                            @foreach($paymentMethods as $method)
                                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="sm:col-span-2 block text-sm">
                                        <span class="text-slate-600">On</span>
                                        <input type="date" name="received_on" value="{{ now()->toDateString() }}"
                                               class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                    </label>
                                    <label class="sm:col-span-2 block text-sm">
                                        <span class="text-slate-600">Reference</span>
                                        <input type="text" name="reference" maxlength="100" placeholder="Optional"
                                               class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                    </label>
                                    <div class="sm:col-span-2">
                                        <button type="submit" class="w-full px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm">Save</button>
                                    </div>
                                    <p class="sm:col-span-12 text-xs text-slate-400">
                                        This posts a receipt and puts it against this invoice. Taking more than is
                                        owed goes through the <a class="underline" href="{{ route('finance.receipts.create') }}">full receipt screen</a>,
                                        where the extra can be left on the customer's account as credit.
                                    </p>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-slate-400">Nothing is waiting to be paid.</li>
                    @endforelse
                </ul>
            </section>

            {{-- What has come in. --}}
            <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">Money in</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse($recentMoneyIn as $item)
                        <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 truncate">{{ $item['name'] }}</p>
                                <p class="text-xs text-slate-500 flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">{{ $item['type'] }}</span>
                                    @if($item['href'])
                                        <a class="hover:underline" href="{{ $item['href'] }}">{{ $item['reference'] }}</a>
                                    @else
                                        <span>{{ $item['reference'] }}</span>
                                    @endif
                                    <span>· {{ \Illuminate\Support\Carbon::parse($item['date'])->format('d M Y') }}</span>
                                    @if($item['method']) <span>· {{ $item['method'] }}</span>@endif
                                </p>
                            </div>
                            <span class="font-semibold tabular-nums text-emerald-700">GHS {{ $money($item['amount']) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-slate-400">No money recorded yet.</li>
                    @endforelse
                </ul>
            </section>

        </div>
    </main>
</x-app-layout>
