{{--
    Storefront → Settings (Phase 8, blueprint §34).

    Mobile-first, as blueprint §55 requires: single column that widens to two on
    a larger screen, controls large enough to use on a phone in a barn.

    Every toggle on this page now DOES something. Before Phase 8 these flags
    existed and were enforced nowhere.
--}}
<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-4">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Shop settings</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        What your shop sells, how buyers pay, and how orders reach them.
                    </p>
                </div>
                <a href="{{ route('storefront.admin.dashboard') }}"
                   class="px-4 py-2 border border-slate-300 rounded-xl text-sm">Back to storefront</a>
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            @unless($moduleEnabled)
                {{--
                    The module itself is off. Say so plainly and point at the
                    screen that owns it: nothing on this page can reopen the
                    shop while the module is disabled, and a form that silently
                    does nothing is worse than one that explains itself.
                --}}
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-semibold">The Online Shop module is switched off for your farm.</p>
                    <p class="mt-1">Your shop is not visible to buyers regardless of the settings below.
                        Turn the module back on under <span class="font-medium">Settings → Modules</span>.</p>
                </div>
            @endunless

            <form method="POST" action="{{ route('storefront.admin.settings.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Open / closed --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-800">Shop status</h2>
                    <label class="mt-3 flex items-start gap-3 text-sm">
                        {{-- Same reason as the feature toggles: old input wins whole, or an
                             administrator who closes the shop and trips a validation error
                             elsewhere finds it reopened. --}}
                        <input type="checkbox" name="public_shop_open" value="1" class="mt-1 h-5 w-5 rounded"
                               @checked(session()->hasOldInput()
                                   ? (bool) old('public_shop_open')
                                   : ($settings->public_shop_open ?? true))>
                        <span>
                            <span class="font-medium">Shop is open to buyers</span><br>
                            <span class="text-slate-500">
                                Uncheck to close the public shop temporarily. Visitors see a closed
                                page with your contact details; your listings, orders and settings
                                stay exactly as they are.
                            </span>
                        </span>
                    </label>
                </section>

                {{-- Store details --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                    <h2 class="font-semibold text-slate-800">Store details</h2>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="block text-sm">
                            <span class="text-slate-600">Store name</span>
                            <input type="text" name="store_name" maxlength="150"
                                   value="{{ old('store_name', $settings->store_name) }}"
                                   placeholder="{{ config('app.name') }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">Currency</span>
                            <input type="text" name="default_currency" maxlength="3" required
                                   value="{{ old('default_currency', $settings->default_currency ?? 'GHS') }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm uppercase">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">Support email</span>
                            <input type="email" name="support_email" maxlength="190"
                                   value="{{ old('support_email', $settings->support_email) }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">Support phone</span>
                            <input type="text" name="support_phone" maxlength="60"
                                   value="{{ old('support_phone', $settings->support_phone) }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                    </div>

                    <label class="block text-sm">
                        <span class="text-slate-600">Pickup address</span>
                        <textarea name="pickup_address" rows="2"
                                  class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ old('pickup_address', $settings->pickup_address) }}</textarea>
                        <span class="text-xs text-slate-400">Shown to buyers who choose farm pickup.</span>
                    </label>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="block text-sm">
                            <span class="text-slate-600">Homepage headline</span>
                            <input type="text" name="hero_headline" maxlength="255"
                                   value="{{ old('hero_headline', $settings->hero_headline) }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block text-sm">
                            <span class="text-slate-600">Homepage sub-headline</span>
                            <input type="text" name="hero_subheadline" maxlength="255"
                                   value="{{ old('hero_subheadline', $settings->hero_subheadline) }}"
                                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        </label>
                    </div>
                </section>

                {{-- What you sell --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-800">What you sell online</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Unticked categories disappear from your shop, its menus and its search —
                        including direct links to individual items.
                    </p>
                    <div class="mt-4 grid sm:grid-cols-2 gap-3">
                        @foreach($flags as $key => $flag)
                            @if($flag['group'] === 'catalog')
                                @include('storefront.admin.partials.feature-toggle', ['key' => $key, 'flag' => $flag])
                            @endif
                        @endforeach
                    </div>
                </section>

                {{-- Payments --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-800">How buyers pay</h2>
                    <div class="mt-4 grid sm:grid-cols-2 gap-3">
                        @foreach($flags as $key => $flag)
                            @if($flag['group'] === 'payments')
                                @include('storefront.admin.partials.feature-toggle', ['key' => $key, 'flag' => $flag])
                            @endif
                        @endforeach
                    </div>

                    {{--
                        Paystack health. Configuration status only — never the
                        key, not even partly masked. Four characters of a live
                        secret in a support screenshot is still four characters
                        of a live secret.
                    --}}
                    <div class="mt-4 rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-sm">
                        <p class="font-medium text-slate-700">Paystack connection</p>
                        @if($paystack['configured'])
                            <p class="text-slate-600 mt-1">
                                Connected in
                                <span class="font-semibold">{{ $paystack['mode'] === 'live' ? 'LIVE' : strtoupper((string) $paystack['mode']) }}</span>
                                mode{{ $paystack['has_public_key'] ? '' : ', but no public key is configured' }}.
                            </p>
                            @if($paystack['mode'] !== 'live')
                                <p class="text-xs text-amber-700 mt-1">
                                    Test mode takes no real money. Your installer switches this over for go-live.
                                </p>
                            @endif
                        @else
                            <p class="text-slate-600 mt-1">
                                Not configured. Online card and mobile money payment will not work until
                                your installer adds the Paystack keys.
                            </p>
                        @endif
                    </div>
                </section>

                {{-- Checkout --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                    <h2 class="font-semibold text-slate-800">Checkout &amp; fulfilment</h2>

                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach($flags as $key => $flag)
                            @if($flag['group'] === 'checkout')
                                @include('storefront.admin.partials.feature-toggle', ['key' => $key, 'flag' => $flag])
                            @endif
                        @endforeach
                    </div>

                    <label class="block text-sm">
                        <span class="text-slate-600">Hold stock for (minutes)</span>
                        <input type="number" name="checkout_hold_minutes" required
                               min="{{ \App\Models\Storefront\StorefrontSetting::MIN_HOLD_MINUTES }}"
                               max="{{ \App\Models\Storefront\StorefrontSetting::MAX_HOLD_MINUTES }}"
                               value="{{ old('checkout_hold_minutes', $settings->checkout_hold_minutes ?? 30) }}"
                               class="mt-1 w-full sm:w-40 rounded-xl border-slate-300 text-sm">
                        <span class="block text-xs text-slate-400 mt-1">
                            How long an item stays reserved for a buyer who has started paying.
                        </span>
                    </label>

                    <label class="block text-sm">
                        <span class="text-slate-600">Refund &amp; returns policy</span>
                        <textarea name="refund_policy" rows="3" maxlength="5000"
                                  class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ old('refund_policy', $settings->refund_policy) }}</textarea>
                        <span class="block text-xs text-slate-400 mt-1">
                            Shown to buyers at checkout, before they pay.
                        </span>
                    </label>
                </section>

                {{-- Tax --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                    <h2 class="font-semibold text-slate-800">Tax</h2>

                    <label class="block text-sm">
                        <span class="text-slate-600">How prices are shown</span>
                        <select name="tax_display_mode" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            @foreach($taxDisplayModes as $value => $label)
                                <option value="{{ $value }}" @selected(old('tax_display_mode', $settings->tax_display_mode) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="text-slate-600">Tax profile for shop orders</span>
                        <select name="default_tax_profile_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            <option value="">No tax on shop orders</option>
                            @foreach($taxProfiles as $profile)
                                <option value="{{ $profile->id }}" @selected(old('default_tax_profile_id', $settings->default_tax_profile_id) == $profile->id)>
                                    {{ $profile->name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="block text-xs text-slate-400 mt-1">
                            Defined under <a class="underline" href="{{ route('finance.tax.index') }}">Finance → Tax</a>.
                            This chooses which one shop orders use; it does not change the rates.
                        </span>
                    </label>
                </section>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-800 text-white font-medium text-sm">
                        Save settings
                    </button>
                </div>
            </form>

            {{-- Delivery zones — separate forms, because each is its own record --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                <div>
                    <h2 class="font-semibold text-slate-800">Delivery areas</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Charge by where an order is going. While you have delivery areas, the buyer
                        picks one at checkout and its fee is the delivery charge — per-item delivery
                        fees are not added on top. With no areas listed, per-item fees apply as usual.
                    </p>
                </div>

                @forelse($zones as $zone)
                    <div class="border-t border-slate-100 pt-4">
                        <form method="POST" action="{{ route('storefront.admin.settings.zones.update', $zone) }}"
                              class="grid sm:grid-cols-12 gap-3 items-end">
                            @csrf
                            @method('PUT')
                            <label class="sm:col-span-4 block text-sm">
                                <span class="text-slate-600">Area</span>
                                <input type="text" name="name" required maxlength="120" value="{{ $zone->name }}"
                                       class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            </label>
                            <label class="sm:col-span-4 block text-sm">
                                <span class="text-slate-600">Notes</span>
                                <input type="text" name="description" maxlength="255" value="{{ $zone->description }}"
                                       class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            </label>
                            <label class="sm:col-span-2 block text-sm">
                                <span class="text-slate-600">Fee (GHS)</span>
                                <input type="number" step="0.01" min="0" name="fee" required value="{{ number_format((float) $zone->fee, 2, '.', '') }}"
                                       class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            </label>
                            <div class="sm:col-span-2 flex items-center gap-3">
                                <label class="inline-flex items-center gap-1.5 text-sm">
                                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded" @checked($zone->is_active)>
                                    <span class="text-slate-600">Active</span>
                                </label>
                                <button type="submit" class="px-3 py-1.5 rounded-lg border border-slate-300 text-sm">Save</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('storefront.admin.settings.zones.destroy', $zone) }}"
                              class="mt-2"
                              onsubmit="return confirm('Remove the {{ $zone->name }} delivery area? Past orders keep the fee they were charged.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 rounded-lg border border-rose-200 text-rose-700 text-sm">Remove area</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 border-t border-slate-100 pt-4">
                        No delivery areas yet. Per-item delivery fees apply.
                    </p>
                @endforelse

                <form method="POST" action="{{ route('storefront.admin.settings.zones.store') }}"
                      class="grid sm:grid-cols-12 gap-3 items-end border-t border-slate-100 pt-4">
                    @csrf
                    <label class="sm:col-span-4 block text-sm">
                        <span class="text-slate-600">New area</span>
                        <input type="text" name="name" maxlength="120" placeholder="e.g. Kumasi metro"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="sm:col-span-5 block text-sm">
                        <span class="text-slate-600">Notes</span>
                        <input type="text" name="description" maxlength="255" placeholder="Optional"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <label class="sm:col-span-2 block text-sm">
                        <span class="text-slate-600">Fee (GHS)</span>
                        <input type="number" step="0.01" min="0" name="fee" value="0.00"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    </label>
                    <div class="sm:col-span-1">
                        <button type="submit" class="w-full px-3 py-2 rounded-lg bg-slate-800 text-white text-sm">Add</button>
                    </div>
                </form>
            </section>

        </div>
    </main>
</x-app-layout>
