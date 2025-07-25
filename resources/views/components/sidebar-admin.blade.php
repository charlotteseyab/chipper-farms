<aside class="w-64 bg-[#1C6C51] shadow-lg h-screen flex flex-col p-0 rounded-r-2xl overflow-hidden">
    <div class="flex flex-col items-center py-8 bg-[#185941]">
        <a href="/">
            <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-14 w-auto rounded-full shadow border-4 border-white mb-2" />
        </a>
        <span class="text-white font-bold text-lg tracking-wide mt-2">Admin Panel</span>
    </div>
    <nav class="flex flex-col gap-2 flex-1 px-4 py-8">
        <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg font-semibold text-white hover:bg-[#218c6a] transition @if(request()->is('admin/dashboard')) bg-[#218c6a] @endif">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6m-6 0v6m0 0H7m6 0h6"/></svg>
            Dashboard
        </a>
        <div class="mt-6 mb-2 text-xs text-green-100 uppercase tracking-wider pl-4">Management</div>
        <a href="/breeding-stocks" class="flex items-center gap-3 px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition @if(request()->is('breeding-stocks*')) bg-[#218c6a] @endif">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Breeding Stock
        </a>
        <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 3.13a4 4 0 010 7.75M8 3.13a4 4 0 010 7.75"/></svg>
            Manage Users
        </a>
        <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg text-white hover:bg-[#218c6a] transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2a4 4 0 014-4h4a4 4 0 014 4v2M9 17H7a4 4 0 01-4-4V7a4 4 0 014-4h10a4 4 0 014 4v6a4 4 0 01-4 4h-2"/></svg>
            Reports
        </a>
        <!-- Add more admin links here -->
    </nav>
    <div class="px-4 pb-6 mt-auto">
        <a href="/logout" class="block w-full text-center py-2 rounded-lg text-[#1C6C51] bg-white font-semibold hover:bg-green-100 transition">Logout</a>
    </div>
</aside> 