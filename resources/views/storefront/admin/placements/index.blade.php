<x-app-layout>
    <style>
        .placement-page{max-width:1180px;margin:0 auto;padding:28px 20px 48px}.placement-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:24px}.placement-kicker{font-size:.72rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#047857}.placement-title{margin-top:5px;font-size:1.75rem;line-height:1.2;font-weight:900;color:#0f172a}.placement-copy{margin-top:7px;max-width:680px;font-size:.9rem;line-height:1.65;color:#64748b}.placement-preview{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 17px;border:1px solid #cbd5e1;border-radius:13px;background:#fff;font-size:.84rem;font-weight:800;color:#334155;box-shadow:0 3px 10px rgba(15,23,42,.04)}.placement-form-card{margin-bottom:26px;padding:22px;border:1px solid #dbe5e1;border-radius:20px;background:linear-gradient(135deg,#f0fdf7 0%,#fff 68%);box-shadow:0 8px 24px rgba(15,23,42,.05)}.placement-form-title{font-size:1rem;font-weight:900;color:#0f172a}.placement-form-copy{margin-top:4px;font-size:.8rem;color:#64748b}.placement-form{display:grid;grid-template-columns:minmax(190px,1fr) minmax(260px,1.6fr) 100px auto;gap:12px;align-items:end;margin-top:18px}.placement-field label{display:block;margin:0 0 6px;font-size:.72rem;font-weight:800;color:#475569}.placement-field select,.placement-field input{width:100%;min-height:45px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;font-size:.84rem;color:#0f172a}.placement-submit{min-height:45px;padding:0 20px;border-radius:12px;background:#047857;color:#fff;font-size:.84rem;font-weight:900;box-shadow:0 8px 16px rgba(4,120,87,.18)}.placement-submit:disabled{cursor:not-allowed;opacity:.55}.placement-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.placement-slot{overflow:hidden;border:1px solid #e2e8f0;border-radius:18px;background:#fff;box-shadow:0 4px 15px rgba(15,23,42,.04)}.placement-slot-head{padding:18px 20px;border-bottom:1px solid #eef2f7;background:#fafcfc}.placement-slot-title{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:.94rem;font-weight:900;color:#0f172a}.placement-count{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;background:#ecfdf5;color:#047857;font-size:.68rem;font-weight:900}.placement-slot-copy{margin-top:5px;font-size:.76rem;line-height:1.5;color:#64748b}.placement-list{padding:8px 18px}.placement-item{display:grid;grid-template-columns:36px minmax(0,1fr) auto;gap:11px;align-items:center;padding:12px 0}.placement-item+.placement-item{border-top:1px solid #eef2f7}.placement-order{display:flex;width:32px;height:32px;align-items:center;justify-content:center;border-radius:9px;background:#f1f5f9;font-size:.72rem;font-weight:900;color:#64748b}.placement-name{overflow-wrap:anywhere;font-size:.82rem;font-weight:800;color:#0f172a}.placement-ref{margin-top:3px;font-size:.69rem;color:#64748b}.placement-remove{padding:7px 9px;border-radius:9px;color:#b91c1c;font-size:.72rem;font-weight:800}.placement-empty{padding:20px 0;text-align:center;font-size:.78rem;color:#94a3b8}@media(max-width:900px){.placement-form{grid-template-columns:1fr 1fr}.placement-grid{grid-template-columns:1fr}}@media(max-width:640px){.placement-page{padding:18px 12px 40px}.placement-head{display:block}.placement-preview{width:100%;margin-top:16px}.placement-form-card{padding:17px}.placement-form{grid-template-columns:1fr}.placement-submit{width:100%}.placement-item{grid-template-columns:34px minmax(0,1fr) auto}}
    </style>

    <div class="placement-page">
        <header class="placement-head">
            <div>
                <div class="placement-kicker">Storefront merchandising</div>
                <h1 class="placement-title">Homepage placements</h1>
                <p class="placement-copy">Choose which currently visible listings guests see first. The homepage spotlight shows one listing; other sections show up to four in order.</p>
            </div>
            <a class="placement-preview" href="{{ route('storefront.home') }}" target="_blank" rel="noopener">Preview storefront <span class="ml-2" aria-hidden="true">&nearr;</span></a>
        </header>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errors->first() }}</div>@endif

        <section class="placement-form-card">
            <h2 class="placement-form-title">Pin a listing</h2>
            <p class="placement-form-copy">Only products that a guest can currently open are available here.</p>
            <form method="POST" action="{{ route('storefront.admin.placements.store') }}" class="placement-form">
                @csrf
                <div class="placement-field"><label for="placement-code">Homepage section</label><select id="placement-code" name="code" required>@foreach($codes as $code)<option value="{{ $code }}" @selected(old('code') === $code)>{{ $labels[$code] }}</option>@endforeach</select></div>
                <div class="placement-field"><label for="placement-listing">Published listing</label><select id="placement-listing" name="listing_id" required><option value="">Select a visible listing…</option>@foreach($publishedListings as $listing)<option value="{{ $listing->id }}" @selected((string) old('listing_id') === (string) $listing->id)>{{ $listing->reference }} — {{ $listing->title }}</option>@endforeach</select></div>
                <div class="placement-field"><label for="placement-position">Order</label><input id="placement-position" name="position" type="number" min="0" max="9999" value="{{ old('position') }}" placeholder="Auto"></div>
                <button class="placement-submit" @disabled($publishedListings->isEmpty())>Pin listing</button>
            </form>
            @if($publishedListings->isEmpty())<p class="mt-3 text-xs font-semibold text-amber-700">There are no currently visible listings. Publish a listing and check its availability dates first.</p>@endif
        </section>

        <div class="placement-grid">
            @foreach($codes as $code)
                @php($pinned = ($placements[$code] ?? collect())->filter(fn ($placement) => $placement->listing_id))
                <section class="placement-slot">
                    <div class="placement-slot-head">
                        <div class="placement-slot-title"><span>{{ $labels[$code] }}</span><span class="placement-count">{{ $pinned->count() }} pinned</span></div>
                        <p class="placement-slot-copy">{{ $descriptions[$code] }}</p>
                    </div>
                    <div class="placement-list">
                        @forelse($pinned as $placement)
                            <div class="placement-item">
                                <span class="placement-order">{{ $code === \App\Models\Storefront\StorefrontPlacement::CODE_HOME_HERO ? '1' : $placement->position }}</span>
                                <div><div class="placement-name">{{ $placement->listing?->title ?? 'Listing unavailable' }}</div><div class="placement-ref">{{ $placement->listing?->reference }} · {{ str_replace('_', ' ', $placement->listing?->kind ?? '') }}</div></div>
                                <form method="POST" action="{{ route('storefront.admin.placements.destroy', $placement) }}" onsubmit="return confirm('Remove this listing from the homepage section?');">@csrf @method('DELETE')<button class="placement-remove">Remove</button></form>
                            </div>
                        @empty
                            <div class="placement-empty">No listing pinned to this section.</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
