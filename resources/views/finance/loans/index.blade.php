{{--
    Finance → Loans (Phase 7, blueprint §26).

    Owner view. §26: "hide amortisation and liability terminology from owner
    view unless the user opens details." So: what the farm still owes, what is
    due next, and who lent it — with the amortisation schedule, accrued
    interest and reclassification kept on the detail page where an accountant
    will look for them.
--}}
@php $fmt = fn ($v) => number_format((float) $v, 2); @endphp

<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Loans</h1>
                    <p class="mt-1 text-sm text-slate-600">Money the farm has borrowed, and what is left to pay.</p>
                </div>
                @can('manage_loans')
                    <a href="{{ route('finance.loans.create') }}" class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm">Add a loan</a>
                @endcan
            </div>

            @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>@endif

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Still to pay back</p>
                <p class="mt-1 text-3xl font-bold text-slate-800">GHS {{ $fmt($stillOwed) }}</p>
                <p class="mt-1 text-xs text-slate-400">Across every loan the farm is still repaying.</p>
            </div>

            {{-- §26 "view upcoming payments". These schedule lines already
                 existed; they were only reachable inside one loan's
                 amortisation table. --}}
            @if($upcoming->isNotEmpty())
                <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h2 class="font-semibold text-slate-800">Payments coming up</h2>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @foreach($upcoming as $line)
                            @php $overdue = $line->due_date && $line->due_date->isPast(); @endphp
                            <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-800 truncate">
                                        {{ $line->loan?->lender_name ?? '—' }}
                                        @if($overdue)
                                            <span class="ml-1 px-1.5 py-0.5 rounded text-xs bg-rose-50 text-rose-700">overdue</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        Due {{ $line->due_date?->format('d M Y') }}
                                        @if($line->loan)
                                            · <a class="hover:underline" href="{{ route('finance.loans.show', $line->loan) }}">{{ $line->loan->reference }}</a>
                                        @endif
                                    </p>
                                </div>
                                <span class="font-semibold tabular-nums text-slate-800">GHS {{ $fmt($line->total_due) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">All loans</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse($loans as $loan)
                        <li>
                            <a href="{{ route('finance.loans.show', $loan) }}" class="flex items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-800 truncate">
                                        {{ $loan->lender_name }}
                                        @php $badge = match($loan->status) {
                                            'active'  => 'bg-emerald-50 text-emerald-700',
                                            'draft'   => 'bg-slate-100 text-slate-600',
                                            'settled' => 'bg-blue-50 text-blue-700',
                                            default   => 'bg-amber-50 text-amber-700',
                                        }; @endphp
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-xs {{ $badge }}">
                                            {{ $loan->status === 'settled' ? 'paid off' : ($loan->status === 'draft' ? 'not received yet' : ucfirst($loan->status)) }}
                                        </span>
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ $loan->reference }} · borrowed GHS {{ $fmt($loan->principal_amount) }}
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="font-semibold tabular-nums text-slate-800">GHS {{ $fmt($loan->principal_outstanding) }}</p>
                                    <p class="text-xs text-slate-400">left to pay</p>
                                </div>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-slate-400">No loans yet.</li>
                    @endforelse
                </ul>
            </section>

            <div>{{ $loans->links() }}</div>

            {{-- Reclassification is an accounting act — §26 lists it under
                 advanced accounting. It stays available, but off the owner's
                 front page. --}}
            @if($advancedAccounting)
                <p class="text-xs text-slate-400">
                    Accountant: <a class="underline" href="{{ route('finance.loans.reclassification') }}">current / non-current reclassification</a>
                </p>
            @endif

        </div>
    </main>
</x-app-layout>
