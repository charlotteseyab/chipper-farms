<x-storefront.layout :title="'Addresses'">
    <div class="max-w-2xl mx-auto px-4 py-10">
        <a href="{{ route('storefront.account.dashboard') }}" class="text-sm text-slate-500">← Account</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2 mb-6">Delivery addresses</h1>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <div class="space-y-3 mb-8">
            @forelse($addresses as $addr)
                <div class="rounded-2xl bg-white border border-slate-200 p-4 flex items-start justify-between">
                    <div class="text-sm">
                        <p class="font-medium text-slate-800">{{ $addr->label }} @if($addr->is_default)<span class="text-xs text-emerald-600">(default)</span>@endif</p>
                        <p class="text-slate-500">{{ $addr->address_line_1 }}@if($addr->address_line_2), {{ $addr->address_line_2 }}@endif</p>
                        <p class="text-slate-500">{{ $addr->city }}@if($addr->region), {{ $addr->region }}@endif @if($addr->ghana_post_gps)· {{ $addr->ghana_post_gps }}@endif</p>
                    </div>
                </div>
            @empty
                <p class="text-slate-400 text-sm">No addresses saved yet.</p>
            @endforelse
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 p-6">
            <h2 class="font-semibold text-slate-700 mb-4">Add an address</h2>
            <form method="POST" action="{{ route('storefront.account.addresses.store') }}" class="space-y-3">
                @csrf
                <input type="text" name="label" placeholder="Label (e.g. Home)" class="w-full rounded-xl border-slate-300 text-sm" value="{{ old('label') }}">
                <input type="text" name="address_line_1" placeholder="Address line 1" required class="w-full rounded-xl border-slate-300 text-sm" value="{{ old('address_line_1') }}">
                <input type="text" name="address_line_2" placeholder="Address line 2" class="w-full rounded-xl border-slate-300 text-sm" value="{{ old('address_line_2') }}">
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="city" placeholder="City" required class="rounded-xl border-slate-300 text-sm" value="{{ old('city') }}">
                    <input type="text" name="region" placeholder="Region" class="rounded-xl border-slate-300 text-sm" value="{{ old('region') }}">
                </div>
                <input type="text" name="ghana_post_gps" placeholder="GhanaPost GPS (optional)" class="w-full rounded-xl border-slate-300 text-sm" value="{{ old('ghana_post_gps') }}">
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" class="rounded border-slate-300"> Set as default</label>
                <div><button class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm">Save address</button></div>
            </form>
        </div>
    </div>
</x-storefront.layout>
