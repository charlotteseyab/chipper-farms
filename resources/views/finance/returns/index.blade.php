{{--
    Finance → Returns (IMP-098, blueprint §43 step 1).

    "User requests return/refund with reason and quantity." Everything after
    that step was already built; this is the step that had no surface.

    The form records a return through RecordSalesReturnService. It posts
    nothing itself — the credit note and the inventory reversal follow from the
    domain event, which is why the confirmation says the credit note "follows".
--}}
@php $money = fn ($v) => number_format((float) $v, 2); @endphp

<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Returns</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Goods a customer brought back. Recording one reverses the sale and
                    raises a credit note automatically.
                </p>
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

            {{-- Record a return --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5"
                     x-data="{ type: '{{ old('sale_type', 'feed') }}' }">
                <h2 class="font-semibold text-slate-800">Record a return</h2>

                <form method="POST" action="{{ route('finance.returns.store') }}" class="mt-4 space-y-4">
                    @csrf

                    <div class="flex gap-4 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="sale_type" value="feed" x-model="type"> Feed
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="sale_type" value="pig" x-model="type"> Pig
                        </label>
                    </div>

                    <div x-show="type === 'feed'" class="grid sm:grid-cols-12 gap-3 items-end">
                        <label class="sm:col-span-7 block text-sm">
                            <span class="text-slate-600">Which sale?</span>
                            <select name="sale_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                @forelse($feedSales as $sale)
                                    <option value="{{ $sale->id }}">
                                        {{ $sale->feedType?->name ?? 'Feed' }} —
                                        {{ rtrim(rtrim((string) $sale->quantity_kg, '0'), '.') }}kg,
                                        GHS {{ $money($sale->sale_total) }},
                                        {{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }}
                                        @if($sale->buyer_name) — {{ $sale->buyer_name }}@endif
                                    </option>
                                @empty
                                    <option value="">No feed sales recorded</option>
                                @endforelse
                            </select>
                        </label>
                        <label class="sm:col-span-3 block text-sm">
                            <span class="text-slate-600">Quantity back (kg)</span>
                            <input type="number" step="0.0001" min="0.0001" name="quantity"
                                   value="{{ old('quantity') }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <div class="sm:col-span-2">
                            {{-- §44: restock is a decision. Getting it wrong overstates
                                 stock the farm cannot sell. --}}
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="restock" value="1" class="h-4 w-4 rounded" @checked(old('restock', true))>
                                <span class="text-slate-600">Back into stock</span>
                            </label>
                        </div>
                    </div>

                    <div x-show="type === 'pig'" x-cloak class="space-y-3">
                        <label class="block text-sm">
                            <span class="text-slate-600">Which sale?</span>
                            <select name="sale_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                @forelse($pigSales as $sale)
                                    <option value="{{ $sale->id }}">
                                        {{ $sale->pig?->identityAt($sale->sale_date) ?? 'Pig #' . $sale->pig_id }} —
                                        GHS {{ $money($sale->sale_price) }},
                                        {{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }}
                                        @if($sale->buyer_name) — {{ $sale->buyer_name }}@endif
                                    </option>
                                @empty
                                    <option value="">No pig sales recorded</option>
                                @endforelse
                            </select>
                        </label>
                        {{-- §45: a pig return is indivisible and never restocks
                             automatically. No quantity field, because a field that
                             only ever accepts one value is a trap rather than a
                             choice. --}}
                        <p class="text-xs text-slate-500">
                            A pig comes back whole — there is no part-return. It will not go
                            back on sale automatically: check its health and condition first,
                            then relist it.
                        </p>
                    </div>

                    <label class="block text-sm">
                        <span class="text-slate-600">Why?</span>
                        <input type="text" name="reason" required maxlength="500"
                               value="{{ old('reason') }}"
                               placeholder="e.g. spoiled on arrival, wrong feed delivered"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        <span class="block text-xs text-slate-400 mt-1">
                            This appears on the customer's credit note.
                        </span>
                    </label>

                    <div class="flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-800 text-white text-sm font-medium">
                            Record return
                        </button>
                    </div>
                </form>
            </section>

            {{-- What has come back --}}
            <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">Recorded returns</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse($returns as $return)
                        @php
                            $tone = match ($return->status) {
                                'posted'  => 'bg-emerald-50 text-emerald-700',
                                'failed'  => 'bg-rose-50 text-rose-700',
                                default   => 'bg-amber-50 text-amber-700',
                            };
                        @endphp
                        <li class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800">
                                    {{ $return->return_number }}
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs {{ $tone }}">{{ $return->status }}</span>
                                </p>
                                <p class="text-xs text-slate-500 truncate">
                                    {{ rtrim(rtrim((string) $return->quantity, '0'), '.') }}
                                    returned · {{ $return->reason }}
                                    @unless($return->restock) · not restocked @endunless
                                </p>
                                {{-- §42: "no silent refunded label" — a failure has to say so. --}}
                                @if($return->status === 'failed' && $return->posting_error)
                                    <p class="text-xs text-rose-600 mt-0.5">{{ $return->posting_error }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                @if($return->creditNote)
                                    <a href="{{ route('finance.credit-notes.show', $return->creditNote) }}"
                                       class="text-slate-600 hover:underline">{{ $return->creditNote->credit_note_number }}</a>
                                @else
                                    <span class="text-xs text-slate-400">credit note pending</span>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-slate-400">Nothing has been returned.</li>
                    @endforelse
                </ul>
            </section>

            <div>{{ $returns->links() }}</div>

        </div>
    </main>
</x-app-layout>
