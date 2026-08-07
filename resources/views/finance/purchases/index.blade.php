<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Purchases</h1>
                    <p class="mt-1 text-sm text-slate-600">Feed, supplies and services the farm has bought.</p>
                </div>
                @can('record_purchases')
                    <a href="{{ route('finance.purchases.create') }}"
                       class="rounded-xl bg-slate-900 px-4 py-2 text-center text-sm font-medium text-white hover:bg-slate-800">
                        Record purchase
                    </a>
                @endcan
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                @forelse ($purchases as $purchase)
                    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 last:border-0 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-slate-900">
                                    {{ $purchase->supplier->name ?? 'No supplier recorded' }}
                                </span>
                                @unless ($purchase->isPaidNow())
                                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                        Unpaid
                                    </span>
                                @endunless
                            </div>

                            <ul class="mt-1 space-y-0.5">
                                @foreach ($purchase->lines as $line)
                                    <li class="truncate text-sm text-slate-600">
                                        {{ $line->description }}
                                        @if ($line->isStock())
                                            <span class="text-xs text-emerald-700">· into stock</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            <p class="mt-1 text-xs text-slate-400">
                                {{ $purchase->purchase_date?->format('j M Y') }} · {{ $purchase->purchase_number }}
                                @if ($purchase->supplier_invoice_reference)
                                    · inv {{ $purchase->supplier_invoice_reference }}
                                @endif
                            </p>
                        </div>

                        <p class="shrink-0 text-sm font-semibold text-slate-900 sm:text-right">
                            GHS {{ number_format((float) $purchase->total_amount, 2) }}
                        </p>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center sm:px-6">
                        <p class="text-sm text-slate-600">No purchases recorded yet.</p>
                    </div>
                @endforelse
            </div>

            {{ $purchases->links() }}
        </div>
    </main>
</x-app-layout>
