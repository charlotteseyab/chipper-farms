<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
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

        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Items</h2>
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach($order->items as $item)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-2">{{ $item->description }}</td>
                                    <td class="py-2 text-slate-500">{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }} {{ $item->quantity_unit }} × {{ number_format((float)$item->unit_price,2) }}</td>
                                    <td class="py-2 text-right font-medium">GHS {{ number_format((float)$item->line_total,2) }}</td>
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

                {{--
                    Choosing the exact animals for a pooled pig line.

                    A listing can advertise "5 head" without promising which
                    five. Somebody has to decide, and it has to be a person: the
                    animals differ in weight, age and condition, and the customer
                    is buying livestock rather than units of stock.

                    The panel appears only for lines whose listing carries a
                    pool. Product lines and legacy single-pig listings never see
                    it.

                    Reading the panel follows view_storefront_orders, like the
                    rest of this page. SELECTING is a POST inside the
                    manage_storefront_orders route group, so the permission is
                    enforced by middleware and not by whether this form renders.
                --}}
                @foreach($pigSelection as $panel)
                    @php
                        $line        = $panel['item'];
                        $outstanding = $panel['outstanding'];
                        $state       = $panel['state'];
                        $ready       = $state === \App\Storefront\Services\StorefrontPigAllocationService::STATE_READY;
                        $conflict    = $state === \App\Storefront\Services\StorefrontPigAllocationService::STATE_CONFLICT;
                        $soldAlready = $panel['sold']->isNotEmpty();
                        $byWeight    = $panel['requested_kg'] !== null;
                    @endphp

                    <div @class([
                        'rounded-2xl border p-5',
                        'border-rose-300 bg-rose-50'       => $conflict,
                        'border-emerald-200 bg-emerald-50' => $ready && ! $conflict,
                        'border-amber-200 bg-amber-50'     => ! $ready && ! $conflict,
                    ])>
                        <p class="font-semibold text-slate-800">
                            {{ $soldAlready ? 'Pigs sold for' : 'Choose the pigs for' }} “{{ $line->description }}”
                        </p>

                        {{--
                            A conflict is something that changed AFTER the
                            animals were chosen — one died, or was sold at the
                            gate. It is shown here rather than raised during
                            approval, because a manager finding out mid-sale
                            has already committed the rest of the order.
                        --}}
                        @if($conflict)
                            <div class="mt-2 rounded-xl border border-rose-300 bg-white p-3 text-sm text-rose-800">
                                <p class="font-semibold">This selection can no longer be sold.</p>
                                <ul class="mt-1 list-disc list-inside space-y-0.5">
                                    @foreach($panel['problems'] as $problem)
                                        <li>{{ $problem }}</li>
                                    @endforeach
                                </ul>
                                <p class="mt-1 text-xs">Choose different animals below before approving.</p>
                            </div>
                        @endif

                        @if($panel['customer_chose'] && ! $soldAlready)
                            <p class="mt-2 rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700">
                                The customer chose these animals themselves and paid for their
                                weights. Nothing needs picking — changing them would deliver a
                                different animal than the one that was bought.
                            </p>
                        @endif

                        @if($soldAlready)
                            {{-- Terminal state: these animals are gone. Shown so
                                 the order can be traced to /sales, not edited. --}}
                            <p class="mt-1 text-sm text-slate-600">
                                Sold at approval. These animals are recorded in Sales.
                            </p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                @foreach($panel['sold'] as $allocation)
                                    <li>
                                        {{ $allocation->pig?->ear_tag_number ?? ('Pig #' . $allocation->pig_id) }}
                                        @if($allocation->pig_sale_id)
                                            <span class="text-slate-400">· sale #{{ $allocation->pig_sale_id }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-1 text-sm text-slate-600">
                                @if($byWeight)
                                    {{--
                                        A carcass order is kilograms. Progress in
                                        head would read "1 of 1 chosen" while the
                                        delivery was 30 kg short.
                                    --}}
                                    This order asks for
                                    <strong>{{ rtrim(rtrim($panel['requested_kg'], '0'), '.') }} kg</strong>
                                    of carcass weight.
                                    Chosen so far: <strong>{{ rtrim(rtrim($panel['selected_kg'], '0'), '.') }} kg</strong>.
                                    @if($ready)
                                        Covered — confirming the order will sell exactly these animals.
                                    @else
                                        Choose enough animals to cover it; the order cannot be confirmed
                                        until the weight is met.
                                    @endif
                                @else
                                    This order asks for <strong>{{ $panel['required'] }}</strong> head.
                                    @if($ready)
                                        All chosen — confirming the order will sell exactly these animals.
                                    @else
                                        <strong>{{ $outstanding }}</strong> still to choose. The order cannot be
                                        confirmed until the count matches.
                                    @endif
                                @endif
                            </p>

                            @if($panel['candidates']->isEmpty())
                                <p class="mt-3 text-sm text-rose-700">
                                    No animals are available in this listing’s pool. Add pigs to the listing,
                                    or cancel this order.
                                </p>
                            @else
                                @can('manage_storefront_orders')
                                    <form method="POST"
                                          action="{{ route('storefront.admin.orders.select-pigs', [$order, $line]) }}"
                                          class="mt-3">
                                        @csrf

                                        <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white divide-y divide-slate-100">
                                            @foreach($panel['candidates'] as $poolRow)
                                                @php $pig = $poolRow->pig; @endphp
                                                <label class="flex items-start gap-3 p-3 text-sm cursor-pointer hover:bg-slate-50">
                                                    <input type="checkbox"
                                                           name="pig_ids[]"
                                                           value="{{ $pig->id }}"
                                                           @checked(in_array((int) $pig->id, $panel['chosen'], true))
                                                           class="mt-1 rounded border-slate-300">
                                                    <span class="min-w-0">
                                                        <span class="font-medium text-slate-800">
                                                            {{ $pig->ear_tag_number ?: ('Pig #' . $pig->id) }}
                                                        </span>
                                                        <span class="block text-xs text-slate-500">
                                                            {{ $pig->breed ?: 'Breed not recorded' }}
                                                            · {{ $pig->gender ?: 'sex not recorded' }}
                                                            @if($pig->date_of_birth)
                                                                · {{ \Carbon\Carbon::parse($pig->date_of_birth)->age }} yr
                                                            @endif
                                                            @if($pig->current_weight)
                                                                · {{ rtrim(rtrim((string) $pig->current_weight, '0'), '.') }} kg
                                                            @endif
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>

                                        <button type="submit"
                                                class="mt-3 px-4 py-2 rounded-xl bg-slate-800 text-white text-sm">
                                            Save selection
                                        </button>
                                        <span class="ml-2 text-xs text-slate-500">
                                            Nothing is sold until you confirm the order.
                                        </span>
                                    </form>
                                @else
                                    <p class="mt-3 text-sm text-slate-500">
                                        You can see what this order needs, but choosing the animals needs
                                        permission to manage orders.
                                    </p>
                                @endcan
                            @endif
                        @endif
                    </div>
                @endforeach

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
</x-app-layout>
