<x-storefront.layout :title="'Your cart'">
    <div class="max-w-4xl mx-auto px-4 py-10">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Your cart</h1>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        @if($cart === null || $cart->items->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-400">
                Your cart is empty. <a href="{{ route('storefront.shop') }}" class="text-slate-700 underline">Browse the shop</a>.
            </div>
        @else
            <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr><th class="px-5 py-2">Item</th><th class="px-5 py-2">Unit price</th><th class="px-5 py-2">Qty</th><th class="px-5 py-2">Total</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($cart->items as $item)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 py-3">
                                    <a href="{{ route('storefront.listing', $item->listing->slug) }}" class="text-slate-800 hover:underline">{{ $item->listing->title }}</a>
                                    {{--
                                        A pig line is a specific animal or a
                                        weight of meat, and saying only the
                                        listing title would leave a basket of
                                        four identical-looking rows at four
                                        different prices.
                                    --}}
                                    @if($item->purchase_type === 'live')
                                        @php($chosen = collect($item->selected_pig_weight_snapshot ?? [])->first())
                                        <span class="block text-xs text-slate-500">
                                            Live animal{{ $chosen ? ' · ' . $chosen['ear_tag'] : '' }}
                                            @if($chosen) · {{ rtrim(rtrim($chosen['kg'], '0'), '.') }} kg @endif
                                            @if($item->price_per_kg_snapshot)
                                                at GHS {{ number_format((float) $item->price_per_kg_snapshot, 2) }}/kg
                                            @endif
                                        </span>
                                    @elseif($item->purchase_type === 'carcass')
                                        <span class="block text-xs text-slate-500">
                                            Carcass weight · {{ rtrim(rtrim((string) $item->requested_kg, '0'), '.') }} kg
                                            @if($item->price_per_kg_snapshot)
                                                at GHS {{ number_format((float) $item->price_per_kg_snapshot, 2) }}/kg
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">GHS {{ number_format((float)$item->price_snapshot, 2) }}</td>
                                <td class="px-5 py-3">
                                    @if($item->purchase_type === 'live')
                                        {{--
                                            One animal is one animal. A quantity
                                            box here would let a buyer set 5 and
                                            sell the same pig five times.
                                        --}}
                                        <span class="text-slate-500">1</span>
                                    @elseif($item->purchase_type === 'carcass')
                                        <span class="text-slate-500">{{ rtrim(rtrim((string) $item->requested_kg, '0'), '.') }} kg</span>
                                    @else
                                        <form method="POST" action="{{ route('storefront.cart.update', $item) }}" class="flex items-center gap-2">
                                            @csrf @method('PATCH')
                                            <input type="number" step="0.0001" name="quantity" value="{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }}" class="w-20 rounded-lg border-slate-300 text-sm">
                                            <button class="text-slate-500 text-xs hover:underline">Update</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-5 py-3 font-medium">GHS {{ number_format((float)$item->lineTotal(), 2) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('storefront.cart.remove', $item) }}">@csrf @method('DELETE')
                                        <button class="text-red-500 text-xs hover:underline">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200 bg-slate-50">
                            <td colspan="3" class="px-5 py-3 text-right font-semibold">Subtotal</td>
                            <td class="px-5 py-3 font-bold">GHS {{ number_format((float)$cart->subtotal(), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <a href="{{ route('storefront.shop') }}" class="text-sm text-slate-500">← Continue shopping</a>
                <a href="{{ route('storefront.checkout.show') }}" class="px-6 py-2.5 rounded-xl bg-slate-800 text-white font-medium">Checkout</a>
            </div>
            <p class="text-xs text-slate-400 mt-3">Delivery fees, if any, are added at checkout. Pig orders are confirmed by our team before acceptance.</p>
        @endif
    </div>
</x-storefront.layout>
