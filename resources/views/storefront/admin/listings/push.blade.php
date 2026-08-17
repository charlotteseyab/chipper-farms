<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Push to storefront</h1>
            <p class="text-sm text-slate-500">
                @if($source)
                    Creating a listing from <span class="font-mono">{{ class_basename($sourceType) }}#{{ $sourceId }}</span>. The operational record is not modified.
                @else
                    Create a new product/service listing.
                @endif
            </p>
        </div>

        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{--
            Phase 11 (§35 step 2). Shown when the wizard OPENS, not after the
            form is submitted: a staff member should learn why an animal cannot
            be sold before filling anything in, and should learn every reason at
            once rather than one per attempt.

            Reasons rejected at submit time land in `eligibility_blockers` and
            render in the same block, so the two paths look identical.
        --}}
        @php $blockers = session('eligibility_blockers', $eligibility['blockers'] ?? []); @endphp

        @if(! empty($blockers))
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">This pig cannot be listed for sale yet.</p>
                <ul class="mt-2 list-disc list-inside space-y-1">
                    @foreach($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if(! empty($eligibility['cautions'] ?? []))
            {{-- Cautions do not block. A treatment with no withdrawal period
                 recorded may genuinely have none, or may just be an unfilled
                 field — either way the gap is shown rather than guessed at. --}}
            <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Worth checking before you publish</p>
                <ul class="mt-2 list-disc list-inside space-y-1">
                    @foreach($eligibility['cautions'] as $caution)<li>{{ $caution }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('storefront.admin.push.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @if($source)
                <input type="hidden" name="source_type" value="{{ $sourceType }}">
                <input type="hidden" name="source_id" value="{{ $sourceId }}">
            @endif

            {{--
                WHERE THE LISTING COMES FROM.

                Staff pick a source CATEGORY and everything else follows from
                it: which animals are offered, what kind of listing is created,
                and which of the farm's configured rates prices it. Nobody
                publishing "market-ready live pigs" should have to know that it
                means kind `live_pig`, sale mode `fixed` and basis `live` — so
                those questions are gone from this page and derived on the
                server instead.
            --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">1. What are you selling?</h2>

                <input type="hidden" name="source_category" value="{{ $sourceCategory }}">

                <div class="grid sm:grid-cols-2 gap-3" data-source-categories>
                    @foreach($categoryOptions as $value => $option)
                        <a href="{{ route('storefront.admin.push.create', array_filter(['source_category' => $value, 'pig' => $sourceId])) }}"
                           data-source-category="{{ $value }}"
                           @class([
                               'block rounded-2xl border p-4 text-sm transition',
                               'border-slate-800 bg-slate-800 text-white shadow' => $sourceCategory === $value,
                               'border-slate-200 bg-white hover:border-slate-400' => $sourceCategory !== $value,
                           ])>
                            <span class="font-semibold">{{ $option['label'] }}</span>
                            <span @class([
                                'block text-xs mt-1',
                                'text-slate-300' => $sourceCategory === $value,
                                'text-slate-500' => $sourceCategory !== $value,
                            ])>{{ $option['hint'] }}</span>
                        </a>
                    @endforeach
                </div>

                @if($sourceCategory === \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_MANUAL)
                    {{--
                        Manual listings are the only ones that still need a kind
                        chosen by hand — a bag of feed is not derivable from the
                        herd. Stock-backed categories derive theirs.
                    --}}
                    <label class="block text-sm">
                        <span class="text-slate-600">Kind</span>
                        <select name="kind" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            @foreach($kinds as $k)
                                @continue(in_array($k, [
                                    \App\Models\Storefront\StorefrontListing::KIND_LIVE_PIG,
                                    \App\Models\Storefront\StorefrontListing::KIND_BREEDING_STOCK,
                                    \App\Models\Storefront\StorefrontListing::KIND_PIGLET_LOT,
                                ], true))
                                <option value="{{ $k }}" @selected(old('kind', $prefill['kind'] ?? 'feed') === $k)>{{ ucwords(str_replace('_',' ',$k)) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="block text-sm">
                    <span class="text-slate-600">Shop category (optional)</span>
                    <select name="category_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        <option value="">—</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((int)old('category_id') === $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">2. Listing</h2>
                <label class="block text-sm">
                    <span class="text-slate-600">Title</span>
                    <input type="text" name="title" value="{{ old('title', $prefill['title'] ?? '') }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Public description</span>
                    <textarea name="public_description" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ old('public_description') }}</textarea>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Seller note (internal-facing on detail page)</span>
                    <input type="text" name="seller_note" value="{{ old('seller_note') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
            </div>

            @can('manage_storefront_media')
                @include('storefront.admin.listings.partials.media-manager', ['mode' => 'create'])
            @endcan

            @php
                $isManual = $sourceCategory === \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_MANUAL;
                $isPiglet = $sourceCategory === \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_PIGLETS;
            @endphp

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">4. Stock &amp; price</h2>

                @unless($isManual)
                    {{--
                        THE RATES ARE THE FARM'S OWN, AND ARE NOT TYPED HERE.

                        These come from /settings/setup — the same numbers that
                        price a sale at the farm gate. Letting staff type a rate
                        on this page is how a shop ends up quoting one price
                        online and another in the yard.
                    --}}
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                        <p class="font-semibold text-emerald-900">Priced from your farm setup</p>
                        <dl class="mt-2 grid sm:grid-cols-2 gap-2 text-emerald-900">
                            <div class="flex justify-between gap-3">
                                <dt>Live weight</dt>
                                <dd class="font-semibold" data-live-rate="{{ $livePricePerKg }}">
                                    {{ number_format((float) $livePricePerKg, 2) }} / kg
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>Carcass weight</dt>
                                <dd class="font-semibold">{{ number_format((float) $carcassPricePerKg, 2) }} / kg</dd>
                            </div>
                        </dl>
                        @if(bccomp($livePricePerKg, '0', 4) === 0)
                            <p class="mt-2 text-xs text-rose-700">
                                No live weight price is configured. Set one at Settings &rarr; Setup
                                before publishing, or this listing will price at zero.
                            </p>
                        @endif
                    </div>
                @endunless

                @if($isPiglet)
                    <p class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        Piglets are sold by the litter. Individual animal records are
                        created when a manager approves the order, so there is nothing
                        to tick here — set the quantity of piglets on offer below.
                    </p>
                @endif

                @if(! $isManual && ! $isPiglet)
                    {{--
                        Building a POOL.

                        A pig listing used to mean one animal, reached from that
                        animal's page with ?pig=. A farm more often wants to
                        advertise "market-ready live pigs" and decide
                        which five when an order comes in. Both shapes are
                        supported; this block is the second one, and appears only
                        when the wizard is not already pinned to a single animal.

                        The quantity is derived from what is ticked. Availability
                        follows the animals, because a pig sold at the farm gate
                        has changed what the shop can offer and the number must
                        not disagree with the herd.
                    --}}
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" data-pig-pool>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">
                                {{ $categoryOptions[$sourceCategory]['label'] ?? 'Stock' }} for this listing
                            </p>
                            <p class="text-xs text-slate-500">{{ $poolSummary['count'] }} available</p>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Tick the animals this listing may draw from. Only stock that is
                            unsold, alive, out of any medication withdrawal and not already on the
                            shop is shown.
                        </p>

                        <input type="search"
                               data-pig-pool-filter
                               value="{{ $stockSearch }}"
                               placeholder="Filter by tag, ID, breed, sex or weight..."
                               class="mt-3 w-full rounded-xl border-slate-300 text-sm">

                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <button type="button" data-pig-select-visible
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-1 font-medium text-slate-700 hover:border-slate-500">
                                Select all visible
                            </button>
                            <button type="button" data-pig-clear
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-1 font-medium text-slate-700 hover:border-slate-500">
                                Clear selected
                            </button>
                        </div>

                        @if($poolCandidates->isEmpty())
                            <p class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">
                                No {{ strtolower($categoryOptions[$sourceCategory]['label'] ?? 'stock') }} is
                                available to list right now.
                            </p>
                        @else
                            <div class="mt-2 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white divide-y divide-slate-100">
                                @foreach($poolCandidates as $candidate)
                                    @php
                                        $row = $poolRows[$candidate->getKey()] ?? ['weight' => null, 'value' => null];
                                        $ageText = $candidate->date_of_birth
                                            ? \Carbon\Carbon::parse($candidate->date_of_birth)->age . ' yr'
                                            : '';
                                    @endphp
                                    <label class="flex items-start gap-3 p-3 text-sm cursor-pointer hover:bg-slate-50"
                                           data-pig-row
                                           data-pig-search="{{ strtolower(trim(($candidate->ear_tag_number ?? '') . ' ' . ($candidate->code ?? '') . ' ' . ($candidate->local_id ?? '') . ' ' . ($candidate->breed ?? '') . ' ' . ($candidate->gender ?? '') . ' ' . ($row['weight'] ?? '') . ' ' . $ageText)) }}">
                                        <input type="checkbox"
                                               name="pool_pig_ids[]"
                                               value="{{ $candidate->id }}"
                                               data-pig-checkbox
                                               data-pig-weight="{{ $row['weight'] ?? '' }}"
                                               @checked(in_array((string) $candidate->id, (array) old('pool_pig_ids', []), true))
                                               class="mt-1 rounded border-slate-300">
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-baseline justify-between gap-2">
                                                <span class="font-medium text-slate-800">
                                                    {{ $candidate->ear_tag_number ?: ('Pig #' . $candidate->id) }}
                                                </span>
                                                <span class="text-xs font-semibold text-slate-700">
                                                    @if($row['weight'] !== null)
                                                        {{ rtrim(rtrim($row['weight'], '0'), '.') }} kg
                                                        @if($row['value'] !== null)
                                                            · {{ number_format((float) $row['value'], 2) }}
                                                        @endif
                                                    @else
                                                        <span class="text-amber-700">not weighed</span>
                                                    @endif
                                                </span>
                                            </span>
                                            <span class="block text-xs text-slate-500">
                                                {{ $candidate->breed ?: 'Breed not recorded' }}
                                                · {{ $candidate->gender ?: 'sex not recorded' }}
                                                @if($candidate->date_of_birth)
                                                    · {{ $ageText }}
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        {{--
                            The selection's figures. JS keeps these live as boxes
                            are ticked; the SERVER recomputes every one of them
                            from the animals actually attached, so a browser with
                            this script off still produces a correct listing.
                        --}}
                        <dl class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div class="rounded-xl bg-white border border-slate-200 p-2">
                                <dt class="text-slate-500">Selected</dt>
                                <dd class="text-sm font-semibold text-slate-800"><span data-pig-pool-count>0</span> pigs</dd>
                            </div>
                            <div class="rounded-xl bg-white border border-slate-200 p-2">
                                <dt class="text-slate-500">Total weight</dt>
                                <dd class="text-sm font-semibold text-slate-800"><span data-pool-total-weight>0</span> kg</dd>
                            </div>
                            <div class="rounded-xl bg-white border border-slate-200 p-2">
                                <dt class="text-slate-500">Min / max / avg</dt>
                                <dd class="text-sm font-semibold text-slate-800">
                                    <span data-pool-min>—</span> / <span data-pool-max>—</span> / <span data-pool-avg>—</span>
                                </dd>
                            </div>
                            <div class="rounded-xl bg-white border border-slate-200 p-2">
                                <dt class="text-slate-500">Estimated amount</dt>
                                <dd class="text-sm font-semibold text-slate-800"><span data-pool-estimate>0.00</span></dd>
                            </div>
                        </dl>

                        @if($poolSummary['unweighed'] !== [])
                            <p class="mt-2 text-xs text-amber-700">
                                Not weighed, so they cannot be priced:
                                {{ implode(', ', array_slice($poolSummary['unweighed'], 0, 8)) }}@if(count($poolSummary['unweighed']) > 8) and {{ count($poolSummary['unweighed']) - 8 }} more @endif.
                            </p>
                        @endif
                    </div>
                @endif

                <div class="grid md:grid-cols-3 gap-4">
                    @if($isManual || $isPiglet)
                        {{--
                            No animal to weigh, so the price is typed here and
                            nowhere else. Currency follows the farm's
                            configuration rather than being asked for.

                            Piglets are here for the same reason as a bag of
                            feed: there is nothing on the scale. A farm prices a
                            weaner by the pig, not by the kilo.
                        --}}
                        <label class="block text-sm">
                            <span class="text-slate-600">{{ $isPiglet ? 'Price per piglet' : 'Price' }}</span>
                            <input type="number" step="0.01" name="fixed_price" value="{{ old('fixed_price') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                    @endif

                    @if($isManual || $isPiglet)
                        <label class="block text-sm">
                            <span class="text-slate-600">Quantity available</span>
                            <input type="number" step="0.0001" name="quantity_available" value="{{ old('quantity_available', $prefill['quantity_available'] ?? 1) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">Unit</span>
                            <input type="text" name="quantity_unit" value="{{ old('quantity_unit', $prefill['quantity_unit'] ?? ($isPiglet ? 'piglet' : 'unit')) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                    @else
                        {{--
                            Quantity is the number of animals ticked. It is not
                            asked for, and the server derives it regardless of
                            what arrives in this field.
                        --}}
                        <input type="hidden" name="quantity_unit" value="head">
                    @endif
                </div>
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">5. Fulfilment</h2>
                <div class="flex flex-wrap gap-6 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="pickup_allowed" value="1" checked class="rounded border-slate-300"> Pickup allowed</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="delivery_allowed" value="1" class="rounded border-slate-300"> Delivery allowed</label>
                </div>
                <label class="block text-sm">
                    <span class="text-slate-600">Delivery fee (only if delivery allowed)</span>
                    <input type="number" step="0.01" name="delivery_fee" value="{{ old('delivery_fee') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Fulfilment notes</span>
                    <input type="text" name="fulfilment_notes" value="{{ old('fulfilment_notes') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-5">
                <label class="inline-flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="publish_now" value="1" class="rounded border-slate-300"> Publish immediately
                </label>
                <p class="text-xs text-slate-400 mt-1">Leave unchecked to save as a draft and review before publishing.</p>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('storefront.admin.dashboard') }}" class="px-4 py-2 border border-slate-300 rounded-xl text-sm">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-slate-800 text-white rounded-xl text-sm">Create listing</button>
            </div>
        </form>
    </div>

{{--
    Counting, filtering and keeping the quantity honest.

    Plain JS rather than a component because this page has no build-time
    JS of its own, and the form works without any of it: the server derives
    the real quantity from the pigs actually attached, so a browser with
    this script disabled still produces a correct listing.
--}}
@if(! $isManual && ! $isPiglet)
<script>
(function () {
    var pool = document.querySelector('[data-pig-pool]');
    if (!pool) { return; }

    var boxes   = Array.prototype.slice.call(pool.querySelectorAll('[data-pig-checkbox]'));
    var filter  = pool.querySelector('[data-pig-pool-filter]');
    var rateEl  = document.querySelector('[data-live-rate]');
    var rate    = rateEl ? parseFloat(rateEl.getAttribute('data-live-rate')) || 0 : 0;

    function put(selector, text) {
        var el = pool.querySelector(selector);
        if (el) { el.textContent = text; }
    }

    function trim(n) {
        return (Math.round(n * 10000) / 10000).toString();
    }

    function visible(box) {
        var row = box.closest('[data-pig-row]');
        return row && row.style.display !== 'none';
    }

    function refresh() {
        var count = 0, total = 0, min = null, max = null, weighed = 0;

        boxes.forEach(function (box) {
            if (!box.checked) { return; }
            count++;

            var raw = box.getAttribute('data-pig-weight');
            if (!raw) { return; }

            var kg = parseFloat(raw);
            if (isNaN(kg)) { return; }

            weighed++;
            total += kg;
            if (min === null || kg < min) { min = kg; }
            if (max === null || kg > max) { max = kg; }
        });

        put('[data-pig-pool-count]', String(count));
        put('[data-pool-total-weight]', trim(total));
        put('[data-pool-min]', min === null ? '—' : trim(min));
        put('[data-pool-max]', max === null ? '—' : trim(max));
        put('[data-pool-avg]', weighed === 0 ? '—' : trim(total / weighed));
        put('[data-pool-estimate]', (total * rate).toFixed(2));
    }

    boxes.forEach(function (box) { box.addEventListener('change', refresh); });

    var selectVisible = pool.querySelector('[data-pig-select-visible]');
    if (selectVisible) {
        // "All visible" means what the filter has left on screen, which is the
        // only reading that is not a trap: ticking hidden animals a user cannot
        // see would put stock on the shop they never chose.
        selectVisible.addEventListener('click', function () {
            boxes.forEach(function (box) { if (visible(box)) { box.checked = true; } });
            refresh();
        });
    }

    var clear = pool.querySelector('[data-pig-clear]');
    if (clear) {
        clear.addEventListener('click', function () {
            boxes.forEach(function (box) { box.checked = false; });
            refresh();
        });
    }

    if (filter) {
        filter.addEventListener('input', function () {
            var needle = filter.value.toLowerCase().trim();
            pool.querySelectorAll('[data-pig-row]').forEach(function (row) {
                var hay = row.getAttribute('data-pig-search') || '';
                row.style.display = (needle === '' || hay.indexOf(needle) !== -1) ? '' : 'none';
            });
        });
    }

    refresh();
})();
</script>
@endif

</x-app-layout>
