<x-storefront.layout :title="'Quote requested'">
    <div class="max-w-xl mx-auto px-4 py-16 text-center">
        <div class="text-5xl mb-4">OK</div>
        <h1 class="text-2xl font-bold text-slate-800">Thank you!</h1>
        <p class="text-slate-500 mt-2">Your quote request <span class="font-mono font-semibold">{{ $reference }}</span> has been received. Our team will review it and respond shortly.</p>
        <div class="mt-8 flex justify-center gap-3">
            <a href="{{ route('storefront.shop') }}" class="px-5 py-2.5 rounded-xl bg-slate-800 text-white text-sm font-medium">Continue shopping</a>
            @auth<a href="{{ route('storefront.account.quotes') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-sm">View my quotes</a>@endauth
        </div>
    </div>
</x-storefront.layout>
