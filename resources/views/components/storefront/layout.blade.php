@php
    use App\Services\Organizations\OrganizationFeatureService;
    use App\Storefront\Support\StorefrontCatalog;
    use App\Storefront\Support\StorefrontSettings;
    use App\Support\Features\FeatureCatalog;

    // Phase 8: the nav used to hard-code every kind, so a farm that had turned
    // Manure Sales off still advertised a Manure tab that led to an empty shop.
    $navKinds = StorefrontCatalog::enabledKinds();
    $featureService = app(OrganizationFeatureService::class);
    $customerAccountsEnabled = $featureService->usable(FeatureCatalog::STOREFRONT_CUSTOMER_ACCOUNTS);
    $storeName = StorefrontSettings::storeName();
    $appName = 'Chipper Farms';
    $kindLabels = [
        'live_pig'       => 'Live Pigs',
        'piglet_lot'     => 'Piglets',
        'breeding_stock' => 'Breeding',
        'feed'           => 'Feed',
        'manure'         => 'Manure',
        'service'        => 'Services',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? StorefrontSettings::storeName() }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-slate-100 font-sans text-slate-900 antialiased">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between gap-4">
            <a href="{{ route('storefront.home') }}" class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('images/chipper-farms-logo.jpg') }}" alt="{{ $appName }} logo" class="h-11 w-11 rounded-2xl border border-emerald-100 bg-white object-cover shadow-sm">
                <span class="min-w-0">
                    <span class="block truncate text-base font-semibold text-slate-900">{{ $appName }}</span>
                    <span class="block truncate text-xs font-medium text-emerald-700">{{ $storeName }}</span>
                </span>
            </a>
            <div class="flex shrink-0 items-center gap-2 text-sm">
                {{-- Phase 9: the cart is for everyone. It sat inside @auth, which
                     meant a guest could not see the basket they were allowed to
                     fill — the link and the capability disagreed. --}}
                <a href="{{ route('storefront.cart.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50">Cart</a>
                @auth
                    @if($customerAccountsEnabled)
                        <a href="{{ route('storefront.account.dashboard') }}" class="hidden rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50 sm:inline-flex">My account</a>
                    @endif
                    <a href="{{ route('storefront.account.orders.index') }}" class="inline-flex rounded-lg bg-[#1C6C51] px-3 py-2 font-semibold text-white hover:bg-[#185941]">My orders</a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex rounded-lg bg-[#1C6C51] px-3 py-2 font-semibold text-white hover:bg-[#185941]">Sign in</a>
                @endauth
            </div>
            </div>

            <nav class="flex items-center gap-2 overflow-x-auto text-sm font-semibold text-slate-600">
                <a href="{{ route('storefront.shop') }}" class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-slate-900">Shop all</a>
                @foreach($navKinds as $navKind)
                    <a href="{{ route('storefront.shop', ['kind' => $navKind]) }}" class="whitespace-nowrap rounded-lg px-3 py-2 hover:bg-slate-100 hover:text-slate-900">{{ $kindLabels[$navKind] ?? $navKind }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="flex-1">
        @if(session('success'))
            <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8"><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div></div>
        @endif
        @if(session('error'))
            <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8"><div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div></div>
        @endif

        {{ $slot }}
    </main>

    <footer class="mt-12 border-t border-slate-200 bg-white">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 text-sm text-slate-600 sm:px-6 md:grid-cols-3 lg:px-8">
            <div>
                <p class="mb-2 font-semibold text-slate-900">{{ $appName }}</p>
                <p>{{ $storeName }} handles public browsing, checkout and quote requests through the same farm system.</p>
            </div>
            <div>
                <p class="mb-2 font-semibold text-slate-900">Ordering</p>
                <ul class="space-y-1">
                    <li>Browse &amp; place an order or request a quote</li>
                    <li>We confirm availability before acceptance</li>
                    <li>Pay by Paystack or cash on delivery</li>
                </ul>
            </div>
            <div>
                <p class="mb-2 font-semibold text-slate-900">Contact</p>
                <ul class="space-y-1">
                    @if(StorefrontSettings::supportPhone())<li>Phone: {{ StorefrontSettings::supportPhone() }}</li>@endif
                    @if(StorefrontSettings::supportEmail())<li>Email: {{ StorefrontSettings::supportEmail() }}</li>@endif
                    @if(StorefrontSettings::pickupAddress())<li>Pickup: {{ StorefrontSettings::pickupAddress() }}</li>@endif
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-200 py-4 text-center text-xs text-slate-500">
            &copy; {{ date('Y') }} {{ $storeName }}. Powered by Innosol Ghana Limited.
        </div>
    </footer>
</body>
</html>
