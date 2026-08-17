@php
    $mode = $mode ?? 'create';
    $listing = $listing ?? null;
    $recommendedSize = 'Recommended image size: 1200 x 900 px (4:3). Use JPG, PNG, or WebP up to 5 MB.';
@endphp

<div class="rounded-2xl bg-white border border-slate-200 p-5">
    <div class="mb-4">
        <h2 class="font-semibold text-slate-700">{{ $mode === 'edit' ? '3. Media' : '3. Media' }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ $recommendedSize }}</p>
    </div>

    @if($mode === 'edit' && $listing)
        @if($listing->media->isEmpty())
            <p class="text-sm text-slate-400 mb-4">No media yet.</p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                @foreach($listing->media as $m)
                    <div class="rounded-xl border {{ $m->is_primary ? 'border-emerald-400' : 'border-slate-200' }} overflow-hidden">
                        <div class="aspect-[4/3] bg-slate-100">
                            @if($m->url())
                                <img src="{{ $m->url() }}" class="w-full h-full object-cover" alt="{{ $m->alt_text }}">
                            @endif
                        </div>
                        <div class="p-1.5 flex items-center justify-between gap-2 text-[11px]">
                            @if($m->is_primary)
                                <span class="text-emerald-600">Primary</span>
                            @else
                                <button type="submit" formaction="{{ route('storefront.admin.listings.media.primary', [$listing, $m]) }}" class="text-slate-500 hover:underline">Make primary</button>
                            @endif
                            <button type="submit" name="_method" value="DELETE" formaction="{{ route('storefront.admin.listings.media.remove', [$listing, $m]) }}" class="text-red-500 hover:underline">Remove</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid md:grid-cols-3 gap-3 items-end border-t border-slate-100 pt-4">
            @include('storefront.admin.listings.partials.media-fields')
        </div>
    @else
        <div class="grid md:grid-cols-3 gap-3 items-end">
            @include('storefront.admin.listings.partials.media-fields')
        </div>
    @endif
</div>
