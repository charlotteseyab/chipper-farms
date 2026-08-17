{{--
    Phase 8: the fulfilment and payment choices are the organisation's, not the
    enum's. This page previously offered pickup, delivery, COD and Paystack to
    every farm regardless of what it had switched on.
--}}
<x-storefront.layout :title="'Checkout'">
    <div class="max-w-4xl mx-auto px-4 py-10" x-data="{ fulfil: '{{ $fulfilmentMethods[0] }}', addr: 'saved' }">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Checkout</h1>

        @if($errors->any())<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        @if(empty($paymentMethods))
            {{--
                A farm with neither Paystack nor cash switched on cannot take an
                order. Say so, with a way to reach it — blueprint §46, no dead
                ends. The submit button is withheld rather than left to fail
                validation after the buyer has filled the form in.
            --}}
            <div class="rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 px-5 py-4 text-sm">
                <p class="font-semibold">This farm is not taking online payment at the moment.</p>
                <p class="mt-1">Your cart is saved. Please contact us to arrange your order
                    @if($supportPhone) on <a class="underline font-medium" href="tel:{{ $supportPhone }}">{{ $supportPhone }}</a>@endif
                    @if($supportEmail) at <a class="underline font-medium" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>@endif.
                </p>
            </div>
        @else

        @if($isGuest && ! $guestAllowed)
            {{-- This farm asks for an account. Not a 404 on a full basket —
                 the buyer is told what to do and their cart is waiting. --}}
            <div class="rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 px-5 py-4 text-sm">
                <p class="font-semibold">This farm asks buyers to sign in before ordering.</p>
                <p class="mt-1">Your cart is saved — it will still be here after you sign in.</p>
                <a href="{{ route('login') }}" class="mt-3 inline-block px-4 py-2 rounded-xl bg-slate-800 text-white">Sign in or create an account</a>
            </div>
        @else

        <form method="POST" action="{{ route('storefront.checkout.place') }}" class="grid md:grid-cols-3 gap-6">
            @csrf
            <div class="md:col-span-2 space-y-6">
                @if($isGuest)
                    {{-- Phase 9 (§38): guest identity. A name and one way to be
                         reached — the farm has to be able to confirm a live
                         animal is available before anyone travels. --}}
                    <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-3">
                        <h2 class="font-semibold text-slate-700">Your details</h2>
                        <p class="text-xs text-slate-500">
                            No account needed. We save your details with this order and give you a private tracking link. If you create an account later with the same email, you can claim the order.
                        </p>
                        <input type="text" name="guest_name" required maxlength="200" placeholder="Your name"
                               value="{{ old('guest_name') }}" class="w-full rounded-xl border-slate-300 text-sm">
                        <div class="grid sm:grid-cols-2 gap-3">
                            <input type="email" name="guest_email" maxlength="190" placeholder="Email"
                                   value="{{ old('guest_email') }}" class="w-full rounded-xl border-slate-300 text-sm">
                            <input type="text" name="guest_phone" maxlength="60" placeholder="Phone"
                                   value="{{ old('guest_phone') }}" class="w-full rounded-xl border-slate-300 text-sm">
                        </div>
                        <p class="text-xs text-slate-400">A phone number or an email — whichever is easier to reach you on.</p>
                    </div>
                @endif

                {{-- Fulfilment --}}
                <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                    <h2 class="font-semibold text-slate-700">Fulfilment</h2>
                    <div class="flex gap-4 text-sm">
                        @foreach($fulfilmentMethods as $method)
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" name="fulfilment_method" value="{{ $method }}" x-model="fulfil" @checked($loop->first)>
                                {{ $method === 'pickup' ? 'Farm pickup' : 'Local delivery' }}
                            </label>
                        @endforeach
                    </div>

                    <div x-show="fulfil === 'delivery'" class="space-y-3 border-t border-slate-100 pt-4">
                        @if($deliveryZones->isNotEmpty())
                            {{--
                                Zone pricing. When a farm has defined zones the
                                buyer must pick one and its fee is the delivery
                                fee — per-listing fees are not added on top, or
                                one journey would be charged several times.
                            --}}
                            <label class="block text-sm">
                                <span class="text-slate-600">Delivery area</span>
                                <select name="delivery_zone_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                    <option value="">Choose your area…</option>
                                    @foreach($deliveryZones as $zone)
                                        <option value="{{ $zone->id }}" @selected(old('delivery_zone_id') == $zone->id)>
                                            {{ $zone->name }} — GHS {{ number_format((float) $zone->fee, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
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
                        <input type="text" name="ship_to_name" placeholder="Recipient name" class="w-full rounded-xl border-slate-300 text-sm" value="{{ old('ship_to_name', auth()->user()?->name ?? old('guest_name')) }}">
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
                    @foreach($paymentMethods as $method)
                        <label class="flex items-start gap-3 text-sm rounded-xl border border-slate-200 p-3">
                            <input type="radio" name="payment_method" value="{{ $method }}" @checked($loop->first) class="mt-1">
                            @if($method === 'cod')
                                <span><span class="font-medium">Cash on delivery / pickup</span><br><span class="text-slate-500">Pay when you collect or receive your order. We confirm availability first.</span></span>
                            @else
                                <span><span class="font-medium">Pay online (Paystack)</span><br><span class="text-slate-500">Secure card / mobile money. Your order is still confirmed by our team before fulfilment.</span></span>
                            @endif
                        </label>
                    @endforeach
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
                    <div class="border-t border-slate-100 mt-3 pt-3 flex justify-between">
                        <span class="text-slate-600">Subtotal</span><span>GHS {{ number_format((float)$subtotal,2) }}</span>
                    </div>

                    {{-- §38: "show subtotal, delivery, tax, total". Until
                         IMP-091 the order carried no tax at all, so this line
                         could not exist. The figure shown is what the real
                         TaxCalculator will charge on the goods; delivery is
                         part of the taxable base too, so the final amount is
                         confirmed once the fulfilment choice is known. --}}
                    @if($taxPreview !== null)
                        <div class="mt-1 flex justify-between text-slate-600">
                            <span>{{ $taxLabel }}</span>
                            <span>{{ $taxInclusive ? 'included' : 'GHS ' . number_format((float) $taxPreview, 2) }}</span>
                        </div>
                    @endif

                    <div class="mt-2 pt-2 border-t border-slate-100 flex justify-between font-bold">
                        <span>Total so far</span>
                        <span>GHS {{ number_format((float) $totalPreview, 2) }}</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Delivery fee (if applicable) is added on the next step, and is taxed with the rest.</p>
                    <button type="submit" class="mt-4 w-full px-5 py-2.5 rounded-xl bg-slate-800 text-white font-medium">Place order</button>
                </div>

                @if($refundPolicy)
                    <div class="rounded-2xl bg-white border border-slate-200 p-5 text-xs text-slate-500">
                        <p class="font-semibold text-slate-600 mb-1">Refunds &amp; returns</p>
                        <p class="whitespace-pre-line">{{ $refundPolicy }}</p>
                    </div>
                @endif
            </div>
        </form>
        @endif
        @endif
    </div>
</x-storefront.layout>
