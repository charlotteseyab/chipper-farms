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
                        <span class="text-6xl">🐷</span>
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

                {{-- CTA --}}
                <div class="mt-6 flex flex-wrap gap-3">
                    @if($detail['orderable'])
                        @auth
                            <form method="POST" action="{{ route('storefront.account.cart.add', $listing) }}" class="flex items-center gap-2">
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
                                {{ $favourited ? '★ Saved' : '☆ Save' }}
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
