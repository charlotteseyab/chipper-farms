<x-app-layout>
    <div class="max-w-3xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-mono text-slate-400">{{ $listing->reference }}</p>
                <h1 class="text-2xl font-bold text-gray-800">Edit listing</h1>
            </div>
            <a href="{{ route('storefront.admin.listings.show', $listing) }}" class="text-sm text-slate-500 hover:text-slate-800">← Back</a>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        {{-- Content editor --}}
        <form method="POST" action="{{ route('storefront.admin.listings.update', $listing) }}" class="space-y-6">
            @csrf @method('PUT')
            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">Content</h2>
                <label class="block text-sm">
                    <span class="text-slate-600">Title</span>
                    <input type="text" name="title" value="{{ old('title', $listing->title) }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Category</span>
                    <select name="category_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        <option value="">—</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((int)old('category_id', $listing->category_id) === $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Public description</span>
                    <textarea name="public_description" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ old('public_description', $listing->public_description) }}</textarea>
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">Seller note</span>
                    <input type="text" name="seller_note" value="{{ old('seller_note', $listing->seller_note) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-5 space-y-4">
                <h2 class="font-semibold text-slate-700">Pricing &amp; fulfilment</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <label class="block text-sm">
                        <span class="text-slate-600">Sale mode</span>
                        <select name="sale_mode" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            @foreach($modes as $m)<option value="{{ $m }}" @selected(old('sale_mode',$listing->sale_mode)===$m)>{{ ucfirst($m) }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Pricing basis</span>
                        <select name="pricing_basis" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            @foreach($bases as $b)<option value="{{ $b }}" @selected(old('pricing_basis',$listing->pricing_basis)===$b)>{{ ucfirst($b) }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Price / kg</span>
                        <input type="number" step="0.0001" name="price_per_kg" value="{{ old('price_per_kg', $listing->price_per_kg) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Fixed price</span>
                        <input type="number" step="0.01" name="fixed_price" value="{{ old('fixed_price', $listing->fixed_price) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Quantity available</span>
                        <input type="number" step="0.0001" name="quantity_available" value="{{ old('quantity_available', $listing->quantity_available) }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Unit</span>
                        <input type="text" name="quantity_unit" value="{{ old('quantity_unit', $listing->quantity_unit) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">SKU</span>
                        <input type="text" name="sku" value="{{ old('sku', $listing->sku) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Pack size</span>
                        <input type="text" name="pack_size" value="{{ old('pack_size', $listing->pack_size) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                </div>
                <div class="flex flex-wrap gap-6 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="pickup_allowed" value="1" @checked(old('pickup_allowed', $listing->pickup_allowed)) class="rounded border-slate-300"> Pickup allowed</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="delivery_allowed" value="1" @checked(old('delivery_allowed', $listing->delivery_allowed)) class="rounded border-slate-300"> Delivery allowed</label>
                </div>
                <label class="block text-sm">
                    <span class="text-slate-600">Delivery fee</span>
                    <input type="number" step="0.01" name="delivery_fee" value="{{ old('delivery_fee', $listing->delivery_fee) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                </label>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('storefront.admin.listings.show', $listing) }}" class="px-4 py-2 border border-slate-300 rounded-xl text-sm">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-slate-800 text-white rounded-xl text-sm">Save changes</button>
            </div>
        </form>

        {{-- Media manager --}}
        @can('manage_storefront_media')
            <div class="mt-8 rounded-2xl bg-white border border-slate-200 p-5">
                <h2 class="font-semibold text-slate-700 mb-3">Media</h2>

                @if($listing->media->isEmpty())
                    <p class="text-sm text-slate-400 mb-4">No media yet.</p>
                @else
                    <div class="grid grid-cols-4 gap-3 mb-4">
                        @foreach($listing->media as $m)
                            <div class="rounded-xl border {{ $m->is_primary ? 'border-emerald-400' : 'border-slate-200' }} overflow-hidden">
                                <div class="aspect-square bg-slate-100">
                                    @if($m->url())<img src="{{ $m->url() }}" class="w-full h-full object-cover" alt="{{ $m->alt_text }}">@endif
                                </div>
                                <div class="p-1.5 flex items-center justify-between text-[11px]">
                                    @if($m->is_primary)
                                        <span class="text-emerald-600">Primary</span>
                                    @else
                                        <form method="POST" action="{{ route('storefront.admin.listings.media.primary', [$listing, $m]) }}">@csrf<button class="text-slate-500 hover:underline">Make primary</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('storefront.admin.listings.media.remove', [$listing, $m]) }}">@csrf @method('DELETE')<button class="text-red-500 hover:underline">Remove</button></form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('storefront.admin.listings.media.add', $listing) }}" enctype="multipart/form-data" class="grid md:grid-cols-3 gap-3 items-end border-t border-slate-100 pt-4">
                    @csrf
                    <label class="block text-sm">
                        <span class="text-slate-600">Upload image</span>
                        <input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm">
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">…or image URL</span>
                        <input type="url" name="media_url" placeholder="https://…" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <button class="rounded-xl bg-slate-800 text-white text-sm px-4 py-2">Add media</button>
                </form>
            </div>
        @endcan
    </div>
</x-app-layout>
