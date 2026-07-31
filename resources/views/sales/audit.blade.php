<x-app-layout>
    @php
        $labels = [
            'payment_status' => 'Payment Status',
            'payment_status_updated_at' => 'Payment Status Updated At',
            'payment_status_updated_by_user_id' => 'Updated By',
            'notes' => 'Notes',
            'transaction_state' => 'Transaction State',
            'return_number' => 'Reversal Number',
            'reason' => 'Reason',
            'restock' => 'Restore Pig to Stock',
            'is_posted' => 'Posted',
            'posted_at' => 'Posted At',
            'invoice_id' => 'Invoice',
            'cogs_amount' => 'Cost of Sale',
            'cogs_posting_status' => 'Cost Posting Status',
            'cogs_posting_error' => 'Cost Posting Error',
        ];
        $formatValue = function ($field, $value) {
            if ($value === null || $value === '') return 'Not set';
            if (in_array($field, ['restock', 'is_posted'], true)) return $value ? 'Yes' : 'No';
            if ($field === 'cogs_amount') return 'GHS '.number_format((float) $value, 2);
            return is_scalar($value) ? (string) $value : json_encode($value);
        };
    @endphp

    <div class="min-h-screen bg-slate-50 py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-3xl bg-gradient-to-r from-violet-700 via-indigo-700 to-blue-700 p-7 text-white shadow-xl">
                <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-100">Sales governance</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">Sale Change History</h1>
                        <p class="mt-2 text-sm text-indigo-100">A read-only record of payment updates and reversal activity for this transaction.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('sales.show', $sale->id) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-indigo-700 shadow hover:bg-indigo-50">View Sale</a>
                        <a href="{{ route('sales.index') }}" class="rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/20">Back to Sales</a>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Pig</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ $sale->pig?->code ?? $sale->pig?->ear_tag_number ?? 'Pig #'.$sale->pig_id }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Sale Value</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ $sale->formatted_sale_price }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Payment</div>
                    <div class="mt-2 text-lg font-black capitalize text-slate-900">{{ $sale->payment_status }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-400">Recorded Changes</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ $logs->total() }}</div>
                </div>
            </div>

            @if($returns->isNotEmpty())
                <section class="mt-6 overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm">
                    <div class="border-b border-red-100 bg-red-50 px-6 py-4">
                        <h2 class="font-black text-red-950">Transaction Reversal</h2>
                        <p class="mt-1 text-sm text-red-700">Operational state of the compensating transaction. The original sale remains preserved.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($returns as $return)
                            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2 lg:grid-cols-4">
                                <div><div class="text-xs font-bold uppercase text-slate-400">Reference</div><div class="mt-1 font-bold text-slate-900">{{ $return->return_number }}</div></div>
                                <div><div class="text-xs font-bold uppercase text-slate-400">Status</div><div class="mt-1 font-bold capitalize {{ $return->status === 'failed' ? 'text-red-700' : ($return->status === 'posted' ? 'text-emerald-700' : 'text-amber-700') }}">{{ $return->status }}</div></div>
                                <div><div class="text-xs font-bold uppercase text-slate-400">Initiated By</div><div class="mt-1 font-bold text-slate-900">{{ $return->returnedBy?->name ?? 'System' }}</div></div>
                                <div><div class="text-xs font-bold uppercase text-slate-400">Initiated</div><div class="mt-1 font-bold text-slate-900">{{ $return->returned_at?->format('M d, Y · H:i') ?? $return->created_at?->format('M d, Y · H:i') }}</div></div>
                                <div class="sm:col-span-2 lg:col-span-4"><div class="text-xs font-bold uppercase text-slate-400">Reason</div><div class="mt-1 text-sm text-slate-700">{{ $return->reason }}</div></div>
                                @if($return->posting_error)
                                    <div class="rounded-lg bg-red-50 p-3 text-sm text-red-800 sm:col-span-2 lg:col-span-4">{{ $return->posting_error }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-black text-slate-900">Audit Timeline</h2>
                    <p class="mt-1 text-sm text-slate-500">Newest changes appear first.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        @php($fields = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))))
                        <article class="p-6">
                            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 font-black text-indigo-700">{{ strtoupper(substr($log->user?->name ?? $log->name ?? 'S', 0, 1)) }}</div>
                                    <div><div class="font-bold text-slate-900">{{ $log->user?->name ?? $log->name ?? 'System' }}</div><div class="text-sm text-slate-500">{{ Str::headline($log->action) }}</div></div>
                                </div>
                                <div class="sm:text-right"><time class="text-sm font-semibold text-slate-700">{{ $log->created_at->format('M d, Y · H:i') }}</time><div class="mt-1 text-xs text-slate-400">{{ $log->created_at->diffForHumans() }} @if($log->ip_address) · {{ $log->ip_address }} @endif</div></div>
                            </div>
                            @if($log->description)<p class="mt-4 text-sm text-slate-600">{{ $log->description }}</p>@endif
                            @if($fields)
                                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Field</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Before</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">After</th></tr></thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach($fields as $field)
                                                <tr><td class="px-4 py-3 font-semibold text-slate-700">{{ $labels[$field] ?? Str::headline($field) }}</td><td class="px-4 py-3 text-slate-500">{{ $formatValue($field, $log->old_values[$field] ?? null) }}</td><td class="px-4 py-3 font-medium text-slate-900">{{ $formatValue($field, $log->new_values[$field] ?? null) }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="px-6 py-16 text-center"><div class="text-3xl text-slate-300">↺</div><h3 class="mt-3 font-bold text-slate-900">No changes recorded yet</h3><p class="mt-1 text-sm text-slate-500">Payment updates and reversal activity will appear here.</p></div>
                    @endforelse
                </div>
                @if($logs->hasPages())<div class="border-t border-slate-200 bg-slate-50 px-6 py-4"><x-simple-pagination :paginator="$logs" /></div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
