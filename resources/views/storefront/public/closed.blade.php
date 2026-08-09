{{--
    Shown when an organisation has switched its public shop off (Phase 8).

    Deliberately standalone rather than wrapped in the storefront layout: that
    layout's header links straight back into the catalogue, which is the thing
    that is closed. A closed door with a working handle is worse than no door.

    Served with 503 so search engines treat the closure as temporary. The
    support details are the point of the page — blueprint §46 asks that no
    state be a dead end, and "we are closed, here is how to reach us" is the
    difference between a pause and a lost customer.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $storeName }} — shop closed</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-900 min-h-screen flex items-center justify-center px-4">
    <main class="max-w-md w-full bg-white border border-slate-200 rounded-2xl shadow-sm p-8 text-center">
        <h1 class="text-2xl font-bold">{{ $storeName }}</h1>

        <p class="mt-4 text-slate-600">
            Our online shop is closed at the moment. We are still here — please get in
            touch and we will help you directly.
        </p>

        @if($supportPhone || $supportEmail)
            <div class="mt-6 space-y-2 text-sm">
                @if($supportPhone)
                    <p><span class="text-slate-500">Phone</span><br>
                        <a class="font-semibold text-emerald-700" href="tel:{{ $supportPhone }}">{{ $supportPhone }}</a>
                    </p>
                @endif
                @if($supportEmail)
                    <p><span class="text-slate-500">Email</span><br>
                        <a class="font-semibold text-emerald-700" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                    </p>
                @endif
            </div>
        @endif

        @auth
            <a href="{{ route('storefront.account.orders.index') }}"
               class="mt-8 inline-block px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium hover:bg-slate-100">
                View my orders
            </a>
        @endauth
    </main>
</body>
</html>
