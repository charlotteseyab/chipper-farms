{{-- Finance → Credits → detail (Phase 7, blueprint §29). --}}
@php $money = fn ($v) => number_format((float) $v, 2); @endphp

<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">{{ $creditNote->credit_note_number }}</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $creditNote->customer?->name ?? '—' }} ·
                        {{ \Illuminate\Support\Carbon::parse($creditNote->issue_date)->format('d M Y') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('finance.credit-notes.document', $creditNote) }}" target="_blank"
                       class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm">Customer copy</a>
                    <a href="{{ route('finance.credit-notes.index') }}"
                       class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm">Back</a>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 grid sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Total credited</p>
                    <p class="mt-1 text-xl font-bold text-slate-800">{{ $creditNote->currency_code }} {{ $money($creditNote->total) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Status</p>
                    <p class="mt-1 font-medium text-slate-700">{{ ucfirst(str_replace('_', ' ', $creditNote->status)) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Against invoice</p>
                    <p class="mt-1 font-medium text-slate-700">
                        @if($creditNote->originalInvoice)
                            <a class="hover:underline" href="{{ route('finance.invoices.show', $creditNote->originalInvoice) }}">
                                {{ $creditNote->originalInvoice->invoice_number }}
                            </a>
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>

            @if($creditNote->reason)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Reason</p>
                    <p class="mt-1 text-slate-700">{{ $creditNote->reason }}</p>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Description</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3 text-right">Unit price</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($creditNote->lines as $line)
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">{{ $line->description }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $money($line->unit_price) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $money($line->line_total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No lines.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-xs text-slate-400">
                Credits are created by recording a sales return, which is what makes each one
                unique to the goods that came back. They are not raised directly.
            </p>

        </div>
    </main>
</x-app-layout>
