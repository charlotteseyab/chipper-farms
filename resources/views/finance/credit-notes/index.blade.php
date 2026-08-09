{{--
    Finance → Credits (Phase 7, blueprint §29).

    Read-only. Credit notes are created by recording a sales return, which is
    where their idempotency key comes from — see the controller docblock.
--}}
@php $money = fn ($v) => number_format((float) $v, 2); @endphp

<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Credits</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Money credited back to customers for returns and corrections.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Credited to customers</p>
                <p class="mt-1 text-3xl font-bold text-slate-800">GHS {{ $money($totalCredited) }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <ul class="divide-y divide-slate-100">
                    @forelse($creditNotes as $note)
                        <li class="px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800">
                                    {{ $note->customer?->name ?? '—' }}
                                    @if($note->status !== \App\Models\Finance\CreditNote::STATUS_POSTED)
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-xs bg-slate-100 text-slate-600">{{ str_replace('_', ' ', $note->status) }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-slate-500">
                                    <a class="hover:underline" href="{{ route('finance.credit-notes.show', $note) }}">{{ $note->credit_note_number }}</a>
                                    · {{ \Illuminate\Support\Carbon::parse($note->issue_date)->format('d M Y') }}
                                    @if($note->originalInvoice) · against {{ $note->originalInvoice->invoice_number }}@endif
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-semibold tabular-nums text-slate-800">GHS {{ $money($note->total) }}</span>
                                <a href="{{ route('finance.credit-notes.document', $note) }}" target="_blank"
                                   class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs">Customer copy</a>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-slate-400">
                            No credits yet. One is created automatically whenever a sales return is recorded.
                        </li>
                    @endforelse
                </ul>
            </div>

            <div>{{ $creditNotes->links() }}</div>

        </div>
    </main>
</x-app-layout>
