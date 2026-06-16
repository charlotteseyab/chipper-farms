<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <div class="flex items-start justify-between mb-6">
            <div>
                <p class="text-xs font-mono text-slate-400">{{ $quote->reference }}</p>
                <h1 class="text-2xl font-bold text-gray-800">Quote from {{ $quote->contact_name }}</h1>
                <p class="text-sm text-slate-500 mt-1">
                    <span class="font-medium">{{ $quote->status }}</span>
                    · {{ $quote->source }} · {{ $quote->fulfilment_preference }}
                </p>
            </div>
            <a href="{{ route('storefront.admin.quotes.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Back</a>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Requested items</h2>
                    <table class="w-full text-sm">
                        <thead class="text-slate-400 text-left"><tr><th class="py-1">Item</th><th class="py-1">Qty</th><th class="py-1">Note</th></tr></thead>
                        <tbody>
                            @foreach($quote->items as $item)
                                <tr class="border-t border-slate-100">
                                    <td class="py-2">{{ $item->description }}</td>
                                    <td class="py-2">{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }} {{ $item->quantity_unit }}</td>
                                    <td class="py-2 text-slate-500">{{ $item->line_note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($quote->message)
                        <p class="mt-4 text-sm text-slate-600 whitespace-pre-line border-t border-slate-100 pt-3">{{ $quote->message }}</p>
                    @endif
                </div>

                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Contact</h2>
                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-slate-400">Name</dt><dd>{{ $quote->contact_name }}</dd>
                        <dt class="text-slate-400">Email</dt><dd>{{ $quote->contact_email ?? '—' }}</dd>
                        <dt class="text-slate-400">Phone</dt><dd>{{ $quote->contact_phone ?? '—' }}</dd>
                        @if($quote->fulfilment_preference === 'delivery')
                            <dt class="text-slate-400">Delivery to</dt><dd>{{ $quote->delivery_city }} {{ $quote->delivery_region }}</dd>
                        @endif
                        @if($quote->counterparty)
                            <dt class="text-slate-400">Customer</dt><dd class="font-mono">{{ $quote->counterparty->code }}</dd>
                        @endif
                    </dl>
                </div>

                @if($quote->staff_notes)
                    <div class="rounded-2xl bg-white border border-slate-200 p-5">
                        <h2 class="font-semibold text-slate-700 mb-2">Staff notes</h2>
                        <p class="text-sm text-slate-600 whitespace-pre-line">{{ $quote->staff_notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="space-y-4">
                @can('manage_storefront_quotes')
                    @unless($quote->isTerminal())
                        <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-3">
                            <h2 class="font-semibold text-slate-700">Actions</h2>

                            @if($quote->status === \App\Models\Storefront\StorefrontQuote::STATUS_REQUESTED)
                                <form method="POST" action="{{ route('storefront.admin.quotes.review', $quote) }}">@csrf
                                    <button class="w-full px-3 py-2 rounded-xl bg-slate-700 text-white text-sm">Mark as reviewing</button>
                                </form>
                            @endif

                            @if(in_array($quote->status, [\App\Models\Storefront\StorefrontQuote::STATUS_REQUESTED, \App\Models\Storefront\StorefrontQuote::STATUS_REVIEWING]))
                                <form method="POST" action="{{ route('storefront.admin.quotes.quote', $quote) }}" class="space-y-2 border-t border-slate-100 pt-3">@csrf
                                    <p class="text-xs text-slate-500">Provide pricing</p>
                                    <input type="number" step="0.01" name="quoted_total" placeholder="Total ({{ $quote->currency_code }})" required class="w-full rounded-xl border-slate-300 text-sm">
                                    <input type="date" name="valid_until" class="w-full rounded-xl border-slate-300 text-sm">
                                    <textarea name="staff_notes" rows="2" placeholder="Notes (optional)" class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                                    <button class="w-full px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm">Send quote</button>
                                </form>
                            @endif

                            @if($quote->status === \App\Models\Storefront\StorefrontQuote::STATUS_QUOTED)
                                <form method="POST" action="{{ route('storefront.admin.quotes.accept', $quote) }}" class="border-t border-slate-100 pt-3">@csrf
                                    <button class="w-full px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm">Mark accepted</button>
                                </form>
                            @endif

                            @if($quote->status === \App\Models\Storefront\StorefrontQuote::STATUS_ACCEPTED)
                                <form method="POST" action="{{ route('storefront.admin.quotes.convert', $quote) }}" class="border-t border-slate-100 pt-3">@csrf
                                    <button class="w-full px-3 py-2 rounded-xl bg-slate-800 text-white text-sm">Convert to order</button>
                                </form>
                                <p class="text-xs text-slate-400">Creates a pending order from this quote.</p>
                            @endif

                            <form method="POST" action="{{ route('storefront.admin.quotes.decline', $quote) }}" class="border-t border-slate-100 pt-3" onsubmit="return confirm('Decline this quote?');">@csrf
                                <input type="text" name="reason" placeholder="Reason (optional)" class="w-full rounded-xl border-slate-300 text-sm mb-2">
                                <button class="w-full px-3 py-2 rounded-xl border border-red-300 text-red-600 text-sm">Decline</button>
                            </form>
                        </div>
                    @else
                        <div class="rounded-2xl bg-white border border-slate-200 p-5 text-sm text-slate-500">
                            This quote is {{ $quote->status }} and closed to further action.
                        </div>
                    @endunless
                @endcan

                @if($quote->quoted_total !== null)
                    <div class="rounded-2xl bg-white border border-slate-200 p-5">
                        <p class="text-xs uppercase text-slate-400">Quoted total</p>
                        <p class="text-2xl font-bold text-slate-800">{{ $quote->currency_code }} {{ number_format((float)$quote->quoted_total, 2) }}</p>
                        @if($quote->valid_until)<p class="text-xs text-slate-400 mt-1">Valid until {{ $quote->valid_until->format('Y-m-d') }}</p>@endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
