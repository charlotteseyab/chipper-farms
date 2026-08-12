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

        <form method="POST" action="{{ route('storefront.admin.push.store') }}" class="space-y-6">
            @csrf
            @if($source)
                <input type="hidden" name="source_type" value="{{ $sourceType }}">
                <input type="hidden" name="source_id" value="{{ $sourceId }}">
            @endif

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">1. Source &amp; mode</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <label class="block text-sm">
                        <span class="text-slate-600">Kind</span>
                        <select name="kind" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            @foreach($kinds as $k)
                                <option value="{{ $k }}" @selected(old('kind', $prefill['kind'] ?? '') === $k)>{{ ucwords(str_replace('_',' ',$k)) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Category</span>
                        <select name="category_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            <option value="">—</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((int)old('category_id') === $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Sale mode</span>
                        <select name="sale_mode" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            @foreach($modes as $m)
                                <option value="{{ $m }}" @selected(old('sale_mode', $prefill['sale_mode'] ?? 'fixed') === $m)>{{ ucfirst($m) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Pricing basis</span>
                        <select name="pricing_basis" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            @foreach($bases as $b)
                                <option value="{{ $b }}" @selected(old('pricing_basis', $prefill['pricing_basis'] ?? 'fixed') === $b)>{{ ucfirst($b) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
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

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">3. Pricing &amp; quantity</h2>
                <div class="grid md:grid-cols-3 gap-4">
                    <label class="block text-sm">
                        <span class="text-slate-600">Price / kg (live·carcass)</span>
                        <input type="number" step="0.0001" name="price_per_kg" value="{{ old('price_per_kg') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" placeholder="defaults from settings">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Fixed price</span>
                        <input type="number" step="0.01" name="fixed_price" value="{{ old('fixed_price') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Currency</span>
                        <input type="text" name="currency_code" value="{{ old('currency_code', 'GHS') }}" maxlength="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    {{--
                        Building a POOL of Standard Stock.

                        A pig listing used to mean one animal, reached from that
                        animal's page with ?pig=. A farm more often wants to
                        advertise "market-ready live pigs — 5 head" and decide
                        which five when an order comes in. Both shapes are
                        supported; this block is the second one, and appears only
                        when the wizard is not already pinned to a single animal.

                        The quantity below is derived from what is ticked here.
                        Availability follows the animals, because a pig sold at
                        the farm gate has changed what the shop can offer and the
                        number must not disagree with the herd.
                    --}}
                    @if($poolCandidates->isNotEmpty())
                        <div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-4"
                             data-pig-pool>
                            <p class="text-sm font-semibold text-slate-800">
                                Standard Stock for this listing
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Tick the animals this listing may draw from. Only Standard Stock that is
                                unsold, alive, out of any medication withdrawal and not already on the shop
                                is shown. Leave it empty for a listing that is not backed by specific pigs.
                            </p>

                            <input type="search"
                                   data-pig-pool-filter
                                   placeholder="Filter by ear tag, breed or sex…"
                                   class="mt-3 w-full rounded-xl border-slate-300 text-sm">

                            <div class="mt-2 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white divide-y divide-slate-100">
                                @foreach($poolCandidates as $candidate)
                                    <label class="flex items-start gap-3 p-3 text-sm cursor-pointer hover:bg-slate-50"
                                           data-pig-row
                                           data-pig-search="{{ strtolower(trim(($candidate->ear_tag_number ?? '') . ' ' . ($candidate->breed ?? '') . ' ' . ($candidate->gender ?? ''))) }}">
                                        <input type="checkbox"
                                               name="pool_pig_ids[]"
                                               value="{{ $candidate->id }}"
                                               data-pig-checkbox
                                               @checked(in_array((string) $candidate->id, (array) old('pool_pig_ids', []), true))
                                               class="mt-1 rounded border-slate-300">
                                        <span class="min-w-0">
                                            <span class="font-medium text-slate-800">
                                                {{ $candidate->ear_tag_number ?: ('Pig #' . $candidate->id) }}
                                            </span>
                                            <span class="block text-xs text-slate-500">
                                                {{ $candidate->breed ?: 'Breed not recorded' }}
                                                · {{ $candidate->gender ?: 'sex not recorded' }}
                                                @if($candidate->date_of_birth)
                                                    · {{ \Carbon\Carbon::parse($candidate->date_of_birth)->age }} yr
                                                @endif
                                                @if($candidate->current_weight)
                                                    · {{ rtrim(rtrim((string) $candidate->current_weight, '0'), '.') }} kg
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <p class="mt-2 text-xs text-slate-600">
                                <span data-pig-pool-count>0</span> selected — this becomes the listing quantity.
                            </p>
                        </div>
                    @endif

                    <label class="block text-sm">
                        <span class="text-slate-600">Quantity available</span>
                        <input type="number" step="0.0001" name="quantity_available" value="{{ old('quantity_available', $prefill['quantity_available'] ?? 1) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Unit</span>
                        <input type="text" name="quantity_unit" value="{{ old('quantity_unit', $prefill['quantity_unit'] ?? 'unit') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Est. weight (kg)</span>
                        <input type="number" step="0.0001" name="estimated_weight_kg" value="{{ old('estimated_weight_kg', $prefill['estimated_weight_kg'] ?? '') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                </div>
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">4. Fulfilment</h2>
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
<script>
(function () {
    var pool = document.querySelector('[data-pig-pool]');
    if (!pool) { return; }

    var boxes    = pool.querySelectorAll('[data-pig-checkbox]');
    var counter  = pool.querySelector('[data-pig-pool-count]');
    var filter   = pool.querySelector('[data-pig-pool-filter]');
    var quantity = document.querySelector('input[name="quantity_available"]');

    function refresh() {
        var selected = 0;
        boxes.forEach(function (box) { if (box.checked) { selected++; } });
        if (counter) { counter.textContent = String(selected); }
        // The server recomputes this from the attached animals; setting it
        // here just stops the form showing a number that contradicts the
        // ticks in front of the user.
        if (quantity && selected > 0) { quantity.value = selected; }
    }

    boxes.forEach(function (box) { box.addEventListener('change', refresh); });

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

</x-app-layout>
