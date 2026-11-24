<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Chipper Farms') }}</title>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon.svg">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-100 font-sans antialiased text-slate-900">
    <x-banner />

    @php
        $user = Auth::user();
        $sidebarSections = $user ? \App\Helpers\NavigationHelper::sidebarSections($user) : [];
        $sidebarLogoUrl = asset('images/chipper-farms-logo.jpg');
        $searchItems = collect($sidebarSections)->flatMap(function ($section) {
            return collect($section['items'] ?? [])->flatMap(function ($item) use ($section) {
                if (($item['type'] ?? 'link') === 'group') {
                    return collect($item['children'] ?? [])->map(function ($child) use ($section, $item) {
                        return [
                            'label' => $child['label'],
                            'route' => $child['route'],
                            'url' => route($child['route']),
                            'section' => $section['title'],
                            'group' => $item['label'],
                        ];
                    });
                }

                return [[
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'url' => route($item['route']),
                    'section' => $section['title'],
                    'group' => null,
                ]];
            });
        })->values();
    @endphp

    <div class="min-h-screen">
        @if($user)
            <div id="appOverlay" class="fixed inset-0 z-40 hidden bg-slate-950/50 lg:hidden"></div>

            <aside id="appSidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-[#185941] bg-[#1C6C51] text-white shadow-xl transition-transform duration-300 lg:translate-x-0">
                <div class="flex items-center justify-between border-b border-white/10 bg-[#185941] px-5 py-5">
                    <a href="{{ \App\Helpers\NavigationHelper::dashboardRoute($user) }}" class="flex items-center gap-3">
                        <img src="{{ $sidebarLogoUrl }}" alt="{{ config('app.name', 'Chipper Farms') }} logo" class="h-12 w-12 rounded-2xl border-2 border-white/80 bg-white object-cover shadow">
                        <div>
                            <p class="text-sm font-semibold tracking-wide">{{ config('app.name', 'Chipper Farms') }}</p>
                            <p class="text-xs text-emerald-100/90">{{ \App\Helpers\NavigationHelper::displayRole($user) }}</p>
                        </div>
                    </a>

                    <button id="closeSidebarBtn" type="button" class="rounded-xl border border-white/15 px-2 py-1 text-sm text-white/90 transition hover:bg-white/10 lg:hidden">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- <div class="border-b border-white/10 px-5 py-4 text-sm text-emerald-100/90">
                    <p class="font-semibold text-white">{{ $user->name }}</p>
                    <p class="mt-1 text-xs leading-5">Operational navigation changes based on your role and access level.</p>
                </div> -->

                <nav id="sidebarNav" class="flex-1 space-y-1 overflow-y-auto px-4 py-5">
                    @foreach($sidebarSections as $section)
                        @php
                            $sectionIsActive = collect($section['items'] ?? [])->contains(function ($item) {
                                $itemActive = collect($item['active_patterns'] ?? [])->contains(
                                    fn ($pattern) => request()->routeIs($pattern) || request()->is($pattern)
                                );

                                if (($item['type'] ?? 'link') !== 'group') {
                                    return $itemActive;
                                }

                                return $itemActive || collect($item['children'] ?? [])->contains(function ($child) {
                                    return collect($child['active_patterns'] ?? [])->contains(
                                        fn ($pattern) => request()->routeIs($pattern) || request()->is($pattern)
                                    );
                                });
                            });
                            $sectionIcon = match ($section['title']) {
                                'Overview' => 'fa-solid fa-gauge-high',
                                'Pig Operations' => 'fa-solid fa-piggy-bank',
                                'Breeding & Lifecycle' => 'fa-solid fa-dna',
                                'Monitoring' => 'fa-solid fa-chart-line',
                                'Feed Management' => 'fa-solid fa-wheat-awn',
                                'Storefront' => 'fa-solid fa-store',
                                'Finance' => 'fa-solid fa-wallet',
                                'System Settings' => 'fa-solid fa-gear',
                                'Platform' => 'fa-solid fa-building-shield',
                                default => 'fa-solid fa-folder-open',
                            };
                        @endphp
                        <details data-sidebar-section class="group/section" {{ $sectionIsActive ? 'open' : '' }}>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 border-l-2 px-3 py-3.5 text-sm font-bold transition {{ $sectionIsActive ? 'border-white text-white' : 'border-transparent text-emerald-50 hover:border-white/40 hover:text-white' }}">
                                <span class="flex min-w-0 items-center gap-3">
                                    <i class="{{ $sectionIcon }} w-4 shrink-0 text-center text-xs {{ $sectionIsActive ? 'text-white' : 'text-emerald-200/80' }}"></i>
                                    <span class="truncate">{{ $section['title'] }}</span>
                                </span>
                                <i class="fa-solid fa-chevron-down text-[10px] text-emerald-100/80 transition-transform group-open/section:rotate-180"></i>
                            </summary>

                            <div class="space-y-1 pb-3 pl-4 pr-1">
                                @foreach($section['items'] as $item)
                                    @php
                                        $patterns = $item['active_patterns'] ?? [];
                                        $isActive = collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern) || request()->is($pattern));
                                        $labelForSearch = strtolower(trim($section['title'].' '.($item['label'] ?? '')));
                                    @endphp

                                    @if(($item['type'] ?? 'link') === 'group')
                                        @php
                                            $groupChildren = $item['children'] ?? [];
                                            $childActive = collect($groupChildren)->contains(function ($child) {
                                                return collect($child['active_patterns'] ?? [])->contains(fn ($pattern) => request()->routeIs($pattern) || request()->is($pattern));
                                            });
                                        @endphp

                                        <details
                                            class="group rounded-2xl {{ $childActive || $isActive ? 'bg-white/10' : 'hover:bg-white/5' }}"
                                            data-sidebar-group
                                            data-sidebar-searchable
                                            data-sidebar-label="{{ strtolower(trim($section['title'].' '.($item['label'] ?? ''))) }}"
                                            {{ $childActive || $isActive ? 'open' : '' }}
                                        >
                                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-white/90 transition">
                                                <span class="flex items-center gap-3">
                                                    <i class="{{ $item['icon'] }} w-4 text-center"></i>
                                                    <span>{{ $item['label'] }}</span>
                                                </span>
                                                <i class="fa-solid fa-chevron-down text-xs text-white/70 transition group-open:rotate-180"></i>
                                            </summary>

                                            <div class="space-y-1 pb-2 pl-4 pr-2">
                                                @foreach($groupChildren as $child)
                                                    @php
                                                        $childPatterns = $child['active_patterns'] ?? [];
                                                        $childIsActive = collect($childPatterns)->contains(fn ($pattern) => request()->routeIs($pattern) || request()->is($pattern));
                                                    @endphp
                                                    <a
                                                        href="{{ route($child['route']) }}"
                                                        data-sidebar-searchable
                                                        data-sidebar-link
                                                        data-sidebar-label="{{ strtolower(trim($section['title'].' '.($item['label'] ?? '').' '.($child['label'] ?? ''))) }}"
                                                        class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition {{ $childIsActive ? 'bg-white text-[#185941] shadow-sm' : 'text-white/90 hover:bg-white/10 hover:text-white' }}"
                                                    >
                                                        <i class="{{ $child['icon'] }} w-4 text-center"></i>
                                                        <span>{{ $child['label'] }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </details>
                                    @else
                                        <a
                                            href="{{ route($item['route']) }}"
                                            data-sidebar-searchable
                                            data-sidebar-link
                                            data-sidebar-label="{{ $labelForSearch }}"
                                            class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition {{ $isActive ? 'bg-white text-[#185941] shadow-sm' : 'text-white/90 hover:bg-white/10 hover:text-white' }}"
                                        >
                                            <i class="{{ $item['icon'] }} w-4 text-center"></i>
                                            <span>{{ $item['label'] }}</span>
                                            @if(! empty($item['badge']))
                                                <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-300/90 text-amber-950' }}">{{ $item['badge'] }}</span>
                                            @endif
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                    @endforeach

                    <div id="sidebarSearchEmptyState" class="hidden rounded-2xl border border-white/15 bg-white/5 px-4 py-4 text-sm text-emerald-100">
                        No menu links match your search yet.
                    </div>
                </nav>
            </aside>
        @endif

        <div class="min-h-screen {{ $user ? 'lg:pl-72' : '' }}">
            @if($user)
                @include('navigation-menu', ['user' => $user, 'searchItems' => $searchItems])
            @endif

            <div class="flex min-h-screen flex-col px-4 {{ $user ? 'pt-24 sm:px-6 lg:px-8' : 'pt-8' }}" style="padding-bottom: var(--app-footer-clearance, 7rem);">
                @if($user && request()->routeIs('finance.*'))
                    <x-finance.hub-tabs />
                @endif

                @if (isset($header))
                    <header class="mb-5 rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:px-6">
                        {{ $header }}
                    </header>
                @endif

                @include('layouts.partials.flash-messages')

                @if($user?->isSuperuser() && request()->routeIs('settings.*'))
                    <div class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm">
                        <p class="font-bold">Platform settings mode</p>
                        <p class="mt-1">You are not inside a farm. Shared settings changed here become defaults for all organizations; organization-specific controls are identified on their page.</p>
                    </div>
                @endif

                <main class="flex-1">
                    {{ $slot }}
                </main>

                @include('layouts.partials.footer')
            </div>
        </div>
    </div>

    @stack('modals')
    @livewireScripts

    <script>
        (function () {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('appOverlay');
            const openBtn = document.getElementById('openSidebarBtn');
            const closeBtn = document.getElementById('closeSidebarBtn');
            const searchInput = document.getElementById('navSearchInput');
            const searchForm = document.getElementById('navSearchForm');
            const navSearchItems = Array.from(document.querySelectorAll('[data-nav-search-item]'));
            const sectionEls = Array.from(document.querySelectorAll('[data-sidebar-section]'));
            const emptyState = document.getElementById('sidebarSearchEmptyState');
            const appFooter = document.getElementById('appFooter');

            sectionEls.forEach((section) => {
                section.addEventListener('toggle', () => {
                    if (!section.open || (searchInput?.value || '').trim()) return;

                    sectionEls.forEach((otherSection) => {
                        if (otherSection !== section) otherSection.removeAttribute('open');
                    });
                });
            });

            const reserveFooterSpace = () => {
                if (!appFooter) return;
                const clearance = Math.ceil(appFooter.getBoundingClientRect().height + 32);
                document.documentElement.style.setProperty('--app-footer-clearance', `${clearance}px`);
            };

            reserveFooterSpace();
            window.addEventListener('resize', reserveFooterSpace);

            if (appFooter && 'ResizeObserver' in window) {
                new ResizeObserver(reserveFooterSpace).observe(appFooter);
            }

            const openSidebar = () => {
                if (!sidebar || !overlay) return;
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            };

            const closeSidebar = () => {
                if (!sidebar || !overlay) return;
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            openBtn?.addEventListener('click', openSidebar);
            closeBtn?.addEventListener('click', closeSidebar);
            overlay?.addEventListener('click', closeSidebar);

            window.addEventListener('resize', () => {
                if (!sidebar || !overlay) return;
                if (window.innerWidth >= 1024) {
                    overlay.classList.add('hidden');
                    sidebar.classList.remove('-translate-x-full');
                    document.body.classList.remove('overflow-hidden');
                } else {
                    sidebar.classList.add('-translate-x-full');
                }
            });

            const updateClock = () => {
                const now = new Date();
                const dateEl = document.getElementById('navCurrentDate');
                const timeEl = document.getElementById('navCurrentTime');
                const mobileEl = document.getElementById('navCurrentDateTimeMobile');

                const dateText = now.toLocaleDateString([], {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                });
                const timeText = now.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                });

                if (dateEl) dateEl.textContent = dateText;
                if (timeEl) timeEl.textContent = timeText;
                if (mobileEl) mobileEl.textContent = `${dateText} · ${timeText}`;
            };

            const filterSidebar = (term) => {
                const normalized = (term || '').trim().toLowerCase();
                let totalVisibleLinks = 0;

                sectionEls.forEach((section) => {
                    let sectionVisibleCount = 0;

                    section.querySelectorAll('[data-sidebar-link]').forEach((link) => {
                        const label = (link.dataset.sidebarLabel || '').toLowerCase();
                        const visible = !normalized || label.includes(normalized);
                        link.classList.toggle('hidden', !visible);
                        if (visible) sectionVisibleCount += 1;
                    });

                    section.querySelectorAll('[data-sidebar-group]').forEach((group) => {
                        const visibleChildren = Array.from(group.querySelectorAll('[data-sidebar-link]')).filter((child) => !child.classList.contains('hidden'));
                        const groupMatches = (group.dataset.sidebarLabel || '').includes(normalized);
                        const shouldShow = !normalized || groupMatches || visibleChildren.length > 0;
                        group.classList.toggle('hidden', !shouldShow);
                        if (normalized && shouldShow && visibleChildren.length > 0) {
                            group.setAttribute('open', 'open');
                        }
                    });

                    sectionVisibleCount = section.querySelectorAll('[data-sidebar-link]:not(.hidden)').length;
                    totalVisibleLinks += sectionVisibleCount;
                    section.classList.toggle('hidden', sectionVisibleCount === 0);
                    if (normalized && sectionVisibleCount > 0) {
                        section.setAttribute('open', 'open');
                    }
                });

                if (emptyState) {
                    emptyState.classList.toggle('hidden', totalVisibleLinks > 0 || !normalized);
                }
            };

            searchInput?.addEventListener('input', (event) => {
                filterSidebar(event.target.value);
            });

            searchForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                const query = (searchInput?.value || '').trim().toLowerCase();
                if (!query) return;

                const exactMatch = navSearchItems.find((item) => (item.dataset.searchLabel || '').toLowerCase() === query);
                if (exactMatch?.dataset.searchUrl) {
                    window.location.href = exactMatch.dataset.searchUrl;
                    return;
                }

                const partialMatch = navSearchItems.find((item) => (item.dataset.searchLabel || '').toLowerCase().includes(query));
                if (partialMatch?.dataset.searchUrl) {
                    window.location.href = partialMatch.dataset.searchUrl;
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
                    event.preventDefault();
                    searchInput?.focus();
                }
            });

            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationToggle = document.getElementById('notificationDropdownToggle');
            const notificationPanel = document.getElementById('notificationDropdownPanel');

            const closeNotificationDropdown = () => {
                if (!notificationDropdown || !notificationPanel) return;
                notificationDropdown.dataset.open = 'false';
                notificationPanel.classList.add('hidden');
                notificationToggle?.setAttribute('aria-expanded', 'false');
            };

            const openNotificationDropdown = () => {
                if (!notificationDropdown || !notificationPanel) return;
                notificationDropdown.dataset.open = 'true';
                notificationPanel.classList.remove('hidden');
                notificationToggle?.setAttribute('aria-expanded', 'true');
            };

            notificationToggle?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (notificationDropdown?.dataset.open === 'true') {
                    closeNotificationDropdown();
                } else {
                    openNotificationDropdown();
                }
            });

            notificationPanel?.addEventListener('click', (event) => {
                event.stopPropagation();
            });

            document.addEventListener('click', (event) => {
                if (!notificationDropdown) return;
                if (!notificationDropdown.contains(event.target)) {
                    closeNotificationDropdown();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeNotificationDropdown();
                }
            });

            closeNotificationDropdown();

            updateClock();
            window.setInterval(updateClock, 30000);
        })();
    </script>
</body>
</html>
