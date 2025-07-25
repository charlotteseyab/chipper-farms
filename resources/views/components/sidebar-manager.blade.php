<aside class="fixed top-0 left-0 z-50 w-64 bg-[#1C6C51] shadow-lg h-screen flex flex-col p-0 rounded-r-2xl overflow-hidden">
    <div class="flex flex-col items-center py-8 bg-[#185941]">
        <a href="/">
            <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-14 w-auto rounded-full shadow border-4 border-white mb-2" />
        </a>
        <span class="text-white font-bold text-lg tracking-wide mt-2">Manager Panel</span>
    </div>
    <nav class="flex flex-col gap-2 flex-1 px-4 py-8">
        <a href="/manager/dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold text-white hover:bg-[#218c6a] transition @if(request()->is('manager/dashboard')) bg-[#218c6a] @endif">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6m-6 0v6m0 0H7m6 0h6"/></svg>
            Dashboard
        </a>
        <div class="mt-6 mb-2 text-xs text-green-100 uppercase tracking-wider pl-4">Operations</div>
        <a href="/breeding-stocks" class="flex items-center gap-3 px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition @if(request()->is('breeding-stocks*')) bg-[#218c6a] @endif">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Breeding Stock
        </a>
        <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 10c-4.41 0-8-1.79-8-4V6c0-2.21 3.59-4 8-4s8 1.79 8 4v8c0 2.21-3.59 4-8 4z"/></svg>
            Farm Operations
        </a>
        <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Schedules
        </a>
        <!-- Add more manager links here -->
    </nav>
    <div class="px-4 pb-6 mt-auto">
        <a href="/logout" class="block w-full text-center py-2 rounded-lg text-[#1C6C51] bg-white font-semibold hover:bg-green-100 transition">Logout</a>
    </div>
</aside> 