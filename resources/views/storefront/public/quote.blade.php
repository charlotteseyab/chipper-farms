@php use App\Storefront\Support\StorefrontSettings; @endphp
<x-storefront.layout :title="'Request a quote'">
    <div class="max-w-3xl mx-auto px-4 py-10">
        <h1 class="text-2xl font-bold text-slate-800 mb-1">Request a quote</h1>
        <p class="text-slate-500 mb-6">For bulk, breeding stock or anything you'd like priced. We'll review and get back to you.</p>

        @if($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('storefront.quote.store') }}" class="space-y-6" x-data="{ rows: {{ $listing ? 1 : 1 }} }">
            @csrf
            <input type="hidden" name="source" value="{{ $source }}">

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">Your details</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <label class="block text-sm">
                        <span class="text-slate-600">Name</span>
                        <input type="text" name="contact_name" value="{{ old('contact_name', $user?->name) }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Email</span>
                        <input type="email" name="contact_email" value="{{ old('contact_email', $user?->email) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Phone</span>
                        <input type="text" name="contact_phone" value="{{ old('contact_phone') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Fulfilment preference</span>
                        <select name="fulfilment_preference" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            <option value="pickup">Farm pickup</option>
                            <option value="delivery">Local delivery</option>
                        </select>
                    </label>
                </div>
                <p class="text-xs text-slate-400">Provide at least an email or a phone number.</p>
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">What would you like?</h2>

                @if($listing)
                    {{-- Pre-seeded single line from a listing. --}}
                    <input type="hidden" name="items[0][listing_id]" value="{{ $listing->id }}">
                    <input type="hidden" name="items[0][kind]" value="{{ $listing->kind }}">
                    <div class="grid md:grid-cols-4 gap-3 items-end">
                        <label class="block text-sm md:col-span-2">
                            <span class="text-slate-600">Item</span>
                            <input type="text" name="items[0][description]" value="{{ old('items.0.description', $listing->title) }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">Quantity</span>
                            <input type="number" step="0.0001" name="items[0][quantity]" value="{{ old('items.0.quantity', 1) }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">Unit</span>
                            <input type="text" name="items[0][quantity_unit]" value="{{ old('items.0.quantity_unit', $listing->quantity_unit) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                    </div>
                @else
                    {{-- Bulk: three blank lines (extra lines left blank are ignored). --}}
                    @for($i = 0; $i < 3; $i++)
                        <div class="grid md:grid-cols-4 gap-3 items-end">
                            <label class="block text-sm md:col-span-2">
                                <span class="text-slate-600">Item {{ $i + 1 }}{{ $i === 0 ? '' : ' (optional)' }}</span>
                                <input type="text" name="items[{{ $i }}][description]" value="{{ old("items.$i.description") }}" @if($i===0) required @endif class="mt-1 w-full rounded-xl border-slate-300 text-sm" placeholder="e.g. 10 weaner piglets">
                            </label>
                            <label class="block text-sm">
                                <span class="text-slate-600">Quantity</span>
                                <input type="number" step="0.0001" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", $i===0 ? 1 : '') }}" @if($i===0) required @endif class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            </label>
                            <label class="block text-sm">
                                <span class="text-slate-600">Unit</span>
                                <input type="text" name="items[{{ $i }}][quantity_unit]" value="{{ old("items.$i.quantity_unit", 'piglets') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            </label>
                        </div>
                    @endfor
                @endif

                <label class="block text-sm">
                    <span class="text-slate-600">Message (optional)</span>
                    <textarea name="message" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm" placeholder="Anything else we should know?">{{ old('message') }}</textarea>
                </label>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('storefront.shop') }}" class="px-4 py-2 border border-slate-300 rounded-xl text-sm">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-slate-800 text-white rounded-xl text-sm">Submit request</button>
            </div>
        </form>
    </div>
</x-storefront.layout>
