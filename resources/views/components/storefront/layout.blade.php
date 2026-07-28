@php use App\Storefront\Support\StorefrontSettings; @endphp
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
<body class="bg-slate-50 font-sans antialiased text-slate-900 min-h-screen flex flex-col">
    <header class="bg-white border-b border-slate-200 sticky top-0 z-20">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
            <a href="{{ route('storefront.home') }}" class="flex items-center gap-2 font-bold text-lg">
                <x-ui-icon name="pig" class="h-6 w-6 text-emerald-700" />
                <span>{{ StorefrontSettings::storeName() }}</span>
            </a>
            <nav class="hidden md:flex items-center gap-5 text-sm font-medium text-slate-600">
                <a href="{{ route('storefront.shop', ['kind' => 'live_pig']) }}" class="hover:text-slate-900">Live Pigs</a>
                <a href="{{ route('storefront.shop', ['kind' => 'piglet_lot']) }}" class="hover:text-slate-900">Piglets</a>
                <a href="{{ route('storefront.shop', ['kind' => 'breeding_stock']) }}" class="hover:text-slate-900">Breeding</a>
                <a href="{{ route('storefront.shop', ['kind' => 'feed']) }}" class="hover:text-slate-900">Feed</a>
                <a href="{{ route('storefront.shop', ['kind' => 'manure']) }}" class="hover:text-slate-900">Manure</a>
                <a href="{{ route('storefront.shop') }}" class="hover:text-slate-900">Shop all</a>
            </nav>
            <div class="flex items-center gap-3 text-sm">
                @auth
                    <a href="{{ route('storefront.account.cart.index') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100">Cart</a>
                    <a href="{{ route('storefront.account.dashboard') }}" class="px-3 py-1.5 rounded-lg bg-slate-800 text-white">My account</a>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100">Sign in</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-1">
        @if(session('success'))
            <div class="max-w-6xl mx-auto px-4 pt-4"><div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div></div>
        @endif
        @if(session('error'))
            <div class="max-w-6xl mx-auto px-4 pt-4"><div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div></div>
        @endif

        {{ $slot }}
    </main>

    <footer class="bg-slate-900 text-slate-300 mt-12">
        <div class="max-w-6xl mx-auto px-4 py-8 grid md:grid-cols-3 gap-6 text-sm">
            <div>
                <p class="font-semibold text-white mb-2">{{ StorefrontSettings::storeName() }}</p>
                <p class="text-slate-400">Quality pigs, breeding stock and feed — for pickup or local delivery.</p>
            </div>
            <div>
                <p class="font-semibold text-white mb-2">Ordering</p>
                <ul class="space-y-1 text-slate-400">
                    <li>Browse &amp; place an order or request a quote</li>
                    <li>We confirm availability before acceptance</li>
                    <li>Pay by Paystack or cash on delivery</li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-white mb-2">Contact</p>
                <ul class="space-y-1 text-slate-400">
                    @if(StorefrontSettings::supportPhone())<li>Phone: {{ StorefrontSettings::supportPhone() }}</li>@endif
                    @if(StorefrontSettings::supportEmail())<li>Email: {{ StorefrontSettings::supportEmail() }}</li>@endif
                    @if(StorefrontSettings::pickupAddress())<li>Pickup: {{ StorefrontSettings::pickupAddress() }}</li>@endif
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-800 text-center text-xs text-slate-500 py-4">
            &copy; {{ date('Y') }} {{ StorefrontSettings::storeName() }}. All rights reserved.
        </div>
    </footer>
</body>
</html>
