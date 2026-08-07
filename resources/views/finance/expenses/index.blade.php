<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Expenses</h1>
                    <p class="mt-1 text-sm text-slate-600">Money the farm has paid out.</p>
                </div>
                @can('record_expenses')
                    <a href="{{ route('finance.expenses.create') }}"
                       class="rounded-xl bg-slate-900 px-4 py-2 text-center text-sm font-medium text-white hover:bg-slate-800">
                        Record expense
                    </a>
                @endcan
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                @forelse ($expenses as $expense)
                    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-slate-900">{{ $expense->categoryLabel() }}</span>
                                @if ($expense->needs_reclassification)
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        Needs review
                                    </span>
                                @endif
                                @if (! $expense->isPaidNow())
                                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                        Unpaid bill
                                    </span>
                                @endif
                            </div>
                            <p class="mt-0.5 truncate text-sm text-slate-600">{{ $expense->description }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $expense->expense_date?->format('j M Y') }}
                                · {{ $expense->expense_number }}
                                @if ($expense->paymentLabel()) · {{ $expense->paymentLabel() }} @endif
                                @if ($expense->supplier) · {{ $expense->supplier->name }} @endif
                            </p>
                        </div>
                        <div class="shrink-0 text-left sm:text-right">
                            <p class="text-sm font-semibold text-slate-900">
                                GHS {{ number_format((float) $expense->total_amount, 2) }}
                            </p>
                            @if (bccomp((string) $expense->tax_amount, '0', 2) > 0)
                                <p class="text-xs text-slate-500">
                                    incl. GHS {{ number_format((float) $expense->tax_amount, 2) }} tax
                                </p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center sm:px-6">
                        <p class="text-sm text-slate-600">No expenses recorded yet.</p>
                        @can('record_expenses')
                            <a href="{{ route('finance.expenses.create') }}"
                               class="mt-3 inline-block text-sm font-medium text-slate-900 underline">
                                Record your first one
                            </a>
                        @endcan
                    </div>
                @endforelse
            </div>

            {{ $expenses->links() }}
        </div>
    </main>
</x-app-layout>
