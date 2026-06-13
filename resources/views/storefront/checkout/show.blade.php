<x-storefront.layout :title="'Checkout'">
    <div class="max-w-4xl mx-auto px-4 py-10" x-data="{ fulfil: 'pickup', addr: 'saved' }">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Checkout</h1>

        @if($errors->any())<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ route('storefront.account.checkout.place') }}" class="grid md:grid-cols-3 gap-6">
            @csrf
            <div class="md:col-span-2 space-y-6">
                {{-- Fulfilment --}}
                <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                    <h2 class="font-semibold text-slate-700">Fulfilment</h2>
                    <div class="flex gap-4 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="fulfilment_method" value="pickup" x-model="fulfil" checked> Farm pickup</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="fulfilment_method" value="delivery" x-model="fulfil"> Local delivery</label>
                    </div>

                    <div x-show="fulfil === 'delivery'" class="space-y-3 border-t border-slate-100 pt-4">
                        @if($addresses->isNotEmpty())
                            <label class="block text-sm">
                                <span class="text-slate-600">Saved address</span>
                                <select name="shipping_address_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                    <option value="">Enter a new address…</option>
                                    @foreach($addresses as $addr)
                                        <option value="{{ $addr->id }}">{{ $addr->label }} — {{ $addr->address_line_1 }}, {{ $addr->city }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <p class="text-xs text-slate-400">Or fill the fields below for a new delivery address.</p>
                        @endif
                        <input type="text" name="ship_to_name" placeholder="Recipient name" class="w-full rounded-xl border-slate-300 text-sm" value="{{ old('ship_to_name', auth()->user()->name) }}">
                        <input type="text" name="ship_to_phone" placeholder="Phone" class="w-full rounded-xl border-slate-300 text-sm" value="{{ old('ship_to_phone') }}">
                        <input type="text" name="ship_address_line_1" placeholder="Address line 1" class="w-full rounded-xl border-slate-300 text-sm" value="{{ old('ship_address_line_1') }}">
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="ship_city" placeholder="City" class="rounded-xl border-slate-300 text-sm" value="{{ old('ship_city') }}">
                            <input type="text" name="ship_region" placeholder="Region" class="rounded-xl border-slate-300 text-sm" value="{{ old('ship_region') }}">
                        </div>
                        <input type="text" name="ship_ghana_post_gps" placeholder="GhanaPost GPS (optional)" class="w-full rounded-xl border-slate-300 text-sm" value="{{ old('ship_ghana_post_gps') }}">
                    </div>
                </div>

                {{-- Payment --}}
                <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-3">
                    <h2 class="font-semibold text-slate-700">Payment</h2>
                    <label class="flex items-start gap-3 text-sm rounded-xl border border-slate-200 p-3">
                        <input type="radio" name="payment_method" value="cod" checked class="mt-1">
                        <span><span class="font-medium">Cash on delivery / pickup</span><br><span class="text-slate-500">Pay when you collect or receive your order. We confirm availability first.</span></span>
                    </label>
                    <label class="flex items-start gap-3 text-sm rounded-xl border border-slate-200 p-3">
                        <input type="radio" name="payment_method" value="paystack" class="mt-1">
                        <span><span class="font-medium">Pay online (Paystack)</span><br><span class="text-slate-500">Secure card / mobile money. Your order is still confirmed by our team before fulfilment.</span></span>
                    </label>
                </div>

                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <label class="block text-sm">
                        <span class="text-slate-600">Order note (optional)</span>
                        <textarea name="customer_note" rows="2" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ old('customer_note') }}</textarea>
                    </label>
                </div>
            </div>

            {{-- Summary --}}
            <div class="space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Order summary</h2>
                    <ul class="text-sm space-y-2">
                        @foreach($cart->items as $item)
                            <li class="flex justify-between"><span class="text-slate-600">{{ $item->listing->title }} × {{ rtrim(rtrim((string)$item->quantity,'0'),'.') }}</span><span>GHS {{ number_format((float)$item->lineTotal(),2) }}</span></li>
                        @endforeach
                    </ul>
                    <div class="border-t border-slate-100 mt-3 pt-3 flex justify-between font-bold">
                        <span>Subtotal</span><span>GHS {{ number_format((float)$subtotal,2) }}</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Delivery fee (if applicable) added on the next step.</p>
                    <button type="submit" class="mt-4 w-full px-5 py-2.5 rounded-xl bg-slate-800 text-white font-medium">Place order</button>
                </div>
            </div>
        </form>
    </div>
</x-storefront.layout>
