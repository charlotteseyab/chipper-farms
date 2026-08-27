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
    $storefrontBuyer = session('storefront_buyer_id')
        ? \App\Models\Storefront\StorefrontBuyer::find(session('storefront_buyer_id'))
        : null;
    $buyerUnreadNotifications = $storefrontBuyer?->unreadNotifications()->count() ?? 0;
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
    <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root{
            --green-900:#124534;
            --green-800:#155A43;
            --green-700:#1C6C51;
            --green-600:#218c6a;
            --green-100:#eaf6f1;
            --green-50:#f6fbf8;
            --text-main:#10221a;
            --text-soft:#5f6f68;
            --line:#dfeee7;
            --white:#ffffff;
            --shadow-sm:0 8px 24px rgba(16,34,26,.06);
            --shadow-md:0 18px 40px rgba(16,34,26,.10);
            --shadow-lg:0 28px 70px rgba(16,34,26,.14);
        }

        html{
            scroll-behavior:smooth;
        }

        body{
            min-height:100vh;
            display:flex;
            flex-direction:column;
            font-family:'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            background:linear-gradient(180deg, #f8fcfa 0%, #ffffff 100%);
            color:var(--text-main);
        }

        .container-wrap{
            max-width:1200px;
            margin:0 auto;
            padding:0 20px;
        }

        .top-nav{
            position:sticky;
            top:0;
            z-index:50;
            border-bottom:1px solid rgba(223,238,231,.85);
            background:rgba(255,255,255,.92);
            backdrop-filter:blur(10px);
            -webkit-backdrop-filter:blur(10px);
        }

        .nav-link{
            position:relative;
            color:var(--green-700);
            font-weight:600;
            transition:.25s ease;
            text-decoration:none;
        }

        .nav-link:hover,
        .nav-link:focus{
            color:var(--green-900);
            outline:none;
        }

        .nav-link::after{
            content:"";
            position:absolute;
            left:0;
            bottom:-8px;
            width:0;
            height:2px;
            border-radius:999px;
            background:linear-gradient(90deg, var(--green-700), var(--green-600));
            transition:width .25s ease;
        }

        .nav-link:hover::after,
        .nav-link:focus::after{
            width:100%;
        }

        .shop-nav{
            position:relative;
            display:flex;
            align-items:center;
        }

        .shop-menu-button{
            display:inline-flex;
            align-items:center;
            gap:7px;
            border:0;
            background:transparent;
            cursor:pointer;
            font:inherit;
            padding:0;
        }

        .shop-menu-button svg{
            width:14px;
            height:14px;
            transition:transform .2s ease;
        }

        .shop-nav:hover .shop-menu-button svg,
        .shop-nav:focus-within .shop-menu-button svg{
            transform:rotate(180deg);
        }

        .shop-dropdown{
            position:absolute;
            top:calc(100% + 18px);
            left:50%;
            min-width:220px;
            transform:translateX(-50%) translateY(8px);
            opacity:0;
            visibility:hidden;
            pointer-events:none;
            background:#fff;
            border:1px solid var(--line);
            border-radius:18px;
            box-shadow:var(--shadow-lg);
            padding:10px;
            transition:.22s ease;
        }

        .shop-nav:hover .shop-dropdown,
        .shop-nav:focus-within .shop-dropdown{
            opacity:1;
            visibility:visible;
            pointer-events:auto;
            transform:translateX(-50%) translateY(0);
        }

        .shop-dropdown::before{
            content:"";
            position:absolute;
            left:0;
            right:0;
            top:-18px;
            height:18px;
        }

        .shop-dropdown a{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            border-radius:12px;
            padding:10px 12px;
            color:var(--green-800);
            font-weight:700;
            text-decoration:none;
            white-space:nowrap;
            transition:.2s ease;
        }

        .shop-dropdown a:hover,
        .shop-dropdown a:focus{
            background:var(--green-50);
            color:var(--green-900);
            outline:none;
        }

        .btn-primary{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:.6rem;
            background:linear-gradient(135deg, var(--green-700), var(--green-600));
            color:#fff;
            border:none;
            border-radius:14px;
            padding:12px 20px;
            font-weight:700;
            box-shadow:0 14px 30px rgba(28,108,81,.22);
            transition:.28s ease;
            text-decoration:none;
        }

        .btn-primary:hover,
        .btn-primary:focus{
            transform:translateY(-2px);
            box-shadow:0 20px 38px rgba(28,108,81,.28);
            outline:none;
        }

        .shop-page-bg{
            position:relative;
            overflow:hidden;
            background:
                radial-gradient(circle at top left, rgba(33,140,106,.10), transparent 34%),
                radial-gradient(circle at bottom right, rgba(28,108,81,.08), transparent 32%),
                linear-gradient(135deg, #f7fcf9 0%, #f2faf6 45%, #ffffff 100%);
        }

        .shop-page-bg::before{
            content:"";
            position:absolute;
            inset:0;
            background-image:
                linear-gradient(rgba(28,108,81,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(28,108,81,.025) 1px, transparent 1px);
            background-size:40px 40px;
            pointer-events:none;
        }

        .shop-content{
            position:relative;
            z-index:1;
            flex:1;
        }

        .big-footer{
            background:linear-gradient(180deg, #103327 0%, #0c291f 100%);
            color:#d6ece2;
            margin-top:0;
        }

        .footer-grid{
            display:grid;
            grid-template-columns:1.2fr .8fr .8fr .9fr;
            gap:34px;
        }

        .footer-brand{
            max-width:360px;
        }

        .footer-title{
            font-size:1rem;
            font-weight:800;
            color:#fff;
            margin-bottom:14px;
        }

        .footer-text{
            color:#cde6dc;
            line-height:1.9;
            font-size:.95rem;
        }

        .footer-list{
            display:grid;
            gap:10px;
        }

        .footer-link{
            color:#d4ece2;
            text-decoration:none;
            transition:.25s ease;
        }

        .footer-link:hover,
        .footer-link:focus{
            color:#fff;
            transform:translateX(2px);
            outline:none;
        }

        .footer-bottom{
            border-top:1px solid rgba(255,255,255,.10);
            margin-top:34px;
            padding-top:22px;
            display:flex;
            flex-direction:column;
            gap:10px;
        }

        .mobile-nav{
            display:none;
            position:relative;
        }

        .mobile-nav summary{
            display:flex;
            width:46px;
            height:46px;
            align-items:center;
            justify-content:center;
            border:1px solid var(--line);
            border-radius:14px;
            background:#fff;
            color:var(--green-800);
            cursor:pointer;
            box-shadow:var(--shadow-sm);
            list-style:none;
        }

        .mobile-nav summary::-webkit-details-marker{ display:none; }

        .mobile-nav summary:focus-visible{
            outline:3px solid rgba(28,108,81,.18);
            outline-offset:2px;
        }

        .mobile-nav-icon,
        .mobile-nav-icon::before,
        .mobile-nav-icon::after{
            display:block;
            width:20px;
            height:2px;
            border-radius:999px;
            background:currentColor;
            transition:.2s ease;
        }

        .mobile-nav-icon{ position:relative; }
        .mobile-nav-icon::before,
        .mobile-nav-icon::after{ content:""; position:absolute; left:0; }
        .mobile-nav-icon::before{ top:-6px; }
        .mobile-nav-icon::after{ top:6px; }
        .mobile-nav[open] .mobile-nav-icon{ background:transparent; }
        .mobile-nav[open] .mobile-nav-icon::before{ top:0; transform:rotate(45deg); }
        .mobile-nav[open] .mobile-nav-icon::after{ top:0; transform:rotate(-45deg); }

        .mobile-nav-panel{
            position:absolute;
            top:calc(100% + 12px);
            right:0;
            width:min(320px, calc(100vw - 32px));
            max-height:calc(100vh - 100px);
            overflow-y:auto;
            border:1px solid var(--line);
            border-radius:20px;
            background:#fff;
            padding:10px;
            box-shadow:var(--shadow-lg);
        }

        .mobile-nav-panel a{
            display:flex;
            align-items:center;
            justify-content:space-between;
            min-height:44px;
            border-radius:12px;
            padding:10px 12px;
            color:var(--green-800);
            font-weight:700;
            text-decoration:none;
        }

        .mobile-nav-panel a:hover,
        .mobile-nav-panel a:focus{
            background:var(--green-50);
            color:var(--green-900);
            outline:none;
        }

        .mobile-nav-label{
            padding:10px 12px 5px;
            color:#809087;
            font-size:.7rem;
            font-weight:800;
            letter-spacing:.12em;
            text-transform:uppercase;
        }

        .mobile-nav-divider{
            height:1px;
            margin:8px 6px;
            background:var(--line);
        }

        @media (max-width:1024px){
            .footer-grid{
                grid-template-columns:1fr;
            }
        }

        @media (max-width:768px){
            .top-nav .desktop-nav{
                display:none;
            }

            .top-nav .desktop-actions{
                display:none;
            }

            .mobile-nav{
                display:block;
            }

            .container-wrap{
                padding:0 16px;
            }
        }
    </style>
</head>
<body class="antialiased">
    <nav class="top-nav">
        <div class="container-wrap">
            <div class="flex items-center justify-between py-4">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <img src="{{ asset('images/chipper-farms-logo.jpg') }}" alt="{{ $appName }} logo" class="h-11 w-auto rounded-xl border border-green-100 shadow-sm">
                </a>

                <div class="desktop-nav flex items-center gap-8">
                    <a href="{{ url('/#home') }}" class="nav-link">Home</a>
                    <div class="shop-nav">
                        <button type="button" class="nav-link shop-menu-button" aria-haspopup="true" aria-expanded="false">
                            Shop
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div class="shop-dropdown" role="menu">
                            <a href="{{ route('storefront.shop') }}" role="menuitem">Shop all</a>
                            @foreach($navKinds as $navKind)
                                <a href="{{ route('storefront.shop', ['kind' => $navKind]) }}" role="menuitem">{{ $kindLabels[$navKind] ?? $navKind }}</a>
                            @endforeach
                            @if(StorefrontSettings::quotesEnabled())
                                <a href="{{ route('storefront.quote.create') }}" role="menuitem">Request a quote</a>
                            @endif
                            <a href="{{ route('storefront.cart.index') }}" role="menuitem">Cart</a>
                        </div>
                    </div>
                    <a href="{{ url('/#about') }}" class="nav-link">About</a>
                    <a href="{{ url('/#features') }}" class="nav-link">Features</a>
                    <a href="{{ url('/#contact') }}" class="nav-link">Contact</a>
                </div>

                <div class="desktop-actions flex items-center gap-3">
                    @if(session('storefront_buyer_id'))
                        <a href="{{ route('storefront.buyer.notifications.index') }}" class="relative inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[var(--line)] bg-white text-[var(--green-700)]" aria-label="Buyer notifications">
                            <x-ui-icon name="notification" class="h-5 w-5" />
                            @if($buyerUnreadNotifications > 0)<span class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $buyerUnreadNotifications > 99 ? '99+' : $buyerUnreadNotifications }}</span>@endif
                        </a>
                        <a href="{{ route('storefront.buyer.orders.index') }}" class="btn-primary">My orders</a>
                        <a href="{{ route('storefront.buyer.quotes.index') }}" class="nav-link">My quotes</a>
                    @else
                        @auth
                            <a href="{{ route('notifications.index') }}" class="relative hidden h-11 w-11 items-center justify-center rounded-xl border border-[var(--line)] bg-white text-[var(--green-700)] sm:inline-flex" aria-label="Notifications">
                                <x-ui-icon name="notification" class="h-5 w-5" />
                                @if(auth()->user()->unreadNotifications()->count() > 0)<span class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ auth()->user()->unreadNotifications()->count() > 99 ? '99+' : auth()->user()->unreadNotifications()->count() }}</span>@endif
                            </a>
                            @if($customerAccountsEnabled)
                                <a href="{{ route('storefront.account.dashboard') }}" class="hidden nav-link sm:inline-flex">My account</a>
                            @endif
                            <a href="{{ route('storefront.account.orders.index') }}" class="btn-primary">My orders</a>
                        @else
                            <a href="{{ route('storefront.buyer.login') }}" class="btn-primary">Sign In</a>
                        @endauth
                    @endif
                </div>

                <details class="mobile-nav">
                    <summary aria-label="Open navigation menu"><span class="mobile-nav-icon" aria-hidden="true"></span></summary>
                    <nav class="mobile-nav-panel" aria-label="Mobile navigation">
                        <a href="{{ url('/#home') }}">Home</a>
                        <a href="{{ url('/#about') }}">About</a>
                        <a href="{{ url('/#features') }}">Features</a>
                        <a href="{{ url('/#contact') }}">Contact</a>
                        <div class="mobile-nav-divider"></div>
                        <div class="mobile-nav-label">Shop</div>
                        <a href="{{ route('storefront.shop') }}">Shop all <span aria-hidden="true">&rarr;</span></a>
                        @foreach($navKinds as $navKind)
                            <a href="{{ route('storefront.shop', ['kind' => $navKind]) }}">{{ $kindLabels[$navKind] ?? $navKind }}</a>
                        @endforeach
                        @if(StorefrontSettings::quotesEnabled())
                            <a href="{{ route('storefront.quote.create') }}">Request a quote</a>
                        @endif
                        <a href="{{ route('storefront.cart.index') }}">Cart</a>
                        <div class="mobile-nav-divider"></div>
                        @if(session('storefront_buyer_id'))
                            <a href="{{ route('storefront.buyer.notifications.index') }}">Notifications @if($buyerUnreadNotifications > 0)({{ $buyerUnreadNotifications }})@endif</a>
                            <a href="{{ route('storefront.buyer.orders.index') }}">My orders</a>
                            <a href="{{ route('storefront.buyer.quotes.index') }}">My quotes</a>
                        @else
                            @auth
                                <a href="{{ route('notifications.index') }}">Notifications @if(auth()->user()->unreadNotifications()->count() > 0)({{ auth()->user()->unreadNotifications()->count() }})@endif</a>
                                @if($customerAccountsEnabled)
                                    <a href="{{ route('storefront.account.dashboard') }}">My account</a>
                                @endif
                                <a href="{{ route('storefront.account.orders.index') }}">My orders</a>
                            @else
                                <a href="{{ route('storefront.buyer.login') }}">Sign In <span aria-hidden="true">&rarr;</span></a>
                            @endauth
                        @endif
                    </nav>
                </details>
            </div>
        </div>
    </nav>

    <main class="shop-page-bg shop-content">
        @include('layouts.partials.flash-messages')

        {{ $slot }}
    </main>

    <footer class="big-footer">
        <div class="container-wrap py-16 md:py-20">
            <div class="footer-grid">
                <div class="footer-brand mt-6">
                    <div class="flex items-center gap-3 mb-5">
                        <img src="{{ asset('images/chipper-farms-logo.jpg') }}" alt="{{ $appName }} logo" class="h-12 w-auto rounded-xl border border-white/10">
                    </div>

                    <p class="footer-text">
                        {{ $storeName }} is part of the Chipper Farms platform, connecting public ordering with the same operational records, stock controls, and finance workflows used by the farm team.
                    </p>
                </div>

                <div>
                    <h3 class="footer-title mt-6">Navigation</h3>
                    <div class="footer-list">
                        <a href="{{ url('/#home') }}" class="footer-link">Home</a>
                        <a href="{{ route('storefront.shop') }}" class="footer-link">Shop</a>
                        <a href="{{ url('/#about') }}" class="footer-link">About</a>
                        <a href="{{ url('/#features') }}" class="footer-link">Features</a>
                        <a href="{{ url('/#contact') }}" class="footer-link">Contact</a>
                    </div>
                </div>

                <div>
                    <h3 class="footer-title mt-6">Shop</h3>
                    <div class="footer-list">
                        <a href="{{ route('storefront.shop') }}" class="footer-link">Shop all</a>
                        @foreach(array_slice($navKinds, 0, 4) as $navKind)
                            <a href="{{ route('storefront.shop', ['kind' => $navKind]) }}" class="footer-link">{{ $kindLabels[$navKind] ?? $navKind }}</a>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h3 class="footer-title mt-6">Contact</h3>
                    <div class="footer-list">
                        @if(StorefrontSettings::supportEmail())
                            <a href="mailto:{{ StorefrontSettings::supportEmail() }}" class="footer-link">{{ StorefrontSettings::supportEmail() }}</a>
                        @else
                            <a href="mailto:support@chipperfarms.com" class="footer-link">support@chipperfarms.com</a>
                        @endif
                        @if(StorefrontSettings::supportPhone())
                            <a href="tel:{{ preg_replace('/\s+/', '', StorefrontSettings::supportPhone()) }}" class="footer-link">{{ StorefrontSettings::supportPhone() }}</a>
                        @else
                            <a href="tel:+233244853386" class="footer-link">+233 24 485 3386</a>
                        @endif
                        <span class="text-sm text-green-200">{{ StorefrontSettings::pickupAddress() ?: 'Accra, Ghana' }}</span>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="flex flex-col gap-3 text-sm text-green-200 md:flex-row md:items-center md:justify-between">
                    <div>&copy; {{ date('Y') }} Chipper Farms. Powered by Innosol Limited All rights reserved.</div>
                    <div>Built for efficient pig farm operations and smarter farm management.</div>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
