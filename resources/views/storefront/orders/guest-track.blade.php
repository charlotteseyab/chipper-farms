{{--
    A guest's order (Phase 9, blueprint §38).

    This one URL is the confirmation page, the tracking page and the recovery
    page — §38's last row asks that expired, failed and pending payment states
    "all lead to action", and for a guest there is nowhere else to lead them.

    The link carries the order's token, so it is worth keeping: the page says so
    rather than assuming the buyer will work it out.
--}}
@php $money = fn ($v) => number_format((float) $v, 2); @endphp

<x-storefront.layout :title="'Order ' . $order->reference">
    <div class="max-w-3xl mx-auto px-4 py-10 space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-slate-800">Order {{ $order->reference }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                Placed {{ optional($order->placed_at)->format('d M Y') }}
                @if($order->guest_name) · {{ $order->guest_name }}@endif
            </p>
        </div>

        @php
            $stateCopy = match ($order->status) {
                'pending_payment'      => ['Waiting for your payment', 'We have not received payment yet. If your payment was interrupted, contact us and we will help you finish it.', 'amber'],
                'pending_confirmation' => ['We are checking availability', 'Live animals are confirmed by our team before your order is accepted. We will be in touch shortly.', 'amber'],
                'payment_confirmed'    => ['Payment received', 'Thank you. Our team is confirming availability before we accept the order.', 'emerald'],
                'confirmed'            => ['Order confirmed', 'Your order is accepted and being prepared.', 'emerald'],
                'ready_for_pickup'     => ['Ready for pickup', 'Come and collect when it suits you.', 'emerald'],
                'out_for_delivery'     => ['On its way', 'Your order is out for delivery.', 'emerald'],
                'completed'            => ['Completed', 'Thank you for your business.', 'emerald'],
                'cancelled'            => ['Cancelled', 'This order was cancelled. Please contact us if that is unexpected.', 'rose'],
                'failed'               => ['Payment failed', 'The payment did not go through and nothing was charged. Contact us and we will arrange another way to pay.', 'rose'],
                'refunded'             => ['Refunded', 'This order has been refunded.', 'slate'],
                default                => [ucfirst(str_replace('_', ' ', $order->status)), 'Contact us if you need an update.', 'slate'],
            };

            // Full class strings, not interpolated fragments: Tailwind scans
            // source for complete class names, so "border-{$tone}-200" compiles
            // to nothing and the banner renders unstyled.
            $tone = match ($stateCopy[2]) {
                'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                'amber'   => 'border-amber-200 bg-amber-50 text-amber-900',
                'rose'    => 'border-rose-200 bg-rose-50 text-rose-900',
                default   => 'border-slate-200 bg-slate-50 text-slate-900',
            };
        @endphp

        <div class="rounded-2xl border px-5 py-4 {{ $tone }}">
            <p class="font-semibold">{{ $stateCopy[0] }}</p>
            <p class="mt-1 text-sm">{{ $stateCopy[1] }}</p>
            @if($supportPhone || $supportEmail)
                <p class="mt-2 text-sm">
                    @if($supportPhone)<a class="underline" href="tel:{{ $supportPhone }}">{{ $supportPhone }}</a>@endif
                    @if($supportPhone && $supportEmail) · @endif
                    @if($supportEmail)<a class="underline" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>@endif
                </p>
            @endif
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach($order->items as $item)
                    <li class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-800 truncate">{{ $item->description }}</p>
                            <p class="text-xs text-slate-500">
                                {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} {{ $item->displayQuantityUnit() }}
                                × GHS {{ $money($item->unit_price) }}
                            </p>
                        </div>
                        <span class="tabular-nums">GHS {{ $money($item->line_total) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 py-3 border-t border-slate-100 text-sm space-y-1">
                <div class="flex justify-between text-slate-600"><span>Subtotal</span><span>GHS {{ $money($order->subtotal) }}</span></div>
                @if(bccomp((string) $order->delivery_fee, '0', 2) > 0)
                    <div class="flex justify-between text-slate-600">
                        <span>Delivery{{ $order->delivery_zone_name ? ' — ' . $order->delivery_zone_name : '' }}</span>
                        <span>GHS {{ $money($order->delivery_fee) }}</span>
                    </div>
                @endif
                <div class="flex justify-between font-bold text-slate-800 pt-1"><span>Total</span><span>GHS {{ $money($order->total) }}</span></div>
            </div>
        </div>

        @auth
            @if($canClaim)
                {{-- The claim needs the token, which is why it is posted from
                     this page rather than offered from the account area. --}}
                <form method="POST" action="{{ route('storefront.order.claim', ['reference' => $order->reference, 'token' => $token]) }}"
                      class="rounded-2xl bg-white border border-slate-200 p-5">
                    @csrf
                    <p class="font-semibold text-slate-800">Keep this order on your account</p>
                    <p class="mt-1 text-sm text-slate-500">
                        You are signed in. Add this order to your account so you can find it without this link.
                    </p>
                    <button type="submit" class="mt-3 px-4 py-2 rounded-xl bg-slate-800 text-white text-sm">Add to my account</button>
                </form>
            @endif
        @else
            <div class="rounded-2xl bg-white border border-slate-200 p-5">
                @if($buyerAccountExists)
                    <p class="font-semibold text-slate-800">You already have a buyer account</p>
                    <p class="mt-1 text-sm text-slate-500">Sign in with {{ $order->guest_email }} and this order will be saved to your account.</p>
                    <a href="{{ route('storefront.buyer.login', ['email' => $order->guest_email, 'claim_reference' => $order->reference, 'claim_token' => $token]) }}" class="mt-3 inline-flex rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">Sign in to save this order</a>
                @else
                    <p class="font-semibold text-slate-800">Save this order</p>
                    <p class="mt-1 text-sm text-slate-500">Create a buyer account with the details from checkout. You only need to choose a password, and this order will be saved to that account.</p>
                    <a href="{{ route('storefront.buyer.register.from-order', ['reference' => $order->reference, 'token' => $token]) }}" class="mt-3 inline-flex rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">Create buyer account</a>
                @endif
            </div>
        @endauth

    </div>
</x-storefront.layout>
