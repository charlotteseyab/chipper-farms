<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-mono text-slate-400">{{ $listing->reference }}</p>
                <h1 class="text-2xl font-bold text-gray-800">Edit listing</h1>
            </div>
            <a href="{{ route('storefront.admin.listings.show', $listing) }}" class="text-sm text-slate-500 hover:text-slate-800">← Back</a>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        @php
            $isEditablePool = in_array($sourceCategory, [
                \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_BREEDING,
                \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_STANDARD,
            ], true);
            $isPiglet = $sourceCategory === \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_PIGLETS;
            $isManual = $sourceCategory === \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_MANUAL;
        @endphp

        <form method="POST" action="{{ route('storefront.admin.listings.update', $listing) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <input type="hidden" name="sale_mode" value="{{ old('sale_mode', $listing->sale_mode) }}">
            <input type="hidden" name="pricing_basis" value="{{ old('pricing_basis', $listing->pricing_basis) }}">
            <input type="hidden" name="currency_code" value="{{ old('currency_code', $listing->currency_code) }}">
            <input type="hidden" name="source_category" value="{{ $sourceCategory }}">

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">1. What are you selling?</h2>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stock type</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">
                        {{ $categoryOptions[$sourceCategory]['label'] ?? ucwords(str_replace('_', ' ', $sourceCategory)) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $categoryOptions[$sourceCategory]['hint'] ?? 'The listing source is locked after creation.' }}
                    </p>
                    <p class="mt-3 text-xs text-amber-700">
                        Stock type is locked from the original create flow. To move this listing to another stock type, archive it and create a new listing.
                    </p>
                </div>

                <label class="block text-sm">
                    <span class="text-slate-600">Shop category (optional)</span>
                    <select name="category_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        <option value="">—</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((int)old('category_id', $listing->category_id) === $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">2. Listing</h2>
                <label class="block text-sm">
                    <span class="text-slate-600">Title</span>
                    <input type="text" name="title" value="{{ old('title', $listing->title) }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Public description</span>
                    <textarea name="public_description" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ old('public_description', $listing->public_description) }}</textarea>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Seller note</span>
                    <input type="text" name="seller_note" value="{{ old('seller_note', $listing->seller_note) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
            </div>

            @can('manage_storefront_media')
                @include('storefront.admin.listings.partials.media-manager', ['mode' => 'edit', 'listing' => $listing])
            @endcan

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">4. Stock &amp; price</h2>

                @if($isEditablePool)
                    <input type="hidden" name="price_per_kg" value="{{ old('price_per_kg', $listing->price_per_kg) }}">
                    <input type="hidden" name="fixed_price" value="{{ old('fixed_price', $listing->fixed_price) }}">
                    <input type="hidden" name="quantity_available" value="{{ old('quantity_available', $listing->quantity_available) }}">
                    <input type="hidden" name="quantity_unit" value="{{ old('quantity_unit', $listing->quantity_unit) }}">
                    <input type="hidden" name="estimated_weight_kg" value="{{ old('estimated_weight_kg', $listing->estimated_weight_kg) }}">
                    <input type="hidden" name="pool_pig_ids_present" value="1">

                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <p class="font-semibold">Priced from your farm setup</p>
                        <dl class="mt-2 grid sm:grid-cols-2 gap-2">
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
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" data-pig-pool>
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">
                                {{ $categoryOptions[$sourceCategory]['label'] ?? 'Stock' }} for this listing
                            </p>
                            <p class="text-xs text-slate-500">{{ $poolSummary['count'] }} available</p>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Tick the animals this listing may draw from. Animals already sold,
                            committed to an order, or selected by another active listing are not editable here.
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
                                               @checked(in_array((string) $candidate->id, $selectedPoolPigIds, true))
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
                @else
                    <input type="hidden" name="estimated_weight_kg" value="{{ old('estimated_weight_kg', $listing->estimated_weight_kg) }}">

                    <div class="grid md:grid-cols-2 gap-4">
                        @if($listing->pricing_basis === \App\Models\Storefront\StorefrontListing::BASIS_FIXED)
                            <label class="block text-sm">
                                <span class="text-slate-600">{{ $isPiglet ? 'Price per piglet' : 'Price' }}</span>
                                <input type="number" step="0.01" name="fixed_price" value="{{ old('fixed_price', $listing->fixed_price) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            </label>
                            <input type="hidden" name="price_per_kg" value="{{ old('price_per_kg', $listing->price_per_kg) }}">
                        @else
                            <label class="block text-sm">
                                <span class="text-slate-600">Price / kg</span>
                                <input type="number" step="0.0001" name="price_per_kg" value="{{ old('price_per_kg', $listing->price_per_kg) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            </label>
                            <input type="hidden" name="fixed_price" value="{{ old('fixed_price', $listing->fixed_price) }}">
                        @endif

                        <label class="block text-sm">
                            <span class="text-slate-600">Quantity available</span>
                            <input type="number" step="0.0001" name="quantity_available" value="{{ old('quantity_available', $listing->quantity_available) }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">Unit</span>
                            <input type="text" name="quantity_unit" value="{{ old('quantity_unit', $listing->displayQuantityUnit((string) $listing->quantity_available)) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">SKU</span>
                            <input type="text" name="sku" value="{{ old('sku', $listing->sku) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">Pack size</span>
                            <input type="text" name="pack_size" value="{{ old('pack_size', $listing->pack_size) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">5. Fulfilment</h2>
                <div class="flex flex-wrap gap-6 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="pickup_allowed" value="1" @checked(old('pickup_allowed', $listing->pickup_allowed)) class="rounded border-slate-300"> Pickup allowed</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="delivery_allowed" value="1" @checked(old('delivery_allowed', $listing->delivery_allowed)) class="rounded border-slate-300"> Delivery allowed</label>
                </div>
                <label class="block text-sm">
                    <span class="text-slate-600">Delivery fee</span>
                    <input type="number" step="0.01" name="delivery_fee" value="{{ old('delivery_fee', $listing->delivery_fee) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Fulfilment notes</span>
                    <input type="text" name="fulfilment_notes" value="{{ old('fulfilment_notes', $listing->fulfilment_notes) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('storefront.admin.listings.show', $listing) }}" class="px-4 py-2 border border-slate-300 rounded-xl text-sm">Cancel</a>
                <button type="submit" name="_method" value="PUT" class="px-5 py-2 bg-slate-800 text-white rounded-xl text-sm">Save changes</button>
            </div>
        </form>
    </div>

@if($isEditablePool)
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
