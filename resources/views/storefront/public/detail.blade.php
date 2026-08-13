@php use App\Storefront\Support\StorefrontSettings; @endphp
<x-storefront.layout :title="$listing->title">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <a href="{{ url()->previous() }}" class="text-sm text-slate-500">← Back</a>

        <div class="grid md:grid-cols-2 gap-8 mt-4">
            {{-- Gallery --}}
            <div>
                @php $primary = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first(); @endphp
                <div class="aspect-[4/3] bg-slate-100 rounded-2xl overflow-hidden flex items-center justify-center">
                    @if($primary && $primary->url())
                        <img src="{{ $primary->url() }}" alt="{{ $primary->alt_text ?? $listing->title }}" class="w-full h-full object-cover">
                    @else
                        <x-ui-icon name="pig" class="h-16 w-16 text-slate-400" />
                    @endif
                </div>
                @if($listing->media->count() > 1)
                    <div class="mt-3 grid grid-cols-5 gap-2">
                        @foreach($listing->media as $m)
                            @if($m->url())
                                <div class="aspect-square rounded-lg overflow-hidden bg-slate-100">
                                    <img src="{{ $m->url() }}" alt="{{ $m->alt_text }}" class="w-full h-full object-cover">
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Summary + CTA --}}
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-400">{{ ucwords(str_replace('_', ' ', $listing->kind)) }}</p>
                <h1 class="text-2xl md:text-3xl font-bold text-slate-800 mt-1">{{ $listing->title }}</h1>
                <p class="text-sm text-slate-400 font-mono mt-1">{{ $listing->reference }}</p>

                <div class="mt-4">
                    @if($detail['quote_only'])
                        <p class="text-lg font-semibold text-amber-600">Price on request</p>
                    @elseif($detail['price'] !== null)
                        <p class="text-2xl font-bold text-slate-900">
                            {{ $detail['currency'] }} {{ number_format((float) $detail['price'], 2) }}
                            @if($detail['price_basis'] !== 'fixed')<span class="text-base text-slate-400 font-normal">/ kg ({{ $detail['price_basis'] }})</span>@endif
                        </p>
                    @endif
                    <p class="text-sm text-slate-500 mt-1">
                        {{ rtrim(rtrim((string) $detail['quantity'], '0'), '.') }} {{ $detail['quantity_unit'] }} available
                    </p>
                </div>

                @unless($detail['available'])
                    <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 text-sm">
                        This item is currently unavailable.
                    </div>
                @endunless

                {{-- Attributes --}}
                <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Details</h2>
                    <dl class="grid grid-cols-2 gap-y-2 text-sm">
                        @foreach($detail['attributes'] as $key => $value)
                            @if($value !== null && $value !== '')
                                <dt class="text-slate-400">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                                <dd class="text-slate-700">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</dd>
                            @endif
                        @endforeach
                    </dl>
                </div>

                {{-- Fulfilment --}}
                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    @if($detail['fulfilment']['pickup'])
                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600">Farm pickup</span>
                    @endif
                    @if($detail['fulfilment']['delivery'])
                        @php($feeLabel = $detail['fulfilment']['delivery_fee'] ? ' (' . $listing->currency_code . ' ' . number_format((float) $detail['fulfilment']['delivery_fee'], 2) . ')' : '')
                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-600">Local delivery{{ $feeLabel }}</span>
                    @endif
                </div>

                {{--
                    BUYING A PIG.

                    Two purchases, shown as two forms rather than one form with
                    a mode switch. They ask for different things — animals, or
                    kilograms — and a buyer choosing between them is choosing
                    between two products, not filling in the same one twice.
                    Two plain forms also work with JavaScript off.

                    Every price here was computed on the server from the
                    listing's snapshotted rate and the animals' recorded
                    weights. Nothing the browser posts back is trusted as money.
                --}}
                @if($pigPurchase && $detail['orderable'])
                    <div class="mt-6 space-y-4" data-pig-purchase>
                        @auth
                            @if($pigPurchase['animals']->isNotEmpty() && $pigPurchase['live_rate'])
                                <form method="POST" action="{{ route('storefront.cart.add-pigs', $listing) }}"
                                      class="rounded-2xl border border-slate-200 p-4">
                                    @csrf
                                    <input type="hidden" name="purchase_type" value="live">

                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <h3 class="font-semibold text-slate-800">Buy live animals</h3>
                                        <p class="text-sm text-slate-500">
                                            {{ $listing->currency_code }}
                                            {{ number_format((float) $pigPurchase['live_rate'], 2) }} / kg live weight
                                        </p>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Choose the animals you want. You pay for the weight each one is
                                        carrying, so the price differs between them.
                                    </p>

                                    <div class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200">
                                        @foreach($pigPurchase['animals'] as $animal)
                                            <label class="flex items-center gap-3 p-3 text-sm cursor-pointer hover:bg-slate-50">
                                                <input type="checkbox"
                                                       name="listing_pig_ids[]"
                                                       value="{{ $animal['row_id'] }}"
                                                       @disabled($animal['price'] === null)
                                                       class="rounded border-slate-300">
                                                <span class="flex-1 min-w-0">
                                                    <span class="font-medium text-slate-800">{{ $animal['ear_tag'] }}</span>
                                                    @if($animal['breed'])
                                                        <span class="text-xs text-slate-500">· {{ $animal['breed'] }}</span>
                                                    @endif
                                                </span>
                                                <span class="text-right">
                                                    @if($animal['price'] !== null)
                                                        <span class="block font-semibold text-slate-800">
                                                            {{ $listing->currency_code }} {{ number_format((float) $animal['price'], 2) }}
                                                        </span>
                                                        <span class="block text-xs text-slate-500">
                                                            {{ rtrim(rtrim($animal['weight'], '0'), '.') }} kg
                                                        </span>
                                                    @else
                                                        <span class="text-xs text-amber-700">Ask us for a price</span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <button type="submit" class="mt-3 w-full px-5 py-2.5 rounded-xl bg-slate-800 text-white font-medium">
                                        Add chosen animals to cart
                                    </button>
                                </form>
                            @endif

                            @if($pigPurchase['carcass_rate'] && bccomp($pigPurchase['carcass_kg_available'], '0', 4) > 0)
                                <form method="POST" action="{{ route('storefront.cart.add-pigs', $listing) }}"
                                      class="rounded-2xl border border-slate-200 p-4">
                                    @csrf
                                    <input type="hidden" name="purchase_type" value="carcass">

                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <h3 class="font-semibold text-slate-800">Buy by carcass weight</h3>
                                        <p class="text-sm text-slate-500">
                                            {{ $listing->currency_code }}
                                            {{ number_format((float) $pigPurchase['carcass_rate'], 2) }} / kg
                                        </p>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Tell us how much you need and we choose the animals.
                                        Up to {{ rtrim(rtrim($pigPurchase['carcass_kg_available'], '0'), '.') }} kg available.
                                    </p>

                                    <div class="mt-3 flex items-center gap-2">
                                        <input type="number" name="requested_kg" step="0.1" min="0.1"
                                               max="{{ $pigPurchase['carcass_kg_available'] }}"
                                               placeholder="kg"
                                               class="w-28 rounded-xl border-slate-300 text-sm">
                                        <button type="submit" class="flex-1 px-5 py-2.5 rounded-xl border border-slate-800 text-slate-800 font-medium">
                                            Add to cart
                                        </button>
                                    </div>
                                </form>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="inline-block px-5 py-2.5 rounded-xl bg-slate-800 text-white font-medium">Sign in to buy</a>
                        @endauth
                    </div>
                @endif

                {{-- CTA --}}
                <div class="mt-6 flex flex-wrap gap-3">
                    @if($detail['orderable'] && ! $pigPurchase)
                        @auth
                            <form method="POST" action="{{ route('storefront.cart.add', $listing) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="number" name="quantity" value="1" min="1" step="1" class="w-20 rounded-xl border-slate-300 text-sm">
                                <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-800 text-white font-medium">Add to cart</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-xl bg-slate-800 text-white font-medium">Sign in to buy</a>
                        @endauth
                    @endif
                    <a href="{{ route('storefront.quote.create', ['listing' => $listing->id]) }}" class="px-5 py-2.5 rounded-xl border border-slate-300 font-medium">Request a quote</a>

                    @auth
                        <form method="POST" action="{{ route('storefront.account.favourites.toggle', $listing) }}">
                            @csrf
                            <button class="px-5 py-2.5 rounded-xl border border-slate-300 font-medium">
                                {{ $favourited ? 'Saved' : 'Save' }}
                            </button>
                        </form>
                    @endauth
                </div>

                @if($listing->public_description)
                    <div class="mt-6 text-sm text-slate-600 whitespace-pre-line">{{ $listing->public_description }}</div>
                @endif
                @if($listing->seller_note)
                    <p class="mt-3 text-sm text-slate-500 italic">{{ $listing->seller_note }}</p>
                @endif
            </div>
        </div>
    </div>
</x-storefront.layout>
