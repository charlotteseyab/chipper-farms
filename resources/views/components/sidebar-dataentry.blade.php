<aside class="fixed top-0 left-0 z-50 w-72 bg-[#1C6C51] shadow-lg h-screen flex flex-col p-0 rounded-r-2xl overflow-hidden">
    <div class="flex flex-col items-center py-8 bg-[#185941]">
        <a href="/">
            <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-14 w-auto rounded-full shadow border-4 border-white mb-2" />
        </a>
        <span class="text-white font-bold text-lg tracking-wide mt-2">Data Entry Panel</span>
    </div>
    <nav class="flex flex-col gap-2 flex-1 px-4 pt-20 pb-8 overflow-y-auto scrollbar-thin scrollbar-thumb-[#218c6a] scrollbar-track-[#1C6C51]">
        <a href="/dataentry/dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold text-white hover:bg-[#218c6a] transition @if(request()->is('dataentry/dashboard')) bg-[#218c6a] @endif">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6m-6 0v6m0 0H7m6 0h6"/></svg>
            Dashboard
        </a>
        <div class="mt-2 mb-2 text-xs text-green-100 uppercase tracking-wider pl-4">Management</div>
        <!-- Stock Management Dropdown -->
        <div x-data="{ open: {{ request()->is('*stocks*') || request()->is('*structures*') ? 'true' : 'false' }} }" class="relative">
            <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition @if(request()->is('*stocks*')) bg-[#218c6a] @endif">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    Stock
                </div>
                <svg class="h-4 w-4 transition-transform" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-transition class="mt-2 ml-4 space-y-1">
                <a href="/all-stocks" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('all-stocks*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    All Stock
                </a>
                <a href="/breeding-stocks" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('breeding-stocks*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Breeding Stock
                </a>
                <a href="/standard-stocks" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('standard-stocks*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Standard Stock
                </a>
                <a href="/structures" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('structures*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16"/>
                    </svg>
                    Structures
                </a>
            </div>
        </div>

        <!-- Mating Management Dropdown -->
        <div x-data="{ open: {{ request()->is('mating*') ? 'true' : 'false' }} }" class="relative">
            <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition @if(request()->is('mating*')) bg-[#218c6a] @endif">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    Mating Planner
                </div>
                <svg class="h-4 w-4 transition-transform" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-transition class="mt-2 ml-4 space-y-1">
                <a href="/mating" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('mating') && !request()->is('mating/manage')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Plan Mating
                </a>
                <a href="/mating/manage" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('mating/manage*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Manage Matings
                </a>
            </div>
        </div>

        <!-- Gestation Period -->
        <a href="/gestation" class="flex items-center gap-3 px-4 py-3 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition @if(request()->is('gestation*')) bg-[#218c6a] text-white @endif">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="font-medium">Gestation Period</span>
        </a>

        <!-- Farrowing Records -->
        <a href="/farrowing" class="flex items-center gap-3 px-4 py-3 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition @if(request()->is('farrowing*')) bg-[#218c6a] text-white @endif">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="font-medium">Farrowing Records</span>
        </a>

        <!-- Weaning Period Dropdown -->
        <div class="relative" x-data="{ open: {{ request()->is('weaning*') ? 'true' : 'false' }} }">
            <button @click="open = !open" class="flex items-center justify-between w-full gap-3 px-4 py-3 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition @if(request()->is('weaning*')) bg-[#218c6a] text-white @endif">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                    <span class="font-medium">Weaning Period</span>
                </div>
                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-transition class="mt-2 ml-4 space-y-1">
                <a href="/weaning/pending" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('weaning/pending*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Pending</span>
                </a>
                <a href="/weaning/in-progress" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('weaning/in-progress*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>In Progress</span>
                </a>
                <a href="/weaning/weaned" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('weaning/weaned*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Completed</span>
                </a>
                <a href="/weaning/overdue" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('weaning/overdue*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Overdue</span>
                </a>
            </div>
        </div>

         <!-- Weight Management Dropdown -->
         <div x-data="{ open: {{ request()->is('weights*') ? 'true' : 'false' }} }" class="relative">
            <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition @if(request()->is('weights*')) bg-[#218c6a] @endif">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                    </svg>
                    Weight Management
                </div>
                <svg class="h-4 w-4 transition-transform" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-transition class="mt-2 ml-4 space-y-1">
                <a href="/weights" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('weights') && !request()->is('weights/*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    All Weights
                </a>
                <a href="/weights/create" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('weights/create')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Record Weight
                </a>
                <a href="/weights/reports" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('weights/reports*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Weight Reports
                </a>
            </div>
        </div>

         <!-- Feed Management Dropdown -->
         <div x-data="{ open: {{ request()->is('feed-types*') || request()->is('feeding-standards*') || request()->is('recipes*') || request()->is('feed-inventory*') || request()->is('feed-purchase-requests*') || request()->is('feeds/schedule*') || request()->is('feeds/reports*') ? 'true' : 'false' }} }" class="relative">
            <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition @if(request()->is('feed-types*') || request()->is('feeding-standards*') || request()->is('feed-inventory*') || request()->is('feed-purchase-requests*')) bg-[#218c6a] @endif">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Feed Management
                </div>
                <svg class="h-4 w-4 transition-transform" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-transition class="mt-2 ml-4 space-y-1">
                <a href="{{ route('feed-types.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('feed-types*')) bg-[#218c6a] text-white @endif">Feed Types</a>
                <a href="{{ route('recipes.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('recipes*')) bg-[#218c6a] text-white @endif">Feed Recipes</a>
                <a href="{{ route('feeding-standards.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('feeding-standards*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Feeding Standards
                </a>
                <a href="{{ route('feed-inventory.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('feed-inventory*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Feed Inventory
                </a>
                <a href="{{ route('feed-purchase-requests.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('feed-purchase-requests*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Purchase Requests
                </a>
                <a href="{{ route('feeds.schedule') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('feeds/schedule*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Feed Schedule
                </a>
                <a href="{{ route('feeds.reports') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('feeds/reports*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Feed Reports
                </a>
            </div>
        </div>

         <!-- General Settings Dropdown -->
         <div x-data="{ open: {{ request()->is('settings*') || request()->is('deaths*') || request()->is('sales*') ? 'true' : 'false' }} }" class="relative">
            <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition @if(request()->is('settings*')) bg-[#218c6a] @endif">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    General Settings
                </div>
                <svg class="h-4 w-4 transition-transform" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-transition class="mt-2 ml-4 space-y-1">
                <!-- <a href="{{ route('settings.setup') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('settings/setup*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Set Up
                </a> -->
                <a href="{{ route('deaths.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('deaths*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    Death Records
                </a>
                <a href="{{ route('sales.index') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('sales*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>
                    Sales Records
                </a>
                <a href="{{ route('settings.reports') }}" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('settings/reports*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    System Reports
                </a>
            </div>
        </div>
	</nav>
</aside>
