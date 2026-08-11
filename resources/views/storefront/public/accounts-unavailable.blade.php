{{--
    Shown when a farm does not offer customer accounts (Phase 16, §18/§46).

    Standalone rather than wrapped in the account layout, for the same reason
    the closed-shop page is: that layout's navigation is the account area, which
    is the thing that is unavailable.

    The link is the whole point. Blueprint §46 asks that no state be a dead
    end, and this page has to answer the question the visitor actually arrived
    with — "where are my orders?" Order history stays open when accounts are
    switched off, so it is offered here. Everyone who reaches this page is
    signed in, because the account routes require authentication before this
    middleware runs.

    503, not 404: the surface is unavailable rather than nonexistent, and the
    visitor is a customer rather than someone probing for staff pages.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $storeName }} — accounts unavailable</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-900 min-h-screen flex items-center justify-center px-4">
    <main class="max-w-md w-full bg-white border border-slate-200 rounded-2xl shadow-sm p-8 text-center">
        <h1 class="text-2xl font-bold">{{ $storeName }}</h1>

        <p class="mt-4 text-slate-600">
            We are not offering customer accounts at the moment. Your orders are still
            here, and you can look one up at any time.
        </p>

        <div class="mt-8 space-y-3">
            @auth
                <a href="{{ route('storefront.account.orders.index') }}"
                   class="block w-full px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium">
                    View my orders
                </a>
            @endauth
        </div>

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
    </main>
</body>
</html>
