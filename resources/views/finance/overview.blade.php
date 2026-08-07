<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Money</h1>
                <p class="mt-1 text-sm text-slate-600">
                    What {{ $organization?->name ?? 'your farm' }} has taken in, paid out, and still owes.
                </p>
            </div>

            {{-- Needs attention. Deliberately first: if something is wrong,
                 that is the most important thing on the page. --}}
            @if (count($attention))
                <section class="overflow-hidden rounded-3xl border border-amber-200 bg-amber-50/60 shadow-sm">
                    <div class="border-b border-amber-200 px-5 py-4 sm:px-6">
                        <h2 class="text-base font-semibold text-amber-900">
                            Needs attention
                            <span class="ml-1 rounded-full bg-amber-200 px-2 py-0.5 text-xs font-medium text-amber-900">
                                {{ $attentionTotal }}
                            </span>
                        </h2>
                    </div>

                    <ul class="divide-y divide-amber-200">
                        @foreach ($attention as $item)
                            <li class="px-5 py-4 sm:px-6">
                                <div class="flex items-start gap-3">
                                    <span @class([
                                        'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                        'bg-rose-500'   => $item['severity'] === 'critical',
                                        'bg-amber-500'  => $item['severity'] === 'warning',
                                        'bg-sky-500'    => $item['severity'] === 'info',
                                    ])></span>

                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900">{{ $item['message'] }}</p>

                                        {{-- Technical detail is available but never the headline.
                                             Blueprint §54: outbox errors must not be the primary
                                             user message. --}}
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-xs text-slate-500 hover:text-slate-700">
                                                What does this mean?
                                            </summary>
                                            <p class="mt-1 text-xs leading-relaxed text-slate-600">
                                                {{ $item['detail'] }}
                                            </p>
                                        </details>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @else
                <section class="rounded-3xl border border-emerald-200 bg-emerald-50/60 px-5 py-4 sm:px-6">
                    <p class="text-sm text-emerald-900">
                        Nothing needs your attention right now.
                    </p>
                </section>
            @endif

            {{-- Quick actions --}}
            @if (count($quickActions))
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="text-base font-semibold text-slate-900">Record something</h2>
                    </div>

                    <div class="grid grid-cols-1 gap-px bg-slate-100 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($quickActions as $action)
                            <a href="{{ route($action['route']) }}"
                               class="flex items-center gap-3 bg-white px-5 py-4 text-sm font-medium text-slate-800 hover:bg-slate-50 sm:px-6">
                                <i class="{{ $action['icon'] }} text-slate-400"></i>
                                {{ $action['label'] }}
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Money summary (Phase 7). Every figure is derived from posted
                 journal lines, so nothing here can disagree with the ledger. --}}
            <section>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Cash, bank &amp; MoMo</p>
                        <p @class([
                            'mt-1 text-2xl font-semibold',
                            'text-rose-600'  => bccomp($cards['cash_available'], '0', 2) < 0,
                            'text-slate-900' => bccomp($cards['cash_available'], '0', 2) >= 0,
                        ])>
                            GHS {{ number_format((float) $cards['cash_available'], 2) }}
                        </p>
                        @if (bccomp($cards['cash_available'], '0', 2) < 0)
                            <p class="mt-1 text-xs text-rose-600">
                                This is below zero, which usually means a payment was recorded
                                against the wrong account.
                            </p>
                        @endif
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Money in — {{ $cards['month_label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">
                            GHS {{ number_format((float) $cards['sales_this_month'], 2) }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Money out — {{ $cards['month_label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">
                            GHS {{ number_format((float) $cards['expenses_this_month'], 2) }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Left over — {{ $cards['month_label'] }}</p>
                        <p @class([
                            'mt-1 text-2xl font-semibold',
                            'text-rose-600'    => bccomp($cards['net_this_month'], '0', 2) < 0,
                            'text-emerald-700' => bccomp($cards['net_this_month'], '0', 2) >= 0,
                        ])>
                            GHS {{ number_format((float) $cards['net_this_month'], 2) }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Customers owe us</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">
                            GHS {{ number_format((float) $cards['customers_owing'], 2) }}
                        </p>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Bills to pay</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">
                            GHS {{ number_format((float) $cards['bills_to_pay'], 2) }}
                        </p>
                    </div>

                </div>

                <p class="mt-3 text-xs text-slate-500">
                    Figures come straight from your books and update as soon as something is
                    recorded.
                </p>
            </section>

        </div>
    </main>
</x-app-layout>
