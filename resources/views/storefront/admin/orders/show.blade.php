<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <div class="flex items-start justify-between mb-6">
            <div>
                <p class="text-xs font-mono text-slate-400">{{ $order->reference }}</p>
                <h1 class="text-2xl font-bold text-gray-800">Order from {{ optional($order->counterparty)->name ?? 'customer' }}</h1>
                <p class="text-sm text-slate-500 mt-1">
                    <span class="font-medium">{{ str_replace('_',' ',$order->status) }}</span>
                    · {{ strtoupper($order->payment_method) }} ({{ str_replace('_',' ',$order->payment_status) }})
                    · {{ $order->fulfilment_method }}
                </p>
            </div>
            <a href="{{ route('storefront.admin.orders.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Back</a>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <div class="space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Items</h2>
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach($order->items as $item)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-3 align-top">
                                        <p class="font-medium text-slate-800">{{ $item->description }}</p>
                                        @if($item->purchase_type)
                                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                                @if($item->purchase_type === \App\Storefront\Services\StorefrontPigPricingService::PURCHASE_LIVE && is_array($item->selected_pig_weight_snapshot))
                                                    @foreach($item->selected_pig_weight_snapshot as $snapshot)
                                                        <span class="rounded-full bg-slate-100 px-2 py-1">
                                                            {{ $snapshot['ear_tag'] ?? 'Selected pig' }}
                                                            @if(isset($snapshot['kg']))
                                                                · {{ rtrim(rtrim((string) $snapshot['kg'], '0'), '.') }} kg
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                @elseif($item->purchase_type === \App\Storefront\Services\StorefrontPigPricingService::PURCHASE_CARCASS && $item->requested_kg)
                                                    <span class="rounded-full bg-slate-100 px-2 py-1">
                                                        Requested carcass: {{ rtrim(rtrim((string) $item->requested_kg, '0'), '.') }} kg
                                                    </span>
                                                @endif
                                                @if($item->price_per_kg_snapshot)
                                                    <span class="rounded-full bg-slate-100 px-2 py-1">
                                                        GHS {{ number_format((float) $item->price_per_kg_snapshot, 2) }} / kg
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3 align-top text-slate-500">{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }} {{ $item->displayQuantityUnit() }} × {{ number_format((float)$item->unit_price,2) }}</td>
                                    <td class="py-3 align-top text-right font-medium">GHS {{ number_format((float)$item->line_total,2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="border-t border-slate-200 mt-3 pt-3 text-sm space-y-1">
                        <div class="flex justify-between text-slate-500"><span>Subtotal</span><span>GHS {{ number_format((float)$order->subtotal,2) }}</span></div>
                        <div class="flex justify-between text-slate-500"><span>Delivery</span><span>GHS {{ number_format((float)$order->delivery_fee,2) }}</span></div>
                        <div class="flex justify-between font-bold text-slate-800"><span>Total</span><span>GHS {{ number_format((float)$order->total,2) }}</span></div>
                    </div>
                </div>

                @foreach($pigSelection as $panel)
                    @include('storefront.admin.orders.partials.pig-selection-panel', ['panel' => $panel, 'order' => $order])
                @endforeach

                @if($order->fulfilment_method === \App\Models\Storefront\StorefrontOrder::FULFIL_DELIVERY)
                    <div class="rounded-2xl bg-white border border-slate-200 p-5 text-sm">
                        <h2 class="font-semibold text-slate-700 mb-2">Delivery</h2>
                        <p class="text-slate-600">{{ $order->ship_to_name }} · {{ $order->ship_to_phone }}<br>{{ $order->ship_address_line_1 }} {{ $order->ship_address_line_2 }}<br>{{ $order->ship_city }} {{ $order->ship_region }} {{ $order->ship_ghana_post_gps }}</p>
                    </div>
                @endif

                @if($order->payments->isNotEmpty())
                    <div class="rounded-2xl bg-white border border-slate-200 p-5">
                        <h2 class="font-semibold text-slate-700 mb-3">Payments</h2>
                        <table class="w-full text-sm">
                            <thead class="text-slate-400 text-left"><tr><th class="py-1">Provider</th><th class="py-1">Ref</th><th class="py-1">Amount</th><th class="py-1">Status</th></tr></thead>
                            <tbody>
                                @foreach($order->payments as $p)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-2">{{ strtoupper($p->provider) }}</td>
                                        <td class="py-2 font-mono text-xs">{{ $p->provider_reference }}</td>
                                        <td class="py-2">GHS {{ number_format((float)$p->amount,2) }}</td>
                                        <td class="py-2">{{ $p->status }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Timeline</h2>
                    <ol class="space-y-3">
                        @foreach($order->events as $event)
                            <li class="text-xs">
                                <p class="text-slate-700">{{ $event->note ?? str_replace('_',' ',(string)$event->to_status) }}</p>
                                <p class="text-slate-400">{{ $event->created_at->format('d M, H:i') }} @if($event->actor) · {{ $event->actor->name }}@endif</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- Actions --}}
            <div class="space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-3">
                    <h2 class="font-semibold text-slate-700">Actions</h2>

                    @if($order->isConfirmable())
                        @can('confirm_storefront_orders')
                            <form method="POST" action="{{ route('storefront.admin.orders.confirm', $order) }}">@csrf
                                <button class="w-full px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm">Confirm order</button>
                            </form>
                            <p class="text-xs text-slate-400">Confirming reserves the listing(s). Payment, if online, is already verified.</p>
                        @endcan
                    @endif

                    @if($order->payment_method === 'paystack' && $order->payment_status === \App\Models\Storefront\StorefrontOrder::PAY_PAYSTACK_PEND)
                        @can('confirm_storefront_orders')
                            <form method="POST" action="{{ route('storefront.admin.orders.verify-payment', $order) }}" class="border-t border-slate-100 pt-3">@csrf
                                <button class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">Re-verify payment</button>
                            </form>
                        @endcan
                    @endif

                    @if($order->status === \App\Models\Storefront\StorefrontOrder::STATUS_CONFIRMED)
                        @can('manage_storefront_orders')
                            <form method="POST" action="{{ route('storefront.admin.orders.dispatch', $order) }}" class="border-t border-slate-100 pt-3">@csrf
                                <button class="w-full px-3 py-2 rounded-xl bg-slate-700 text-white text-sm">
                                    {{ $order->fulfilment_method === \App\Models\Storefront\StorefrontOrder::FULFIL_DELIVERY ? 'Mark out for delivery' : 'Mark ready for pickup' }}
                                </button>
                            </form>
                        @endcan
                    @endif

                    @if(in_array($order->status, [\App\Models\Storefront\StorefrontOrder::STATUS_READY_FOR_PICKUP, \App\Models\Storefront\StorefrontOrder::STATUS_OUT_FOR_DELIVERY], true))
                        @can('manage_storefront_orders')
                            <form method="POST" action="{{ route('storefront.admin.orders.complete', $order) }}" class="border-t border-slate-100 pt-3">@csrf
                                <button class="w-full px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm">Complete order</button>
                            </form>
                            <p class="text-xs text-slate-400">Completion settles cash-on-delivery and closes the listing.</p>
                        @endcan
                    @endif

                    @can('refund_storefront_orders')
                        @if(in_array($order->status, [\App\Models\Storefront\StorefrontOrder::STATUS_CONFIRMED, \App\Models\Storefront\StorefrontOrder::STATUS_READY_FOR_PICKUP, \App\Models\Storefront\StorefrontOrder::STATUS_OUT_FOR_DELIVERY, \App\Models\Storefront\StorefrontOrder::STATUS_COMPLETED], true))
                            <form method="POST" action="{{ route('storefront.admin.orders.refund', $order) }}" class="border-t border-slate-100 pt-3" onsubmit="return confirm('Refund this order? This reverses customer-facing financial legs.');">@csrf
                                <input name="reason" required maxlength="500" placeholder="Refund reason" class="w-full rounded-xl border-slate-300 text-sm mb-2">
                                <button class="w-full px-3 py-2 rounded-xl bg-rose-600 text-white text-sm">Refund order</button>
                            </form>
                            <p class="text-xs text-slate-400">Treasurer-grade. COGS/inventory return is handed to finance.</p>
                        @endif
                    @endcan

                    @if($order->isCancellable())
                        @can('cancel_storefront_orders')
                            <form method="POST" action="{{ route('storefront.admin.orders.cancel', $order) }}" class="border-t border-slate-100 pt-3" onsubmit="return confirm('Cancel this order?');">@csrf
                                <input type="text" name="reason" placeholder="Reason (optional)" class="w-full rounded-xl border-slate-300 text-sm mb-2">
                                <button class="w-full px-3 py-2 rounded-xl border border-red-300 text-red-600 text-sm">Cancel order</button>
                            </form>
                        @endcan
                    @endif
                </div>

                @if($order->customer_note)
                    <div class="rounded-2xl bg-white border border-slate-200 p-5 text-sm">
                        <p class="text-xs uppercase text-slate-400 mb-1">Customer note</p>
                        <p class="text-slate-600">{{ $order->customer_note }}</p>
                    </div>
                @endif

                {{--
                    §46 — the recovery console.

                    "Every non-final state needs a visible recovery path."
                    The order has recorded its finance posting outcome since
                    Phase 4 and showed it nowhere, so a line that never
                    reached the books was invisible on the one page a staff
                    member actually opens.

                    Seeing the state follows `view_storefront_orders` — anyone
                    who can open the order should be able to tell that something
                    is wrong with it, and hiding that from a clerk recreates the
                    dead end this panel exists to close. Acting on it is a
                    separate right, below.
                --}}
                @if($order->finance_posting_status && $order->finance_posting_status !== 'posted')
                    @php
                        $financeTone = match ($order->finance_posting_status) {
                            'failed'  => 'border-rose-200 bg-rose-50 text-rose-900',
                            'pending' => 'border-sky-200 bg-sky-50 text-sky-900',
                            default   => 'border-amber-200 bg-amber-50 text-amber-900',
                        };
                    @endphp
                    <div class="rounded-2xl border p-5 {{ $financeTone }}">
                        <p class="font-semibold">
                            @switch($order->finance_posting_status)
                                @case('failed')  This order has not reached the books @break
                                @case('pending') Finance posting queued @break
                                @case('skipped') Some lines were not posted @break
                                @default        Finance posting: {{ $order->finance_posting_status }}
                            @endswitch
                        </p>

                        @if($order->finance_posting_error)
                            <p class="mt-2 text-sm">{{ $order->finance_posting_error }}</p>
                        @endif

                        @if($order->finance_posting_status === 'skipped')
                            <p class="mt-2 text-sm">
                                Deferred lines are not errors — they are lines the sale spine could not
                                route automatically (a pig with no weight, feed not sold in kg). Record
                                them by hand, or correct the listing and re-run.
                            </p>
                        @endif

                        @can('manage_storefront_orders')
                            @if($replayableEvent)
                                <form method="POST" action="{{ route('storefront.admin.orders.retry-finance', $order) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm">
                                        Retry finance posting
                                    </button>
                                    <span class="ml-2 text-xs">
                                        Safe to press more than once — the posting is idempotent.
                                    </span>
                                </form>
                            @endif
                        @endcan
                    </div>
                @endif

                {{-- §46: held listings need a visible expire/release/confirm path. --}}
                @if($liveHolds->isNotEmpty())
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <p class="font-semibold text-slate-800">Stock held for this order</p>
                        <ul class="mt-2 space-y-1 text-sm text-slate-600">
                            @foreach($liveHolds as $hold)
                                <li class="flex items-center justify-between gap-3">
                                    <span>{{ $hold->listing?->title ?? 'Listing #' . $hold->listing_id }}</span>
                                    <span class="tabular-nums">
                                        {{ rtrim(rtrim($hold->quantityString(), '0'), '.') }}
                                        @if($hold->isLive())
                                            @if($hold->expires_at)
                                                · until {{ $hold->expires_at->format('d M H:i') }}
                                            @else
                                                · until you decide
                                            @endif
                                        @else
                                            · {{ $hold->status }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs text-slate-500">
                            Confirming the order consumes the hold; cancelling releases it. A hold with a
                            time limit lapses on its own and puts the stock back on sale.
                        </p>
                    </div>
                @endif

                @can('manage_storefront_orders')
                    <form method="POST" action="{{ route('storefront.admin.orders.note', $order) }}" class="rounded-2xl bg-white border border-slate-200 p-5">@csrf
                        <h2 class="font-semibold text-slate-700 mb-2 text-sm">Add a note</h2>
                        <textarea name="note" rows="2" required class="w-full rounded-xl border-slate-300 text-sm" placeholder="Internal note (also shown on the timeline)"></textarea>
                        <button class="mt-2 w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">Add note</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    <script>
        (function () {
            function bootSelectionForm(form) {
                var limit = parseInt(form.getAttribute('data-selection-limit') || '0', 10);
                if (!limit) { return; }

                var boxes = Array.prototype.slice.call(form.querySelectorAll('[data-order-pig-checkbox]'));
                var message = form.querySelector('[data-selection-message]');

                function refresh(changed) {
                    var checked = boxes.filter(function (box) { return box.checked; });

                    if (checked.length > limit && changed) {
                        changed.checked = false;
                        checked = boxes.filter(function (box) { return box.checked; });
                    }

                    boxes.forEach(function (box) {
                        if (box.checked || box.hasAttribute('data-server-disabled')) {
                            return;
                        }

                        box.disabled = checked.length >= limit;
                    });

                    if (message) {
                        message.textContent = checked.length >= limit
                            ? 'Selection limit reached for this paid line.'
                            : 'Nothing is sold until you confirm the order.';
                    }
                }

                boxes.forEach(function (box) {
                    if (box.disabled) {
                        box.setAttribute('data-server-disabled', 'true');
                    }

                    box.addEventListener('change', function () { refresh(box); });
                });

                refresh(null);
            }

            document.querySelectorAll('[data-order-pig-selection]').forEach(bootSelectionForm);
        })();
    </script>
</x-app-layout>
