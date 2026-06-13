<x-storefront.layout :title="'Saved items'">
    <div class="max-w-5xl mx-auto px-4 py-10">
        <a href="{{ route('storefront.account.dashboard') }}" class="text-sm text-slate-500">← Account</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2 mb-6">Saved items</h1>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif

        @if($favourites->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-400">
                You haven't saved anything yet. Tap <span class="font-medium">☆ Save</span> on a listing to keep it here.
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($favourites as $fav)
                    @include('storefront.partials.listing-card', ['listing' => $fav->listing])
                @endforeach
            </div>
        @endif
    </div>
</x-storefront.layout>
