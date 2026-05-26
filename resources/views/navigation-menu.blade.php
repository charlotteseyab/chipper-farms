<div class="fixed inset-x-0 top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur lg:left-72">
    <div class="flex min-h-16 flex-wrap items-center gap-3 px-4 py-3 sm:px-6 lg:flex-nowrap lg:px-8">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <button id="openSidebarBtn" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 text-slate-600 shadow-sm transition hover:border-green-200 hover:text-green-700 lg:hidden">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- <div class="min-w-0">
                <p class="truncate text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Farm operations</p>
                <p id="navCurrentDateTimeMobile" class="truncate text-sm font-semibold text-slate-900 lg:hidden"></p>
            </div> -->
            <form id="navSearchForm" class="order-3 w-full lg:order-2 lg:max-w-xl lg:flex-1">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input
                    id="navSearchInput"
                    type="search"
                    list="navSearchSuggestions"
                    placeholder="Search menu links, modules, or reports..."
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-24 text-sm text-slate-700 shadow-sm transition placeholder:text-slate-400 focus:border-green-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-green-100"
                    autocomplete="off"
                >
                <div class="absolute inset-y-0 right-0 hidden items-center pr-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 sm:flex">
                    Press /
                </div>
            </div>
            <datalist id="navSearchSuggestions">
                @foreach(($searchItems ?? collect()) as $item)
                    <option value="{{ $item['label'] }}">{{ $item['section'] }}{{ $item['group'] ? ' › '.$item['group'] : '' }}</option>
                @endforeach
            </datalist>
            <div id="navSearchRoutes" class="hidden">
                @foreach(($searchItems ?? collect()) as $item)
                    <a data-nav-search-item data-search-label="{{ $item['label'] }} {{ $item['section'] }} {{ $item['group'] ?? '' }}" data-search-url="{{ $item['url'] }}"></a>
                @endforeach
            </div>
        </form>
        </div>

        

        <div class="order-2 ml-auto flex items-center gap-2 sm:gap-3 lg:order-3">
            <!-- <div class="hidden rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-right shadow-sm md:block">
                <p id="navCurrentDate" class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"></p>
                <p id="navCurrentTime" class="mt-1 text-sm font-semibold text-slate-900"></p>
            </div> -->

            @include('layouts.partials.topbar-notifications', ['user' => $user])

            @php
                $customLogoUrl = \App\Models\Setting::logoUrl();
                $hasCustomLogo = \App\Models\Setting::hasLogo();
            @endphp

            <details class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm transition hover:border-green-200 hover:bg-slate-50">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                        <p class="text-xs text-slate-500">{{ \App\Helpers\NavigationHelper::displayRole($user) }}</p>
                    </div>
                    @if($hasCustomLogo)
                        <span class="inline-flex h-10 w-10 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <img src="{{ $customLogoUrl }}" alt="Farm logo" class="h-full w-full object-contain p-1">
                        </span>
                    @else
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-green-100 text-sm font-semibold text-green-700">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </span>
                    @endif
                </summary>

                <div class="absolute right-0 mt-3 w-64 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
                    <div class="border-b border-slate-100 px-4 py-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                    <div class="p-2">
                        <a href="{{ route('profile.show') }}" class="flex items-center gap-3 rounded-2xl px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900">
                            <i class="fa-regular fa-user w-4 text-center"></i>
                            Profile settings
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="mt-1">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-3 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 hover:text-red-700">
                                <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </details>
        </div>
    </div>
</div>