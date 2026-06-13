<x-storefront.layout :title="'My account'">
    <div class="max-w-4xl mx-auto px-4 py-10">
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Hello, {{ auth()->user()->name }}</h1>
        <p class="text-slate-500 mb-8">Manage your profile, addresses and (soon) your orders.</p>

        <div class="grid md:grid-cols-3 gap-4 mb-8">
            <div class="rounded-2xl bg-white border border-slate-200 p-5">
                <p class="text-xs uppercase text-slate-400">Orders</p>
                <p class="text-2xl font-bold text-slate-800">{{ $profile->order_count }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5">
                <p class="text-xs uppercase text-slate-400">Lifetime value</p>
                <p class="text-2xl font-bold text-slate-800">GHS {{ number_format((float) $profile->lifetime_value, 2) }}</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5">
                <p class="text-xs uppercase text-slate-400">Referral code</p>
                <p class="text-lg font-mono font-bold text-slate-800">{{ $profile->referral_code }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('storefront.account.profile') }}" class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm">Profile</a>
            <a href="{{ route('storefront.account.addresses') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Addresses</a>
            <a href="{{ route('storefront.account.saved') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Saved items</a>
            <a href="{{ route('storefront.account.quotes') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">My quotes</a>
            <a href="{{ route('storefront.account.orders.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">My orders</a>
            <a href="{{ route('storefront.shop') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">Continue shopping</a>
        </div>

        <div class="mt-8 rounded-2xl border border-dashed border-slate-300 p-6 text-sm text-slate-400">
            Your orders, quotes and saved items will appear here as those features go live (Phases 2–5).
        </div>
    </div>
</x-storefront.layout>
