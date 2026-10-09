@php
    $line        = $panel['item'];
    $outstanding = $panel['outstanding'];
    $state       = $panel['state'];
    $ready       = $state === \App\Storefront\Services\StorefrontPigAllocationService::STATE_READY;
    $conflict    = $state === \App\Storefront\Services\StorefrontPigAllocationService::STATE_CONFLICT;
    $soldAlready = $panel['sold']->isNotEmpty();
    $byWeight    = $panel['requested_kg'] !== null;
    $expectedLiveSelections = $panel['expected_live_selections'] ?? [];
@endphp

<div @class([
    'rounded-2xl border p-5',
    'border-rose-300 bg-rose-50'       => $conflict,
    'border-emerald-200 bg-emerald-50' => $ready && ! $conflict,
    'border-amber-200 bg-amber-50'     => ! $ready && ! $conflict,
])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ $soldAlready ? 'Sold animals' : 'Animal fulfilment' }}
            </p>
            <h2 class="mt-1 text-lg font-semibold text-slate-900">
                {{ $soldAlready ? 'Pigs sold for' : 'Choose the pigs for' }} “{{ $line->description }}”
            </h2>
        </div>
        <div class="rounded-xl bg-white px-3 py-2 text-right text-sm shadow-sm">
            <p class="font-semibold text-slate-900">GHS {{ number_format((float) $line->line_total, 2) }}</p>
            @if($line->price_per_kg_snapshot)
                <p class="text-xs text-slate-500">{{ number_format((float) $line->price_per_kg_snapshot, 2) }} / kg</p>
            @endif
        </div>
    </div>

    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-3">
            <dt class="text-xs text-slate-500">Ordered</dt>
            <dd class="mt-1 font-semibold text-slate-900">
                {{ rtrim(rtrim((string) $line->quantity, '0'), '.') }} {{ $line->displayQuantityUnit() }}
            </dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3">
            <dt class="text-xs text-slate-500">{{ $byWeight ? 'Requested weight' : 'Paid live weight' }}</dt>
            <dd class="mt-1 font-semibold text-slate-900">
                @if($byWeight)
                    {{ rtrim(rtrim($panel['requested_kg'], '0'), '.') }} kg
                @elseif($line->line_weight_kg)
                    {{ rtrim(rtrim((string) $line->line_weight_kg, '0'), '.') }} kg
                @else
                    Not recorded
                @endif
            </dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3">
            <dt class="text-xs text-slate-500">Selected so far</dt>
            <dd class="mt-1 font-semibold text-slate-900">
                @if($byWeight)
                    {{ rtrim(rtrim($panel['selected_kg'], '0'), '.') }} kg
                @else
                    {{ count($panel['chosen']) }} / {{ $panel['required'] }} {{ (int) $panel['required'] === 1 ? 'pig' : 'pigs' }}
                @endif
            </dd>
        </div>
    </dl>

    @if($expectedLiveSelections !== [])
        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3 text-sm">
            <p class="font-semibold text-slate-800">Customer paid for</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($expectedLiveSelections as $expected)
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                        {{ $expected['ear_tag'] ?: ('Pig #' . ($expected['pig_id'] ?? '?')) }}
                        · {{ rtrim(rtrim($expected['kg'], '0'), '.') }} kg
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    @if($conflict)
        <div class="mt-3 rounded-xl border border-rose-300 bg-white p-3 text-sm text-rose-800">
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
        <p class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700">
            The customer chose/purchased this live weight. Only pigs matching that paid
            weight are selectable; heavier or unmatched pigs are locked out.
        </p>
    @endif

    @if($soldAlready)
        <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            @foreach($panel['sold'] as $allocation)
                <li class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="font-semibold text-slate-800">
                        {{ $allocation->pig?->ear_tag_number ?: ($allocation->pig?->code ?? ('Pig #' . $allocation->pig_id)) }}
                    </p>
                    <p class="text-xs text-slate-500">
                        @if($allocation->allocated_live_weight_kg)
                            {{ rtrim(rtrim((string) $allocation->allocated_live_weight_kg, '0'), '.') }} kg live
                        @elseif($allocation->allocated_carcass_weight_kg)
                            {{ rtrim(rtrim((string) $allocation->allocated_carcass_weight_kg, '0'), '.') }} kg carcass
                        @endif
                        @if($allocation->pig_sale_id)
                            · sale #{{ $allocation->pig_sale_id }}
                        @endif
                    </p>
                    @if($allocation->pigSale)
                        <p class="mt-2 text-xs text-slate-500">
                            Revenue:
                            <span class="font-semibold {{ $allocation->pigSale->is_posted ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $allocation->pigSale->is_posted ? 'posted' : 'pending' }}
                            </span>
                            · COGS:
                            <span class="font-semibold {{ $allocation->pigSale->cogs_posting_status === 'posted' ? 'text-emerald-700' : ($allocation->pigSale->cogs_posting_status === 'failed' ? 'text-rose-700' : 'text-amber-700') }}">
                                {{ $allocation->pigSale->cogs_posting_status ?: 'pending' }}
                            </span>
                        </p>
                        @if($allocation->pigSale->cogs_posting_error)
                            <p class="mt-1 text-xs text-rose-700">{{ $allocation->pigSale->cogs_posting_error }}</p>
                        @endif
                    @else
                        <p class="mt-2 text-xs font-semibold text-amber-700">Sale row pending</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @elseif($panel['candidates']->isEmpty())
        <p class="mt-3 text-sm text-rose-700">
            No animals are available in this listing’s pool. Add pigs to the listing,
            or cancel this order.
        </p>
    @else
        @can('manage_storefront_orders')
            <form method="POST"
                  action="{{ route('storefront.admin.orders.select-pigs', [$order, $line]) }}"
                  class="mt-4"
                  data-order-pig-selection
                  @if($panel['selection_limit']) data-selection-limit="{{ $panel['selection_limit'] }}" @endif>
                @csrf

                <div class="max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-white divide-y divide-slate-100">
                    @foreach($panel['candidates'] as $poolRow)
                        @php
                            $pig = $poolRow->pig;
                            $meta = $panel['candidate_meta'][$poolRow->getKey()] ?? [];
                            $weight = $meta['weight'] ?? null;
                            $isChosen = in_array((int) $pig->id, $panel['chosen'], true);
                            $isSelectable = (bool) ($meta['selectable'] ?? true);
                        @endphp
                        <label @class([
                            'flex items-start gap-3 p-3 text-sm',
                            'cursor-pointer hover:bg-slate-50' => $isSelectable,
                            'cursor-not-allowed bg-slate-50 text-slate-400' => ! $isSelectable,
                        ])>
                            <input type="checkbox"
                                   name="pig_ids[]"
                                   value="{{ $pig->id }}"
                                   data-order-pig-checkbox
                                   data-pig-weight="{{ $weight ?? '' }}"
                                   @checked($isChosen)
                                   @disabled(! $isSelectable)
                                   class="mt-1 rounded border-slate-300">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-baseline justify-between gap-2">
                                    <span class="font-medium text-slate-800">
                                        {{ $pig->ear_tag_number ?: ('Pig #' . $pig->id) }}
                                    </span>
                                    <span class="text-xs font-semibold text-slate-700">
                                        @if($weight !== null)
                                            {{ rtrim(rtrim($weight, '0'), '.') }} kg
                                        @else
                                            not weighed
                                        @endif
                                    </span>
                                </span>
                                <span class="block text-xs text-slate-500">
                                    {{ \App\Models\Setting::breedName($pig->breed) ?: 'Breed not recorded' }}
                                    · {{ $pig->gender ?: 'sex not recorded' }}
                                    @if($pig->date_of_birth)
                                        · {{ \Carbon\Carbon::parse($pig->date_of_birth)->age }} yr
                                    @endif
                                </span>
                                @if(! $isSelectable && ($meta['reason'] ?? null))
                                    <span class="mt-1 block text-xs text-amber-700">{{ $meta['reason'] }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500" data-selection-message>
                        Nothing is sold until you confirm the order.
                    </p>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm">
                        Save selection
                    </button>
                </div>
            </form>
        @else
            <p class="mt-3 text-sm text-slate-500">
                You can see what this order needs, but choosing the animals needs
                permission to manage orders.
            </p>
        @endcan
    @endif
</div>
