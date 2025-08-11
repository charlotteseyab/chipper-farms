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
        <div x-data="{ open: {{ request()->is('*stocks*') ? 'true' : 'false' }} }" class="relative">
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

        <!-- Finisher Management Dropdown -->
        <div class="relative" x-data="{ open: {{ request()->is('finishers*') ? 'true' : 'false' }} }">
            <button @click="open = !open" class="flex items-center justify-between w-full gap-3 px-4 py-3 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition @if(request()->is('finishers*')) bg-[#218c6a] text-white @endif">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                    <span class="font-medium">Finisher Data</span>
                </div>
                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-transition class="mt-2 ml-4 space-y-1">
                <a href="/finishers/growing" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('finishers/growing*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                    <span>Growing (50-80kg)</span>
                </a>
                <a href="/finishers/ready" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('finishers/ready*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Near Market (80-100kg)</span>
                </a>
                <a href="/finishers/market-ready" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-100 hover:bg-[#218c6a] hover:text-white transition text-sm @if(request()->is('finishers/market-ready*')) bg-[#218c6a] text-white @endif">
                    <svg class="h-4 w-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Market Ready (100kg+)</span>
                </a>
            </div>
        </div>
    </nav>
</aside>
